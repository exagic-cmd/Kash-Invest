@php
    $model = $model ?? $property ?? null;

    if (!$model || $model->source !== 'treeb') {
        return;
    }

    // Flat lookup: field name => value (case-insensitive)
    $cf = [];
    foreach ($model->customFields as $customField) {
        if ($customField->name && $customField->value !== null && $customField->value !== '') {
            $cf[strtolower(trim($customField->name))] = $customField->value;
        }
    }

    $isUnknown = function ($value): bool {
        if ($value === null || $value === '') {
            return true;
        }
        $str = strtolower(trim(strip_tags((string) $value)));
        return in_array($str, ['unknown', 'n/a', 'na', 'null', 'none', '-'], true);
    };

    $val = function(string|array $keys) use ($cf) {
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        foreach ($keys as $k) {
            $lower = strtolower(trim($k));
            if (isset($cf[$lower]) && $cf[$lower] !== '') {
                return $cf[$lower];
            }
        }
        return null;
    };

    // Room entries: custom fields named "Room 01", "Room 02", …
    $roomFields = $model->customFields
        ->filter(fn($f) => preg_match('/^Room \d+$/', $f->name))
        ->sortBy('name')
        ->values();

    // Sections definition: [title, [[store_keys, display_label], ...]]
    $sections = [
        [
            'title' => __('Property'),
            'rows'  => [
                ['Standard Status',                         __('Status')],
                ['Type',                                    __('Type')],
                ['Property Type',                           __('Property Type')],
                ['Property Sub Type',                       __('Property Sub Type')],
                ['Transaction Type',                        __('Transaction Type')],
                ['Style',                                   __('Style')],
                ['Year Built',                              __('Year Built')],
                ['Approximate Age',                         __('Approximate Age')],
                ['Assessment Year',                         __('Assesment Year')],
                ['Survey Type',                             __('Survey Type')],
                ['Common Interest',                         __('Common Interest')],
                [['Area', 'Area Major'],                    __('Area')],
                [['Community', 'Area Minor'],               __('Community')],
                ['Occupant Type',                           __('Occupant Type')],
                ['Possession Type',                         __('Possession Type')],
                ['Special Designation',                     __('Special Designation')],
                ['Cross Street',                            __('Cross Street')],
                ['Municipality District',                   __('Municipality District')],
                ['Listed On',                               __('Listed On')],
                ['Last Updated',                            __('Last Modified')],
                ['MLS Number',                              __('MLS Number')],
                ['Listing Brokerage',                       __('Listing Brokerage')],
                ['Co-Listing Brokerage',                    __('Co-Listing Brokerage')],
            ],
        ],
        [
            'title' => __('Inside'),
            'rows'  => [
                [['Air Conditioning', 'Cooling'],           __('Air Conditioning')],
                ['Bedrooms',                                __('Bedrooms')],
                ['Total Bathrooms',                         __('Total Bathrooms')],
                ['Heating',                                 __('Heating')],
                ['Fireplaces Total',                        __('Fireplaces Total')],
                ['Fireplace Features',                      __('Fireplace Features')],
                ['Interior Features',                       __('Interior Features')],
                ['Laundry Features',                        __('Laundry Features')],
                ['Inclusions',                              __('Inclusions')],
                ['Exclusions',                              __('Exclusions')],
                ['Kitchen Appliances',                      __('Kitchen Appliances')],
            ],
        ],
        [
            'title' => __('Building'),
            'rows'  => [
                ['Kitchens Total',                          __('Kitchens Total')],
                ['Basement',                                __('Basement')],
                ['Foundation',                              __('Foundation')],
                ['Roof',                                    __('Roof')],
                ['Construction Materials',                  __('Construction')],
                ['Security Features',                       __('Security Features')],
                ['Pool',                                    __('Pool')],
                ['Building Area Total',                     __('Building Area Total')],
                ['Building Area Units',                     __('Building Area Units')],
                ['Living Area Range',                       __('Living Area Range')],
                ['HST Application',                         __('HST Application')],
            ],
        ],
        [
            'title' => __('Parking'),
            'rows'  => [
                ['Garage Type',                             __('Garage Type')],
                ['Parking Features',                        __('Parking Features')],
                ['Parking Spaces',                          __('Parking Spaces (Driveway)')],
                ['Parking Total',                           __('Parking Total')],
                ['Covered Spaces',                          __('Covered Spaces')],
            ],
        ],
        [
            'title' => __('Financial'),
            'rows'  => [
                ['Tax Annual Amount',                       __('Tax Annual Amount')],
                ['Tax Year',                                __('Tax Year')],
                ['Tax Legal Description',                   __('Tax Legal Description')],
                ['Association Fee',                         __('Association Fee')],
                ['Association Fee Frequency',               __('Association Fee Frequency')],
            ],
        ],
        [
            'title' => __('Land'),
            'rows'  => [
                [['Frontage', 'Lot Width'],                 __('Frontage')],
                ['Lot Depth',                               __('Lot Depth')],
                ['Lot Size Units',                          __('Lot Size Units')],
                ['Lot Size',                                __('Lot Size')],
                ['Lot Size Source',                         __('Lot Size Source')],
                ['Lot Features',                            __('Lot Features')],
                ['Direction Faces',                         __('Frontage Type')],
                ['Directions',                              __('Directions')],
                ['Water Source',                            __('Water Source')],
                ['Sewer',                                   __('Sewer')],
                ['Zoning',                                  __('Zoning')],
                ['Parcel Number',                           __('Parcel Number')],
                ['Roll Number',                             __('Roll Number')],
                ['Community Features',                      __('Community Features')],
                ['Cross Street',                            __('Cross Street')],
                ['Municipality District',                   __('Municipality District')],
            ],
        ],
    ];

    // Filter out unknown/empty values for each section
    $activeSections = [];
    foreach ($sections as $section) {
        $rows = [];
        foreach ($section['rows'] as [$keys, $label]) {
            $v = $val($keys);
            if (!$isUnknown($v)) {
                $rows[] = ['label' => $label, 'value' => $v];
            }
        }
        if (!empty($rows)) {
            $activeSections[] = [
                'title' => $section['title'],
                'rows'  => $rows,
            ];
        }
    }

    // By default show 1st fact section (Property), others on "Load More"
    $firstSection = $activeSections[0] ?? null;
    $extraCol1    = array_slice($activeSections, 1, 2); // e.g. Inside, Building (left col)
    $extraCol2    = array_slice($activeSections, 3);    // e.g. Parking, Financial, Land (right col)
    $hasExtra     = count($activeSections) > 1;

    $hasRooms = $roomFields->isNotEmpty();
@endphp

@if (!empty($activeSections) || $hasRooms)
<div @class(['single-property-element', 'property-details-reso', $class ?? null])>
    <div class="h7 title fw-7 mb-4">{{ __('Facts and Features') }}</div>

    {{-- 2 Columns: 1st fact visible by default, other facts loaded on demand --}}
    <div class="row g-4 reso-two-columns">
        {{-- Left Column: 1st fact always visible, remaining left facts collapsible --}}
        <div class="col-12 col-md-6 d-flex flex-column gap-4 reso-col-left">
            @if ($firstSection)
                <div class="reso-section-block">
                    <h5 class="fw-bold text-dark mb-3" style="font-size: 1.15rem;">{{ $firstSection['title'] }}</h5>
                    <div class="d-flex flex-column gap-2" style="font-size: 0.95rem;">
                        @foreach ($firstSection['rows'] as $row)
                            <div class="reso-item align-items-baseline gap-2">
                                <span class="fw-bold text-dark flex-shrink-0">{{ $row['label'] }}:</span>
                                <span class="fw-normal text-dark text-break">{{ $row['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (!empty($extraCol1))
                <div id="reso-extra-left" class="d-flex flex-column gap-4" style="display: none !important;">
                    @foreach ($extraCol1 as $section)
                        <div class="reso-section-block">
                            <h5 class="fw-bold text-dark mb-3" style="font-size: 1.15rem;">{{ $section['title'] }}</h5>
                            <div class="d-flex flex-column gap-2" style="font-size: 0.95rem;">
                                @foreach ($section['rows'] as $row)
                                    <div class="reso-item align-items-baseline gap-2">
                                        <span class="fw-bold text-dark flex-shrink-0">{{ $row['label'] }}:</span>
                                        <span class="fw-normal text-dark text-break">{{ $row['value'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Right Column: Collapsible extra right facts --}}
        <div class="col-12 col-md-6 d-flex flex-column gap-4 reso-col-right" id="reso-col-right" style="display: none !important;">
            @foreach ($extraCol2 as $section)
                <div class="reso-section-block">
                    <h5 class="fw-bold text-dark mb-3" style="font-size: 1.15rem;">{{ $section['title'] }}</h5>
                    <div class="d-flex flex-column gap-2" style="font-size: 0.95rem;">
                        @foreach ($section['rows'] as $row)
                            <div class="reso-item align-items-baseline gap-2">
                                <span class="fw-bold text-dark flex-shrink-0">{{ $row['label'] }}:</span>
                                <span class="fw-normal text-dark text-break">{{ $row['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Load more button --}}
    @if ($hasExtra)
        <div class="reso-toggle-wrap mt-3 pt-3" id="reso-toggle-wrap">
            <button type="button" class="reso-toggle-btn" id="reso-load-more" onclick="resoToggle()">
                <x-core::icon name="ti ti-chevron-down" class="reso-toggle-icon" />
                {{ __('Load more facts and features') }}
            </button>
            <button type="button" class="reso-toggle-btn" id="reso-see-less" style="display:none;" onclick="resoToggle()">
                <x-core::icon name="ti ti-chevron-up" class="reso-toggle-icon" />
                {{ __('See less facts and features') }}
            </button>
        </div>
    @endif

    {{-- Rooms section (if present) --}}
    @if ($hasRooms)
        <div class="reso-rooms-block mt-4">
            <h5 class="fw-bold text-dark mb-3" style="font-size: 1.15rem;">{{ __('Rooms') }}</h5>
            <div class="reso-rooms-header d-flex justify-content-between border-bottom pb-2 mb-2 fw-bold text-dark" style="font-size: 0.9rem;">
                <span style="flex: 2;">{{ __('Room') }}</span>
                <span style="flex: 1;">{{ __('Level') }}</span>
                <span style="flex: 1.5; text-align: right;">{{ __('Dimensions') }}</span>
            </div>
            @foreach ($roomFields as $roomField)
                @php
                    $parts     = array_map('trim', explode(' / ', $roomField->value, 3));
                    $roomName  = $parts[0] ?? $roomField->value;
                    $roomLevel = $parts[1] ?? '';
                    $roomDims  = $parts[2] ?? '';
                @endphp
                <div class="reso-room-row d-flex justify-content-between py-1" style="font-size: 0.9rem;">
                    <span class="fw-bold text-dark" style="flex: 2;">{{ $roomName }}</span>
                    <span class="fw-normal text-dark" style="flex: 1;">{{ $roomLevel }}</span>
                    <span class="fw-normal text-dark" style="flex: 1.5; text-align: right;">{{ $roomDims }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endif

<style>
.property-details-reso { font-size: 0.95rem; }
.reso-item { line-height: 1.5; }
.reso-section-block { width: 100%; }
.reso-toggle-wrap { text-align: center; margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #e9ecef; }
.reso-toggle-btn {
    background: none;
    border: none;
    color: #2563eb;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    transition: background 0.15s;
}
.reso-toggle-btn:hover { background: #eff6ff; }
.reso-toggle-icon { width: 16px; height: 16px; }
</style>

<script>
function resoToggle() {
    var extraLeft = document.getElementById('reso-extra-left');
    var colRight  = document.getElementById('reso-col-right');
    var btnMore   = document.getElementById('reso-load-more');
    var btnLess   = document.getElementById('reso-see-less');

    var isHidden = (colRight && (colRight.style.display === 'none' || colRight.style.getPropertyValue('display') === 'none')) ||
                   (extraLeft && (extraLeft.style.display === 'none' || extraLeft.style.getPropertyValue('display') === 'none'));

    if (isHidden) {
        if (extraLeft) extraLeft.style.setProperty('display', 'flex', 'important');
        if (colRight)  colRight.style.setProperty('display', 'flex', 'important');
        if (btnMore)   btnMore.style.display = 'none';
        if (btnLess)   btnLess.style.display = 'inline-flex';
    } else {
        if (extraLeft) extraLeft.style.setProperty('display', 'none', 'important');
        if (colRight)  colRight.style.setProperty('display', 'none', 'important');
        if (btnMore)   btnMore.style.display = 'inline-flex';
        if (btnLess)   btnLess.style.display = 'none';
        document.querySelector('.property-details-reso').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
</script>
