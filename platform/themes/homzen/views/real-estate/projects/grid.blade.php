@php
    $itemsPerRow ??= 3;
@endphp

@if ($projects->isNotEmpty())
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-xl-{{ $itemsPerRow }} g-3">
        @foreach($projects as $project)
            <div class="col">
                @include(Theme::getThemeNamespace('views.real-estate.projects.item-grid'))
            </div>
        @endforeach
    </div>
@endif
