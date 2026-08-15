@php
    $model = $model ?? $property ?? null;
    $isProject = $model instanceof \Botble\RealEstate\Models\Project;
@endphp

@if (theme_option('real_estate_show_location_on_detail_page', 'yes') === 'yes')
    <div @class([$isProject ? 'box-project-card' : 'single-property-map', $class ?? null])>
        @if ($isProject)
            <h3 class="h5 fw-bold text-dark project-section-title">{{ $model->name ? $model->name . ' - ' : '' }}{{ __('Location') }}</h3>
            <hr class="project-section-divider">
        @else
            <div class="h7 title fw-7">{{ __('Location') }}</div>
        @endif

        @if (theme_option('real_estate_show_map_on_single_detail_page', 'yes') === 'yes')
            @if ($model->latitude && $model->longitude)
                <div data-bb-toggle="detail-map" id="map" style="min-height: 400px; border-radius: 8px; overflow: hidden;" data-tile-layer="{{ RealEstateHelper::getMapTileLayer() }}" data-center="{{ json_encode([$model->latitude, $model->longitude]) }}" data-map-icon="{{ $model->map_icon }}" data-max-zoom="{{ theme_option('map_max_zoom', '22') }}" data-gesture-text="{{ __('Use two fingers to move the map') }}"></div>
            @else
                <iframe width="100%" style="min-height: 400px; border-radius: 8px;" src="https://maps.google.com/maps?q={{ urlencode($model->location) }}%20&t=&z=13&ie=UTF8&iwloc=&output=embed&hl={{ app()->getLocale() }}" frameborder="0" scrolling="no" marginheight="0" marginwidth="0"></iframe>
            @endif
        @endif

        @if ($locationOnMap = ($model->location ?: $model->short_address))
            @php
                $mapUrl = 'https://www.google.com/maps/search/' . urlencode($locationOnMap);

                if ($model->latitude && $model->longitude) {
                    $mapUrl = 'https://maps.google.com/?q=' . $model->latitude . ',' . $model->longitude;
                }
            @endphp
            <ul class="info-map mt-3">
                <li>
                    <div class="fw-7">{{ __('Address') }}</div>
                    <a class="mt-1 text-variant-1 d-inline-block" href="{{ $mapUrl }}" target="_blank">
                        {{ $locationOnMap }}
                    </a>
                </li>
            </ul>
        @endif
    </div>
@endif
