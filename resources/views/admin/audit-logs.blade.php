@extends('layouts.app')
@section('title', 'Audit Logs')

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
      <span class="sub-nav__title">Audit Logs</span>
      <span class="t-caption t-muted">{{ number_format($logs->total()) }} entries</span>
    </div>
  </div>

  {{-- Filters --}}
  <section style="background:var(--canvas);padding:32px 22px 0;">
    <div style="max-width:980px;margin:0 auto;">
      <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
        <select name="action" class="form-input" style="width:auto;">
          <option value="">All actions</option>
          @foreach([
            'qr_code_generated'          => 'QR Generated',
            'qr_validate_success'        => 'QR Validated',
            'qr_validate_failed'         => 'QR Failed',
            'qr_validate_not_found'      => 'QR Not Found',
            'qr_validate_geofence_failed'=> 'Geofence Violation',
            'qr_generate_access_denied'  => 'Access Denied',
            'role_access_denied'         => 'Role Denied',
            'role_changed'               => 'Role Changed',
            'login_success'              => 'Login',
            'login_failed'               => 'Login Failed',
            'logout'                     => 'Logout',
          ] as $val => $label)
          <option value="{{ $val }}" {{ request('action') === $val ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>

        <input type="number" name="user_id" value="{{ request('user_id') }}"
               class="form-input" style="width:120px;" placeholder="User ID">

        <input type="date" name="from_date" value="{{ request('from_date') }}" class="form-input" style="width:auto;">
        <input type="date" name="to_date"   value="{{ request('to_date') }}"   class="form-input" style="width:auto;">

        <button type="submit" class="btn-primary btn-sm">Filter</button>
        @if(request()->hasAny(['action','user_id','from_date','to_date']))
        <a href="{{ route('audit.logs') }}" class="btn-ghost-pill btn-sm">Clear</a>
        @endif
      </form>
    </div>
  </section>

  {{-- Table --}}
  <section style="background:var(--parchment);padding:32px 22px var(--space-section);">
    <div style="max-width:980px;margin:0 auto;">
      <div class="card" style="overflow:hidden;">
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Time</th>
                <th>User</th>
                <th>Action</th>
                <th>IP</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              @forelse($logs as $log)
              <tr style="vertical-align:top;">
                <td style="white-space:nowrap;">
                  <p style="margin:0;font-weight:600;color:var(--ink);">{{ $log->created_at?->format('M j, Y') ?? '—' }}</p>
                  <p class="t-fine t-muted" style="margin:0;">{{ $log->created_at?->format('H:i:s') ?? '' }}</p>
                </td>
                <td style="white-space:nowrap;">
                  @if($log->user)
                  <p style="margin:0;font-weight:600;color:var(--ink);">{{ $log->user->name }}</p>
                  <p class="t-fine t-muted" style="margin:0;">{{ $log->user->email }}</p>
                  @else
                  <span class="t-caption t-muted">System #{{ $log->user_id ?? '—' }}</span>
                  @endif
                </td>
                <td>
                  @php
                    $cls = match(true) {
                      str_contains($log->action,'success') || str_contains($log->action,'generated') => 'badge-success',
                      str_contains($log->action,'failed')  || str_contains($log->action,'denied') || str_contains($log->action,'geofence') => 'badge-error',
                      default => 'badge-gray',
                    };
                  @endphp
                  <span class="badge {{ $cls }}">{{ str_replace('_',' ',$log->action) }}</span>
                </td>
                <td class="mono t-caption t-muted" style="white-space:nowrap;">{{ $log->ip_address ?? '—' }}</td>
                <td>
                  @if(!empty($log->metadata))
                  @php $meta = is_array($log->metadata) ? $log->metadata : json_decode($log->metadata, true); @endphp
                  @if(is_array($meta) && count($meta))
                  <details>
                    <summary class="t-caption t-blue" style="cursor:pointer;list-style:none;">View</summary>
                    <div style="margin-top:8px;background:var(--parchment);border:1px solid var(--line);
                                border-radius:8px;padding:10px 12px;font-size:12px;font-family:monospace;
                                color:var(--ink);max-width:280px;">
                      @foreach($meta as $k => $v)
                      <div style="display:flex;gap:8px;margin-bottom:4px;">
                        <span style="color:var(--muted);flex-shrink:0;">{{ $k }}:</span>
                        <span style="word-break:break-all;">{{ is_array($v) ? json_encode($v) : $v }}</span>
                      </div>
                      @endforeach
                    </div>
                  </details>
                  @else
                  <span class="t-fine t-muted">—</span>
                  @endif
                  @else
                  <span class="t-fine t-muted">—</span>
                  @endif
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="5" style="text-align:center;padding:64px;color:var(--muted);">No log entries found.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if($logs->hasPages())
        <div style="padding:16px 20px;border-top:1px solid var(--soft);">
          {{ $logs->appends(request()->query())->links() }}
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
