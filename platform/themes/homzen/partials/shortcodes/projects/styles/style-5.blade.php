<section class="flat-section-v5 bg-surface flat-recommended flat-recommended-v2">
    <div class="container-fluid">
        {!! Theme::partial('shortcode-heading', compact('shortcode')) !!}

        @if ($projects->isNotEmpty())
            @include(Theme::getThemeNamespace('views.real-estate.projects.grid'), ['itemsPerRow' => 4])
        @endif
    </div>
</section>
