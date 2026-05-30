@extends('layouts.app')
@section('title', 'Attendance History')

@section('content')
<div class="page-wrap">

  <div class="sub-nav">
    <div class="sub-nav__inner">
      <a href="{{ route('dashboard') }}" class="sub-nav__link" style="display:flex;align-items:center;gap:6px;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
        Dashboard
      </a>
      <span class="sub-nav__title">History</span>
      <form method="GET" style="display:flex;align-items:center;gap:8px;">
        <select name="month" class="form-input btn-sm" style="width:auto;border-radius:9999px;min-height:36px;padding:6px 32px 6px 14px;font-size:14px;" onchange="this.form.submit()">
          <option value="">All time</option>
          <option value="{{ date('Y-m') }}" {{ request('month') === date('Y-m') ? 'selected' : '' }}>This month</option>
          <option value="{{ date('Y-m', strtotime('-1 month')) }}" {{ request('month') === date('Y-m', strtotime('-1 month')) ? 'selected' : '' }}>Last month</option>
        </select>
      </form>
    </div>
  </div>

  {{-- Stats tile --}}
  <section style="background:var(--canvas);padding:var(--space-section) 22px;">
    <div style="max-width:980px;margin:0 auto;">
      <h1 class="t-display t-ink" style="margin:0 0 32px;text-align:center;">Attendance History</h1>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;">

        <div class="card" style="text-align:center;padding:28px 24px;">
          <p class="t-fine t-muted" style="margin:0 0 8px;text-transform:uppercase;letter-spacing:0.06em;">Present</p>
          <p style="font-size:40px;font-weight:600;color:#065f46;margin:0;line-height:1;">{{ $stats['present'] ?? 0 }}</p>
        </div>

        <div class="card" style="text-align:center;padding:28px 24px;">
          <p class="t-fine t-muted" style="margin:0 0 8px;text-transform:uppercase;letter-spacing:0.06em;">Absent</p>
          <p style="font-size:40px;font-weight:600;color:#991b1b;margin:0;line-height:1;">{{ $stats['absent'] ?? 0 }}</p>
        </div>

        <div class="card" style="text-align:center;padding:28px 24px;">
          <p class="t-fine t-muted" style="margin:0 0 8px;text-transform:uppercase;letter-spacing:0.06em;">Rate</p>
          <p style="font-size:40px;font-weight:600;color:var(--blue);margin:0;line-height:1;">{{ $stats['rate'] ?? 0 }}%</p>
        </div>

      </div>
    </div>
  </section>

  {{-- Records tile --}}
  <section style="background:var(--parchment);padding:var(--space-section) 22px;">
    <div style="max-width:980px;margin:0 auto;">
      <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:20px;">
        <h2 class="t-section t-ink" style="margin:0;">Records</h2>
        <span class="t-caption t-muted">{{ $records->total() }} total</span>
      </div>

      <div class="card">
        @forelse($records as $record)
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
              <p class="t-body t-ink" style="margin:0;font-weight:600;">{{ $record->created_at->format('l, F j, Y') }}</p>
              <p class="t-caption t-muted" style="margin:0;">
                {{ $record->created_at->format('g:i A') }}
                @if($record->distance_meters)
                · {{ round($record->distance_meters) }}m
                @endif
              </p>
            </div>
          </div>
          <span class="badge {{ $record->status === 'confirmed' ? 'badge-success' : ($record->status === 'rejected' ? 'badge-error' : 'badge-gray') }}">
            {{ ucfirst($record->status) }}
          </span>
        </div>
        @empty
        <div style="padding:64px 24px;text-align:center;">
          <p class="t-body t-muted" style="margin:0;">No attendance records found.</p>
        </div>
        @endforelse

        @if($records->hasPages())
        <div style="padding:16px 24px;border-top:1px solid var(--soft);">
          {{ $records->links() }}
        </div>
        @endif
      </div>
    </div>
  </section>

  <footer style="background:var(--parchment);border-top:1px solid var(--line);padding:24px 22px;">
    <div style="max-width:980px;margin:0 auto;text-align:center;">
      <p class="t-fine t-muted" style="margin:0;">© {{ date('Y') }} Employee Attendance System</p>
    </div>
  </footer>

</div>
@endsection
