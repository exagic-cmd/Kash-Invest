{{--
    INNER CIRCLE — Centered heading, grid of icon/label items, and a CTA button.
    Editable from the admin panel.
    Uses placeholders if no data has been entered yet.
--}}
@php
    $innerCircle = $landing['innerCircle'] ?? [];
    
    $items = $innerCircle['items'] ?? [];

    $heading = $innerCircle['heading'] ?? null;
    $buttonText = $innerCircle['buttonText'] ?? null;
    $buttonLink = $innerCircle['buttonLink'] ?? null;
@endphp

@if ($heading || $items || $buttonText)
<section class="kl-section" id="inner-circle">
    <div class="kl-wrap kl-reveal">
        @if ($heading)
            <div class="kl-head-center">
                <h2 class="kl-h2" style="text-transform:uppercase;margin-bottom:3rem;text-align:center;">{{ $heading }}</h2>
            </div>
        @endif

        @if ($items)
            <div class="kl-inner-circle-grid">
                @foreach ($items as $item)
                    <div class="kl-inner-circle-item">
                        @if (!empty($item['icon']))
                            <div class="kl-inner-circle-item__icon">
                                <img src="{{ $item['icon'] }}" alt="{{ $item['label'] }}" loading="lazy" decoding="async">
                            </div>
                        @endif
                        @if (!empty($item['label']))
                            <div class="kl-inner-circle-item__label">{{ $item['label'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($buttonText && $buttonLink)
            <div style="text-align:center; margin-top:3rem;">
                <a href="{{ $buttonLink }}" class="kl-btn kl-btn--primary" style="min-width:200px;">{{ $buttonText }}</a>
            </div>
        @endif
    </div>
</section>
@endif
