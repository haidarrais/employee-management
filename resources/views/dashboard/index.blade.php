@extends('layouts.app')
@section('title', 'Dashboard — Attendance')

@section('content')
<div class="page-wrap">

  {{-- ── Sub-nav ──────────────────────────────────────────────── --}}
  <div class="sub-nav">
    <div class="sub-nav__inner">
      <span class="sub-nav__title">Dashboard</span>
      <div class="sub-nav__links">
        <a href="{{ route('attendance.history') }}" class="sub-nav__link">History</a>
        @if(Auth::user()->isAdmin() || Auth::user()->isManagement())
        <a href="{{ route('qr.generate') }}" class="sub-nav__link">Generate QR</a>
        <a href="{{ route('reports.payroll') }}" class="sub-nav__link">Reports</a>
        @endif
        @if(Auth::user()->isAdmin())
        <a href="{{ route('admin.users') }}" class="sub-nav__link">Users</a>
        <a href="{{ route('audit.logs') }}" class="sub-nav__link">Audit</a>
        @endif
        <a href="{{ route('profile') }}" class="btn-primary btn-sm" style="margin-left:8px;">Profile</a>
      </div>
    </div>
  </div>

  {{-- ── Flash messages ──────────────────────────────────────── --}}
  @if(session('error'))
  <div style="background:var(--parchment);padding:0 22px;">
    <div style="max-width:980px;margin:0 auto;padding-top:16px;">
      <div class="alert alert-error">{{ session('error') }}</div>
    </div>
  </div>
  @endif

  {{-- ── Employee: scan tile ─────────────────────────────────── --}}
  @if(Auth::user()->isEmployee())
  {{-- Light tile --}}
  <section style="background:var(--canvas);padding:var(--space-section) 22px;">
    <div style="max-width:640px;margin:0 auto;text-align:center;">
      <h1 class="t-display t-ink" style="margin:0 0 12px;">Mark Attendance</h1>
      <p class="t-lead t-muted" style="margin:0 0 32px;">Scan the QR code to record your attendance for today.</p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
        <button onclick="openQRScanner()" class="btn-primary">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
          Scan QR Code
        </button>
        <a href="{{ route('attendance.history') }}" class="btn-ghost-pill">View History</a>
      </div>
    </div>
  </section>

  {{-- Dark tile: today's status --}}
  <section style="background:var(--tile-1);padding:var(--space-section) 22px;">
    <div style="max-width:640px;margin:0 auto;text-align:center;">
      <p class="t-tagline" style="color:var(--faint);margin:0 0 16px;">Today</p>
      <div id="today-status">
        <p class="t-display" style="color:var(--on-dark);margin:0 0 8px;">Not yet recorded</p>
        <p class="t-body" style="color:var(--faint);margin:0;">Scan the QR code above to check in.</p>
      </div>
    </div>
  </section>
  @endif

  {{-- ── Admin/Management: hero tile ───────────────────────── --}}
  @if(Auth::user()->isAdmin() || Auth::user()->isManagement())
  <section style="background:var(--canvas);padding:var(--space-section) 22px;">
    <div style="max-width:640px;margin:0 auto;text-align:center;">
      <h1 class="t-display t-ink" style="margin:0 0 12px;">Attendance Management</h1>
      <p class="t-lead t-muted" style="margin:0 0 32px;">Generate QR codes for check-in or review attendance records.</p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
        <a href="{{ route('qr.generate') }}" class="btn-primary">Generate QR Code</a>
        <a href="{{ route('reports.payroll') }}" class="btn-ghost-pill">Payroll Report</a>
      </div>
    </div>
  </section>
  @endif

  {{-- ── Recent attendance (parchment tile) ─────────────────── --}}
  <section style="background:var(--parchment);padding:var(--space-section) 22px;">
    <div style="max-width:980px;margin:0 auto;">
      <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:24px;">
        <h2 class="t-section t-ink" style="margin:0;">Recent Attendance</h2>
        <a href="{{ route('attendance.history') }}" class="t-caption t-blue">View all →</a>
      </div>

      <div class="card">
        @forelse($recentAttendance as $record)
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:16px 24px;border-bottom:1px solid var(--soft);">
          <div style="display:flex;align-items:center;gap:14px;">
            <div style="width:36px;height:36px;border-radius:50%;flex-shrink:0;
                        display:flex;align-items:center;justify-content:center;
                        background:{{ $record->status === 'confirmed' ? '#d1fae5' : ($record->status === 'rejected' ? '#fee2e2' : 'var(--soft)') }};
                        color:{{ $record->status === 'confirmed' ? '#065f46' : ($record->status === 'rejected' ? '#991b1b' : 'var(--muted)') }};">
              @if($record->status === 'confirmed')
              <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
              </svg>
              @else
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
              @endif
            </div>
            <div>
              <p class="t-body t-ink" style="margin:0;font-weight:600;">{{ $record->created_at->format('l, F j') }}</p>
              <p class="t-caption t-muted" style="margin:0;">{{ $record->created_at->format('g:i A') }}</p>
            </div>
          </div>
          <span class="badge {{ $record->status === 'confirmed' ? 'badge-success' : ($record->status === 'rejected' ? 'badge-error' : 'badge-gray') }}">
            {{ ucfirst($record->status) }}
          </span>
        </div>
        @empty
        <div style="padding:64px 24px;text-align:center;">
          <p class="t-body t-muted" style="margin:0;">No attendance records yet.</p>
        </div>
        @endforelse
      </div>
    </div>
  </section>

  {{-- ── Footer ───────────────────────────────────────────────── --}}
  <footer style="background:var(--parchment);border-top:1px solid var(--line);padding:32px 22px;">
    <div style="max-width:980px;margin:0 auto;text-align:center;">
      <p class="t-fine t-muted" style="margin:0;">© {{ date('Y') }} Employee Attendance System</p>
    </div>
  </footer>

</div>

{{-- ── QR Scanner Modal ─────────────────────────────────────── --}}
<div id="qr-modal" class="hidden">
  <div class="modal-backdrop" onclick="closeQRScanner()"></div>
  <div class="modal">
    <div style="padding:24px;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <p class="t-tagline t-ink" style="margin:0;">Scan QR Code</p>
        <button onclick="closeQRScanner()"
                style="background:none;border:none;cursor:pointer;color:var(--muted);padding:4px;">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <div style="position:relative;aspect-ratio:1;background:#000;border-radius:12px;overflow:hidden;margin-bottom:16px;">
        <video id="qr-video" style="width:100%;height:100%;object-fit:cover;" autoplay playsinline></video>
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
          <div style="width:200px;height:200px;border:2px solid rgba(255,255,255,0.8);border-radius:12px;"></div>
        </div>
      </div>

      <div id="location-status" style="text-align:center;margin-bottom:16px;">
        <span class="t-caption t-muted">Waiting for location…</span>
      </div>

      <div style="background:var(--parchment);border-radius:12px;padding:16px;">
        <p class="t-caption t-ink" style="margin:0 0 8px;font-weight:600;">Instructions</p>
        <ol style="margin:0;padding-left:18px;" class="t-caption t-muted">
          <li style="margin-bottom:4px;">Point your camera at the QR code</li>
          <li style="margin-bottom:4px;">You must be within 10m of the workplace</li>
          <li>Allow location access when prompted</li>
        </ol>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
// Today's attendance status
document.addEventListener('DOMContentLoaded', async () => {
  try {
    const r = await fetch('/attendance/today', { headers: { Accept: 'application/json' } });
    if (!r.ok) return;
    const d = await r.json();
    const el = document.getElementById('today-status');
    if (!el) return;
    if (d.has_attended) {
      const latest = d.data?.latest;
      el.innerHTML = `
        <p class="t-display" style="color:var(--on-dark);margin:0 0 8px;">Checked in ✓</p>
        <p class="t-body" style="color:var(--faint);margin:0;">Recorded at ${latest?.time || ''}${latest?.distance_meters ? ' · ' + latest.distance_meters + 'm' : ''}</p>
      `;
    } else {
      el.innerHTML = `
        <p class="t-display" style="color:var(--on-dark);margin:0 0 8px;">Not yet recorded</p>
        <p class="t-body" style="color:var(--faint);margin:0;">Scan the QR code above to check in.</p>
      `;
    }
  } catch(e) { /* silent */ }
});

async function openQRScanner() {
  const modal  = document.getElementById('qr-modal');
  const video  = document.getElementById('qr-video');
  const status = document.getElementById('location-status');
  modal.classList.remove('hidden');

  try {
    status.innerHTML = '<span class="t-caption t-muted">Getting location…</span>';
    const pos = await new Promise((res, rej) =>
      navigator.geolocation.getCurrentPosition(res, rej, { enableHighAccuracy: true, timeout: 10000 })
    );
    status.innerHTML = `<span class="t-caption" style="color:#34c759;">Location acquired (±${Math.round(pos.coords.accuracy)}m)</span>`;

    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    video.srcObject = stream;
  } catch(e) {
    status.innerHTML = `<span class="t-caption" style="color:#ff3b30;">${e.message}</span>`;
  }
}

function closeQRScanner() {
  const modal = document.getElementById('qr-modal');
  const video = document.getElementById('qr-video');
  modal.classList.add('hidden');
  if (video.srcObject) {
    video.srcObject.getTracks().forEach(t => t.stop());
    video.srcObject = null;
  }
}
</script>
@endpush
@endsection
