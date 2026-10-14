@props(['title','action'=>null])

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;gap:16px;">
    <h2 class="t-section t-ink">{{ $title }}</h2>
    @if($action)
        {{ $action }}
    @endif
</div>
