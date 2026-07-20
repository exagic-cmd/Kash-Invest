{{--
    QUICK FACTS — reference layout: pale tint band, centred uppercase heading,
    two-column teal checkmark list, centred CTA.

    The reference lists facts as bare statements (developer, address,
    intersection, unit count, sizes) rather than label/value pairs, so we render
    the value and only prefix the label where it wouldn't otherwise read clearly.
    LandingData only returns facts this project actually has.
--}}
@php
    // These read fine on their own; the rest get a "Label: value" prefix.
    $selfEvident = ['Developer', 'Neighbourhood', 'Intersection', 'Suite types', 'Suite sizes'];

    $lines = collect($landing['quickFacts'])
        ->map(fn ($fact) => in_array($fact['label'], $selfEvident, true)
            ? $fact['value']
            : $fact['label'] . ': ' . $fact['value'])
        ->all();

    // The address isn't in quickFacts (it's long), but the reference leads with it.
    if ($landing['location']['address']) {
        array_unshift($lines, $landing['location']['address']);
    }
@endphp

@if ($lines)
    <section class="kl-section kl-section--tint" id="facts" aria-label="Quick facts">
        <div class="kl-wrap kl-reveal">
            <div class="kl-head-center">
                <h2 class="kl-h2">Quick Facts</h2>
            </div>

            <ul class="kl-checks kl-checks--facts">
                @foreach ($lines as $line)
                    <li class="kl-num">{{ $line }}</li>
                @endforeach
            </ul>

            <div class="kl-center-cta">
                <a href="#register" class="kl-btn kl-btn--cta">{{ $landing['cta']['secondary'] }}</a>
            </div>
        </div>
    </section>
@endif
