@php
    $model = $model ?? $property ?? null;
    $socialSharing = \Botble\Theme\Supports\ThemeSupport::getSocialSharingButtons($model->url, $model->name);
@endphp

<div class="header-property-detail pb-4 mb-4" style="border-bottom: 1px solid #e0e0e0;">
    <!-- Top Row: Title, Location, Action Buttons -->
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div class="box-name flex-grow-1">
            <h1 class="h5 title mb-1" style="font-size: 1.25rem;">
                {!! BaseHelper::clean($model->name) !!}
            </h1>

            {{-- Address line + buttons side-by-side (mobile only, d-md-none) --}}
            <div class="d-flex justify-content-between align-items-center d-md-none">
                @if ($model->short_address)
                    <p class="text-muted mb-0" style="font-size: 0.875rem;">
                        {{ $model->short_address }}
                    </p>
                @endif

                <div class="action-buttons d-flex gap-2 ms-2">
                    @if($socialSharing)
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Share') }}" style="padding: 0.25rem 0.5rem;">
                                <x-core::icon name="ti ti-share" />
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end p-2">
                                <li class="d-flex gap-2">
                                    @foreach($socialSharing as $social)
                                        <a title="{{ $social['name'] }}" href="{{ $social['url'] }}" class="btn btn-light btn-sm rounded-circle p-2 d-flex" target="_blank">
                                            {!! $social['icon'] !!}
                                        </a>
                                    @endforeach
                                    <button type="button" title="{{ __('Copy link') }}" aria-label="{{ __('Copy link') }}" class="btn btn-light btn-sm rounded-circle p-2 d-flex js-copy-link" data-copy-url="{{ $model->url }}" data-copied-label="{{ __('Copied!') }}">
                                        <svg class="icon icon-copy svg-icon-ti-ti-link" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 15l6 -6"></path><path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464"></path><path d="M13 18l-.397 .534a5.07 5.07 0 0 1 -7.127 0a4.97 4.97 0 0 1 0 -7.071l.524 -.463"></path></svg>
                                        <svg class="icon icon-check svg-icon-ti-ti-check d-none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5l10 -10"></path></svg>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    @endif

                    @if (RealEstateHelper::isEnabledWishlist())
                        <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1"
                                data-type="{{ $model instanceof \Botble\RealEstate\Models\Property ? 'property' : 'project' }}"
                                data-bb-toggle="add-to-wishlist"
                                data-id="{{ $model->getKey() }}"
                                data-add-message="{{ __('Added \":name\" to wishlist successfully!', ['name' => $model->name]) }}"
                                data-remove-message="{{ __('Removed \":name\" from wishlist successfully!', ['name' => $model->name]) }}"
                                aria-label="{{ __('Save') }}" style="padding: 0.25rem 0.5rem;">
                            <x-core::icon name="ti ti-heart" />
                        </button>
                    @endif
                </div>
            </div>

            {{-- Address line only (desktop, d-none d-md-block) --}}
            @if ($model->short_address)
                <p class="text-muted mb-0 d-none d-md-block" style="font-size: 0.875rem;">
                    {{ $model->short_address }}
                </p>
            @endif
        </div>

        {{-- Desktop-only buttons: right of title (d-none d-md-flex) --}}
        <div class="action-buttons d-none d-md-flex gap-2 align-self-start ms-3">
            @if($socialSharing)
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Share') }}" style="padding: 0.25rem 0.5rem;">
                        <x-core::icon name="ti ti-share" />
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end p-2">
                        <li class="d-flex gap-2">
                            @foreach($socialSharing as $social)
                                <a title="{{ $social['name'] }}" href="{{ $social['url'] }}" class="btn btn-light btn-sm rounded-circle p-2 d-flex" target="_blank">
                                    {!! $social['icon'] !!}
                                </a>
                            @endforeach
                            <button type="button" title="{{ __('Copy link') }}" aria-label="{{ __('Copy link') }}" class="btn btn-light btn-sm rounded-circle p-2 d-flex js-copy-link" data-copy-url="{{ $model->url }}" data-copied-label="{{ __('Copied!') }}">
                                <svg class="icon icon-copy svg-icon-ti-ti-link" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 15l6 -6"></path><path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464"></path><path d="M13 18l-.397 .534a5.07 5.07 0 0 1 -7.127 0a4.97 4.97 0 0 1 0 -7.071l.524 -.463"></path></svg>
                                <svg class="icon icon-check svg-icon-ti-ti-check d-none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5l10 -10"></path></svg>
                            </button>
                        </li>
                    </ul>
                </div>
            @endif

            @if (RealEstateHelper::isEnabledWishlist())
                <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1"
                        data-type="{{ $model instanceof \Botble\RealEstate\Models\Property ? 'property' : 'project' }}"
                        data-bb-toggle="add-to-wishlist"
                        data-id="{{ $model->getKey() }}"
                        data-add-message="{{ __('Added \":name\" to wishlist successfully!', ['name' => $model->name]) }}"
                        data-remove-message="{{ __('Removed \":name\" from wishlist successfully!', ['name' => $model->name]) }}"
                        aria-label="{{ __('Save') }}" style="padding: 0.25rem 0.5rem;">
                    <x-core::icon name="ti ti-heart" />
                </button>
            @endif
        </div>
    </div>

    <!-- Second Row: Huge Price and Core Specs -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-3 gap-2">
        @if (!setting('real_estate_hide_price', false) && (($model->price_html ?? null) || ($model->formatted_price ?? null)))
            <div class="box-price">
                <span class="fw-bold text-dark" style="font-size: 1.4rem;">{{ $model->price_html ?? $model->formatted_price }}</span>
            </div>
        @endif

        <div class="core-specs d-flex fw-bold text-dark gap-3" style="font-size: 1rem;">
            @if (($model->number_bedroom ?? null))
                <span>{{ fmod($model->number_bedroom, 1) == 0 ? number_format($model->number_bedroom) : $model->number_bedroom }} {{ __('bed') }}</span>
            @endif
            @if (($model->number_bathroom ?? null))
                <span>{{ fmod($model->number_bathroom, 1) == 0 ? number_format($model->number_bathroom) : $model->number_bathroom }} {{ __('bath') }}</span>
            @endif
            @if (($model->square ?? null))
                <span>{{ $model->square_text }}</span>
            @endif
        </div>
    </div>

    <!-- Third Row: Badges, Dates, Views -->
    <div class="d-flex align-items-center flex-wrap gap-3 text-muted" style="font-size: 0.875rem;">
        <div>{!! BaseHelper::clean($model->status_html) !!}</div>

        @if (theme_option('real_estate_show_listing_date_on_single_detail_page', 'yes') == 'yes')
            <div class="d-flex align-items-center gap-1">
                <span>{{ __('Added') }} {{ $model->created_at ? $model->created_at->diffForHumans() : '' }}</span>
            </div>
        @endif

        @if (setting('real_estate_display_views_count_in_detail_page', true))
            <div class="d-flex align-items-center gap-1">
                <x-core::icon name="ti ti-eye" style="width:16px;" />
                <span>{{ $model->views === 1 ? __('1 View') : __(':number Views', ['number' => number_format($model->views)]) }}</span>
            </div>
        @endif
    </div>
</div>

@once
    <script>
        (function () {
            document.addEventListener('click', async function (e) {
                const btn = e.target.closest('.js-copy-link');
                if (!btn) return;

                e.preventDefault();

                const url = btn.getAttribute('data-copy-url');
                const copiedLabel = btn.getAttribute('data-copied-label') || 'Copied!';
                const originalTitle = btn.getAttribute('title');
                const iconCopy = btn.querySelector('.icon-copy');
                const iconCheck = btn.querySelector('.icon-check');

                let ok = false;
                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(url);
                        ok = true;
                    } else {
                        const ta = document.createElement('textarea');
                        ta.value = url;
                        ta.setAttribute('readonly', '');
                        ta.style.position = 'absolute';
                        ta.style.left = '-9999px';
                        document.body.appendChild(ta);
                        ta.select();
                        ok = document.execCommand('copy');
                        document.body.removeChild(ta);
                    }
                } catch (err) {
                    ok = false;
                }

                if (ok) {
                    if (iconCopy) iconCopy.classList.add('d-none');
                    if (iconCheck) iconCheck.classList.remove('d-none');
                    btn.setAttribute('title', copiedLabel);
                    setTimeout(function () {
                        if (iconCopy) iconCopy.classList.remove('d-none');
                        if (iconCheck) iconCheck.classList.add('d-none');
                        btn.setAttribute('title', originalTitle);
                    }, 1500);
                }
            });
        })();
    </script>
@endonce
