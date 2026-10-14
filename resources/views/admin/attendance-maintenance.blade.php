@extends('layouts.app')
@section('title', 'Attendance Maintenance')

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
      <span class="sub-nav__title">Attendance Maintenance</span>
      <span class="t-caption t-muted">Superadmin only</span>
    </div>
  </div>

  <section style="background:var(--canvas);padding:var(--space-section) 22px 28px;">
    <div style="max-width:1100px;margin:0 auto;">
      <h1 class="t-display t-ink" style="margin:0 0 12px;text-align:center;">Attendance Records</h1>
      <p class="t-lead t-muted" style="margin:0 auto 28px;text-align:center;max-width:720px;">
        Export filtered attendance records to an Excel-compatible CSV, or permanently clean matching records. Cleanup requires typing <strong>CLEANUP</strong>.
      </p>

      @if(session('success'))
      <div class="alert alert-success" style="margin-bottom:18px;">{{ session('success') }}</div>
      @endif
      @if(session('error'))
      <div class="alert alert-error" style="margin-bottom:18px;">{{ session('error') }}</div>
      @endif
      @if($errors->any())
      <div class="alert alert-error" style="margin-bottom:18px;">
        @foreach($errors->all() as $error)
          <p style="margin:0;">{{ $error }}</p>
        @endforeach
      </div>
      @endif

      <div class="card" style="padding:20px;margin-bottom:20px;">
        <form method="GET" action="{{ route('admin.attendance.maintenance') }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;">
          <div>
            <label class="form-label">Employee</label>
            <select name="user_id" class="form-input">
              <option value="">All employees</option>
              @foreach($employees as $employee)
              <option value="{{ $employee->id }}" {{ (string) request('user_id') === (string) $employee->id ? 'selected' : '' }}>
                {{ $employee->name }}
              </option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-input">
              <option value="">All statuses</option>
              <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
              <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
              <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
          </div>
          <div>
            <label class="form-label">From</label>
            <input type="date" name="from_date" value="{{ request('from_date') }}" class="form-input">
          </div>
          <div>
            <label class="form-label">To</label>
            <input type="date" name="to_date" value="{{ request('to_date') }}" class="form-input">
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="submit" class="btn-primary btn-sm">Filter</button>
            <a href="{{ route('admin.attendance.maintenance') }}" class="btn-ghost-pill btn-sm">Clear</a>
          </div>
        </form>
      </div>

      <div style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:20px;">
        <p class="t-caption t-muted" style="margin:0;">{{ $matchingCount }} matching record(s)</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="{{ route('admin.attendance.export', request()->query()) }}" class="btn-primary btn-sm">Export Excel CSV</a>
          <form method="POST" action="{{ route('admin.attendance.cleanup') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;" onsubmit="return confirm('This permanently deletes the matching attendance records. Continue?')">
            @csrf @method('DELETE')
            <input type="hidden" name="user_id" value="{{ request('user_id') }}">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <input type="hidden" name="from_date" value="{{ request('from_date') }}">
            <input type="hidden" name="to_date" value="{{ request('to_date') }}">
            <input type="text" name="confirm_text" class="form-input btn-sm" style="width:140px;min-height:36px;" placeholder="Type CLEANUP">
            <button type="submit" class="btn-danger btn-sm">Cleanup matching</button>
          </form>
        </div>
      </div>
    </div>
  </section>

  <section style="background:var(--parchment);padding:28px 22px var(--space-section);">
    <div style="max-width:1100px;margin:0 auto;">
      <div class="card" style="overflow:hidden;">
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Status</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th style="text-align:center;">Worked</th>
                <th style="text-align:center;">Distance</th>
              </tr>
            </thead>
            <tbody>
              @forelse($records as $record)
              <tr>
                <td>
                  <p style="margin:0;font-weight:600;color:var(--ink);">{{ $record->user?->name ?? 'Unknown user' }}</p>
                  <p class="t-fine t-muted" style="margin:0;">{{ $record->user?->email }}</p>
                </td>
                <td>
                  <span class="badge {{ $record->status === 'confirmed' ? 'badge-success' : ($record->status === 'rejected' ? 'badge-error' : 'badge-gray') }}">
                    {{ ucfirst($record->status) }}
                  </span>
                </td>
                <td style="white-space:nowrap;">{{ $record->check_in_at?->format('d M Y H:i') ?? $record->created_at->format('d M Y H:i') }}</td>
                <td style="white-space:nowrap;">{{ $record->check_out_at?->format('d M Y H:i') ?? 'Open session' }}</td>
                <td style="text-align:center;">{{ $record->workedMinutes() }}m</td>
                <td style="text-align:center;">
                  @if($record->distance_meters !== null)
                    {{ round($record->distance_meters) }}m
                  @else
                    —
                  @endif
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="6" style="text-align:center;padding:48px;color:var(--muted);">No matching attendance records.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if($records->hasPages())
        <div style="padding:16px 20px;border-top:1px solid var(--soft);">
          {{ $records->links() }}
        </div>
        @endif
      </div>
    </div>
  </section>
</div>
@endsection
