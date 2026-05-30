@extends('layouts.app')
@section('title', 'Edit User — ' . $user->name)

@section('content')
<div class="page-wrap">

  <div class="sub-nav">
    <div class="sub-nav__inner">
      <a href="{{ route('admin.users') }}" class="sub-nav__link" style="display:flex;align-items:center;gap:6px;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
        Users
      </a>
      <span class="sub-nav__title">Edit User</span>
      <span></span>
    </div>
  </div>

  <section style="background:var(--canvas);padding:var(--space-section) 22px;">
    <div style="max-width:560px;margin:0 auto;">

      {{-- Avatar + name --}}
      <div style="text-align:center;margin-bottom:32px;">
        <div style="width:64px;height:64px;border-radius:50%;background:#dbeafe;
                    display:flex;align-items:center;justify-content:center;
                    font-size:24px;font-weight:600;color:var(--blue);margin:0 auto 12px;">
          {{ strtoupper(substr($user->name, 0, 1)) }}
        </div>
        <h1 class="t-display t-ink" style="margin:0 0 4px;">{{ $user->name }}</h1>
        <p class="t-body t-muted" style="margin:0;">{{ $user->email }}</p>
      </div>

      @if(session('success'))
      <div class="alert alert-success" style="margin-bottom:24px;">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        {{ session('success') }}
      </div>
      @endif

      @if($errors->any())
      <div class="alert alert-error" style="margin-bottom:24px;">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
          @foreach($errors->all() as $e)
          <p style="margin:0;">{{ $e }}</p>
          @endforeach
        </div>
      </div>
      @endif

      <form method="POST" action="{{ route('admin.users.update', $user) }}">
        @csrf @method('PATCH')

        {{-- ── Profile ──────────────────────────────────────── --}}
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header"><p class="t-tagline t-ink" style="margin:0;">Profile</p></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">

            <div>
              <label class="form-label">Full Name</label>
              <input type="text" name="name" class="form-input"
                     value="{{ old('name', $user->name) }}" required>
              @error('name')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-input"
                     value="{{ old('email', $user->email) }}" required>
              @error('email')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
              <label class="form-label">Role</label>
              <select name="role" class="form-input" required>
                <option value="employee"   {{ old('role', $user->role) === 'employee'   ? 'selected' : '' }}>Employee</option>
                <option value="management" {{ old('role', $user->role) === 'management' ? 'selected' : '' }}>Management</option>
                <option value="admin"      {{ old('role', $user->role) === 'admin'      ? 'selected' : '' }}>Admin</option>
              </select>
              @error('role')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
              <label class="form-label">New Password <span class="t-fine t-muted">(leave blank to keep current)</span></label>
              <input type="password" name="password" class="form-input"
                     autocomplete="new-password" minlength="8">
              @error('password')<p class="form-error">{{ $message }}</p>@enderror
            </div>

          </div>
        </div>

        {{-- ── Pay rates ────────────────────────────────────── --}}
        <div class="card" style="margin-bottom:28px;">
          <div class="card-header">
            <p class="t-tagline t-ink" style="margin:0;">Pay Rates</p>
          </div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">

            <div style="background:var(--parchment);border:1px solid var(--line);border-radius:10px;padding:12px 14px;">
              <p class="t-caption t-muted" style="margin:0;">
                Leave a field blank to use the global schedule default
                (Rp {{ number_format(config('app.default_daily_rate', 150000)) }}/day,
                 Rp {{ number_format(config('app.default_overtime_rate', 25000)) }}/hr).
              </p>
            </div>

            <div>
              <label class="form-label">Daily Base Fee (Rp)</label>
              <input type="number" name="daily_rate" class="form-input"
                     value="{{ old('daily_rate', $user->daily_rate) }}"
                     min="0" step="1000" placeholder="e.g. 150000">
              @error('daily_rate')<p class="form-error">{{ $message }}</p>@enderror
              @if($user->daily_rate)
              <p class="form-hint">
                Currently: <strong>Rp {{ number_format($user->daily_rate) }}</strong>/day
              </p>
              @else
              <p class="form-hint">Using global default.</p>
              @endif
            </div>

            <div>
              <label class="form-label">Overtime Rate per Hour (Rp)</label>
              <input type="number" name="overtime_rate" class="form-input"
                     value="{{ old('overtime_rate', $user->overtime_rate) }}"
                     min="0" step="1000" placeholder="e.g. 25000">
              @error('overtime_rate')<p class="form-error">{{ $message }}</p>@enderror
              @if($user->overtime_rate)
              <p class="form-hint">
                Currently: <strong>Rp {{ number_format($user->overtime_rate) }}</strong>/hr
              </p>
              @else
              <p class="form-hint">Using global default.</p>
              @endif
            </div>

          </div>
        </div>

        <div style="display:flex;gap:12px;">
          <button type="submit" class="btn-primary" style="flex:1;">Save Changes</button>
          <a href="{{ route('admin.users') }}" class="btn-ghost-pill" style="flex:1;text-align:center;">Cancel</a>
        </div>
      </form>

    </div>
  </section>

  <footer style="background:var(--parchment);border-top:1px solid var(--line);padding:24px 22px;">
    <div style="max-width:980px;margin:0 auto;text-align:center;">
      <p class="t-fine t-muted" style="margin:0;">© {{ date('Y') }} Employee Attendance System</p>
    </div>
  </footer>

</div>
@endsection
