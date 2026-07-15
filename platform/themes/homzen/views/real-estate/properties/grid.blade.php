@php
    $itemsPerRow ??= 3;
@endphp

@if ($properties->isNotEmpty())
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-xl-{{ $itemsPerRow }} g-3">
        @foreach($properties as $property)
            <div class="col">
                @include(Theme::getThemeNamespace('views.real-estate.properties.item-grid'))
            </div>
        @endforeach
    </div>
@endif
