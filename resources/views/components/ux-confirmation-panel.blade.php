@props([
    'title' => 'Confirm action',
    'description' => null,
])

<div class="card" style="padding:24px;">
    <h3 class="t-section t-ink" style="font-size:28px;">{{ $title }}</h3>
    @if($description)
    <p class="t-body t-muted">{{ $description }}</p>
    @endif
    <div style="display:flex;gap:12px;margin-top:20px;">
        {{ $slot }}
    </div>
</div>
