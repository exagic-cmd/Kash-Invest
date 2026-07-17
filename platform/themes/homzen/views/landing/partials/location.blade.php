{{--
    LOCATION BENEFITS — reference layout: centred uppercase heading, two-column
    teal checkmark list. The map is kept below it (the brief calls for one) and
    only renders when the project publishes coordinates.

    "nearby" is genuine data: the facilities relation carries a distance on its
    pivot, so these are real walkability points, not invented neighbourhood copy.
--}}
@php
    $loc = $landing['location'];
    $hasCoords = $loc['lat'] && $loc['lng'];

    if ($hasCoords) {
        $d = 0.008; // ~900m box
        $bbox = implode(',', [$loc['lng'] - $d, $loc['lat'] - $d, $loc['lng'] + $d, $loc['lat'] + $d]);
        $mapUrl = 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox . '&layer=mapnik&marker=' . $loc['lat'] . ',' . $loc['lng'];
    }

    // Build the benefit lines from what this project actually publishes.
    $benefits = [];
    if ($loc['intersection']) {
        $benefits[] = 'Located at ' . $loc['intersection'];
    }
    if ($loc['neighbourhood']) {
        $benefits[] = 'In the ' . $loc['neighbourhood'] . ' neighbourhood';
    }
    foreach ($loc['nearby'] as $place) {
        $benefits[] = $place['distance']
            ? $place['name'] . ' — ' . $place['distance']
            : $place['name'];
    }
    if ($loc['shortAddress']) {
        $benefits[] = 'Minutes from the heart of ' . ($loc['city'] ?: $loc['shortAddress']);
    }
@endphp

<section class="kl-section" id="location">
    <div class="kl-wrap kl-reveal">
        <div class="kl-head-center">
            <h2 class="kl-h2">Location Benefits</h2>
            @if ($loc['address'])
                <p class="kl-lede">{{ $loc['address'] }}</p>
            @endif
        </div>

        @if ($benefits)
            <ul class="kl-checks kl-prose">
                @foreach ($benefits as $benefit)
                    <li>{{ $benefit }}</li>
                @endforeach
            </ul>
        @endif

        @if ($hasCoords)
            <div class="kl-prose" style="margin-top:clamp(2rem,4vw,3rem);">
                <iframe
                    class="kl-map"
                    src="{{ $mapUrl }}"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    title="Map showing the location of {{ $landing['name'] }}"
                ></iframe>
                <p class="kl-form__note" style="margin-top:.6rem;text-align:center;">
                    <a href="https://www.openstreetmap.org/?mlat={{ $loc['lat'] }}&mlon={{ $loc['lng'] }}#map=16/{{ $loc['lat'] }}/{{ $loc['lng'] }}"
                       target="_blank" rel="noopener noreferrer">View larger map ↗</a>
                </p>
            </div>
        @endif
    </div>
</section>
