@props(['title', 'message'])

<div class="alert alert-info" role="status">
    <div>
        <strong style="display:block;">{{ $title }}</strong>
        <span>{{ $message }}</span>
    </div>
</div>
