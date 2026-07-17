{{-- Footer: contact, socials, and the legal block. On a preconstruction page
     the disclaimers aren't boilerplate — they're the difference between selling
     and misleading, so they get real space rather than 8px grey text. --}}
@php
    $socials = array_filter([
        'IG' => theme_option('instagram'),
        'FB' => theme_option('facebook'),
        'IN' => theme_option('linkedin'),
        'YT' => theme_option('youtube'),
    ]);
    $phone = theme_option('hotline') ?: theme_option('phone');
    $email = theme_option('email');
@endphp

<footer class="kl-footer">
    <div class="kl-wrap">
        <div class="kl-footer__grid">
            <div>
                <h3 style="font-size:1.1rem;text-transform:var(--kl-display-tt);">{{ $landing['name'] }}</h3>
                @if ($landing['location']['address'])
                    <p class="kl-lede" style="font-size:.86rem;margin-top:.5rem;">{{ $landing['location']['address'] }}</p>
                @endif
                @if ($socials)
                    <ul class="kl-socials">
                        @foreach ($socials as $label => $url)
                            <li>
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                   aria-label="{{ $label }}">{{ $label }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div>
                <div class="kl-fact__label">Brokerage</div>
                <p class="kl-lede" style="font-size:.86rem;margin-top:.5rem;">
                    {{ theme_option('site_title') ?: 'Kash Invest Realty' }}
                    @if ($phone)
                        <br><a href="tel:{{ $phone }}">{{ $phone }}</a>
                    @endif
                    @if ($email)
                        <br><a href="mailto:{{ $email }}">{{ $email }}</a>
                    @endif
                </p>
            </div>

            <div>
                <div class="kl-fact__label">This project</div>
                <p class="kl-lede" style="font-size:.86rem;margin-top:.5rem;">
                    @if ($landing['developer']['name'])
                        Developed by {{ $landing['developer']['name'] }}<br>
                    @endif
                    @if ($landing['status'])
                        Status: {{ $landing['status'] }}
                    @endif
                </p>
                <a href="#register" class="kl-btn kl-btn--ghost" style="margin-top:1rem;">
                    {{ $landing['cta']['primary'] }}
                </a>
            </div>
        </div>

        <div class="kl-footer__legal">
            <p>{{ $landing['legal']['renderings'] }}</p>
            <p>{{ $landing['legal']['pricing'] }}</p>
            <p>{{ $landing['legal']['brokerage'] }}</p>
            <p>&copy; {{ date('Y') }} {{ theme_option('site_title') ?: 'Kash Invest' }}. All rights reserved. *T&amp;C apply.</p>
        </div>
    </div>
</footer>
