@props([
    'title' => 'Nothing here yet',
    'description' => null,
    'action' => null,
])

<div class="card" style="padding:48px 24px;text-align:center;">
    <h3 class="t-section t-ink" style="font-size:28px;margin-bottom:8px;">{{ $title }}</h3>
    @if($description)
    <p class="t-body t-muted" style="margin:0 auto 20px;max-width:420px;">{{ $description }}</p>
    @endif
    @if($action)
        {{ $action }}
    @endif
</div>
