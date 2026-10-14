@props(['tone'=>'canvas'])
<section style="background:{{ $tone === 'soft' ? 'var(--canvas-soft)' : 'var(--canvas)' }};padding:var(--space-section) var(--space-gutter);">{{ $slot }}</section>
