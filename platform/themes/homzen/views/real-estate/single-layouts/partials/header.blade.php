@php
    $model = $model ?? $property ?? null;
    $socialSharing = \Botble\Theme\Supports\ThemeSupport::getSocialSharingButtons($model->url, $model->name);
@endphp

<div class="header-property-detail pb-4 mb-4" style="border-bottom: 1px solid #e0e0e0;">
    <!-- Top Row: Title, Location, Action Buttons -->
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div class="box-name">
            <h1 class="h4 title mb-1" style="font-size: 1.25rem;">
                {!! BaseHelper::clean($model->name) !!}
            </h1>
            @if ($model->short_address)
                <p class="text-muted mb-0" style="font-size: 0.875rem;">
                    {{ $model->short_address }}
                </p>
            @endif
        </div>

        <div class="action-buttons d-flex gap-2">
            @if($socialSharing)
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 0.25rem 0.5rem;">
                        <x-core::icon name="ti ti-share" />
                        <span>{{ __('Share') }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end p-2">
                        <li class="d-flex gap-2">
                            @foreach($socialSharing as $social)
                                <a title="{{ $social['name'] }}" href="{{ $social['url'] }}" class="btn btn-light btn-sm rounded-circle p-2 d-flex" target="_blank">
                                    {!! $social['icon'] !!}
                                </a>
                            @endforeach
                        </li>
                    </ul>
                </div>
            @endif

            @if (RealEstateHelper::isEnabledWishlist())
                <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" data-type="{{ $model instanceof \Botble\RealEstate\Models\Property ? 'property' : 'project' }}"
                        data-bb-toggle="add-to-wishlist"
                        data-id="{{ $model->getKey() }}"
                        data-add-message="{{ __('Added ":name" to wishlist successfully!', ['name' => $model->name]) }}"
                        data-remove-message="{{ __('Removed ":name" from wishlist successfully!', ['name' => $model->name]) }}"
                        aria-label="{{ __('Save') }}" style="padding: 0.25rem 0.5rem;">
                    <x-core::icon name="ti ti-heart" />
                    <span>{{ __('Save') }}</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Second Row: Huge Price and Core Specs -->
    <div class="d-flex justify-content-between align-items-end mb-3">
        @if (!setting('real_estate_hide_price', false) && (($model->price_html ?? null) || ($model->formatted_price ?? null)))
            <div class="box-price">
                <span class="fw-bold text-dark" style="font-size: 1.75rem;">{{ $model->price_html ?? $model->formatted_price }}</span>
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
