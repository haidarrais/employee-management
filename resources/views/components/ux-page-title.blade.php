@props(['title','subtitle'=>null])

<div style="margin-bottom:32px;">
<h1 class="t-display t-ink">{{ $title }}</h1>
@if($subtitle)<p class="t-lead t-muted">{{ $subtitle }}</p>@endif
</div>
