@extends('layouts.app')
@section('title', 'Dashboard — Attendance')

@section('content')
<div class="page-wrap">
  <section class="content-wrap section-pad">
    <div style="max-width:820px;margin:0 auto;">
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', async () => {
 const el=document.getElementById('today-status');
 try {
  const r=await fetch('/attendance/today',{headers:{Accept:'application/json'}});
  const d=await r.json();
  if(d.has_open_session){el.innerHTML='<p class="t-body" style="font-weight:600">🟢 Working</p><p class="t-caption">Your current session is active. Scan QR to checkout.</p>';}
  else if(d.has_attended){el.innerHTML='<p class="t-body" style="font-weight:600">Session completed</p><p class="t-caption">Scan QR to start another session.</p>';}
  else {el.innerHTML='<p class="t-body" style="font-weight:600">Ready to check in</p><p class="t-caption">Scan the workplace QR code.</p>';}
 } catch(e) {}
});
</script>
@endpush
@endsection