@extends('layouts.app')
@section('title', 'Profile Settings')

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
      <span class="sub-nav__title">Profile</span>
      <span></span>
    </div>
  </div>

  <section style="background:var(--canvas);padding:var(--space-section) 22px;">
    <div style="max-width:560px;margin:0 auto;">

      <h1 class="t-display t-ink" style="margin:0 0 8px;text-align:center;">Profile Settings</h1>
      <p class="t-body t-muted" style="margin:0 0 40px;text-align:center;">Manage your account information and security.</p>

      @if(session('success'))
      <div class="alert alert-success" style="margin-bottom:24px;">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        {{ session('success') }}
      </div>
      @endif

      {{-- Profile info --}}
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
          <p class="t-tagline t-ink" style="margin:0;">Profile Information</p>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('profile.update') }}">
            @csrf @method('PUT')
            <div style="margin-bottom:16px;">
              <label for="name" class="form-label">Full Name</label>
              <input type="text" id="name" name="name" class="form-input"
                     value="{{ old('name', $user->name) }}" required>
              @error('name')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div style="margin-bottom:24px;">
              <label for="email" class="form-label">Email Address</label>
              <input type="email" id="email" name="email" class="form-input"
                     value="{{ old('email', $user->email) }}" required>
              @error('email')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary">Save Changes</button>
          </form>
        </div>
      </div>

      {{-- Password --}}
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
          <p class="t-tagline t-ink" style="margin:0;">Change Password</p>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('profile.password') }}">
            @csrf @method('PUT')
            <div style="margin-bottom:16px;">
              <label for="current_password" class="form-label">Current Password</label>
              <input type="password" id="current_password" name="current_password" class="form-input" required>
              @error('current_password')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div style="margin-bottom:16px;">
              <label for="password" class="form-label">New Password</label>
              <input type="password" id="password" name="password" class="form-input" required minlength="8">
              @error('password')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div style="margin-bottom:24px;">
              <label for="password_confirmation" class="form-label">Confirm New Password</label>
              <input type="password" id="password_confirmation" name="password_confirmation" class="form-input" required>
            </div>
            <button type="submit" class="btn-primary">Update Password</button>
          </form>
        </div>
      </div>

      {{-- MFA --}}
      <div class="card">
        <div class="card-header">
          <p class="t-tagline t-ink" style="margin:0;">Two-Factor Authentication</p>
        </div>
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
          <div>
            <p class="t-body t-ink" style="margin:0;font-weight:600;">
              {{ $user->mfa_enabled ? 'Enabled' : 'Disabled' }}
            </p>
            <p class="t-caption t-muted" style="margin:0;">
              {{ $user->mfa_enabled ? 'Your account is protected with 2FA.' : 'Add an extra layer of security.' }}
            </p>
          </div>
          <form method="POST" action="{{ route('profile.mfa') }}">
            @csrf @method('PUT')
            <input type="hidden" name="mfa_enabled" value="{{ $user->mfa_enabled ? '0' : '1' }}">
            <button type="submit" class="{{ $user->mfa_enabled ? 'btn-ghost-pill btn-sm' : 'btn-primary btn-sm' }}">
              {{ $user->mfa_enabled ? 'Disable' : 'Enable' }}
            </button>
          </form>
        </div>
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
