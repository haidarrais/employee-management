@extends('layouts.app')
@section('title', 'Attendance Station')

@section('content')
<div class="page-wrap">
  <section class="content-wrap section-pad">
    <div style="max-width:760px;margin:0 auto;">
      <div class="card" style="text-align:center;">
        <div class="card-body" style="padding:40px;">
          <p class="t-tagline" style="margin:0 0 12px;">Attendance Station</p>
          <h1 class="t-display" style="margin-bottom:12px;">QR Ready</h1>
          <p class="t-body t-muted" style="margin-bottom:28px;">Display this QR code for employees to check in and check out.</p>

          <div style="display:flex;justify-content:center;margin-bottom:28px;">
            <span id="status-badge" class="badge badge-success"><span class="dot dot-green"></span> Active</span>
          </div>

          <div style="background:white;border:1px solid var(--hairline);border-radius:var(--radius-xl);padding:24px;display:inline-block;box-shadow:var(--shadow-elevated);">
            <img id="qr-image" src="{{ $qrCodeImage }}" alt="Attendance QR" style="width:320px;height:320px;display:block;">
          </div>

          <p class="t-caption t-muted" style="margin-top:24px;text-transform:uppercase;">Expires in</p>
          <div id="countdown" class="tabular" style="font-size:56px;font-weight:700;line-height:1;margin:12px 0;">{{ str_pad($validityMinutes,2,'0',STR_PAD_LEFT) }}:00</div>

          <div style="height:6px;background:var(--canvas-soft);border-radius:999px;overflow:hidden;margin:0 auto 28px;max-width:320px;">
            <div id="progress-bar" style="height:100%;width:100%;background:var(--primary);transition:width 1s linear;"></div>
          </div>

          <div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap;">
            <button onclick="refreshQr()" class="btn-primary">Generate New QR</button>
            <a href="{{ route('dashboard') }}" class="btn-ghost-pill">Back Dashboard</a>
          </div>
        </div>
      </div>

      <div class="card" style="margin-top:24px;">
        <div class="card-body">
          <h2 class="t-section" style="font-size:22px;margin-bottom:16px;">Station Information</h2>
          <div style="display:grid;gap:12px;">
            <div class="badge badge-blue">Single use code</div>
            <div class="badge badge-gray">Validity: {{ $validityMinutes }} minutes</div>
            <div class="badge badge-gray">Required distance: {{ (int) $geofenceRadius }}m</div>
            <p class="t-caption t-muted" style="margin:8px 0 0;">Code #{{ $qrCode->id }} · Generated {{ $qrCode->generated_at->format('H:i:s') }}</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
@endsection

@push('scripts')
<script>
(function(){
 const expiresAt=new Date('{{ $expiresAt }}');
 const total={{ $validityMinutes }}*60000;
 const countdown=document.getElementById('countdown');
 const progress=document.getElementById('progress-bar');
 const badge=document.getElementById('status-badge');
 const statusUrl='{{ route('qr.status', $qrCode) }}';
 let regenerating=false;

 async function watchUsage(){
  if(regenerating) return;
  try{
   const response=await fetch(statusUrl,{headers:{Accept:'application/json'},cache:'no-store'});
   if(!response.ok) return;
   const data=await response.json();
   if(data.status==='used'){
    regenerating=true;
    badge.className='badge badge-blue';
    badge.innerHTML='<span class="dot dot-blue"></span> Used · generating next code…';
    window.location.replace('{{ route('qr.generate') }}');
   }
  }catch(error){
   // Keep the station usable if one polling request fails.
  }
 }

 function tick(){
  const rem=expiresAt-Date.now();
  if(rem<=0){
   countdown.textContent='00:00';
   progress.style.width='0%';
   badge.className='badge badge-error';
   badge.innerHTML='<span class="dot dot-red"></span> Expired';
   return;
  }
  const sec=Math.ceil(rem/1000), m=Math.floor(sec/60), s=sec%60;
  countdown.textContent=String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
  progress.style.width=((rem/total)*100)+'%';
 }
 window.refreshQr=function(){window.location.href='{{ route('qr.generate') }}';};
 tick();setInterval(tick,1000);watchUsage();setInterval(watchUsage,1000);
})();
</script>
@endpush