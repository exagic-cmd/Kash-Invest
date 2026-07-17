{{-- Lead capture #2 — near the bottom, per the brief. Same component as the
     hero form, different id so the labels stay unique. --}}
<section class="kl-section" id="register">
    <div class="kl-wrap">
        <div class="kl-2col kl-reveal">
            <div>
                <div class="kl-eyebrow">Register</div>
                <h2 class="kl-h2">Platinum Access to {{ $landing['name'] }}</h2>
                <p class="kl-lede" style="margin-top:1.25rem;">
                    Registrants receive floor plans, the full price list and the deposit structure before
                    public release. Preconstruction allocations are first-come, first-served.
                </p>

                <ul class="kl-nearby" style="margin-top:1.5rem;">
                    <li><span>Full floor plans &amp; price list</span></li>
                    <li><span>Deposit structure &amp; incentives in writing</span></li>
                    <li><span>Worksheet submission on launch day</span></li>
                    @if ($landing['developer']['name'])
                        <li><span>Direct allocation with {{ $landing['developer']['name'] }}</span></li>
                    @endif
                </ul>
            </div>

            <div class="kl-card">
                @include(Theme::getThemeNamespace('views.landing.partials.lead-form'), ['formId' => 'lead-bottom'])
            </div>
        </div>
    </div>
</section>
