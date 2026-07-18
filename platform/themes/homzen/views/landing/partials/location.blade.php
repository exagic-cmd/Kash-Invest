{{--
    LOCATION BENEFITS — centred uppercase heading, two-column teal checkmark list.
    "nearby" is genuine data: the facilities relation carries a distance on its
    pivot, so these are real walkability points, not invented neighbourhood copy.
--}}
@php
    $loc = $landing['location'];

    $benefits = $loc['benefits'] ?? [];

    // Fallback logic for existing projects that haven't saved the new benefits repeater yet
    if (empty($benefits)) {
        if ($loc['intersection']) {
            $benefits[] = ['point' => 'Located at ' . $loc['intersection']];
        }
        if ($loc['neighbourhood']) {
            $benefits[] = ['point' => 'In the ' . $loc['neighbourhood'] . ' neighbourhood'];
        }
        if (!empty($loc['nearby'])) {
            foreach ($loc['nearby'] as $place) {
                $benefits[] = ['point' => $place['distance']
                    ? $place['name'] . ' — ' . $place['distance']
                    : $place['name']];
            }
        }
        if ($loc['shortAddress']) {
            $benefits[] = ['point' => 'Minutes from the heart of ' . ($loc['city'] ?: $loc['shortAddress'])];
        }
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
                    @if(!empty($benefit['point']))
                        <li>{{ $benefit['point'] }}</li>
                    @endif
                @endforeach
            </ul>
        @endif
    </div>
</section>
