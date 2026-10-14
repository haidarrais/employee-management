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
@endsection