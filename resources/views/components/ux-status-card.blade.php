@props([
    'status' => 'Ready',
    'message' => null,
    'action' => null,
])

<div class="card" style="padding:24px;">
    <p class="t-tagline t-blue" style="margin:0 0 8px;">Attendance Status</p>
    <h3 class="t-section t-ink" style="font-size:32px;margin-bottom:8px;">{{ $status }}</h3>
    @if($message)
    <p class="t-body t-muted" style="margin:0 0 20px;">{{ $message }}</p>
    @endif
    @if($action)
        {{ $action }}
    @endif
</div>
