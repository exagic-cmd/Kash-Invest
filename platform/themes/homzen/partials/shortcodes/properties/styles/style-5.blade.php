<section class="flat-section-v5 bg-surface flat-recommended flat-recommended-v2">
    <div class="container-fluid">
        {!! Theme::partial('shortcode-heading', compact('shortcode')) !!}

        @if ($properties->isNotEmpty())
            @include(Theme::getThemeNamespace('views.real-estate.properties.grid'), ['itemsPerRow' => 4])
        @endif
    </div>
</section>
