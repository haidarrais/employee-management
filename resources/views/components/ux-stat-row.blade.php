@props(['label','value'])
<div style="display:flex;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid var(--hairline);">
<span class="t-body t-muted">{{ $label }}</span>
<strong class="t-body t-ink">{{ $value }}</strong>
</div>
