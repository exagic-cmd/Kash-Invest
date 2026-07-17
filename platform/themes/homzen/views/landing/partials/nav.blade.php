{{--
    Persistent CTA bar. The reference microsite has no sticky section nav — its
    header is a plain top bar (rendered in the hero partial) and the thing that
    follows you is the Register CTA. This is that bar; it appears once the hero
    scrolls out and stays put (satisfies the brief's "floating CTA that persists
    on scroll"). Toggled by the observer in base.
--}}
<div class="kl-cta-bar" data-cta-bar>
    <div class="kl-cta-bar__price">
        @if ($landing['price']['fromFormatted'])
            <span>{{ $landing['name'] }} — prices from</span>
            <strong class="kl-num">{{ $landing['price']['fromFormatted'] }}</strong>
        @else
            <strong>{{ $landing['name'] }}</strong>
        @endif
    </div>
    <a href="#register" class="kl-btn">{{ $landing['cta']['primary'] }}</a>
</div>
