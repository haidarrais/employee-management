@extends('layouts.app')
@section('title', 'Attendance Maintenance')

@section('content')
<div class="page-wrap">
  <div class="sub-nav">
    <div class="sub-nav__inner">
      <a href="{{ route('dashboard') }}" class="sub-nav__link">← Dashboard</a>
      <span class="sub-nav__title">Attendance Maintenance</span>
      <span class="badge badge-error">Superadmin only</span>
    </div>
  </div>

  <section style="background:var(--canvas);padding:48px 22px 28px;">
    <div style="max-width:1180px;margin:0 auto;">
      <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:28px;">
        <div>
          <p class="t-tagline t-muted" style="margin:0 0 8px;">System maintenance</p>
          <h1 class="t-display t-ink" style="margin:0 0 10px;">Attendance records</h1>
          <p class="t-body t-muted" style="margin:0;max-width:720px;">
            Review, export, and safely clean attendance data. Cleanup is permanent and requires an explicit confirmation phrase.
          </p>
        </div>
        <a href="{{ route('admin.attendance.export', request()->query()) }}" class="btn-primary">Export current view</a>
      </div>

      @if(session('success'))<div class="alert alert-success" style="margin-bottom:18px;">{{ session('success') }}</div>@endif
      @if(session('error'))<div class="alert alert-error" style="margin-bottom:18px;">{{ session('error') }}</div>@endif
      @if($errors->any())
        <div class="alert alert-error" style="margin-bottom:18px;">
          @foreach($errors->all() as $error)<p style="margin:0;">{{ $error }}</p>@endforeach
        </div>
      @endif

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
        <div class="card" style="padding:22px;">
          <p class="t-fine t-muted" style="margin:0 0 8px;text-transform:uppercase;letter-spacing:.06em;">Matching records</p>
          <p style="font-size:36px;line-height:1;font-weight:700;margin:0;color:var(--ink);">{{ number_format($matchingCount) }}</p>
        </div>
        <div class="card" style="padding:22px;">
          <p class="t-fine t-muted" style="margin:0 0 8px;text-transform:uppercase;letter-spacing:.06em;">Current scope</p>
          <p class="t-body t-ink" style="margin:0;font-weight:600;">
            {{ request('user_id') || request('status') || request('from_date') || request('to_date') ? 'Filtered dataset' : 'All attendance' }}
          </p>
        </div>
        <div class="card" style="padding:22px;">
          <p class="t-fine t-muted" style="margin:0 0 8px;text-transform:uppercase;letter-spacing:.06em;">Safety</p>
          <p class="t-body" style="margin:0;color:var(--danger);font-weight:600;">Cleanup is irreversible</p>
        </div>
      </div>

      <div class="card" style="padding:22px;margin-bottom:28px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
          <div>
            <h2 class="t-section t-ink" style="margin:0 0 4px;font-size:22px;">Filter records</h2>
            <p class="t-caption t-muted" style="margin:0;">Narrow the dataset before exporting or cleanup.</p>
          </div>
          @if(request()->query())
          <a href="{{ route('admin.attendance.maintenance') }}" class="btn-ghost-pill btn-sm">Reset filters</a>
          @endif
        </div>

        <form method="GET" action="{{ route('admin.attendance.maintenance') }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;align-items:end;">
          <div>
            <label class="form-label">Employee</label>
            <select name="user_id" class="form-input">
              <option value="">All employees</option>
              @foreach($employees as $employee)
              <option value="{{ $employee->id }}" {{ (string) request('user_id') === (string) $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
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
          <div><label class="form-label">From</label><input type="date" name="from_date" value="{{ request('from_date') }}" class="form-input"></div>
          <div><label class="form-label">To</label><input type="date" name="to_date" value="{{ request('to_date') }}" class="form-input"></div>
          <div><button type="submit" class="btn-primary" style="width:100%;">Apply filters</button></div>
        </form>
      </div>
    </div>
  </section>

  <section style="background:var(--parchment);padding:32px 22px;">
    <div style="max-width:1180px;margin:0 auto;">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
        <div>
          <h2 class="t-section t-ink" style="margin:0 0 4px;">Records</h2>
          <p class="t-caption t-muted" style="margin:0;">Showing the attendance entries matching the filters above.</p>
        </div>
        <span class="badge badge-gray">{{ number_format($matchingCount) }} matches</span>
      </div>

      <div class="card" style="overflow:hidden;">
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead><tr><th>Employee</th><th>Status</th><th>Check in</th><th>Check out</th><th style="text-align:center;">Worked</th><th style="text-align:center;">Distance</th></tr></thead>
            <tbody>
              @forelse($records as $record)
              <tr>
                <td><p style="margin:0;font-weight:600;color:var(--ink);">{{ $record->user?->name ?? 'Unknown user' }}</p><p class="t-fine t-muted" style="margin:0;">{{ $record->user?->email }}</p></td>
                <td><span class="badge {{ $record->status === 'confirmed' ? 'badge-success' : ($record->status === 'rejected' ? 'badge-error' : 'badge-gray') }}">{{ ucfirst($record->status) }}</span></td>
                <td style="white-space:nowrap;">{{ $record->check_in_at?->format('d M Y H:i') ?? $record->created_at->format('d M Y H:i') }}</td>
                <td style="white-space:nowrap;">{{ $record->check_out_at?->format('d M Y H:i') ?? 'Open session' }}</td>
                <td style="text-align:center;">{{ $record->workedMinutes() }}m</td>
                <td style="text-align:center;">{{ $record->distance_meters !== null ? round($record->distance_meters).'m' : '—' }}</td>
              </tr>
              @empty
              <tr><td colspan="6" style="text-align:center;padding:56px 24px;"><p class="t-body t-muted" style="margin:0;">No attendance records match these filters.</p></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if($records->hasPages())<div style="padding:16px 20px;border-top:1px solid var(--soft);">{{ $records->links() }}</div>@endif
      </div>
    </div>
  </section>

  <section style="background:var(--canvas);padding:36px 22px 56px;">
    <div style="max-width:1180px;margin:0 auto;">
      <div class="card" style="border:1px solid var(--danger);overflow:hidden;">
        <div style="padding:22px;border-bottom:1px solid var(--hairline);background:var(--danger-bg);">
          <p class="t-fine" style="margin:0 0 6px;color:var(--danger);text-transform:uppercase;letter-spacing:.06em;">Danger zone</p>
          <h2 class="t-section" style="margin:0 0 6px;color:var(--ink);font-size:22px;">Cleanup matching attendance</h2>
          <p class="t-caption t-muted" style="margin:0;">This permanently deletes only the records matching the active filters. This action cannot be undone.</p>
        </div>
        <div style="padding:22px;">
          <form method="POST" action="{{ route('admin.attendance.cleanup') }}" onsubmit="return confirm('This permanently deletes the matching attendance records. Continue?')">
            @csrf @method('DELETE')
            <input type="hidden" name="user_id" value="{{ request('user_id') }}">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <input type="hidden" name="from_date" value="{{ request('from_date') }}">
            <input type="hidden" name="to_date" value="{{ request('to_date') }}">
            <div style="display:grid;grid-template-columns:minmax(220px,1fr) auto;gap:12px;align-items:end;">
              <div>
                <label class="form-label" for="confirm_text">Type CLEANUP to confirm</label>
                <input id="confirm_text" type="text" name="confirm_text" class="form-input" placeholder="CLEANUP" autocomplete="off">
              </div>
              <button type="submit" class="btn-danger" {{ $matchingCount < 1 ? 'disabled' : '' }}>Delete {{ number_format($matchingCount) }} matching record(s)</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>
</div>
@endsection
