@php
    $class ??= null;
    $itemsPerRow ??= 3;
    $author = $project->author;
    $investor = $project->investor;

    // Fast lookup for project custom fields
    $customFieldsMap = [];
    foreach ($project->customFields as $cf) {
        if ($cf->name && $cf->value !== null && $cf->value !== '') {
            $customFieldsMap[strtolower(trim($cf->name))] = trim($cf->value);
        }
    }

    // Selling Status (e.g. "Selling Now", "Selling", "Sold Out")
    $sellingStatus = $customFieldsMap['sales status'] ?? null;
    if (! $sellingStatus && $project->status) {
        $val = strtolower($project->status->getValue());
        if ($val === 'pre_sale' || $val === 'selling') {
            $sellingStatus = __('Selling');
        } else {
            $sellingStatus = $project->status->label();
        }
    }
    $sellingStatus = $sellingStatus ?: __('Selling');

    // Construction Status: Pre-construction / Under Construction / Complete
    $rawConstruction = $customFieldsMap['construction status'] ?? null;
    if ($rawConstruction) {
        $clean = strtolower(trim($rawConstruction));
        if ($clean === 'pre construction' || $clean === 'pre-construction') {
            $constructionStatus = 'Pre-construction';
        } elseif ($clean === 'construction' || $clean === 'under construction') {
            $constructionStatus = 'Under Construction';
        } else {
            $constructionStatus = $rawConstruction;
        }
    } elseif ($project->status) {
        $val = strtolower($project->status->getValue());
        if ($val === 'pre_sale') {
            $constructionStatus = 'Pre-construction';
        } elseif ($val === 'building') {
            $constructionStatus = 'Under Construction';
        } else {
            $constructionStatus = $project->status->label();
        }
    } else {
        $constructionStatus = 'Pre-construction';
    }

    // Time duration without "Updated" word (e.g. "10 days", "2 weeks")
    $timeDuration = null;
    if ($project->updated_at) {
        $timeDuration = $project->updated_at->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE, parts: 1);
    }

    // Location / Address
    $cardLocation = $project->location ?: ($project->short_address ?: trim(implode(', ', array_filter([$project->city_name ?? null, $project->state_name ?? null]))));

    // Neighbourhood below address
    $neighbourhood = $project->neighbour ?: ($customFieldsMap['neighbourhood'] ?? ($customFieldsMap['neighbour'] ?? ($customFieldsMap['area'] ?? null)));
@endphp

<div @class(['property-item homeya-box modern-card w-100', $class]) @if ($project->latitude && $project->longitude) data-lat="{{ $project->latitude }}" data-lng="{{ $project->longitude }}" @endif>
    <div class="archive-top h-100">
        <a href="{{ $project->url }}" class="images-group">
            <div class="images-style">
                @include(Theme::getThemeNamespace('partials.real-estate.card-image-slider'), [
                    'item' => $project,
                    'alt' => $project->name,
                    'size' => 'medium-rectangle',
                ])
            </div>
            
            <div class="modern-overlays">
                <div class="modern-overlays-left">
                    @if($sellingStatus)
                        <span class="overlay-tag tag-status">
                            <i class="icon icon-home"></i> 
                            {{ $sellingStatus }}
                        </span>
                    @endif

                    @if($constructionStatus && strcasecmp($constructionStatus, $sellingStatus) !== 0)
                        <span class="overlay-tag tag-construction">
                            {{ $constructionStatus }}
                        </span>
                    @endif
                </div>
                
                @if($timeDuration)
                    <div class="modern-overlays-right">
                        <span class="overlay-tag tag-time">
                            {{ $timeDuration }}
                        </span>
                    </div>
                @endif
            </div>
        </a>
        
        <div class="content modern-content">
            <div class="price-row mb-2">
                <div class="modern-price">
                    <a href="{{ $project->url }}" class="line-clamp-1" title="{{ $project->name }}">
                        {!! BaseHelper::clean($project->name) !!}
                    </a>
                </div>
                
                @if (RealEstateHelper::isEnabledWishlist())
                    <button type="button" class="modern-wishlist-btn"
                            data-type="project"
                            data-bb-toggle="add-to-wishlist"
                            data-id="{{ $project->getKey() }}"
                            data-add-message="{{ __('Added ":name" to wishlist successfully!', ['name' => $project->name]) }}"
                            data-remove-message="{{ __('Removed ":name" from wishlist successfully!', ['name' => $project->name]) }}"
                            aria-label="{{ __('Add to wishlist') }}"
                    >
                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                            <path d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572"></path>
                        </svg>
                    </button>
                @endif
            </div>

            <div class="modern-specs mb-2">
                @if($project->number_block)
                    <span class="spec-item">{{ $project->number_block }} {{ __('blocks') }}</span>
                @endif
                @if($project->number_floor)
                    <span class="spec-item">{{ $project->number_floor }} {{ __('floors') }}</span>
                @endif
                @if($project->number_flat)
                    <span class="spec-item">{{ $project->number_flat }} {{ __('flats') }}</span>
                @endif
                @if($project->category)
                    <span class="spec-item">{{ $project->category->name }}</span>
                @endif
            </div>

            @if (!setting('real_estate_hide_price', false))
                <div class="modern-address mb-1">
                    {{ $project->formatted_price }}
                </div>
            @endif

            @php($cardLocation = $project->location ?: ($project->short_address ?: trim(implode(', ', array_filter([$project->city_name ?? null, $project->state_name ?? null])))))
            @if ($cardLocation)
                <div class="modern-location">
                    <i class="icon icon-mapPin"></i>
                    <span class="line-clamp-1">{{ $cardLocation }}</span>
                </div>
            @endif

            @if ($neighbourhood)
                <div class="modern-neighbourhood text-muted mt-1" style="font-size: 12px; line-height: 1.3;">
                    <!-- <i class="icon icon-mapPin" style="visibility: hidden; font-size: 14px;"></i> -->
                    <span class="fw-medium text-dark">{{ __('Neighbourhood:') }}</span> {{ $neighbourhood }}
                </div>
            @endif
        </div>
    </div>
</div>

@once
<style>
.modern-card .images-group {
    position: relative;
    display: block;
    overflow: hidden;
}
.modern-card .modern-overlays {
    position: absolute;
    bottom: 10px;
    left: 10px;
    right: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    z-index: 3;
    pointer-events: none;
    flex-wrap: wrap;
}
.modern-card .modern-overlays-left {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
}
.modern-card .modern-overlays-right {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-left: auto;
}
.modern-card .overlay-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.4;
    white-space: nowrap;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
}
.modern-card .tag-status {
    background-color: #22c55e;
    color: #ffffff;
}
.modern-card .tag-construction {
    background-color: rgba(0, 0, 0, 0.82);
    color: #ffffff;
    font-weight: 700;
}
.modern-card .tag-time {
    background-color: rgba(0, 0, 0, 0.88);
    color: #ffffff;
    font-weight: 700;
}
</style>
@endonce
