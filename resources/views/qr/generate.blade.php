@extends('layouts.app')
@section('title', 'Attendance QR Code')

@section('content')
<div class="page-wrap">

  {{-- Sub-nav --}}
  <div class="sub-nav">
    <div class="sub-nav__inner">
      <a href="{{ route('dashboard') }}" class="sub-nav__link" style="display:flex;align-items:center;gap:6px;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
        Dashboard
      </a>
      <span class="sub-nav__title">QR Code</span>
      <button onclick="refreshQr()" id="refresh-btn" class="btn-primary btn-sm">New Code</button>
    </div>
  </div>

  @if(session('error'))
  <div style="background:var(--parchment);padding:16px 22px 0;">
    <div style="max-width:640px;margin:0 auto;">
      <div class="alert alert-error">{{ session('error') }}</div>
    </div>
  </div>
  @endif

  {{-- Light tile: QR display --}}
  <section style="background:var(--canvas);padding:var(--space-section) 22px;">
    <div style="max-width:480px;margin:0 auto;text-align:center;">

      <p class="t-tagline t-muted" style="margin:0 0 8px;">Attendance QR Code</p>
      <h1 class="t-display t-ink" style="margin:0 0 4px;">Single-use · {{ $validityMinutes }} min</h1>

      {{-- Status badge --}}
      <div style="display:flex;justify-content:center;margin:16px 0 32px;">
        <span id="status-badge" class="badge badge-success">
          <span class="dot dot-green"></span>
          Active
        </span>
      </div>

      {{-- QR image with product shadow --}}
      <div style="position:relative;display:inline-block;margin-bottom:32px;">
        <img id="qr-image"
             src="{{ $qrCodeImage }}"
             alt="Attendance QR Code"
             style="width:280px;height:280px;display:block;border-radius:12px;
                    box-shadow:var(--shadow-product);">

        {{-- Expired overlay --}}
        <div id="expired-overlay"
             class="hidden"
             style="position:absolute;inset:0;border-radius:12px;
                    background:rgba(255,255,255,0.92);backdrop-filter:blur(8px);
                    display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;">
          <div style="width:48px;height:48px;border-radius:50%;background:#fee2e2;
                      display:flex;align-items:center;justify-content:center;">
            <svg width="22" height="22" fill="none" stroke="#ff3b30" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <p class="t-caption t-ink" style="margin:0;font-weight:600;">Expired</p>
          <button onclick="refreshQr()" class="btn-primary btn-sm">Generate new</button>
        </div>
      </div>

      {{-- Countdown --}}
      <p class="t-fine t-muted" style="margin:0 0 8px;text-transform:uppercase;letter-spacing:0.08em;">Expires in</p>
      <div id="countdown"
           class="tabular"
           style="font-size:48px;font-weight:600;line-height:1;letter-spacing:-0.5px;color:var(--ink);margin-bottom:16px;">
        {{ str_pad($validityMinutes, 2, '0', STR_PAD_LEFT) }}:00
      </div>

      {{-- Progress bar --}}
      <div style="height:3px;background:var(--soft);border-radius:9999px;overflow:hidden;max-width:280px;margin:0 auto 40px;">
        <div id="progress-bar"
             style="height:100%;width:100%;background:var(--blue);border-radius:9999px;
                    transition:width 1s linear,background 0.5s;"></div>
      </div>

      {{-- CTA pair --}}
      <div style="display:flex;gap:12px;justify-content:center;">
        <button onclick="refreshQr()" id="refresh-btn-2" class="btn-ghost-pill">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
          New Code
        </button>
        <a href="{{ route('dashboard') }}" class="btn-primary">Done</a>
      </div>

    </div>
  </section>

  {{-- Dark tile: instructions --}}
  <section style="background:var(--tile-1);padding:var(--space-section) 22px;">
    <div style="max-width:480px;margin:0 auto;">
      <h2 class="t-section" style="color:var(--on-dark);margin:0 0 24px;text-align:center;">How to use</h2>
      <ol style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:16px;">
        @foreach([
          'Display this QR code on screen or print it.',
          'Employees open the Attendance app and tap Scan QR Code.',
          'They must be within ' . \App\Models\QRCode::GEOFENCE_RADIUS_METERS . 'm of the workplace.',
          'Each code is single-use — generate a new one for the next session.',
        ] as $i => $step)
        <li style="display:flex;align-items:flex-start;gap:14px;">
          <span style="width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,0.1);
                       display:flex;align-items:center;justify-content:center;flex-shrink:0;
                       font-size:12px;font-weight:600;color:var(--faint);">{{ $i + 1 }}</span>
          <p class="t-body" style="color:var(--faint);margin:0;">{{ $step }}</p>
        </li>
        @endforeach
      </ol>
    </div>
  </section>

  {{-- Footer --}}
  <footer style="background:var(--parchment);border-top:1px solid var(--line);padding:24px 22px;">
    <div style="max-width:980px;margin:0 auto;text-align:center;">
      <p class="t-fine t-muted" style="margin:0;">
        Code #{{ $qrCode->id }} · Generated {{ $qrCode->generated_at->format('H:i:s') }}
      </p>
    </div>
  </footer>

</div>
@endsection

@push('scripts')
<script>
(function(){
  'use strict';
  const expiresAt = new Date('{{ $expiresAt }}');
  const totalMs   = {{ $validityMinutes }} * 60 * 1000;

  const countdownEl = document.getElementById('countdown');
  const progressEl  = document.getElementById('progress-bar');
  const overlayEl   = document.getElementById('expired-overlay');
  const badgeEl     = document.getElementById('status-badge');

  function tick() {
    const rem = expiresAt - Date.now();
    if (rem <= 0) { expire(); return; }

    const secs = Math.ceil(rem / 1000);
    const m    = Math.floor(secs / 60);
    const s    = secs % 60;
    const pct  = (rem / totalMs) * 100;

    countdownEl.textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    progressEl.style.width  = `${pct}%`;

    if (pct > 50)      progressEl.style.background = 'var(--blue)';
    else if (pct > 20) progressEl.style.background = '#ff9f0a';
    else               progressEl.style.background = '#ff3b30';

    if (pct > 50)      countdownEl.style.color = 'var(--ink)';
    else if (pct > 20) countdownEl.style.color = '#ff9f0a';
    else               countdownEl.style.color = '#ff3b30';
  }

  function expire() {
    clearInterval(id);
    countdownEl.textContent = '00:00';
    progressEl.style.width  = '0%';
    overlayEl.classList.remove('hidden');
    overlayEl.style.display = 'flex';
    badgeEl.className = 'badge badge-error';
    badgeEl.innerHTML = '<span class="dot dot-red"></span> Expired';
  }

  window.refreshQr = function() {
    document.querySelectorAll('#refresh-btn,#refresh-btn-2').forEach(b => b.disabled = true);
    window.location.href = '{{ route('qr.generate') }}';
  };

  tick();
  const id = setInterval(tick, 1000);
})();
</script>
@endpush
