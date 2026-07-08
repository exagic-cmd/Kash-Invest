@php
    Theme::set('pageTitle', __('Our Agents'));

    if (theme_option('breadcrumb_background_image_agents')) {
        Theme::set('breadcrumbBackgroundImage', theme_option('breadcrumb_background_image_agents'));
    }
@endphp

<h1 class="d-none">{{ __('Agents') }}</h1>

<style>
    .zolo-agent-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        background-color: #fff;
    }
    .zolo-agent-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.08) !important;
    }
    .zolo-avatar {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border: 4px solid #fff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .zolo-agent-info li {
        list-style: none;
        margin-bottom: 0.5rem;
        color: #6c757d;
        font-size: 0.9rem;
    }
    .zolo-agent-info li a {
        color: #6c757d;
        text-decoration: none;
        transition: color 0.2s;
    }
    .zolo-agent-info li a:hover {
        color: var(--primary-color, #000);
    }
    .zolo-agent-info svg {
        width: 16px;
        height: 16px;
        margin-right: 8px;
        vertical-align: text-bottom;
        color: var(--primary-color, #000);
    }
</style>

<section class="flat-section py-5 bg-light">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="fw-bold mb-3">{{ __('Meet Our Real Estate Professionals') }}</h2>
            <p class="text-muted max-w-700 mx-auto">{{ __('Our dedicated team is here to guide you through every step of your real estate journey.') }}</p>
        </div>

        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-4">
            @foreach($accounts as $account)
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm rounded-4 zolo-agent-card">
                        <div class="card-body p-4 d-flex flex-column align-items-center text-center">
                            
                            <!-- Agent Avatar -->
                            <a href="{{ $account->url }}" class="d-block mb-3">
                                <img src="{{ RvMedia::getImageUrl($account->avatar_url, 'thumb', false, RvMedia::getDefaultImage()) }}" alt="{{ $account->name }}" class="rounded-circle zolo-avatar">
                            </a>

                            <!-- Agent Name & Badge -->
                            <a href="{{ $account->url }}" class="text-decoration-none">
                                <h5 class="fw-bold text-dark mb-1 d-flex align-items-center justify-content-center gap-1">
                                    {{ $account->name }} {!! $account->badge !!}
                                </h5>
                            </a>
                            <p class="text-muted small mb-3">{{ __('Real Estate Agent') }}</p>

                            <!-- Agent Info (Phone, Email, Properties) -->
                            <ul class="zolo-agent-info text-start w-100 p-0 m-0 mb-4 border-top pt-3">
                                @if ($account->properties_count)
                                    <li>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                        {{ $account->properties_count }} {{ $account->properties_count === 1 ? __('Property') : __('Properties') }}
                                    </li>
                                @endif

                                @if ($account->phone && ! setting('real_estate_hide_agency_phone', 0) && ! $account->hide_phone)
                                    <li>
                                        <a href="tel:{{ $account->phone }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                            {{ $account->phone }}
                                        </a>
                                    </li>
                                @endif

                                @if ($account->email && ! setting('real_estate_hide_agency_email', 0) && ! $account->hide_email)
                                    <li>
                                        <a href="mailto:{{ $account->email }}" class="text-truncate d-inline-block align-bottom" style="max-width: 85%;">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                            {{ $account->email }}
                                        </a>
                                    </li>
                                @endif
                            </ul>

                            <!-- Action Button -->
                            <a href="{{ $account->url }}" class="btn btn-outline-dark rounded-pill w-100 mt-auto fw-medium" style="border-color: #e4e4e4;">
                                {{ __('View Profile') }}
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-5 d-flex justify-content-center">
            {{ $accounts->onEachSide(1)->links(Theme::getThemeNamespace('partials.pagination')) }}
        </div>
    </div>
</section>
