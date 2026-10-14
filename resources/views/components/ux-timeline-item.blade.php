@props([
    'time',
    'title',
    'description' => null,
    'type' => 'default',
])

<div style="display:flex;gap:16px;align-items:flex-start;">
    <div style="width:12px;height:12px;border-radius:999px;background:{{ $type === 'success' ? 'var(--ok)' : ($type === 'danger' ? 'var(--danger)' : 'var(--primary)') }};margin-top:8px;flex-shrink:0;"></div>
    <div>
        <p class="t-caption t-muted" style="margin:0;">{{ $time }}</p>
        <p class="t-body t-ink" style="font-weight:600;margin:0;">{{ $title }}</p>
        @if($description)
        <p class="t-caption t-muted" style="margin:4px 0 0;">{{ $description }}</p>
        @endif
    </div>
</div>
