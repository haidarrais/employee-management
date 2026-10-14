@extends('layouts.app')
@section('title', 'Workplace Location')

@section('content')
<div class="page-wrap">

  <div class="sub-nav">
    <div class="sub-nav__inner">
      <a href="{{ route('admin.users') }}" class="sub-nav__link" style="display:flex;align-items:center;gap:6px;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
        Manage Users
      </a>
      <span class="sub-nav__title">Workplace Location</span>
      <a href="#recent" class="sub-nav__link">Recent check-ins</a>
    </div>
  </div>

  @if(session('success'))
  <div style="background:var(--surface);padding:16px 22px 0;">
    <div style="max-width:640px;margin:0 auto;">
      <div class="alert alert-success">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        {{ session('success') }}
      </div>
    </div>
  </div>
  @endif

  @if($errors->any())
  <div style="background:var(--surface);padding:16px 22px 0;">
    <div style="max-width:640px;margin:0 auto;">
      <div class="alert alert-error">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>{{ $errors->first() }}</span>
      </div>
    </div>
  </div>
  @endif

  {{-- ══════════════════════════════════════════════════════════
       Position + tolerance
  ══════════════════════════════════════════════════════════ --}}
  <section style="background:var(--surface);padding:var(--space-section-y) 22px;">
    <div style="max-width:640px;margin:0 auto;">

      <h1 class="t-display t-ink" style="margin:0 0 8px;">Workplace Location &amp; Tolerance</h1>
      <p class="t-body t-muted" style="margin:0 0 32px;">
        Employees can only check in from inside this radius. Every check-in records the
        distance it was made from, so tightening the tolerance retroactively changes how
        existing records read.
      </p>

      @if($config === null)
      <div class="alert alert-warning" style="margin-bottom:28px;">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <span>
          <strong>No workplace location is configured.</strong>
          Check-in is currently refused for everyone with
          <span class="mono">GEOFENCE_NOT_CONFIGURED</span>. Set the coordinates below.
        </span>
      </div>
      @endif

      <form method="POST" action="{{ route('admin.location.update') }}" id="loc-form">
        @csrf
        @method('PUT')

        <div class="card" style="margin-bottom:24px;">
          <div class="card-header">
            <span class="t-tagline t-ink" style="margin:0;">Coordinates</span>
            <button type="button" id="use-my-location" class="btn-ghost-pill btn-sm">
              Use my current location
            </button>
          </div>
          <div class="card-body">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
              <div>
                <label for="latitude" class="form-label">Latitude</label>
                <input type="number" step="any" id="latitude" name="latitude"
                       class="form-input mono"
                       value="{{ old('latitude', $config['latitude'] ?? '') }}"
                       placeholder="-6.2088" required>
                <p class="form-hint">-90 to 90. Negative is south.</p>
              </div>
              <div>
                <label for="longitude" class="form-label">Longitude</label>
                <input type="number" step="any" id="longitude" name="longitude"
                       class="form-input mono"
                       value="{{ old('longitude', $config['longitude'] ?? '') }}"
                       placeholder="106.8456" required>
                <p class="form-hint">-180 to 180. Negative is west.</p>
              </div>
            </div>

            <p class="t-fine t-muted" id="loc-status" style="margin:16px 0 0;"></p>

            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:16px;">
              <button type="button" id="derive-location" class="btn-ghost-pill btn-sm">
                Derive from recent check-ins
              </button>
              <a href="https://www.openstreetmap.org/?mlat={{ $config['latitude'] ?? '' }}&mlon={{ $config['longitude'] ?? '' }}#map=18/{{ $config['latitude'] ?? '' }}/{{ $config['longitude'] ?? '' }}"
                 target="_blank" rel="noopener noreferrer" id="map-link"
                 class="btn-ghost-pill btn-sm" style="{{ $config === null ? 'display:none;' : '' }}">
                View on map
              </a>
            </div>

          </div>
        </div>

        <div class="card" style="margin-bottom:24px;">
          <div class="card-header">
            <span class="t-tagline t-ink" style="margin:0;">Range tolerance</span>
            <span class="t-fine t-muted">
              min {{ $minRadius }}m · default {{ $defaultRadius }}m · max {{ $maxRadius }}m
            </span>
          </div>
          <div class="card-body">

            <label for="radius_meters" class="form-label">Accepted distance from the workplace</label>

            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
              <input type="number" step="1" id="radius_meters" name="radius_meters"
                     class="form-input mono" style="max-width:160px;"
                     min="{{ $minRadius }}" max="{{ $maxRadius }}"
                     value="{{ old('radius_meters', $config['radius_meters'] ?? $defaultRadius) }}"
                     required>
              <span class="t-body t-muted">meters</span>

              {{-- Presets: the common cases, so nobody has to guess a number. --}}
              <div style="display:flex;gap:8px;flex-wrap:wrap;margin-left:auto;">
                @foreach([10 => 'Strict', 25 => 'Office', 50 => 'Building', 100 => 'Site', 250 => 'Campus'] as $m => $label)
                <button type="button" class="btn-ghost-pill btn-sm radius-preset" data-meters="{{ $m }}">
                  {{ $m }}m <span class="t-fine" style="opacity:.7;">{{ $label }}</span>
                </button>
                @endforeach
              </div>
            </div>

            <div style="margin-top:20px;">
              <input type="range" id="radius_range" min="{{ $minRadius }}" max="500" step="1"
                     value="{{ old('radius_meters', $config['radius_meters'] ?? $defaultRadius) }}"
                     style="width:100%;accent-color:var(--accent);">
              <p class="t-fine t-muted" id="radius-note" style="margin:8px 0 0;"></p>
            </div>

          </div>
        </div>

        <button type="submit" class="btn-primary">Save location &amp; tolerance</button>

      </form>
    </div>
  </section>

  {{-- ══════════════════════════════════════════════════════════
       Recent check-ins — distances against the current radius
  ══════════════════════════════════════════════════════════ --}}
  <section id="recent" style="background:var(--surface-raised);padding:var(--space-section-y-tight) 22px;border-top:1px solid var(--hairline);">
    <div style="max-width:960px;margin:0 auto;">

      <h2 class="t-section t-ink" style="margin:0 0 8px;">Recent check-in distances</h2>
      <p class="t-body t-muted" style="margin:0 0 28px;">
        What the tolerance actually admits. Distances above the current radius are shown
        as outside, which is what an employee would now see.
      </p>

      @php $currentRadius = $config['radius_meters'] ?? $defaultRadius; @endphp

      <div class="card">
        <table class="data-table">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Checked in</th>
              <th>Distance</th>
              <th>Within {{ (int) $currentRadius }}m</th>
            </tr>
          </thead>
          <tbody>
            @forelse($recent as $rec)
            @php $within = $rec->distance_meters <= $currentRadius; @endphp
            <tr>
              <td>{{ $rec->user->name ?? '—' }}</td>
              <td class="mono">{{ $rec->check_in_at?->format('d M Y H:i') ?? '—' }}</td>
              <td class="mono">{{ number_format($rec->distance_meters, 1) }} m</td>
              <td>
                <span class="badge {{ $within ? 'badge-success' : 'badge-error' }}">
                  {{ $within ? 'Inside' : 'Outside' }}
                </span>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="4" style="text-align:center;padding:48px;color:var(--ink-muted);">
                No check-ins recorded yet.
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <footer style="background:var(--surface);border-top:1px solid var(--hairline);padding:24px 22px;">
    <div style="max-width:980px;margin:0 auto;text-align:center;">
      <p class="t-fine t-muted" style="margin:0;">© {{ date('Y') }} Employee Attendance System</p>
    </div>
  </footer>

</div>
@endsection

@push('scripts')
<script>
(function(){
  const latEl   = document.getElementById('latitude');
  const lngEl   = document.getElementById('longitude');
  const radEl   = document.getElementById('radius_meters');
  const rangeEl = document.getElementById('radius_range');
  const status  = document.getElementById('loc-status');
  const note    = document.getElementById('radius-note');
  const mapLink = document.getElementById('map-link');

  function updateNote(){
    const m = parseFloat(radEl.value || '0');
    let text = '';
    if (m && m < 15)      text = 'Very tight. Phone GPS is typically accurate to about 5-10 m, so borderline check-ins will fail at random.';
    else if (m < 60)      text = 'Suitable for a room or a single office floor.';
    else if (m < 200)     text = 'Suitable for a building or a small site.';
    else                  text = 'Large area. Employees can check in from well outside the building — make sure that is intended.';
    note.textContent = text;

    // Keep the slider and the number field in step without a feedback loop.
    if (parseFloat(rangeEl.value) !== m && m >= parseFloat(rangeEl.min) && m <= parseFloat(rangeEl.max)) {
      rangeEl.value = m;
    }
  }

  radEl.addEventListener('input', updateNote);
  rangeEl.addEventListener('input', () => { radEl.value = rangeEl.value; updateNote(); });

  document.querySelectorAll('.radius-preset').forEach(btn => {
    btn.addEventListener('click', () => {
      radEl.value = btn.dataset.meters;
      updateNote();
    });
  });

  // Update the map link as coordinates are typed.
  function updateMap(){
    if (latEl.value && lngEl.value) {
      mapLink.href = `https://www.openstreetmap.org/?mlat=${latEl.value}&mlon=${lngEl.value}#map=18/${latEl.value}/${lngEl.value}`;
      mapLink.style.display = '';
    }
  }
  latEl.addEventListener('input', updateMap);
  lngEl.addEventListener('input', updateMap);

  // Fill the fields from the browser's own position. Only useful when the
  // admin is standing at the workplace, which is the usual case for setup.
  document.getElementById('use-my-location').addEventListener('click', function(){
    if (!navigator.geolocation) { status.textContent = 'This browser does not report a location.'; return; }
    status.textContent = 'Reading your location…';
    navigator.geolocation.getCurrentPosition(
      pos => {
        latEl.value = pos.coords.latitude.toFixed(8);
        lngEl.value = pos.coords.longitude.toFixed(8);
        status.textContent = `Filled from your device (accurate to about ${Math.round(pos.coords.accuracy)} m).`;
        updateMap();
      },
      err => { status.textContent = 'Could not read your location: ' + err.message; },
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
  });

  // Derive the position from where employees actually checked in.
  document.getElementById('derive-location').addEventListener('click', async function(){
    const btn = this;
    btn.disabled = true;
    status.textContent = 'Reading recent check-ins…';
    try {
      const res = await fetch('{{ route('admin.location.suggest') }}', {
        headers: { 'Accept': 'application/json' },
      });
      const data = await res.json();
      if (!data.ok) { status.textContent = data.message; return; }
      latEl.value = data.latitude;
      lngEl.value = data.longitude;
      status.textContent =
        `Derived from ${data.samples} check-ins. Median offset ${data.median_offset} m, `
        + `90th percentile ${data.p90_offset} m — a tolerance of about ${data.suggested_radius} m covers most of them.`;
      updateMap();
    } catch (e) {
      status.textContent = 'Could not read check-ins: ' + e.message;
    } finally {
      btn.disabled = false;
    }
  });

  updateNote();
})();
</script>
@endpush
