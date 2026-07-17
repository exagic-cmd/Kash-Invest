{{--
    Floor plans, one card per unit type.

    Like the reference microsite, full plans are gated behind registration —
    that's the lead-gen mechanic of this page type. We show the honest shape
    (unit types, size bands, entry price) and release the rest to registrants.
--}}
@if ($landing['floorPlans'])
    <section class="kl-section" id="plans">
        <div class="kl-wrap kl-reveal">
            <div class="kl-eyebrow">Floor Plans</div>
            <h2 class="kl-h2">Suite Collection</h2>
            <p class="kl-lede">
                Size bands are indicative of the project's published suite range. Individual plans,
                exact square footage and full pricing are released to registrants first.
            </p>

            <div class="kl-plans">
                @foreach ($landing['floorPlans'] as $plan)
                    <article class="kl-plan">
                        <div class="kl-plan__type">{{ $plan['type'] }}</div>

                        <dl style="margin:1rem 0 0;">
                            @if ($plan['size'])
                                <div class="kl-plan__row">
                                    <dt>Size</dt>
                                    <dd class="kl-num">{{ $plan['size'] }}</dd>
                                </div>
                            @endif
                            @if ($plan['baths'])
                                <div class="kl-plan__row">
                                    <dt>Baths</dt>
                                    <dd class="kl-num">{{ $plan['baths'] }}</dd>
                                </div>
                            @endif
                            <div class="kl-plan__row">
                                <dt>Price</dt>
                                <dd class="kl-num">{{ $plan['price'] ?: 'On request' }}</dd>
                            </div>
                        </dl>

                        <a href="#register" class="kl-btn kl-btn--ghost kl-btn--block" style="margin-top:1.25rem;">
                            Request plan
                        </a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
