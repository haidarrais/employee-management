@props([
    'label',
    'value',
    'description' => null,
])

<div class="card" style="padding:24px;">
    <p class="t-caption t-muted" style="margin:0 0 8px;">{{ $label }}</p>
    <p style="font-size:40px;line-height:1;font-weight:700;color:var(--ink);margin:0;">{{ $value }}</p>
    @if($description)
    <p class="t-caption t-muted" style="margin:8px 0 0;">{{ $description }}</p>
    @endif
</div>
