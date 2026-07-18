{{--
    WHY US — two-column layout: bullet list left, image right.
    Heading, bullet points, and image are all editable from the admin panel.
    Uses placeholders if no data has been entered yet.
--}}
@php
    $whyUs = $landing['whyUs'] ?? [];
    
    $points = $whyUs['points'] ?? [];

    $heading = $whyUs['heading'] ?: 'Why Kash Invest';
    $image = $whyUs['image'] ?? null;
@endphp

@if ($heading || $points || $image)
<section class="kl-section kl-section--tint" id="why-us">
    <div class="kl-wrap kl-reveal">
        <div class="kl-why-us">
            <div class="kl-why-us__copy">
                @if ($heading)
                    <h2 class="kl-h2" style="text-transform:uppercase;margin-bottom:1.5rem;">{{ $heading }}</h2>
                @endif
                @if ($points)
                    <ul class="kl-checks kl-checks--one">
                        @foreach ($points as $point)
                            <li>{{ $point }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            @if ($image)
                <div class="kl-why-us__image">
                    <img src="{{ $image }}" alt="{{ $heading ?: 'Why Us image' }}"
                         loading="lazy" decoding="async">
                </div>
            @endif
        </div>
    </div>
</section>
@endif
