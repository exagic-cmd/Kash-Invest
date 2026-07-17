{{--
    CHEAT SHEET TO SECURE A UNIT — reference layout: two bordered cards.

    This is brokerage process copy rather than project data (the reference's is
    too), but the parts that CAN be driven by the project are: the example unit
    type comes from its real suite mix, and "floor options" only appears when the
    project actually publishes a storey count.
--}}
@php
    // Use a real unit type from this project as the example, not a made-up one.
    $exampleUnit = $landing['floorPlans'][1]['type']
        ?? $landing['floorPlans'][0]['type']
        ?? '2 Bedroom';

    $steps = [
        'Indicate the type of unit you are looking for (i.e. ' . $exampleUnit . ' suite)',
        'Send this to us as soon as possible as everything is sold on a first-come, first-serve basis',
        "Send that back to us along with copies of Driver's Licenses of all Purchasers",
        'We will then call you to confirm your suite choice',
    ];

    $hints = [
        'Be as flexible as possible. Provide as many different unit choices and levels. The more options you provide the better your chances of getting a suite',
        'Be as detailed as possible. If we know exactly what you want and do not want, it will help us in getting you a suite you prefer',
    ];

    if ($landing['quickFacts'] && collect($landing['quickFacts'])->firstWhere('label', 'Storeys')) {
        $hints[] = 'Be open to the floor options as demand is very high for this project';
    }
@endphp

<section class="kl-section" id="cheat-sheet">
    <div class="kl-wrap kl-reveal">
        <div class="kl-head-center">
            <h2 class="kl-h2">Cheat Sheet To Secure A Unit</h2>
        </div>

        <div class="kl-cards2">
            <div>
                <ul class="kl-checks kl-checks--one">
                    @foreach ($steps as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ul>
            </div>

            <div>
                <ul class="kl-checks kl-checks--one">
                    <li>
                        <div>
                            *This is a high-demand project. To have the best chance at getting a suite,
                            follow these hints below:
                            <ul class="kl-sublist">
                                @foreach ($hints as $hint)
                                    <li>{{ $hint }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>
