<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Confirm Password — Attendance</title>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="margin:0;min-height:100vh;display:flex;flex-direction:column;">

  <nav class="global-nav" style="position:static;flex-shrink:0;">
    <div class="global-nav__inner">
      <span class="global-nav__brand">
        <img src="/brand/abdillah-works-mark.svg" alt="Abdillah Works" width="104" height="41">
      </span>
    </div>
  </nav>

  <div style="flex:1;background:var(--parchment);display:flex;align-items:center;justify-content:center;padding:48px 16px;">
    <div style="width:100%;max-width:400px;">

      {{-- Icon + headline --}}
      <div style="text-align:center;margin-bottom:32px;">
        <div style="width:56px;height:56px;border:1px solid var(--danger);
                    display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
          <svg width="24" height="24" fill="none" stroke="var(--danger)" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
          </svg>
        </div>
        <h1 class="t-display t-ink" style="margin:0 0 8px;">Confirm your password</h1>
        <p class="t-body t-muted" style="margin:0;">
          This area is restricted. Enter your password to continue as
          <strong>{{ Auth::user()->name }}</strong>.
        </p>
      </div>

      @if($errors->any())
      <div class="alert alert-error" style="margin-bottom:20px;" role="alert">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>{{ $errors->first() }}</span>
      </div>
      @endif

      <div class="card">
        <div class="card-body" style="padding:32px;">
          <form id="confirm-form" method="POST" action="{{ route('password.confirm') }}">
            @csrf

            <div style="margin-bottom:24px;">
              <label for="password" class="form-label">Password</label>
              <input type="password" id="password" name="password"
                     class="form-input"
                     placeholder="••••••••"
                     autocomplete="current-password"
                     autofocus required>
              @error('password')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <button type="submit" id="confirm-btn" class="btn-primary btn-block">
              Confirm & Continue
            </button>
          </form>
        </div>
      </div>

      <div style="text-align:center;margin-top:20px;">
        <a href="{{ route('dashboard') }}"
           style="color:var(--muted);font-size:14px;letter-spacing:-0.224px;text-decoration:none;">
          ← Back to Dashboard
        </a>
      </div>

    </div>
  </div>

  <script>
  document.getElementById('confirm-form')?.addEventListener('submit', function() {
    const btn = document.getElementById('confirm-btn');
    btn.disabled = true;
    btn.textContent = 'Verifying…';
  });
  </script>
</body>
</html>
