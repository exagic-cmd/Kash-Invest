{{--
    FOOTER DISCLAIMER CARD — White box with developer/partner logo, text disclaimer, and copyright at the bottom right.
    Positioned right under the Inner Circle section.
--}}
@php
    $disclaimer = $landing['disclaimer'] ?? [];
    $showDisclaimer = $landing['show']['disclaimer'] ?? true;
@endphp

@if ($showDisclaimer)
    <section class="kl-section" id="disclaimer-card" style="padding-top: 1rem; padding-bottom: 2rem; background-color: #f7f9fc;">
        <div class="kl-wrap" style="max-width: 900px; margin: 0 auto;">
            <div style="background: #ffffff; border-radius: 12px; padding: 2.5rem 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04); text-align: center;">
                <div style="margin-bottom: 2rem; display: flex; justify-content: center; align-items: center; min-height: 50px;">
                    @if (!empty($disclaimer['logo']))
                        {{-- flex:none so the centring flex row can't squeeze it out of ratio --}}
                        <img src="{{ $disclaimer['logo'] }}" alt="Partner Logo"
                             style="flex:none; height:auto; width:auto; max-height:50px; max-width:240px; object-fit:contain;">
                    @else
                        {!! Theme::getLogoImage(maxHeight: 50) !!}
                    @endif
                </div>

                @php
                    $brokerageName = theme_option('site_title') ?: 'Kash Invest Realty';
                    $developerName = $landing['developer']['name'] ?: 'the developer';
                    $defaultText = sprintf(
                        '%s Brokerage does not represent %s and is a 3rd party brokerage. Brokers protected. Illustrations are artist\'s concept. Specifications are subject to change without notice. All brand names, logos, images, text, and graphics are the copyright of the owner %s. *Conditions apply. Limited time Offer. Reproduction in any form, without the prior written permission of %s, is strictly prohibited.',
                        $brokerageName,
                        $developerName,
                        $developerName,
                        $developerName
                    );
                    $text = $disclaimer['text'] ?: $defaultText;
                @endphp

                <p style="font-size: 0.82rem; line-height: 1.7; color: #555555; margin: 0 auto; max-width: 780px; font-weight: 400; text-align: justify; text-align-last: center;">
                    {{ $text }}
                </p>
            </div>

            <div style="text-align: right; margin-top: 1rem; font-size: 0.8rem; color: #888888;">
                {{ $disclaimer['copyright'] ?: '© Copyright ' . date('Y') . ' - All rights reserved | *T&C Apply.' }}
            </div>
        </div>
    </section>
@endif
