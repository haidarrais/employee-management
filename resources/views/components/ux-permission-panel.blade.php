@props([
    'title' => 'Permission Required',
    'description' => null,
    'action' => null,
])

<div class="alert alert-warning" role="status">
    <div>
        <strong style="display:block;margin-bottom:4px;">{{ $title }}</strong>
        @if($description)
        <span>{{ $description }}</span>
        @endif
        @if($action)
        <div style="margin-top:12px;">{{ $action }}</div>
        @endif
    </div>
</div>
