{{--
    The closing enquiry form — the last thing on the page, directly under the
    disclaimer card.

    Deliberately plain: the old two-column "Platinum Access" block (bullet list,
    lede, side card) was removed so the page ends on the disclaimer and the form
    only. Keeps id="register" because the hero button and the sticky CTA bar
    both anchor to #register.
--}}
<section class="kl-section" id="register" style="padding-top:1rem;">
    <div class="kl-wrap kl-reveal" style="max-width:720px;margin:0 auto;">
        <div class="kl-head-center">
            <h2 class="kl-h2">
                {{ ($landing['register']['heading'] ?? null) ?: 'Register for ' . $landing['name'] }}
            </h2>
            @if ($landing['register']['lede'] ?? null)
                <p class="kl-lede" style="margin-top:1rem;">{{ $landing['register']['lede'] }}</p>
            @endif
        </div>

        <div class="kl-card" style="margin-top:2rem;">
            @include(Theme::getThemeNamespace('views.landing.partials.lead-form'), ['formId' => 'lead-bottom'])
        </div>
    </div>
</section>
