{{--
    Shared landing document. Both themes render through this file, so the
    content structure and the data contract are provably identical — only the
    token set (and the one signature element) differ.

    Expects:
      $landing — the array from Theme\Homzen\Support\LandingData::fromProject()
      $theme   — 'dark' | 'light'

    This is a standalone document rather than a theme layout: a microsite has
    its own nav/footer and must not inherit the main site's Bootstrap build
    (which would fight these tokens). Same call the reference microsite makes.
--}}
@php
    $theme = $theme ?? 'dark';

    // Jost is the reference microsite's face; the dark theme pairs it with Inter
    // for body copy (tabular figures for the cost ladder), light uses Jost alone.
    $fonts = $theme === 'dark'
        ? 'family=Jost:wght@400;500;600&family=Inter:wght@400;500;600'
        : 'family=Jost:wght@300;400;500;600';

    $metaDescription = \Illuminate\Support\Str::limit(strip_tags((string) ($landing['overview']['description'] ?? $landing['tagline'] ?? '')), 155);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $landing['name'] }}@if ($landing['location']['shortAddress']) — {{ $landing['location']['shortAddress'] }}@endif</title>
    @if ($metaDescription)
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    <meta name="robots" content="noindex"> {{-- preview only; phase 2 decides indexing --}}

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?{{ $fonts }}&display=swap" rel="stylesheet">

    @if ($landing['hero']['image'])
        <link rel="preload" as="image" href="{{ $landing['hero']['image'] }}" fetchpriority="high">
    @endif

    @include(Theme::getThemeNamespace('views.landing.partials.styles'))
</head>
<body class="kl" data-landing-theme="{{ $theme }}" id="top">

<a href="#overview" class="kl-skip">Skip to content</a>

<main>
    {{-- Section order follows the reference microsite:
         hero -> overview -> quick facts -> cheat sheet -> location benefits. --}}
    @include(Theme::getThemeNamespace('views.landing.partials.hero'))
    @include(Theme::getThemeNamespace('views.landing.partials.overview'))
    @include(Theme::getThemeNamespace('views.landing.partials.quick-facts'))
    @include(Theme::getThemeNamespace('views.landing.partials.cheat-sheet'))

    {{-- The one place the two themes genuinely diverge. --}}
    @if ($theme === 'dark')
        @include(Theme::getThemeNamespace('views.landing.partials.signature-dark'))
    @else
        @include(Theme::getThemeNamespace('views.landing.partials.signature-light'))
    @endif

    @include(Theme::getThemeNamespace('views.landing.partials.amenities'))
    @include(Theme::getThemeNamespace('views.landing.partials.floor-plans'))
    @include(Theme::getThemeNamespace('views.landing.partials.gallery'))
    @include(Theme::getThemeNamespace('views.landing.partials.location'))
    @include(Theme::getThemeNamespace('views.landing.partials.register'))
</main>

@include(Theme::getThemeNamespace('views.landing.partials.nav'))

@include(Theme::getThemeNamespace('views.landing.partials.footer'))

<script>
(function () {
    'use strict';

    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---- reveal on scroll (skipped entirely when reduced-motion) ---- */
    var revealables = document.querySelectorAll('.kl-reveal');
    if (reduced || !('IntersectionObserver' in window)) {
        revealables.forEach(function (el) { el.setAttribute('data-shown', 'true'); });
    } else {
        var revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.setAttribute('data-shown', 'true');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
        revealables.forEach(function (el) { revealObserver.observe(el); });
    }

    /* ---- SIGNATURE (dark): light each deposit floor plate in sequence ---- */
    var rungs = document.querySelectorAll('[data-rung]');
    if (rungs.length) {
        if (reduced || !('IntersectionObserver' in window)) {
            rungs.forEach(function (r) { r.setAttribute('data-lit', 'true'); });
        } else {
            var ladderObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    var el = entry.target;
                    var i = Array.prototype.indexOf.call(rungs, el);
                    // stagger so the tower reads as rising, not flashing on
                    setTimeout(function () { el.setAttribute('data-lit', 'true'); }, Math.min(i, 6) * 110);
                    ladderObserver.unobserve(el);
                });
            }, { threshold: 0.5 });
            rungs.forEach(function (r) { ladderObserver.observe(r); });
        }
    }

    /* ---- SIGNATURE (light): today -> occupancy compare slider ---- */
    var compare = document.querySelector('[data-compare]');
    if (compare) {
        var range = compare.querySelector('[data-compare-range]');
        var after = compare.querySelector('[data-compare-after]');
        var handle = compare.querySelector('[data-compare-handle]');
        var paint = function () {
            var v = range.value + '%';
            after.style.width = v;
            handle.style.left = v;
        };
        range.addEventListener('input', paint);
        paint();
    }

    /* ---- gallery lightbox (native <dialog>, Esc closes for free) ---- */
    var dialog = document.querySelector('[data-lightbox-dialog]');
    if (dialog) {
        var dialogImg = dialog.querySelector('[data-lightbox-image]');
        var opener = null;

        document.querySelectorAll('[data-lightbox]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                opener = btn;
                dialogImg.src = btn.getAttribute('data-lightbox');
                dialogImg.alt = btn.querySelector('img') ? btn.querySelector('img').alt : '';
                if (typeof dialog.showModal === 'function') { dialog.showModal(); }
            });
        });

        dialog.querySelector('[data-lightbox-close]').addEventListener('click', function () { dialog.close(); });
        dialog.addEventListener('click', function (e) { if (e.target === dialog) { dialog.close(); } });
        // return focus where the user left it
        dialog.addEventListener('close', function () {
            dialogImg.src = '';
            if (opener) { opener.focus(); }
        });
    }

    /* ---- mobile floating CTA: show once the hero CTA is gone ---- */
    var bar = document.querySelector('[data-cta-bar]');
    var hero = document.querySelector('.kl-hero');
    if (bar && hero) {
        if (!('IntersectionObserver' in window)) {
            bar.setAttribute('data-visible', 'true');
        } else {
            new IntersectionObserver(function (entries) {
                bar.setAttribute('data-visible', entries[0].isIntersecting ? 'false' : 'true');
            }, { threshold: 0 }).observe(hero);
        }
    }

    /* ---- hero slider (arrows only; no autoplay, nothing to stop) ---- */
    var heroSlides = document.querySelectorAll('[data-hero-slide]');
    if (heroSlides.length > 1) {
        var current = 0;
        var show = function (i) {
            current = (i + heroSlides.length) % heroSlides.length;
            heroSlides.forEach(function (s, n) {
                s.setAttribute('data-active', n === current ? 'true' : 'false');
            });
        };
        var prev = document.querySelector('[data-hero-prev]');
        var next = document.querySelector('[data-hero-next]');
        if (prev) { prev.addEventListener('click', function () { show(current - 1); }); }
        if (next) { next.addEventListener('click', function () { show(current + 1); }); }
    }
})();
</script>
</body>
</html>
