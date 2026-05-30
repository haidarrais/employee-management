<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <meta name="theme-color" content="#000000">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @auth
  <meta name="api-token" content="{{ session('api_token', '') }}">
  @else
  <meta name="api-token" content="">
  @endauth

  <title>@yield('title', 'Attendance')</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="margin:0; background:#f5f5f7;">

  {{-- ── Global Nav ─────────────────────────────────────────── --}}
  @auth
  <nav class="global-nav" role="navigation" aria-label="Global">
    <div class="global-nav__inner">
      <a href="{{ route('dashboard') }}" class="global-nav__brand">Attendance</a>

      <div class="global-nav__actions">
        {{-- Connection dot --}}
        <span id="conn-dot" title="Connected"
              style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#34c759;"></span>

        {{-- User dropdown --}}
        <div class="dropdown" style="margin-left:12px;">
          <button class="btn-dark btn-sm" style="gap:8px;" aria-haspopup="true">
            <span style="display:inline-flex;align-items:center;justify-content:center;
                         width:22px;height:22px;border-radius:50%;
                         background:rgba(255,255,255,0.15);
                         font-size:12px;font-weight:600;color:#fff;">
              {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </span>
            <span class="hide-mobile">{{ Auth::user()->name }}</span>
            <svg width="10" height="6" viewBox="0 0 10 6" fill="none" style="opacity:.6;">
              <path d="M1 1l4 4 4-4" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </button>
          <div class="dropdown-menu">
            <a href="{{ route('profile') }}" class="dropdown-item">Profile Settings</a>
            @if(Auth::user()->isAdmin() || Auth::user()->isManagement())
            <a href="{{ route('qr.generate') }}" class="dropdown-item">Generate QR Code</a>
            <a href="{{ route('attendance.history') }}" class="dropdown-item">Attendance History</a>
            <a href="{{ route('reports.payroll') }}" class="dropdown-item">Payroll Report</a>
            @endif
            @if(Auth::user()->isAdmin())
            <a href="{{ route('admin.users') }}" class="dropdown-item">Manage Users</a>
            <a href="{{ route('audit.logs') }}" class="dropdown-item">Audit Logs</a>
            @endif
            <div style="height:1px;background:var(--soft);margin:4px 0;"></div>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" class="dropdown-item dropdown-item-danger">Sign Out</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </nav>
  @endauth

  {{-- ── Page Content ────────────────────────────────────────── --}}
  <main>
    @yield('content')
  </main>

  {{-- ── Session timeout modal (mobile sessions) ────────────── --}}
  @auth
  @if(session('is_mobile'))
  <div id="session-modal" class="hidden">
    <div class="modal-backdrop" onclick="document.getElementById('session-modal').classList.add('hidden')"></div>
    <div class="modal" style="padding:32px;text-align:center;">
      <div style="width:56px;height:56px;border-radius:50%;background:#fef3c7;
                  display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
        <svg width="24" height="24" fill="none" stroke="#d97706" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      </div>
      <p class="t-tagline t-ink" style="margin:0 0 8px;">Session Expiring</p>
      <p class="t-caption t-muted" style="margin:0 0 24px;">Your session expires in 2 minutes.</p>
      <div style="display:flex;gap:12px;">
        <button id="extend-btn" class="btn-primary" style="flex:1;">Continue</button>
        <button id="signout-btn" class="btn-ghost-pill" style="flex:1;">Sign Out</button>
      </div>
    </div>
  </div>
  @endif
  @endauth

  {{-- ── Toast container ─────────────────────────────────────── --}}
  <div id="toast-container"></div>

  @stack('scripts')

  <script>
  // Connection indicator
  (function(){
    const dot = document.getElementById('conn-dot');
    if (!dot) return;
    function update(){
      dot.style.background = navigator.onLine ? '#34c759' : '#ff3b30';
      dot.title = navigator.onLine ? 'Connected' : 'Offline';
    }
    window.addEventListener('online', update);
    window.addEventListener('offline', update);
    update();
  })();

  // Session modal
  (function(){
    const modal = document.getElementById('session-modal');
    if (!modal) return;
    document.getElementById('extend-btn')?.addEventListener('click', async () => {
      await fetch('/api/v1/auth/session/extend', { method:'POST',
        headers:{'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}});
      modal.classList.add('hidden');
    });
    document.getElementById('signout-btn')?.addEventListener('click', () => {
      document.querySelector('form[action*=logout]')?.submit();
    });
  })();

  // Toast helper
  window.showToast = function(msg, type='info'){
    const c = document.getElementById('toast-container');
    const t = document.createElement('div');
    t.className = 'toast toast-' + type;
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(() => t.remove(), 4000);
  };
  </script>
</body>
</html>
