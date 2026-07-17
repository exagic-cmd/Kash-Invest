{{--
    Top bar + hero, following the reference microsite: project mark on the left,
    brokerage mark on the right, then full-bleed art with the copy set left —
    headline, "Prices Start from $X", one teal CTA, and slider arrows.

    The hero art is the LCP element so the first slide is eager + high priority
    (and preloaded in <head>); the remaining slides lazy-load.
--}}
@php
    $slides = $landing['gallery'] ?: array_filter([$landing['hero']['image']]);
    $slides = array_slice($slides, 0, 5);
    $brand = theme_option('site_title') ?: 'Kash Invest';
@endphp

<div class="kl-topbar">
    <div class="kl-wrap kl-topbar__in">
        <a href="#top" class="kl-topbar__project">
            @if ($landing['developer']['logo'])
                <img src="{{ $landing['developer']['logo'] }}" alt="{{ $landing['developer']['name'] }}">
            @endif
            <span class="kl-topbar__name">
                {{ $landing['name'] }}
                @if ($landing['location']['shortAddress'] || $landing['location']['neighbourhood'])
                    <span class="kl-topbar__sub">
                        {{ $landing['location']['neighbourhood'] ?: $landing['location']['shortAddress'] }}
                    </span>
                @endif
            </span>
        </a>

        <a href="#register" class="kl-topbar__brand">{{ $brand }}</a>
    </div>
</div>

<header class="kl-hero">
    <div class="kl-hero__media" data-hero-slider>
        @if ($landing['hero']['video'])
            <video autoplay muted loop playsinline
                   @if ($landing['hero']['image']) poster="{{ $landing['hero']['image'] }}" @endif>
                <source src="{{ $landing['hero']['video'] }}" type="video/mp4">
            </video>
        @else
            @foreach ($slides as $i => $slide)
                <div class="kl-hero__slide" data-hero-slide data-active="{{ $i === 0 ? 'true' : 'false' }}">
                    <img src="{{ $slide }}"
                         alt="{{ $landing['name'] }} rendering {{ $i + 1 }}"
                         @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif
                         decoding="async" width="1920" height="1080">
                </div>
            @endforeach
        @endif
    </div>

    @if (count($slides) > 1 && ! $landing['hero']['video'])
        <button type="button" class="kl-hero__arrow kl-hero__arrow--prev" data-hero-prev aria-label="Previous image">&lsaquo;</button>
        <button type="button" class="kl-hero__arrow kl-hero__arrow--next" data-hero-next aria-label="Next image">&rsaquo;</button>
    @endif

    <div class="kl-hero__in">
        <div class="kl-wrap">
            <div class="kl-hero__copy">
                @if ($landing['status'])
                    <span class="kl-hero__badge">{{ $landing['status'] }}</span>
                @endif

                @if ($landing['developer']['name'])
                    <div class="kl-hero__dev">By {{ $landing['developer']['name'] }}</div>
                @endif

                <h1>{{ $landing['name'] }}</h1>

                @if ($landing['price']['fromFormatted'])
                    <div class="kl-hero__price kl-num">
                        Prices Start from <strong>{{ $landing['price']['fromFormatted'] }}</strong>
                    </div>
                @elseif ($landing['tagline'])
                    <p class="kl-hero__tagline">{{ $landing['tagline'] }}</p>
                @endif

                <div class="kl-hero__actions">
                    {{-- TODO(phase 2): point at a real brochure asset per project. --}}
                    <a href="#register" class="kl-btn">{{ $landing['cta']['secondary'] }}</a>
                </div>
            </div>
        </div>
    </div>
</header>
