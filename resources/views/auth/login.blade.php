<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Sign In — Attendance</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="margin:0;background:#000;min-height:100vh;display:flex;flex-direction:column;">

  {{-- ── Minimal black nav ──────────────────────────────────── --}}
  <nav style="height:44px;background:#000;display:flex;align-items:center;padding:0 22px;flex-shrink:0;">
    <span style="font-size:17px;font-weight:600;color:#fff;letter-spacing:-0.374px;">Attendance</span>
  </nav>

  {{-- ── Parchment content area ─────────────────────────────── --}}
  <div style="flex:1;background:var(--parchment);display:flex;align-items:center;justify-content:center;padding:48px 16px;">
    <div style="width:100%;max-width:400px;">

      {{-- Headline --}}
      <div style="text-align:center;margin-bottom:32px;">
        <p class="t-tagline t-ink" style="margin:0 0 6px;">Employee Attendance</p>
        <h1 class="t-display t-ink" style="margin:0;">Sign in</h1>
      </div>

      {{-- Error --}}
      @if($errors->any())
      <div class="alert alert-error" style="margin-bottom:20px;" role="alert">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>{{ $errors->first() }}</span>
      </div>
      @endif

      {{-- Card --}}
      <div class="card">
        <div class="card-body" style="padding:32px;">
          <form id="login-form" method="POST" action="{{ route('login') }}">
            @csrf

            <div style="margin-bottom:20px;">
              <label for="email" class="form-label">Email</label>
              <input type="email" id="email" name="email"
                     class="form-input"
                     value="{{ old('email') }}"
                     placeholder="you@company.com"
                     autocomplete="email" autofocus required>
              @error('email')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div style="margin-bottom:24px;">
              <label for="password" class="form-label">Password</label>
              <div style="position:relative;">
                <input type="password" id="password" name="password"
                       class="form-input"
                       placeholder="••••••••"
                       autocomplete="current-password"
                       style="padding-right:48px;"
                       required>
                <button type="button" id="pw-toggle"
                        aria-label="Toggle password visibility"
                        style="position:absolute;right:14px;top:50%;transform:translateY(-50%);
                               background:none;border:none;cursor:pointer;color:var(--muted);padding:4px;">
                  <svg id="pw-eye" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                </button>
              </div>
              @error('password')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="remember" class="checkbox">
                <span class="t-caption t-muted">Remember me</span>
              </label>
            </div>

            <button type="submit" id="login-btn" class="btn-primary btn-block">
              Sign In
            </button>
          </form>
        </div>
      </div>

      {{-- Footer --}}
      <p class="t-fine t-muted" style="text-align:center;margin-top:24px;">
        © {{ date('Y') }} Employee Attendance System
      </p>
    </div>
  </div>

  <script>
  document.getElementById('pw-toggle')?.addEventListener('click', function(){
    const pw = document.getElementById('password');
    pw.type = pw.type === 'password' ? 'text' : 'password';
  });

  document.getElementById('login-form')?.addEventListener('submit', function(){
    const btn = document.getElementById('login-btn');
    btn.disabled = true;
    btn.textContent = 'Signing in…';
  });
  </script>
</body>
</html>
