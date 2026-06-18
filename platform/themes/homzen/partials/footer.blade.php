@php
    $footerBackgroundColor = theme_option('footer_background_color', '#ffffff');
    if ($footerBackgroundColor === '#161e2d') {
        $footerBackgroundColor = '#ffffff';
    }
    
    $footerTextColor = '#5c5c5c';
    $footerHeadingColor = '#161e2d';
    $footerHoverColor = theme_option('primary_color', '#db1d23');
    $copyright = theme_option('copyright', 'Copyright &copy; ' . date('Y') . ' Kash Invest. All rights reserved.');
@endphp

<footer class="footer zolo-footer" style="background-color: {{ $footerBackgroundColor }}; color: {{ $footerTextColor }};">
    <div class="container">
        <div class="zolo-footer-grid">
            <!-- Left Side: Links, Socials, Disclaimers (70% width) -->
            <div class="zolo-footer-left">
                <!-- Row 1: Footer Links -->
                <div class="zolo-footer-links">
                    <a href="/" class="link-item bold">Kash Invest</a>
                    <a href="/contact" class="link-item">Contact Us</a>
                    <a href="/privacy-policy" class="link-item">Privacy Policy</a>
                    <a href="/cookie-policy" class="link-item">Cookie Policy</a>
                    <a href="/properties" class="link-item">Properties</a>
                    <a href="/projects" class="link-item">Projects</a>
                </div>

                <!-- Row 2: Social Icons & Copyright/Info Line -->
                <div class="zolo-footer-social-row">
                    @if($socialItems = Theme::getSocialLinks())
                        <div class="zolo-social-icons">
                            @foreach($socialItems as $item)
                                <a title="{{ $item->getName() }}" href="{{ $item->getUrl() }}" class="zolo-social-box">
                                    {!! $item->getIconHtml(['style' => 'width: 1rem; height: 1rem;']) !!}
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <div class="zolo-copyright-text">
                        {!! BaseHelper::clean($copyright) !!}
                    </div>
                </div>

                <!-- Row 3: Disclaimer Paragraphs -->
                <div class="zolo-disclaimer-paragraphs">
                    <p>{{ theme_option('footer_disclaimer_1', 'Kash Invest and its subsidiaries, websites, and applications operate as a technology platform connecting real estate buyers, sellers, and investors. All transactions, listings, and investments are subject to terms, conditions, and verification.') }}</p>
                    <p>{{ theme_option('footer_disclaimer_2', 'Mortgage calculators, projections, and financial estimates are provided for illustrative purposes only. They do not constitute financial, legal, or tax advice. Please consult with certified professionals before making investment decisions.') }}</p>
                    <p>{{ theme_option('footer_disclaimer_3', 'The REALTOR® and MLS® trademarks are properties of their respective owners and indicate adherence to professional standards and cooperative selling systems.') }}</p>
                </div>
            </div>

            <!-- Right Side: CTA Section (30% width) -->
            @if(theme_option('footer_cta_enabled', true))
                <div class="zolo-footer-right">
                    <div class="zolo-cta-content">
                        @if($subtitle = theme_option('footer_cta_subtitle', 'BECOME PARTNERS'))
                            <div class="cta-subtitle text-primary">{{ $subtitle }}</div>
                        @endif
                        @if($title = theme_option('footer_cta_title', 'List Your Properties On Homzen, Join Us Now!'))
                            <h2 class="cta-title" style="color: {{ $footerHeadingColor }};">{{ $title }}</h2>
                        @endif
                        @if(($buttonLabel = theme_option('footer_cta_button_label', 'Become A Hosting')) && ($buttonUrl = theme_option('footer_cta_button_url', '/contact')))
                            <a href="{{ $buttonUrl }}" class="tf-btn primary size-1 zolo-cta-btn">
                                {{ $buttonLabel }}
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</footer>

<style>
.zolo-footer {
    padding: 70px 0;
    border-top: 1px solid #e3e3e3;
    font-family: inherit;
    position: relative;
    z-index: 10;
}

.zolo-footer-grid {
    display: flex;
    justify-content: space-between;
    gap: 40px;
}

.zolo-footer-left {
    flex: 1;
    max-width: 60%;
    display: flex;
    flex-direction: column;
    gap: 28px;
    text-align: left;
}

.zolo-footer-right {
    width: 32%;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    text-align: left;
}

/* Links style */
.zolo-footer-links {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 24px;
}

.zolo-footer-links .link-item {
    font-size: 15px;
    font-weight: 500;
    color: {{ $footerHeadingColor }};
    text-decoration: none;
    transition: color 0.2s ease;
}

.zolo-footer-links .link-item:hover {
    color: {{ $footerHoverColor }};
}

.zolo-footer-links .link-item.bold {
    font-weight: 700;
}

/* Social & Copyright row */
.zolo-footer-social-row {
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
}

.zolo-social-icons {
    display: flex;
    align-items: center;
    gap: 12px;
}

.zolo-social-box {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1px solid #e3e3e3;
    color: {{ $footerTextColor }};
    transition: all 0.2s ease;
}

.zolo-social-box:hover {
    border-color: {{ $footerHoverColor }};
    color: #ffffff;
    background-color: {{ $footerHoverColor }};
}

.zolo-copyright-text {
    font-size: 13px;
    color: {{ $footerTextColor }};
}

/* Disclaimer paragraphs */
.zolo-disclaimer-paragraphs {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.zolo-disclaimer-paragraphs p {
    font-size: 11px;
    line-height: 1.6;
    color: {{ $footerTextColor }};
    margin: 0;
}

/* Right side CTA */
.zolo-cta-content {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.zolo-cta-content .cta-subtitle {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
}

.zolo-cta-content .cta-title {
    font-size: 26px;
    font-weight: 700;
    line-height: 1.25;
    margin: 0 0 20px 0;
}

.zolo-cta-content .zolo-cta-btn {
    border-radius: 99px !important;
    padding: 12px 32px !important;
    font-weight: 600;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 991px) {
    .zolo-footer-grid {
        flex-direction: column;
        gap: 40px;
    }
    
    .zolo-footer-left {
        max-width: 100%;
    }
    
    .zolo-footer-right {
        width: 100%;
    }
}
</style>
