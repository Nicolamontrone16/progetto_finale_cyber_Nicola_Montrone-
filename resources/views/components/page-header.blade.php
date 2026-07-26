@props(['eyebrow' => null, 'title', 'description' => null])
<header class="page-header reveal">
    <div>
        @if ($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
        <h1>{{ $title }}</h1>
        @if ($description)<p>{{ $description }}</p>@endif
    </div>
    @if (trim($slot) !== '')<div class="page-header-actions">{{ $slot }}</div>@endif
</header>
