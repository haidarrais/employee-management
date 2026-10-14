@extends('layouts.app')
@section('title', 'Set Up Two-Factor Authentication')

@section('content')
<div class="page-wrap">

  <div class="sub-nav">
    <div class="sub-nav__inner">
      <a href="{{ route('profile') }}" class="sub-nav__link" style="display:flex;align-items:center;gap:6px;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
        Profile
      </a>
      <span class="sub-nav__title">Two-Factor Setup</span>
      <span></span>
    </div>
  </div>

  <section style="background:var(--canvas);padding:var(--space-section) 22px;">
    <div style="max-width:560px;margin:0 auto;">

      <h1 class="t-display t-ink" style="margin:0 0 8px;text-align:center;">Set Up Two-Factor</h1>
      <p class="t-body t-muted" style="margin:0 0 32px;text-align:center;">
        Scan the code with your authenticator app, then enter the 6-digit code to finish.
      </p>

      @if($errors->any())
      <div class="alert alert-error" style="margin-bottom:24px;" role="alert">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>{{ $errors->first() }}</span>
      </div>
      @endif

      {{-- Step 1: scan --}}
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
          <p class="t-tagline t-ink" style="margin:0;">Step 1 — Scan this code</p>
        </div>
        <div class="card-body" style="text-align:center;">

          {{-- The QR panel is deliberately a light island: scanners need a
               light quiet zone around the code, so it stays Warm White even
               though the rest of the surface is dark. --}}
          <div style="display:inline-block;background:var(--warm-white);border:1px solid var(--hairline);
                      border-radius:0;padding:12px;margin-bottom:20px;">
            <img src="{{ $qrDataUri }}" alt="Two-factor authentication QR code"
                 width="260" height="260" style="display:block;">
          </div>

          <p class="t-caption t-muted" style="margin:0 0 6px;">
            Can't scan? Enter this key manually in your authenticator app:
          </p>
          <p class="mono tabular t-body t-ink"
             style="margin:0;font-weight:600;letter-spacing:0.08em;word-break:break-all;
                    background:var(--parchment);border:1px solid var(--line);
                    border-radius:10px;padding:12px 14px;user-select:all;">
            {{ $secret }}
          </p>

        </div>
      </div>

      {{-- Step 2: verify --}}
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
          <p class="t-tagline t-ink" style="margin:0;">Step 2 — Confirm the code</p>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('mfa.setup.confirm') }}">
            @csrf

            <div style="margin-bottom:24px;">
              <label for="code" class="form-label">Authentication Code</label>
              <input type="text" id="code" name="code"
                     class="form-input mono tabular"
                     placeholder="000000"
                     maxlength="6"
                     inputmode="numeric"
                     autocomplete="one-time-code"
                     pattern="[0-9]{6}"
                     style="text-align:center;font-size:24px;letter-spacing:0.3em;font-weight:600;"
                     autofocus required>
              @error('code')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="btn-primary btn-block">Enable Two-Factor Authentication</button>
          </form>
        </div>
      </div>

      <div class="alert alert-warning">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <span>
          Two-factor is switched on only after you confirm a code, so you can't lock
          yourself out by opening this page. If you lose access to your authenticator
          app, an administrator can reset it for you.
        </span>
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
