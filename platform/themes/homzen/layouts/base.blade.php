<!DOCTYPE html>
<html {!! Theme::htmlAttributes() !!}>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=5, user-scalable=1" name="viewport"/>

        @php
            // Dynamically override site name and browser tab title to Kash Invest
            config(['app.name' => 'Kash Invest']);
            $currentTitle = \Botble\SeoHelper\Facades\SeoHelper::getTitle();
            if (empty(trim($currentTitle)) || trim($currentTitle) === 'Homzen') {
                \Botble\SeoHelper\Facades\SeoHelper::setTitle('Kash Invest');
            } else if (str_contains($currentTitle, 'Homzen')) {
                \Botble\SeoHelper\Facades\SeoHelper::setTitle(str_replace('Homzen', 'Kash Invest', $currentTitle));
            }

            // Anchor icomoon URLs to the theme that owns style.css so the preload + inline @font-face
            // match the relative URLs compiled into style.css. Prevents duplicate font downloads when
            // a child theme inherits the parent's CSS but ships its own /fonts/ copies.
            $iconFontsBase = preg_replace('#/css/[^/]+\.css(\?[^"]*)?$#', '/fonts', Theme::asset()->url('css/style.css'));
        @endphp

        <link rel="preload" href="{{ $iconFontsBase }}/icomoon.woff2" as="font" type="font/woff2" crossorigin>

        <style>
            @font-face {
                font-family: 'icomoon';
                src: url('{{ $iconFontsBase }}/icomoon.woff2') format('woff2'),
                     url('{{ $iconFontsBase }}/icomoon.woff') format('woff'),
                     url('{{ $iconFontsBase }}/icomoon.ttf') format('truetype');
                font-weight: normal;
                font-style: normal;
                font-display: block;
            }
            [class^='icon-']:before,
            [class*=' icon-']:before {
                visibility: hidden;
            }
            .icomoon-loaded [class^='icon-']:before,
            .icomoon-loaded [class*=' icon-']:before {
                visibility: visible;
            }
            .wow:not(.animated) {
                visibility: hidden;
            }
        </style>

        <script>
            (function() {
                var html = document.documentElement;
                function markLoaded() { html.classList.add('icomoon-loaded'); }
                function tryMark() {
                    if (!document.fonts) { markLoaded(); return true; }
                    try {
                        if (document.fonts.check('1em icomoon')) { markLoaded(); return true; }
                    } catch (e) { markLoaded(); return true; }
                    return false;
                }
                if (!tryMark()) {
                    document.fonts.ready.then(markLoaded);
                }
                window.addEventListener('pageshow', function (e) {
                    if (e.persisted && !html.classList.contains('icomoon-loaded')) {
                        if (!tryMark()) document.fonts.ready.then(markLoaded);
                    }
                });
            })();
        </script>

        <style>
            :root {
                --primary-color: {{ theme_option('primary_color', '#db1d23') }};
                --hover-color: {{ theme_option('hover_color', '#cd380f') }};
                --top-header-background-color: {{ theme_option('top_header_background_color', '#f7f7f7') }};
                --top-header-text-color: {{ theme_option('top_header_text_color', '#161e2d') }};
                --main-header-background-color: {{ theme_option('main_header_background_color', '#ffffff') }};
                --main-header-text-color: {{ theme_option('main_header_text_color', '#161e2d') }};
                --main-header-border-color: {{ theme_option('main_header_border_color', '#e4e4e4') }};
                --footer-text-color: {{ theme_option('footer_text_color', '#a3abb0') }};
                --footer-heading-color: {{ theme_option('footer_heading_color', '#ffffff') }};
                --footer-hover-color: {{ theme_option('footer_hover_color', '#cd380f') }};
                --map-marker-icon-image: url({{ theme_option('map_marker_image') ? RvMedia::getImageUrl(theme_option('map_marker_image')) : Theme::asset()->url('images/map-icon.png') }});
            }
        </style>

        {!! Theme::header() !!}
    </head>

    <body {!! Theme::bodyAttributes() !!}>
        {!! apply_filters(THEME_FRONT_BODY, null) !!}

        <div id="wrapper">
            <main class="clearfix">
                @yield('content')
            </main>
        </div>

        {!! Theme::footer() !!}

        <script>
            (function () {
                function showSpinnerImmediately() {
                    var existing = document.querySelector('.preload-container');
                    if (existing) {
                        existing.style.display = 'flex';
                        existing.style.opacity = '1';
                        return;
                    }
                    var container = document.createElement('div');
                    container.className = 'preload preload-container';
                    container.setAttribute('data-auto-injected', 'true');
                    container.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background-color:#ffffff;z-index:999999;display:flex;align-items:center;justify-content:center;opacity:1;';
                    container.innerHTML = '<div class="simple-spinner"></div>';
                    document.body.appendChild(container);
                }

                function hideSpinner() {
                    var spinner = document.querySelector('.preload-container');
                    if (spinner) {
                        spinner.style.display = 'none';
                    }
                }

                document.addEventListener('click', function (e) {
                    var link = e.target.closest('a');
                    if (!link) return;

                    // Skip links handled via AJAX (such as filter form pagination, tabs, modal triggers, fancybox)
                    if (
                        link.closest('.filter-form') ||
                        link.closest('.flat-pagination') ||
                        link.hasAttribute('data-bs-toggle') ||
                        link.hasAttribute('data-bb-toggle') ||
                        link.hasAttribute('data-ajax') ||
                        link.hasAttribute('data-fancybox') ||
                        link.closest('[data-fancybox]') ||
                        link.hasAttribute('data-src') ||
                        link.classList.contains('lightbox-image')
                    ) {
                        return;
                    }

                    var href = link.getAttribute('href');
                    if (!href || href === '#' || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('tel:') || href.startsWith('mailto:')) {
                        return;
                    }

                    if (link.getAttribute('target') === '_blank' || link.hasAttribute('download') || e.ctrlKey || e.metaKey || e.shiftKey) {
                        return;
                    }

                    try {
                        var destination = new URL(href, window.location.href);
                        if (destination.origin !== window.location.origin) return;
                        if (destination.pathname === window.location.pathname && destination.search === window.location.search && destination.hash !== window.location.hash) {
                            return;
                        }

                        showSpinnerImmediately();
                    } catch (err) {}
                }, true);

                window.addEventListener('pageshow', function (e) {
                    if (e.persisted) {
                        hideSpinner();
                    }
                });

                document.addEventListener('DOMContentLoaded', function () {
                    if (typeof jQuery !== 'undefined') {
                        jQuery(document).on('ajaxComplete ajaxError', function () {
                            hideSpinner();
                        });
                    }
                });
            })();
        </script>
    </body>
</html>
