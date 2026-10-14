@props(['title','description'=>null])
<section class="ux-hero"><h1 class="t-display t-ink">{{ $title }}</h1>@if($description)<p class="t-lead t-muted">{{ $description }}</p>@endif</section>
