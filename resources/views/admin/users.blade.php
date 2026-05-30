@extends('layouts.app')
@section('title', 'User Management')

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
      <span class="sub-nav__title">Users</span>
      <span class="t-caption t-muted">{{ $users->total() }} total</span>
    </div>
  </div>

  {{-- Filters --}}
  <section style="background:var(--canvas);padding:32px 22px 0;">
    <div style="max-width:980px;margin:0 auto;">
      <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
        <input type="text" name="search" value="{{ request('search') }}"
               class="form-input" style="flex:1;min-width:200px;"
               placeholder="Search by name or email…">
        <select name="role" class="form-input" style="width:auto;">
          <option value="">All roles</option>
          <option value="admin"      {{ request('role') === 'admin'      ? 'selected' : '' }}>Admin</option>
          <option value="management" {{ request('role') === 'management' ? 'selected' : '' }}>Management</option>
          <option value="employee"   {{ request('role') === 'employee'   ? 'selected' : '' }}>Employee</option>
        </select>
        <button type="submit" class="btn-primary btn-sm">Filter</button>
        @if(request()->hasAny(['search','role']))
        <a href="{{ route('admin.users') }}" class="btn-ghost-pill btn-sm">Clear</a>
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
                <th>User</th>
                <th>Role</th>
                <th>Daily Rate</th>
                <th>Status</th>
                <th>Joined</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($users as $user)
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:36px;height:36px;border-radius:50%;background:#dbeafe;
                                display:flex;align-items:center;justify-content:center;
                                font-size:14px;font-weight:600;color:var(--blue);flex-shrink:0;">
                      {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <div>
                      <p style="margin:0;font-weight:600;color:var(--ink);">{{ $user->name }}</p>
                      <p class="t-caption t-muted" style="margin:0;">{{ $user->email }}</p>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="badge {{ $user->role === 'admin' ? 'badge-error' : ($user->role === 'management' ? 'badge-warning' : 'badge-gray') }}">
                    {{ ucfirst($user->role) }}
                  </span>
                </td>
                <td>
                  @if($user->role === 'employee')
                    @if($user->daily_rate)
                    <span class="t-caption t-ink" style="font-weight:600;">Rp {{ number_format($user->daily_rate) }}</span>
                    <span class="t-fine t-muted">/day</span>
                    @else
                    <span class="t-fine t-muted">Global default</span>
                    @endif
                  @else
                  <span class="t-fine t-muted">—</span>
                  @endif
                </td>
                <td>
                  <span style="display:inline-flex;align-items:center;gap:6px;" class="t-caption">
                    <span class="dot {{ $user->is_active ? 'dot-green' : 'dot-red' }}"></span>
                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="t-caption t-muted">{{ $user->created_at->format('M j, Y') }}</td>
                <td style="text-align:right;">
                  <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;">
                    <a href="{{ route('admin.users.edit', $user) }}"
                       class="btn-dark btn-sm">Edit</a>
                    @if($user->id !== auth()->id())
                    <form method="POST" action="{{ route('admin.users.delete', $user) }}"
                          onsubmit="return confirm('Delete {{ addslashes($user->name) }}?')">
                      @csrf @method('DELETE')
                      <button type="submit" class="btn-danger btn-sm">Delete</button>
                    </form>
                    @endif
                  </div>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="5" style="text-align:center;padding:48px;color:var(--muted);">No users found.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if($users->hasPages())
        <div style="padding:16px 20px;border-top:1px solid var(--soft);">
          {{ $users->appends(request()->query())->links() }}
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
