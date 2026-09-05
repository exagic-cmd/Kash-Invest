@php
    $class ??= null;
    $itemsPerRow ??= 3;
    $author = $property->author;
    $brokerage = $property->listing_brokerage;

    // Fast lookup for property custom fields
    $customFieldsMap = [];
    foreach ($property->customFields as $cf) {
        if ($cf->name && $cf->value !== null && $cf->value !== '') {
            $customFieldsMap[strtolower(trim($cf->name))] = trim($cf->value);
        }
    }

    // Bedroom formatting: "5+1 bed" if below grade exists, otherwise "5 bed"
    $bedsAbove = $customFieldsMap['bedrooms above grade'] ?? null;
    $bedsBelow = $customFieldsMap['bedrooms below grade'] ?? null;
    if ($bedsAbove !== null && $bedsBelow !== null && (int)$bedsBelow > 0) {
        $bedText = sprintf('%d+%d %s', (int)$bedsAbove, (int)$bedsBelow, __('bed'));
    } elseif ($bedsAbove !== null && (int)$bedsAbove > 0) {
        $bedText = sprintf('%d %s', (int)$bedsAbove, __('bed'));
    } elseif ($property->number_bedroom) {
        $bedText = (fmod($property->number_bedroom, 1) == 0 ? number_format($property->number_bedroom) : $property->number_bedroom) . ' ' . __('bed');
    } else {
        $bedText = null;
    }

    // Bath formatting: "8 bath"
    $bathText = null;
    if ($property->number_bathroom) {
        $bathText = (fmod($property->number_bathroom, 1) == 0 ? number_format($property->number_bathroom) : $property->number_bathroom) . ' ' . __('bath');
    }

    // Square footage formatting: "5000 + sqft" or square_text
    $livingAreaRange = $customFieldsMap['living area range'] ?? null;
    if ($livingAreaRange && !in_array(strtolower($livingAreaRange), ['unknown', 'n/a', 'na', 'null', 'none', '-'], true)) {
        $sqftText = $livingAreaRange . ' sqft';
    } elseif ($property->square) {
        $sqftText = $property->square_text;
    } else {
        $sqftText = null;
    }

    // Age formatting: "0-5 Years Old" (removed fake placeholder year)
    $approxAge = $customFieldsMap['approximate age'] ?? null;
    $yearBuiltVal = $customFieldsMap['year built'] ?? null;
    $ageText = null;
    if ($approxAge && !in_array(strtolower($approxAge), ['unknown', 'n/a', 'na', 'null', 'none', '-'], true)) {
        if (str_contains(strtolower($approxAge), 'year') || str_contains(strtolower($approxAge), 'new')) {
            $ageText = $approxAge;
        } else {
            $ageText = __(':age Years Old', ['age' => $approxAge]);
        }
    } elseif ($yearBuiltVal && is_numeric($yearBuiltVal) && (int)$yearBuiltVal > 1800) {
        $yearsOld = now()->year - (int)$yearBuiltVal;
        $ageText = $yearsOld > 0 ? __(':age Years Old', ['age' => $yearsOld]) : __('New');
    }

    // Determine if property is a Condo or Freehold/House
    $propertyType = strtolower($customFieldsMap['property type'] ?? '');
    $propertySubType = strtolower($customFieldsMap['property sub type'] ?? '');
    $isCondo = str_contains($propertyType, 'condo') || str_contains($propertySubType, 'condo') || str_contains($propertySubType, 'apartment');

    // For Condo: Monthly Maintenance Fee (Association Fee)
    $maintFeeText = null;
    if ($isCondo) {
        $maintFeeVal = $customFieldsMap['association fee'] ?? null;
        if ($maintFeeVal && is_numeric($maintFeeVal) && (float)$maintFeeVal > 0) {
            $maintFeeText = '$' . number_format((float)$maintFeeVal) . '/mo ' . __('Maint');
        }
    }

    // Parking total or spaces formatting (e.g. "9 parking")
    $parkingVal = $customFieldsMap['parking total'] ?? ($customFieldsMap['parking spaces'] ?? null);
    $parkingText = null;
    if ($parkingVal && is_numeric($parkingVal) && (int)$parkingVal > 0) {
        $parkingText = sprintf('%d %s', (int)$parkingVal, __('parking'));
    }

    // For House / Freehold: Complete Lot Size (e.g. "Lot: 150.92 x 459.44 ft (1.58 ac)")
    $lotSizeText = null;
    if (! $isCondo) {
        $lotArea = $customFieldsMap['lot size'] ?? null;
        $lotUnits = $customFieldsMap['lot size units'] ?? '';
        $lotWidth = $customFieldsMap['lot width'] ?? null;
        $lotDepth = $customFieldsMap['lot depth'] ?? null;

        $hasDims = ($lotWidth && $lotDepth && (float)$lotWidth > 0 && (float)$lotDepth > 0);
        $hasArea = ($lotArea && (float)$lotArea > 0);

        if ($hasDims && $hasArea) {
            $unitStr = (strtolower($lotUnits) === 'acres' || (float)$lotArea < 10) ? 'ac' : 'sqft';
            $lotSizeText = sprintf('Lot: %s x %s ft (%s %s)', 
                rtrim(rtrim(number_format((float)$lotWidth, 2), '0'), '.'), 
                rtrim(rtrim(number_format((float)$lotDepth, 2), '0'), '.'), 
                rtrim(rtrim(number_format((float)$lotArea, 2), '0'), '.'), 
                $unitStr
            );
        } elseif ($hasDims) {
            $lotSizeText = sprintf('Lot: %s x %s ft', 
                rtrim(rtrim(number_format((float)$lotWidth, 2), '0'), '.'), 
                rtrim(rtrim(number_format((float)$lotDepth, 2), '0'), '.')
            );
        } elseif ($hasArea) {
            $unitStr = (strtolower($lotUnits) === 'acres' || (float)$lotArea < 10) ? 'ac' : 'sqft';
            $lotSizeText = sprintf('Lot: %s %s', rtrim(rtrim(number_format((float)$lotArea, 2), '0'), '.'), $unitStr);
        }
    }

    // Property Sub Type / Type label (e.g. "Detached", "Condo Apartment")
    $subTypeDisplay = $customFieldsMap['property sub type'] ?? ($customFieldsMap['property type'] ?? null);

    // Community / Sub-area (e.g. "Rural Clarington")
    $communityName = $customFieldsMap['neighbourhood'] ?? ($customFieldsMap['area minor'] ?? null);

    // Next upcoming open house label (e.g. "Open: Sat Aug 29, 1-4")
    $openHouseLabel = null;
    $upcomingOH = $property->upcoming_open_houses;
    if ($upcomingOH && $upcomingOH->isNotEmpty()) {
        $firstOH = $upcomingOH->first();
        if ($firstOH && $firstOH->open_house_date) {
            $dateFormatted = \Carbon\Carbon::parse($firstOH->open_house_date)->isoFormat('ddd MMM D');
            $timeFormatted = $firstOH->formatted_time ? preg_replace('/\s*(am|pm)/i', '', $firstOH->formatted_time) : '';
            $openHouseLabel = 'Open: ' . $dateFormatted . ($timeFormatted ? ', ' . $timeFormatted : '');
        }
    }

    // Time duration on market without "Updated" word (e.g. "125 days")
    $timeDuration = null;
    if ($property->updated_at) {
        $timeDuration = $property->updated_at->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE, parts: 1);
    }
@endphp

<div @class(['property-item homeya-box modern-card', $class]) @if ($property->latitude && $property->longitude) data-lat="{{ $property->latitude }}" data-lng="{{ $property->longitude }}" @endif>
    <div class="archive-top">
        <a href="{{ $property->url }}" class="images-group">
            <div class="images-style">
                @include(Theme::getThemeNamespace('partials.real-estate.card-image-slider'), [
                    'item' => $property,
                    'alt' => $property->name,
                    'size' => 'medium-rectangle',
                ])
            </div>
            
            <div class="modern-overlays">
                <span class="overlay-tag tag-status">
                    <i class="icon icon-home"></i> 
                    @if($property->type)
                        {{ __('For :type', ['type' => $property->type->label()]) }}
                    @else
                        {{ __('For Sale') }}
                    @endif
                </span>
                
                <div class="modern-overlays-right">
                    @if($openHouseLabel)
                        <span class="overlay-tag tag-open-house">
                            {{ $openHouseLabel }}
                        </span>
                    @endif
                    @if($timeDuration)
                        <span class="overlay-tag tag-time">
                            {{ $timeDuration }}
                        </span>
                    @endif
                </div>
            </div>
        </a>
        
        <div class="content modern-content">
            <div class="price-row mb-2">
                @if (!setting('real_estate_hide_price', false))
                    <div class="modern-price">{{ $property->price_format }}</div>
                @endif
                
                @if (RealEstateHelper::isEnabledWishlist())
                    <button type="button" class="modern-wishlist-btn"
                            data-type="property"
                            data-bb-toggle="add-to-wishlist"
                            data-id="{{ $property->getKey() }}"
                            data-add-message="{{ __('Added ":name" to wishlist successfully!', ['name' => $property->name]) }}"
                            data-remove-message="{{ __('Removed ":name" from wishlist successfully!', ['name' => $property->name]) }}"
                            aria-label="{{ __('Add to wishlist') }}"
                    >
                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                            <path d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572"></path>
                        </svg>
                    </button>
                @endif
            </div>

            {{-- 2-Row Specs Layout --}}
            <div class="modern-specs-container mb-2">
                {{-- Row 1: Beds • Baths • Sqft • Parking --}}
                @php
                    $row1Specs = array_filter([$bedText, $bathText, $sqftText, $parkingText]);
                @endphp
                @if(!empty($row1Specs))
                    <div class="modern-specs-row modern-specs-row-1">
                        @foreach($row1Specs as $spec)
                            <span class="spec-item">{{ $spec }}</span>
                            @if(!$loop->last)
                                <span class="spec-dot">•</span>
                            @endif
                        @endforeach
                    </div>
                @endif

                {{-- Row 2: Lot Size (or Condo Maint Fee) • Age --}}
                @php
                    $row2Specs = array_filter([$lotSizeText ?: $maintFeeText, $ageText]);
                @endphp
                @if(!empty($row2Specs))
                    <div class="modern-specs-row modern-specs-row-2">
                        @foreach($row2Specs as $spec)
                            <span class="spec-item">{{ $spec }}</span>
                            @if(!$loop->last)
                                <span class="spec-dot">•</span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="modern-address mb-2">
                <a href="{{ $property->url }}" class="line-clamp-1" title="{{ $property->name }}">
                    {{ $property->location ?: $property->name }}@if($communityName && !str_contains($property->location ?: $property->name, $communityName)) • {{ $communityName }}@endif
                </a>
            </div>

            <div class="modern-meta mt-auto">
                @if($property->unique_id)
                    <span>MLS® {{ $property->unique_id }}</span>
                @endif
                {{-- Attribution: the listing brokerage for IDX rows, the author
                     only for manually added ones. Showing the local admin account
                     against an MLS listing misrepresents who listed it. --}}
                @if($brokerage)
                    <span class="dot-separator">•</span>
                    <span title="{{ $brokerage }}">{{ Str::limit($brokerage, 30) }}</span>
                @elseif($author && $author->exists)
                    <span class="dot-separator">•</span>
                    <span>{{ $author->name }}</span>
                @endif
            </div>
        </div>
    </div>
</div>

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
    padding: 3px 10px;
    border-radius: 9999px;
    font-size: 11.5px;
    font-weight: 700;
    line-height: 1.4;
    white-space: nowrap;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
}
.modern-card .tag-status {
    background-color: #22c55e;
    color: #ffffff;
}
.modern-card .tag-open-house {
    background-color: rgba(0, 0, 0, 0.88);
    color: #ffffff;
    font-weight: 700;
}
.modern-card .tag-time {
    background-color: rgba(0, 0, 0, 0.88);
    color: #ffffff;
    font-weight: 700;
}
.modern-card .modern-specs-container {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.modern-card .modern-specs-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    font-size: 13px;
    line-height: 1.35;
}
.modern-card .modern-specs-row-1 {
    font-weight: 600;
    color: #1e293b;
}
.modern-card .modern-specs-row-2 {
    font-weight: 500;
    color: #64748b;
    font-size: 12px;
}
.modern-card .spec-dot {
    color: #cbd5e1;
    font-size: 10px;
}
.modern-card .modern-meta {
    padding-bottom: 2px;
}
</style>



