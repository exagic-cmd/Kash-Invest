{{--
    Landing design system. One structural stylesheet, two token sets.
    Everything below is scoped under .kl so it can never leak into (or be
    polluted by) the main theme's Bootstrap build.

    Token rationale (see also: the critique in the build notes)
      dark  — warm graphite + champagne bronze + blue-steel. Deliberately NOT
              near-black + one acid accent: the base is warm (#171513, reads as
              a lit sales gallery, not a void) and there are two accents so the
              page never goes mono-neon. Bronze is used structurally only —
              lit floor plates, hairlines, figures — never as glow or gradient.
      light — pale sage-limestone + ravine green. Deliberately NOT cream +
              high-contrast serif + terracotta: the ground is sage-grey (ravine
              city, not cream), the serif is low-contrast (Fraunces, not a
              Didone), and the accent is deep green (not #D97757). Warmth comes
              from the photography and a sand neutral instead of a warm accent.
--}}
<style>
    .kl {
        /* shared scale */
        --kl-maxw: 1200px;
        --kl-gutter: clamp(1rem, 4vw, 2.5rem);
        --kl-section-y: clamp(2rem, 4vw, 3.5rem);
        --kl-radius: 0px; /* square, per the reference microsite */
        --kl-ease: cubic-bezier(.22, .61, .36, 1);
    }

    .kl[data-landing-theme="dark"] {
        --kl-bg: #171513;
        --kl-surface: #221E1A;
        --kl-surface-2: #2B2621;
        --kl-ink: #E8E3DC;
        --kl-muted: #8A8178;
        --kl-accent: #B08D57;
        --kl-accent-ink: #171513;
        --kl-cool: #5B7B8A;
        --kl-line: rgba(232, 227, 220, .14);
        --kl-display: 'Jost', 'Helvetica Neue', Arial, sans-serif;
        --kl-body: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        --kl-display-tt: uppercase;
        --kl-display-ls: .06em;
        --kl-display-w: 500;
        --kl-scrim: linear-gradient(90deg, rgba(23, 21, 19, .82) 0%, rgba(23, 21, 19, .5) 45%, rgba(23, 21, 19, .15) 80%);
    }

    /* Light follows the Square Yards reference microsite directly:
       white ground, pale-blue tint band, one teal accent, Jost throughout. */
    .kl[data-landing-theme="light"] {
        --kl-bg: #FFFFFF;
        --kl-surface: #F3F7FD;
        --kl-surface-2: #F3F7FD;
        --kl-ink: #212529;
        --kl-muted: #6C757D;
        --kl-accent: #0392A6;
        --kl-accent-ink: #FFFFFF;
        --kl-cool: #0392A6;
        --kl-line: rgba(33, 37, 41, .12);
        --kl-display: 'Jost', 'Helvetica Neue', Arial, sans-serif;
        --kl-body: 'Jost', 'Helvetica Neue', Arial, sans-serif;
        --kl-display-tt: none;
        --kl-display-ls: 0;
        --kl-display-w: 500;
        --kl-radius: 6px; /* the reference's CTA is softly rounded */
        /* hero art is bright: scrim only on the text side, for legibility */
        --kl-scrim: linear-gradient(90deg, rgba(20, 28, 36, .55) 0%, rgba(20, 28, 36, .25) 45%, rgba(20, 28, 36, 0) 75%);
    }

    /* ---------- base ---------- */
    .kl * { box-sizing: border-box; }
    .kl {
        margin: 0;
        background: var(--kl-bg);
        color: var(--kl-ink);
        font-family: var(--kl-body);
        font-size: 16px;
        line-height: 1.65;
        -webkit-font-smoothing: antialiased;
        overflow-x: hidden;
    }
    .kl img { max-width: 100%; display: block; }
    .kl a { color: inherit; }

    .kl h1, .kl h2, .kl h3, .kl h4 {
        font-family: var(--kl-display);
        font-weight: var(--kl-display-w);
        letter-spacing: var(--kl-display-ls);
        line-height: 1.1;
        margin: 0;
    }
    /* Section headings echo the reference: compact, uppercase, tracked. */
    .kl .kl-h2 {
        font-size: clamp(1.5rem, 2.4vw, 1.9rem);
        text-transform: var(--kl-display-tt);
        margin-bottom: .35rem;
    }
    .kl .kl-eyebrow {
        font-family: var(--kl-body);
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .16em;
        text-transform: uppercase;
        color: var(--kl-accent);
        margin-bottom: .9rem;
    }
    .kl .kl-lede { color: var(--kl-muted); max-width: 62ch; }
    /* Prices/deposits must line up — this page is a money document. */
    .kl .kl-num { font-variant-numeric: tabular-nums; }

    .kl-wrap { max-width: var(--kl-maxw); margin-inline: auto; padding-inline: var(--kl-gutter); }
    .kl-section { padding-block: var(--kl-section-y); border-top: 1px solid var(--kl-line); }
    .kl-section:first-of-type { border-top: 0; }

    /* ---------- buttons ---------- */
    .kl-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
        padding: .95rem 1.6rem;
        font-family: var(--kl-body); font-size: .82rem; font-weight: 600;
        letter-spacing: .1em; text-transform: uppercase; text-decoration: none;
        border: 1px solid var(--kl-accent); border-radius: var(--kl-radius);
        background: var(--kl-accent); color: #FFFFFF !important;
        cursor: pointer; transition: transform .18s var(--kl-ease), opacity .18s var(--kl-ease);
    }
    .kl-btn:hover, .kl-btn:focus, .kl-btn:active { opacity: .88; transform: translateY(-1px); color: #FFFFFF !important; }
    .kl-btn--ghost { background: transparent; color: var(--kl-ink) !important; border-color: var(--kl-line); }
    .kl-btn--ghost:hover { border-color: var(--kl-accent); color: var(--kl-accent) !important; }
    .kl-btn--block { width: 100%; }

    /* Keyboard focus must always be visible (quality floor). */
    .kl :focus-visible {
        outline: 2px solid var(--kl-accent);
        outline-offset: 3px;
    }
    .kl-skip {
        position: absolute; left: -9999px; top: 0; z-index: 100;
        background: var(--kl-accent); color: var(--kl-accent-ink); padding: .75rem 1rem;
    }
    .kl-skip:focus { left: 0; }

    /* ---------- sticky nav / floating CTA ---------- */
    .kl-nav {
        position: sticky; top: 0; z-index: 40;
        background: color-mix(in srgb, var(--kl-bg) 92%, transparent);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid var(--kl-line);
    }
    .kl-nav__in { display: flex; align-items: center; gap: 1.25rem; min-height: 62px; }
    .kl-nav__brand {
        font-family: var(--kl-display); font-weight: var(--kl-display-w);
        text-transform: var(--kl-display-tt); letter-spacing: var(--kl-display-ls);
        text-decoration: none; font-size: 1rem; margin-right: auto;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 45vw;
    }
    .kl-nav__links { display: none; gap: 1.5rem; }
    .kl-nav__links a {
        font-size: .8rem; letter-spacing: .06em; text-transform: uppercase;
        text-decoration: none; color: var(--kl-muted); white-space: nowrap;
        border-bottom: 1px solid transparent; padding-block: 2px;
    }
    .kl-nav__links a:hover, .kl-nav__links a[aria-current="true"] { color: var(--kl-ink); border-bottom-color: var(--kl-accent); }
    .kl-nav .kl-btn { padding: .6rem 1rem; font-size: .72rem; }
    @media (min-width: 900px) { .kl-nav__links { display: flex; } }

    /* The register CTA follows you at every width, like the reference's fixed bar. */
    .kl-cta-bar {
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 45;
        display: flex; gap: .6rem; align-items: center; justify-content: space-between;
        padding: .5rem var(--kl-gutter);
        background: var(--kl-bg); border-top: 1px solid var(--kl-line);
        box-shadow: 0 -6px 24px rgba(0, 0, 0, .08);
        transform: translateY(110%); transition: transform .28s var(--kl-ease);
    }
    .kl-cta-bar[data-visible="true"] { transform: translateY(0); }
    .kl-cta-bar__price { font-size: .78rem; color: var(--kl-muted); min-width: 0; flex: 1; }
    .kl-cta-bar__price span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .kl-cta-bar__price strong { display: block; color: var(--kl-ink); font-size: .95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .kl-cta-bar .kl-btn {
        white-space: nowrap;
        padding: .55rem 1.1rem;
        font-size: .76rem;
        letter-spacing: .04em;
        flex-shrink: 0;
    }
    @media (max-width: 576px) {
        .kl-cta-bar { padding: .45rem .75rem; }
        .kl-cta-bar__price strong { font-size: .88rem; }
        .kl-cta-bar .kl-btn { padding: .5rem .8rem; font-size: .7rem; letter-spacing: .02em; }
    }

    /* ---------- top bar: project mark left, brokerage right ---------- */
    .kl-topbar { background: #fff; border-bottom: 1px solid rgba(33, 37, 41, .08); }
    .kl-topbar__in { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 92px; padding-block: .75rem; }
    .kl-topbar__project { display: flex; align-items: center; gap: .85rem; text-decoration: none; color: #212529; min-width: 0; }
    /* Logos sit in a flex row next to the project name. Without flex:none the
       image is a shrinkable flex item — it gets squeezed horizontally while
       max-height pins the height, which distorts the logo. flex:none keeps its
       intrinsic ratio; object-fit:contain is a belt-and-braces guard in case a
       height ever gets forced on it. */
    .kl-topbar__project img {
        flex: none;
        width: auto;
        height: auto;
        max-height: 46px;
        max-width: 190px;
        object-fit: contain;
    }
    @media (max-width: 600px) {
        .kl-topbar__project img { max-height: 34px; max-width: 120px; }
    }
    .kl-topbar__name {
        font-family: 'Jost', sans-serif; font-weight: 500; letter-spacing: .12em; text-transform: uppercase;
        font-size: clamp(1.1rem, 2.4vw, 1.6rem); line-height: 1.1; color: #0F3B4C;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .kl-topbar__sub {
        display: block; font-size: .6rem; letter-spacing: .22em; text-transform: uppercase;
        color: #6C757D; font-weight: 400; margin-top: .25rem;
    }
    .kl-topbar__brand {
        display: flex; align-items: center; justify-content: flex-end; flex: none;
        font-family: 'Jost', sans-serif; font-weight: 700; font-size: clamp(.95rem, 2vw, 1.35rem);
        line-height: 1; text-align: right; color: #111; text-decoration: none; white-space: nowrap;
    }
    .kl-topbar__brand img {
        max-height: 44px;
        width: auto;
        max-width: 180px;
        object-fit: contain;
    }
    .kl-topbar__brand span { display: block; }
    @media (max-width: 600px) {
        .kl-topbar__brand img { max-height: 34px; max-width: 120px; }
    }

    /* ---------- hero: full-bleed art, copy left ---------- */
    .kl-hero { position: relative; min-height: min(78vh, 720px); display: flex; align-items: center; }
    .kl-hero__media { position: absolute; inset: 0; overflow: hidden; background: var(--kl-surface); }
    .kl-hero__slide { position: absolute; inset: 0; opacity: 0; transition: opacity .6s var(--kl-ease); }
    .kl-hero__slide[data-active="true"] { opacity: 1; }
    .kl-hero__media img, .kl-hero__media video { width: 100%; height: 100%; object-fit: cover; }
    .kl-hero__media::after { content: ""; position: absolute; inset: 0; background: var(--kl-scrim); pointer-events: none; }
    .kl-hero__in { position: relative; z-index: 1; width: 100%; }
    .kl-hero__copy { color: #fff; max-width: 40ch; text-shadow: 0 2px 18px rgba(0, 0, 0, .28); }
    .kl-hero h1 {
        font-size: clamp(2.2rem, 5.2vw, 3.9rem); text-transform: uppercase; letter-spacing: .01em; font-weight: 500;
    }
    .kl-hero__price {
        font-family: var(--kl-display); font-weight: 400; margin-top: .9rem;
        font-size: clamp(1.35rem, 3vw, 2.2rem);
    }
    .kl-hero__price strong { font-weight: 600; }
    .kl-hero__tagline { font-size: clamp(.95rem, 1.5vw, 1.1rem); opacity: .95; margin-top: .75rem; }
    .kl-hero__dev { font-size: .74rem; letter-spacing: .18em; text-transform: uppercase; opacity: .9; margin-bottom: .9rem; }
    .kl-hero__actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.6rem; }
    .kl-hero__badge {
        display: inline-block; padding: .35rem .7rem; margin-bottom: 1rem;
        font-size: .66rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase;
        background: var(--kl-accent); color: var(--kl-accent-ink);
    }
    /* slider arrows, as on the reference */
    .kl-hero__arrow {
        position: absolute; top: 50%; transform: translateY(-50%); z-index: 5;
        width: 44px; height: 44px; display: grid; place-items: center;
        background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px);
        border: 1px solid rgba(255, 255, 255, 0.25); border-radius: 50%;
        cursor: pointer; color: #ffffff; line-height: 1;
        opacity: .85; transition: all .2s var(--kl-ease);
    }
    .kl-hero__arrow:hover { opacity: 1; background: rgba(0, 0, 0, 0.75); transform: translateY(-50%) scale(1.06); }
    .kl-hero__arrow--prev { left: .5rem; }
    .kl-hero__arrow--next { right: .5rem; }
    @media (max-width: 767px) {
        .kl-hero__arrow {
            display: none !important;
        }
    }
    @media (min-width: 768px) { .kl-hero__arrow--prev { left: 1.25rem; } .kl-hero__arrow--next { right: 1.25rem; } }

    /* ---------- centred section head (reference style) ---------- */
    .kl-section--tint { background: var(--kl-surface); border-top: 0; }
    .kl-head-center { text-align: center; margin-bottom: clamp(1.25rem, 2.5vw, 1.75rem); }
    .kl-head-center .kl-h2 { text-transform: uppercase; }
    .kl-head-center .kl-lede { margin-inline: auto; }
    /* the overview head on the reference is title-case, not uppercase */
    .kl-head-center--title .kl-h2 { text-transform: none; font-size: clamp(1.5rem, 2.6vw, 2rem); }

    /* ---------- teal circled checkmark lists (used all over the reference) ---------- */
    .kl-checks { display: grid; gap: 1.15rem 3rem; list-style: none; margin: 0; padding: 0; }
    @media (min-width: 860px) { .kl-checks { grid-template-columns: 1fr 1fr; } }
    .kl-checks--one { grid-template-columns: 1fr; }
    .kl-checks li { display: flex; align-items: flex-start; gap: .85rem; font-size: .98rem; line-height: 1.6; }
    .kl-checks li::before {
        content: ""; flex: none; width: 22px; height: 22px; margin-top: .15rem;
        border-radius: 50%; border: 1.5px solid var(--kl-accent);
        background: no-repeat center/11px 11px;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%230392A6' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='M2 8.5L6 12.5L14 3.5'/%3e%3c/svg%3e");
    }
    /* the dark theme tints its checks bronze instead of teal */
    .kl[data-landing-theme="dark"] .kl-checks li::before {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%23B08D57' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='M2 8.5L6 12.5L14 3.5'/%3e%3c/svg%3e");
    }

    /* ---------- prose block (overview copy) ---------- */
    .kl-prose { max-width: 96ch; margin-inline: auto; }
    .kl-prose p { margin: 0 0 1.15rem; color: var(--kl-ink); }

    /* ---------- two bordered cards (cheat sheet) ---------- */
    .kl-cards2 { display: grid; gap: 1.5rem; }
    @media (min-width: 860px) { .kl-cards2 { grid-template-columns: 1fr 1fr; } }
    .kl-cards2 > div {
        border: 1px solid var(--kl-line); border-radius: 10px; padding: clamp(1.5rem, 3vw, 2.25rem);
        background: var(--kl-bg);
    }
    .kl-cards2 ul { margin: 0; }
    /* lead-in line above the hint checks in the cheat sheet's second card */
    .kl-cheat__lead { margin: 0 0 1.15rem; font-size: .98rem; line-height: 1.6; color: var(--kl-ink); }

    .kl-center-cta { text-align: center; margin-top: clamp(1.25rem, 2.5vw, 1.75rem); }

    /* ---------- quick facts: enhanced check list + CTA ----------
       Scoped with --facts so the plainer check style stays intact in the
       overview, location and cheat-sheet lists that share .kl-checks. */
    .kl-checks--facts {
        gap: clamp(0.65rem, 1.2vw, 1rem) clamp(2rem, 6vw, 5rem);
        max-width: 1080px;
        margin-inline: auto;
    }
    .kl-checks--facts li {
        gap: 1.1rem;
        font-size: 1.02rem;
        line-height: 1.55;
        align-items: center;
    }
    /* circled check with the tail running past the ring, as per the reference */
    .kl-checks--facts li::before {
        width: 27px; height: 27px; margin-top: 0;
        border: 0; border-radius: 0;
        background: no-repeat center/27px 27px;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 26 26' fill='none'%3e%3ccircle cx='11.5' cy='13.5' r='9.4' stroke='%230392A6' stroke-width='1.4'/%3e%3cpath d='M5.8 13.8 L9.9 17.9 L23 3.6' stroke='%230392A6' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'/%3e%3c/svg%3e");
    }
    .kl[data-landing-theme="dark"] .kl-checks--facts li::before {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 26 26' fill='none'%3e%3ccircle cx='11.5' cy='13.5' r='9.4' stroke='%23B08D57' stroke-width='1.4'/%3e%3cpath d='M5.8 13.8 L9.9 17.9 L23 3.6' stroke='%23B08D57' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'/%3e%3c/svg%3e");
    }

    /* softer, title-case CTA button used under the facts list */
    .kl-btn--cta {
        padding: 1.05rem 2.5rem;
        font-family: var(--kl-display);
        font-size: 1.02rem;
        font-weight: 500;
        letter-spacing: .01em;
        text-transform: none;
        border-radius: 8px;
    }

    /* ---------- quick facts ---------- */
    .kl-facts { background: var(--kl-surface); border-block: 1px solid var(--kl-line); }
    .kl-facts__grid {
        display: grid; grid-template-columns: repeat(2, 1fr);
        border-left: 1px solid var(--kl-line);
    }
    @media (min-width: 720px) { .kl-facts__grid { grid-template-columns: repeat(3, 1fr); } }
    @media (min-width: 1100px) { .kl-facts__grid { grid-template-columns: repeat(4, 1fr); } }
    .kl-fact {
        padding: 1.4rem 1.25rem;
        border-right: 1px solid var(--kl-line); border-bottom: 1px solid var(--kl-line);
    }
    .kl-fact__label { font-size: .68rem; letter-spacing: .14em; text-transform: uppercase; color: var(--kl-muted); }
    .kl-fact__value { font-family: var(--kl-display); font-size: 1.05rem; font-weight: var(--kl-display-w); margin-top: .4rem; }

    /* ---------- generic grids / cards ---------- */
    .kl-2col { display: grid; gap: clamp(2rem, 5vw, 4rem); }
    @media (min-width: 992px) { .kl-2col { grid-template-columns: 1.3fr .7fr; } }
    .kl-card { background: var(--kl-surface); border: 1px solid var(--kl-line); border-radius: var(--kl-radius); padding: 1.5rem; }

    /* ---------- why us ---------- */
    .kl-why-us { display: grid; gap: clamp(2rem, 5vw, 3rem); align-items: center; }
    @media (min-width: 860px) {
        .kl-why-us { grid-template-columns: 1fr 1fr; }
        /* no image uploaded -> don't leave an empty half */
        .kl-why-us--no-image { grid-template-columns: 1fr; }
    }
    /* width:100% stops the auto margins from shrink-wrapping the grid item to
       its longest line; the block centres while the text stays left-aligned. */
    .kl-why-us--no-image .kl-why-us__copy { width: 100%; max-width: 900px; margin-inline: auto; }
    /* spread the bullets across the freed-up width instead of one narrow column */
    @media (min-width: 860px) {
        .kl-why-us--no-image .kl-checks--one { grid-template-columns: 1fr 1fr; }
    }
    .kl-why-us__copy { padding: clamp(1.5rem, 3vw, 2.5rem); }
    .kl-why-us__image { border-radius: 10px; overflow: hidden; }
    .kl-why-us__image img { width: 100%; height: 100%; object-fit: cover; min-height: 320px; }

    /* ---------- inner circle ---------- */
    .kl-inner-circle-grid { display: grid; gap: 2rem; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); text-align: center; }
    .kl-inner-circle-item { display: flex; flex-direction: column; align-items: center; gap: 1rem; }
    /* ---------- disclaimer card logo ----------
       Covers both the uploaded partner logo and the Theme::getLogoImage()
       fallback. The fallback ships only an inline `max-height`, so on its own it
       renders at the logo's full natural width — a wide banner-style logo then
       spans most of the card and reads as stretched/oversized next to the
       uploaded logo, which was already capped. Constraining width AND height
       (with object-fit as a guard) keeps either source at a sane size and in
       proportion. `flex: none` stops the centring flex row shrinking it. */
    .kl-disclaimer-logo img {
        flex: none !important;
        width: auto !important;
        height: auto !important;
        max-height: 50px !important;
        max-width: 240px !important;
        object-fit: contain;
    }

    .kl-inner-circle-item__icon { flex: none; }
    .kl-inner-circle-item__icon img { flex: none; width: auto; height: auto; max-height: 60px; max-width: 120px; object-fit: contain; }
    .kl-inner-circle-item__label { font-weight: 600; font-size: 1.1rem; }

    .kl-amenities { display: grid; gap: 1px; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); background: var(--kl-line); border: 1px solid var(--kl-line); margin-top: 2rem; }
    .kl-amenity { background: var(--kl-bg); padding: 1.1rem 1.25rem; display: flex; align-items: center; gap: .75rem; font-size: .92rem; }
    .kl-amenity::before { content: ""; width: 6px; height: 6px; background: var(--kl-accent); flex: none; }

    /* ---------- floor plans ---------- */
    .kl-plans { display: grid; gap: 1.25rem; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); margin-top: 2rem; }
    .kl-plan { border: 1px solid var(--kl-line); padding: 1.5rem; background: var(--kl-surface); }
    .kl-plan__type { font-family: var(--kl-display); font-size: 1.2rem; font-weight: var(--kl-display-w); text-transform: var(--kl-display-tt); }
    .kl-plan__row { display: flex; justify-content: space-between; gap: 1rem; padding-block: .5rem; border-top: 1px solid var(--kl-line); margin-top: .75rem; font-size: .88rem; }
    .kl-plan__row dt { color: var(--kl-muted); }
    .kl-plan__row dd { margin: 0; }

    /* ---------- gallery ---------- */
    .kl-gallery { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); margin-top: 2rem; }
    .kl-shot { position: relative; margin: 0; border: 0; padding: 0; background: var(--kl-surface); cursor: zoom-in; overflow: hidden; }
    .kl-shot img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; transition: transform .5s var(--kl-ease); }
    .kl-shot:hover img { transform: scale(1.04); }
    .kl-shot--wide { grid-column: span 2; }
    @media (max-width: 560px) { .kl-shot--wide { grid-column: span 1; } }

    .kl-lightbox { position: fixed; inset: 0; z-index: 60; display: none; place-items: center; background: rgba(10, 9, 8, .93); padding: var(--kl-gutter); }
    .kl-lightbox[open] { display: grid; }
    .kl-lightbox img { max-width: 100%; max-height: 86vh; object-fit: contain; }
    .kl-lightbox__close { position: absolute; top: 1rem; right: 1rem; background: none; border: 1px solid rgba(255,255,255,.4); color: #fff; width: 44px; height: 44px; cursor: pointer; font-size: 1.2rem; }

    /* ---------- location ---------- */
    .kl-map { border: 1px solid var(--kl-line); background: var(--kl-surface); aspect-ratio: 16 / 10; width: 100%; }
    .kl-nearby { list-style: none; margin: 0; padding: 0; }
    .kl-nearby li { display: flex; justify-content: space-between; gap: 1rem; padding: .8rem 0; border-bottom: 1px solid var(--kl-line); font-size: .92rem; }
    .kl-nearby span:last-child { color: var(--kl-muted); }

    /* ---------- lead form ---------- */
    .kl-form { display: grid; gap: .9rem; }
    .kl-form__row { display: grid; gap: .9rem; }
    @media (min-width: 620px) { .kl-form__row { grid-template-columns: 1fr 1fr; } }
    .kl-field label { display: block; font-size: .7rem; letter-spacing: .12em; text-transform: uppercase; color: var(--kl-muted); margin-bottom: .4rem; }
    .kl-field input, .kl-field textarea {
        width: 100%; padding: .85rem 1rem; font: inherit; font-size: .95rem;
        color: var(--kl-ink); background: var(--kl-bg);
        border: 1px solid var(--kl-line); border-radius: var(--kl-radius);
    }
    .kl-field input:focus, .kl-field textarea:focus { border-color: var(--kl-accent); outline: none; }
    .kl-field textarea { min-height: 110px; resize: vertical; }
    .kl-form__note { font-size: .74rem; color: var(--kl-muted); }
    .kl-alert { padding: .9rem 1rem; border: 1px solid var(--kl-accent); background: color-mix(in srgb, var(--kl-accent) 12%, transparent); font-size: .9rem; }

    /* ---------- footer ---------- */
    .kl-footer { background: var(--kl-surface); border-top: 1px solid var(--kl-line); padding-block: clamp(2.5rem, 5vw, 4rem) 6rem; }
    .kl-footer__grid { display: grid; gap: 2rem; }
    @media (min-width: 860px) { .kl-footer__grid { grid-template-columns: 1.4fr .8fr .8fr; } }
    .kl-footer__legal { margin-top: 2.5rem; padding-top: 1.5rem; border-top: 1px solid var(--kl-line); }
    .kl-footer__legal p { font-size: .74rem; line-height: 1.7; color: var(--kl-muted); margin: 0 0 .75rem; }
    .kl-socials { display: flex; gap: .75rem; list-style: none; padding: 0; margin: 1rem 0 0; }
    .kl-socials a { width: 38px; height: 38px; display: grid; place-items: center; border: 1px solid var(--kl-line); text-decoration: none; font-size: .7rem; letter-spacing: .04em; }
    .kl-socials a:hover { border-color: var(--kl-accent); color: var(--kl-accent); }
    /* extra bottom room so the fixed CTA bar never covers the legal block */

    /* ================= SIGNATURE — dark: the Deposit Ladder =================
       The deposit schedule drawn as a tower section: each milestone is a floor
       plate that lights as it scrolls into view. The most anxiety-inducing part
       of preconstruction becomes the thing you remember. */
    .kl-ladder { display: grid; gap: 0; margin-top: 2.5rem; border-top: 1px solid var(--kl-line); }
    .kl-rung {
        position: relative; display: grid; grid-template-columns: 4.5rem 1fr auto; gap: 1rem;
        align-items: center; padding: 1.15rem 1rem 1.15rem 0;
        border-bottom: 1px solid var(--kl-line);
        opacity: .35; transform: translateY(6px);
        transition: opacity .55s var(--kl-ease), transform .55s var(--kl-ease), background-color .55s var(--kl-ease);
    }
    .kl-rung[data-lit="true"] { opacity: 1; transform: none; background: linear-gradient(90deg, color-mix(in srgb, var(--kl-accent) 10%, transparent), transparent 55%); }
    /* the lit floor plate */
    .kl-rung__plate {
        height: 3px; background: var(--kl-line); position: relative; overflow: hidden;
    }
    .kl-rung__plate::after {
        content: ""; position: absolute; inset: 0; background: var(--kl-accent);
        transform: scaleX(0); transform-origin: left; transition: transform .7s var(--kl-ease);
    }
    .kl-rung[data-lit="true"] .kl-rung__plate::after { transform: scaleX(1); }
    .kl-rung__amount { font-family: var(--kl-display); font-size: 1.15rem; font-weight: var(--kl-display-w); color: var(--kl-accent); }
    .kl-rung__label { font-size: .92rem; }
    .kl-ladder__foot { display: flex; flex-wrap: wrap; gap: 2rem; margin-top: 1.75rem; }
    .kl-ladder__foot div span { display: block; font-size: .68rem; letter-spacing: .14em; text-transform: uppercase; color: var(--kl-muted); }
    .kl-ladder__foot div strong { font-family: var(--kl-display); font-size: 1.1rem; font-weight: var(--kl-display-w); }

    /* ================ SIGNATURE — light: Today → Occupancy =================
       Drag between the site as it stands today and the rendering. It confronts
       the core preconstruction fact (it doesn't exist yet) and turns the E&OE
       disclaimer into an honest feature instead of fine print. */
    .kl-compare { position: relative; margin-top: 2rem; overflow: hidden; border: 1px solid var(--kl-line); background: var(--kl-surface); touch-action: pan-y; }
    .kl-compare img { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; display: block; }
    .kl-compare__after { position: absolute; inset: 0; width: 50%; overflow: hidden; }
    .kl-compare__after img { width: 100vw; max-width: none; height: 100%; }
    .kl-compare__handle {
        position: absolute; top: 0; bottom: 0; left: 50%; width: 2px; background: var(--kl-surface);
        transform: translateX(-1px); pointer-events: none;
    }
    .kl-compare__handle::after {
        content: "↔"; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
        width: 44px; height: 44px; display: grid; place-items: center;
        background: var(--kl-surface); color: var(--kl-ink); border: 1px solid var(--kl-line);
    }
    .kl-compare__range {
        position: absolute; inset: 0; width: 100%; margin: 0; opacity: 0; cursor: ew-resize;
    }
    .kl-compare__range:focus-visible + .kl-compare__handle::after { outline: 2px solid var(--kl-accent); outline-offset: 2px; }
    .kl-compare__tag {
        position: absolute; bottom: 1rem; padding: .4rem .7rem; font-size: .68rem;
        letter-spacing: .14em; text-transform: uppercase; background: var(--kl-surface); color: var(--kl-ink);
    }
    .kl-compare__tag--l { left: 1rem; }
    .kl-compare__tag--r { right: 1rem; }

    /* ---------- reveal-on-scroll (quality floor: reduced motion) ---------- */
    .kl-reveal { opacity: 0; transform: translateY(14px); transition: opacity .7s var(--kl-ease), transform .7s var(--kl-ease); }
    .kl-reveal[data-shown="true"] { opacity: 1; transform: none; }

    @media (prefers-reduced-motion: reduce) {
        .kl *, .kl *::before, .kl *::after {
            animation-duration: .001ms !important;
            transition-duration: .001ms !important;
            scroll-behavior: auto !important;
        }
        .kl-reveal { opacity: 1; transform: none; }
        .kl-rung { opacity: 1; transform: none; }
        .kl-rung__plate::after { transform: scaleX(1); }
        .kl-shot:hover img { transform: none; }
    }
</style>
