<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Two-Factor Authentication — Attendance</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="margin:0;background:#000;min-height:100vh;display:flex;flex-direction:column;">

  <nav style="height:44px;background:#000;display:flex;align-items:center;padding:0 22px;flex-shrink:0;">
    <span style="font-size:17px;font-weight:600;color:#fff;letter-spacing:-0.374px;">Attendance</span>
  </nav>

  <div style="flex:1;background:var(--parchment);display:flex;align-items:center;justify-content:center;padding:48px 16px;">
    <div style="width:100%;max-width:400px;">

      <div style="text-align:center;margin-bottom:32px;">
        <div style="width:56px;height:56px;border-radius:50%;background:#dbeafe;
                    display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
          <svg width="24" height="24" fill="none" stroke="var(--blue)" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
          </svg>
        </div>
        <h1 class="t-display t-ink" style="margin:0 0 8px;">Two-Factor Auth</h1>
        <p class="t-body t-muted" style="margin:0;">Enter the 6-digit code from your authenticator app.</p>
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
          <form id="mfa-form" method="POST" action="{{ route('mfa.verify') }}">
            @csrf

            <div style="margin-bottom:28px;">
              <label for="code" class="form-label" style="text-align:center;display:block;">Authentication Code</label>
              <input type="text" id="code" name="code"
                     class="form-input mono tabular"
                     placeholder="000 000"
                     maxlength="6"
                     inputmode="numeric"
                     autocomplete="one-time-code"
                     style="text-align:center;font-size:28px;letter-spacing:0.3em;font-weight:600;"
                     autofocus required>
            </div>

            <button type="submit" id="verify-btn" class="btn-primary btn-block">
              Verify
            </button>
          </form>
        </div>
      </div>

      <div style="text-align:center;margin-top:20px;">
        <form method="POST" action="{{ route('logout') }}" style="display:inline;">
          @csrf
          <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:14px;letter-spacing:-0.224px;">
            ← Sign out and try again
          </button>
        </form>
      </div>
    </div>
  </div>

  <script>
  (function(){
    const input = document.getElementById('code');
    const form  = document.getElementById('mfa-form');
    const btn   = document.getElementById('verify-btn');

    input?.addEventListener('input', e => {
      e.target.value = e.target.value.replace(/\D/g, '');
      if (e.target.value.length === 6) form.submit();
    });

    form?.addEventListener('submit', () => {
      btn.disabled = true;
      btn.textContent = 'Verifying…';
    });
  })();
  </script>
</body>
</html>
