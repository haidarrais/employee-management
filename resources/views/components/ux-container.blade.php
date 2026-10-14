@props(['wide'=>false])

<div class="{{ $wide ? 'content-wrap-wide' : 'content-wrap' }}">
    {{ $slot }}
</div>
