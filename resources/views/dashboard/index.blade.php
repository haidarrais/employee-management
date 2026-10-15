@extends('layouts.app')
@section('title', 'Dashboard — Attendance')

@section('content')
<div class="page-wrap">
  <section class="content-wrap section-pad">
    <div style="max-width:980px;margin:0 auto;">
      @if(Auth::user()->isAdmin() || Auth::user()->isManagement())
      <div class="card" style="margin-bottom:24px;">
        <div class="card-body" style="padding:32px;">
          <p class="t-caption t-muted" style="margin:0 0 8px;">Management overview</p>
          <h1 class="t-display" style="margin-bottom:24px;">Today's Workforce</h1>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;">
            <div class="card"><div class="card-body"><p class="t-caption t-muted">Present</p><p class="t-display" style="font-size:36px">—</p></div></div>
            <div class="card"><div class="card-body"><p class="t-caption t-muted">Late</p><p class="t-display" style="font-size:36px">—</p></div></div>
            <div class="card"><div class="card-body"><p class="t-caption t-muted">Working Now</p><p class="t-display" style="font-size:36px">—</p></div></div>
          </div>
          <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:24px;">
            <a href="{{ route('qr.generate') }}" class="btn-primary">Generate QR</a>
            <a href="{{ route('reports.payroll') }}" class="btn-ghost-pill">Payroll Report</a>
            <a href="{{ route('attendance.history') }}" class="btn-ghost-pill">Attendance</a>
          </div>
        </div>
      </div>
      @else
      <div class="card" style="margin-bottom:24px;">
        <div class="card-body" style="padding:32px;">
          <p class="t-caption t-muted" style="margin:0 0 8px;">Good {{ now()->hour < 12 ? 'morning' : 'afternoon' }}, {{ Auth::user()->name }}</p>
          <h1 class="t-display" style="margin-bottom:20px;">Today's Attendance</h1>
          <div id="today-status" style="padding:20px;background:var(--canvas-soft);border-radius:var(--radius-lg);">
            <p class="t-body" style="margin:0;color:var(--ink-muted);">Checking attendance status...</p>
          </div>
          <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:24px;">
            <button onclick="openQRScanner()" class="btn-primary">Scan QR Code</button>
            <a href="{{ route('attendance.history') }}" class="btn-ghost-pill">View Timeline</a>
          </div>
        </div>
      </div>
      @endif

      <div class="card">
        <div class="card-header"><h2 class="t-section" style="font-size:22px;">Recent Sessions</h2></div>
        <div class="card-body">
          @forelse($recentAttendance as $record)
          <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid var(--hairline);">
            <div>
              <p class="t-body" style="margin:0;font-weight:600;">{{ optional($record->check_in_at ?? $record->created_at)->format('l, d M Y') }}</p>
              <p class="t-caption t-muted" style="margin:4px 0 0;">{{ optional($record->check_in_at ?? $record->created_at)->format('H:i') }} - {{ $record->check_out_at?->format('H:i') ?? 'Working now' }}</p>
            </div>
            <span class="badge {{ $record->isOpen() ? 'badge-success' : 'badge-gray' }}">{{ $record->isOpen() ? 'Working' : 'Completed' }}</span>
          </div>
          @empty
          <p class="t-body t-muted">No attendance sessions yet.</p>
          @endforelse
        </div>
      </div>
    </div>
  </section>
</div>

@if(Auth::user()->isEmployee())
<div id="qr-modal" class="hidden">
  <div class="modal-backdrop" onclick="closeQRScanner()"></div>
  <div class="modal">
    <div style="padding:24px;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <p class="t-tagline t-ink" style="margin:0;">Scan QR Code</p>
        <button type="button" onclick="closeQRScanner()" aria-label="Close scanner"
                style="background:none;border:none;cursor:pointer;color:var(--muted);padding:4px;">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <div style="position:relative;aspect-ratio:1;background:var(--charcoal-black);overflow:hidden;margin-bottom:16px;">
        <video id="qr-video" style="width:100%;height:100%;object-fit:cover;" autoplay playsinline></video>
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
          <div style="width:200px;height:200px;border:2px solid rgba(247,246,242,.78);"></div>
        </div>
      </div>

      <div id="location-status" style="text-align:center;margin-bottom:16px;">
        <span class="t-caption t-muted">Camera and location access are required.</span>
      </div>

      <div style="background:var(--parchment);padding:16px;">
        <p class="t-caption t-ink" style="margin:0 0 8px;font-weight:600;">How it works</p>
        <ol class="t-caption t-muted" style="margin:0;padding-left:18px;">
          <li style="margin-bottom:4px;">Allow location and camera access.</li>
          <li style="margin-bottom:4px;">Point the camera at the attendance QR code.</li>
          <li style="margin-bottom:4px;">You must be within {{ (int) $geofenceRadius }}m of the workplace.</li>
          <li>The same scan flow handles check-in and check-out.</li>
        </ol>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const el = document.getElementById('today-status');
  if (!el) return;

  try {
    const response = await fetch('/attendance/today', { headers: { Accept: 'application/json' } });
    if (!response.ok) return;

    const data = await response.json();
    if (data.has_open_session) {
      const open = data.data?.open_session;
      el.innerHTML = '<p class="t-body" style="margin:0 0 4px;font-weight:600;color:var(--ink);">Working now</p>' +
        '<p class="t-caption t-muted" style="margin:0;">Current session started at ' + (open?.check_in_time || open?.time || '') + '. Scan again to check out.</p>';
    } else if (data.has_attended) {
      const latest = data.data?.latest;
      el.innerHTML = '<p class="t-body" style="margin:0 0 4px;font-weight:600;color:var(--ink);">Session completed</p>' +
        '<p class="t-caption t-muted" style="margin:0;">Last session ended at ' + (latest?.check_out_time || latest?.time || '') + '. Scan again to start another session.</p>';
    } else {
      el.innerHTML = '<p class="t-body" style="margin:0 0 4px;font-weight:600;color:var(--ink);">Ready to check in</p>' +
        '<p class="t-caption t-muted" style="margin:0;">Scan the workplace QR code to begin.</p>';
    }
  } catch (error) {
    el.innerHTML = '<p class="t-caption t-muted" style="margin:0;">Attendance status could not be loaded.</p>';
  }
});

let qrWatcher = null;

async function openQRScanner() {
  const modal = document.getElementById('qr-modal');
  const video = document.getElementById('qr-video');
  const status = document.getElementById('location-status');

  if (!modal || !video || !status) return;

  const setStatus = (message, color) => {
    const span = document.createElement('span');
    span.className = 't-caption' + (color ? '' : ' t-muted');
    if (color) span.style.color = color;
    span.textContent = message;
    status.replaceChildren(span);
  };

  if (!window.AttendanceScanner) {
    setStatus('Scanner module is unavailable. Please refresh the page.', 'var(--danger)');
    modal.classList.remove('hidden');
    return;
  }

  modal.classList.remove('hidden');

  try {
    setStatus('Getting your location…');
    if (!navigator.geolocation) throw new Error('Location is not supported by this browser.');

    const position = await new Promise((resolve, reject) =>
      navigator.geolocation.getCurrentPosition(resolve, reject, {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0
      })
    );

    const coords = {
      latitude: position.coords.latitude,
      longitude: position.coords.longitude
    };

    setStatus('Location ready (±' + Math.round(position.coords.accuracy) + 'm). Starting camera…', 'var(--ok)');

    if (!navigator.mediaDevices?.getUserMedia) {
      throw new Error('Camera access is not supported by this browser.');
    }

    const stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: 'environment' } },
      audio: false
    });
    video.srcObject = stream;
    await video.play();

    setStatus('Point the camera at the QR code.', 'var(--ok)');
    qrWatcher = window.AttendanceScanner.watchForQr(video);
    const payload = await qrWatcher.promise;
    qrWatcher = null;

    setStatus('QR detected. Recording attendance…');
    const result = await window.AttendanceScanner.submitAttendance(payload, coords);
    setStatus(result.message || 'Attendance recorded.', 'var(--ok)');

    setTimeout(() => window.location.reload(), 1200);
  } catch (error) {
    if (error?.message !== 'cancelled') {
      let message = error?.message || 'Could not scan the QR code.';
      if (error?.name === 'NotAllowedError' || error?.code === 1) {
        message = 'Camera or location permission was denied. Enable it in your browser settings and try again.';
      } else if (error?.name === 'NotFoundError') {
        message = 'No camera was found on this device.';
      } else if (error?.code === 2) {
        message = 'Your location could not be determined. Check GPS/location services and try again.';
      } else if (error?.code === 3) {
        message = 'Location request timed out. Try again in a place with a better GPS signal.';
      }
      setStatus(message, 'var(--danger)');
    }
  } finally {
    stopCamera();
  }
}

function stopCamera() {
  const video = document.getElementById('qr-video');
  if (video?.srcObject) {
    video.srcObject.getTracks().forEach(track => track.stop());
    video.srcObject = null;
  }
}

function closeQRScanner() {
  document.getElementById('qr-modal')?.classList.add('hidden');
  if (qrWatcher) {
    qrWatcher.cancel();
    qrWatcher = null;
  }
  stopCamera();
}
</script>
@endpush
@endif
@endsection