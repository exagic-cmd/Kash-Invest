@php
    $model = $model ?? $property ?? null;

    if (!$model || $model->source !== 'treeb') {
        return;
    }

    // Flat lookup: field name => value
    $cf  = $model->customFields->pluck('value', 'name')->all();
    $na  = __('Unknown');
    $val = fn(string $key) => (isset($cf[$key]) && $cf[$key] !== '') ? $cf[$key] : $na;

    // Room entries: custom fields named "Room 01", "Room 02", …
    $roomFields = $model->customFields
        ->filter(fn($f) => preg_match('/^Room \d+$/', $f->name))
        ->sortBy('name')
        ->values();

    // Sections definition: [title, [[store_key, display_label], ...]]
    $sections = [
        [
            'title' => __('Property'),
            'rows'  => [
                ['Property Type',       __('Property Type')],
                ['Property Sub Type',   __('Property Sub Type')],
                ['Transaction Type',    __('Transaction Type')],
                ['Standard Status',     __('Status')],
                ['Style',               __('Style')],
                ['Year Built',          __('Year Built')],
                ['Approximate Age',     __('Approximate Age')],
                ['Assessment Year',     __('Assessment Year')],
                ['Survey Type',         __('Survey Type')],
                ['Common Interest',     __('Common Interest')],
                ['Area Major',          __('Area Major')],
                ['Area Minor',          __('Area Minor')],
                ['Occupant Type',       __('Occupant Type')],
                ['Possession Type',     __('Possession Type')],
                ['Special Designation', __('Special Designation')],
                ['Cross Street',        __('Cross Street')],
                ['Listed On',           __('Listed On')],
                ['Last Updated',        __('Last Modified')],
                ['MLS Number',          __('MLS Number')],
                ['Listing Brokerage',   __('Listing Brokerage')],
                ['Co-Listing Brokerage',__('Co-Listing Brokerage')],
            ],
        ],
        [
            'title' => __('Interior'),
            'rows'  => [
                ['Bedrooms',            __('Bedrooms')],
                ['Total Bathrooms',     __('Total Bathrooms')],
                ['Cooling',             __('Cooling')],
                ['Heating',             __('Heating')],
                ['Fireplaces Total',    __('Fireplaces Total')],
                ['Fireplace Features',  __('Fireplace Features')],
                ['Interior Features',   __('Interior Features')],
                ['Laundry Features',    __('Laundry Features')],
                ['Inclusions',          __('Inclusions')],
                ['Exclusions',          __('Exclusions')],
                ['Kitchen Appliances',  __('Kitchen Appliances')],
            ],
        ],
        [
            'title' => __('Building'),
            'rows'  => [
                ['Kitchens Total',         __('Kitchens Total')],
                ['Basement',               __('Basement')],
                ['Foundation',             __('Foundation')],
                ['Roof',                   __('Roof')],
                ['Construction Materials', __('Construction')],
                ['Security Features',      __('Security Features')],
                ['Pool',                   __('Pool')],
                ['Building Area Total',    __('Building Area Total')],
                ['Building Area Units',    __('Building Area Units')],
                ['Living Area Range',      __('Living Area Range')],
                ['HST Application',        __('HST Application')],
            ],
        ],
        [
            'title' => __('Parking'),
            'rows'  => [
                ['Garage Type',      __('Garage Type')],
                ['Parking Features', __('Parking Features')],
                ['Parking Spaces',   __('Parking Spaces (Driveway)')],
                ['Parking Total',    __('Parking Total')],
                ['Covered Spaces',   __('Covered Spaces')],
            ],
        ],
        [
            'title' => __('Financial'),
            'rows'  => [
                ['Tax Annual Amount',         __('Tax Annual Amount')],
                ['Tax Year',                  __('Tax Year')],
                ['Tax Legal Description',     __('Tax Legal Description')],
                ['Association Fee',           __('Association Fee')],
                ['Association Fee Frequency', __('Association Fee Frequency')],
            ],
        ],
        [
            'title' => __('Land'),
            'rows'  => [
                ['Lot Width',        __('Frontage (ft)')],
                ['Lot Depth',        __('Lot Depth (ft)')],
                ['Lot Size',         __('Lot Size')],
                ['Lot Size Source',  __('Lot Size Source')],
                ['Lot Features',     __('Lot Features')],
                ['Direction Faces',  __('Frontage Type')],
                ['Directions',       __('Directions')],
                ['Water Source',     __('Water Source')],
                ['Sewer',            __('Sewer')],
                ['Zoning',           __('Zoning')],
                ['Parcel Number',    __('Parcel Number')],
                ['Roll Number',      __('Roll Number')],
                ['Community Features', __('Community Features')],
            ],
        ],
    ];

    // First 2 sections always visible; the rest are behind "Load more"
    $visibleSections = array_slice($sections, 0, 2);
    $hiddenSections  = array_slice($sections, 2);

    $hasRooms = $roomFields->isNotEmpty();
@endphp

<div @class(['single-property-element', 'property-details-reso', $class ?? null])>
    <div class="h7 title fw-7 mb-4">{{ __('Facts and Features') }}</div>

    {{-- Always-visible sections --}}
    @foreach ($visibleSections as $section)
        <div class="reso-section mb-4">
            <div class="reso-section-title">{{ $section['title'] }}</div>
            <div class="reso-grid reso-grid-2">
                @foreach ($section['rows'] as [$key, $label])
                    @php $v = $val($key); @endphp
                    <div class="reso-row">
                        <span class="reso-label">{{ $label }}</span>
                        <span @class(['reso-value', 'reso-unknown' => $v === $na])>{{ $v }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- Rooms section (always visible) --}}
    @if ($hasRooms)
        <div class="reso-section mb-4">
            <div class="reso-section-title">{{ __('Rooms') }}</div>
            <div class="reso-rooms-header">
                <span>{{ __('Room') }}</span>
                <span>{{ __('Level') }}</span>
                <span>{{ __('Dimensions (m)') }}</span>
            </div>
            @foreach ($roomFields as $roomField)
                @php
                    $parts     = array_map('trim', explode(' / ', $roomField->value, 3));
                    $roomName  = $parts[0] ?? $roomField->value;
                    $roomLevel = $parts[1] ?? '';
                    $roomDims  = $parts[2] ?? '';
                @endphp
                <div class="reso-room-row">
                    <span class="reso-room-name">{{ $roomName }}</span>
                    <span class="reso-room-level">{{ $roomLevel }}</span>
                    <span class="reso-room-dims">{{ $roomDims }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Collapsible sections --}}
    @if (!empty($hiddenSections))
        <div class="reso-extra-sections" id="reso-extra" style="display:none;">
            @foreach ($hiddenSections as $section)
                <div class="reso-section mb-4">
                    <div class="reso-section-title">{{ $section['title'] }}</div>
                    <div class="reso-grid reso-grid-2">
                        @foreach ($section['rows'] as [$key, $label])
                            @php $v = $val($key); @endphp
                            <div class="reso-row">
                                <span class="reso-label">{{ $label }}</span>
                                <span @class(['reso-value', 'reso-unknown' => $v === $na])>{{ $v }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Load more button --}}
        <div class="reso-toggle-wrap" id="reso-toggle-wrap">
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
</div>

<style>
.property-details-reso { font-size: 0.9rem; }

.reso-section-title {
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #6c757d;
    margin-bottom: 0.4rem;
    padding-bottom: 0.35rem;
    border-bottom: 1px solid #e9ecef;
}
.reso-grid { display: grid; gap: 0; }
.reso-grid-2 { grid-template-columns: 1fr 1fr; }
@media (max-width: 640px) { .reso-grid-2 { grid-template-columns: 1fr; } }

.reso-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 0.75rem;
    padding: 0.4rem 0.5rem;
    border-bottom: 1px solid #f1f3f5;
}
.reso-row:last-child { border-bottom: none; }
.reso-label { color: #6c757d; white-space: nowrap; flex-shrink: 0; font-size: 0.82rem; }
.reso-value { font-weight: 600; color: #212529; text-align: right; word-break: break-word; font-size: 0.82rem; }
.reso-unknown { color: #adb5bd; font-weight: 400; font-style: italic; }

.reso-rooms-header {
    display: grid;
    grid-template-columns: 2fr 1fr 1.5fr;
    gap: 0;
    padding: 0.3rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6c757d;
    border-bottom: 1px solid #dee2e6;
}
.reso-room-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1.5fr;
    gap: 0;
    padding: 0.35rem 0.5rem;
    border-bottom: 1px solid #f1f3f5;
    font-size: 0.82rem;
}
.reso-room-row:last-child { border-bottom: none; }
.reso-room-name  { font-weight: 600; color: #212529; }
.reso-room-level { color: #495057; }
.reso-room-dims  { color: #495057; text-align: right; }
@media (max-width: 480px) {
    .reso-rooms-header,
    .reso-room-row { grid-template-columns: 2fr 1fr 1.2fr; font-size: 0.78rem; }
}

.reso-toggle-wrap { text-align: center; margin-top: 0.25rem; padding-top: 0.75rem; border-top: 1px solid #e9ecef; }
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
    var extra    = document.getElementById('reso-extra');
    var btnMore  = document.getElementById('reso-load-more');
    var btnLess  = document.getElementById('reso-see-less');
    var expanded = extra.style.display !== 'none';

    if (expanded) {
        extra.style.display   = 'none';
        btnMore.style.display = '';
        btnLess.style.display = 'none';
        // Scroll back to top of this section
        document.querySelector('.property-details-reso').scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        extra.style.display   = '';
        btnMore.style.display = 'none';
        btnLess.style.display = '';
    }
}
</script>
