@php
    $model = $model ?? $project ?? null;
    if (! ($model instanceof \Botble\RealEstate\Models\Project)) {
        return;
    }

    $cfCollection = $model->customFields ?? collect();
    $customFields = $cfCollection->keyBy(fn($item) => strtolower(trim($item->name)));
    $usedCustomFieldIds = [];

    $getCustom = function(...$keys) use ($customFields, &$usedCustomFieldIds) {
        foreach ($keys as $key) {
            $lower = strtolower(trim($key));
            if ($customFields->has($lower)) {
                $field = $customFields->get($lower);
                if (filled($field->value)) {
                    $usedCustomFieldIds[$field->id ?? $lower] = true;
                    return $field->value;
                }
            }
        }
        return null;
    };

    // 1. Total Units specs
    $unitItems = [];
    if ($val = $getCustom('1 bedrooms', '1 bedroom', '1 bed', '1 bd', '1 bed units')) {
        $unitItems[] = ['icon' => 'ti ti-bed', 'label' => __('1 Bedrooms'), 'value' => $val];
    }
    if ($val = $getCustom('2 bedrooms', '2 bedroom', '2 bed', '2 bd', '2 bed units')) {
        $unitItems[] = ['icon' => 'ti ti-bed', 'label' => __('2 Bedrooms'), 'value' => $val];
    }
    if ($val = $getCustom('3+ bedrooms', '3 bedrooms', '3 bedroom', '3+ bed', '3 bed', '3 bd', '3+ bd', '3 bed units')) {
        $unitItems[] = ['icon' => 'ti ti-bed', 'label' => __('3+ Bedrooms'), 'value' => $val];
    }
    if ($val = $getCustom('studio', 'studios', 'studio units')) {
        $unitItems[] = ['icon' => 'ti ti-layout-grid', 'label' => __('Studio Units'), 'value' => $val];
    }
    if (empty($unitItems) && ($val = $getCustom('bedrooms', 'beds'))) {
        $unitItems[] = ['icon' => 'ti ti-bed', 'label' => __('Bedrooms'), 'value' => $val];
    }
    if ($val = $getCustom('bathrooms', 'baths', 'bathroom')) {
        $unitItems[] = ['icon' => 'ti ti-bath', 'label' => __('Bathrooms'), 'value' => $val];
    }

    $totalUnitsVal = $getCustom('total number of units', 'total units', 'number of units') ?: ($model->number_flat ? number_format($model->number_flat) : null);
    if ($totalUnitsVal) {
        $unitItems[] = ['icon' => 'ti ti-building', 'label' => __('Total Number of Units'), 'value' => $totalUnitsVal];
    }
    if ($val = $getCustom('condo units')) {
        $unitItems[] = ['icon' => 'ti ti-building-community', 'label' => __('Condo Units'), 'value' => $val];
    }
    if ($model->suites_starting_floor) {
        $unitItems[] = ['icon' => 'ti ti-stairs', 'label' => __('Suites Starting Floor'), 'value' => $model->suites_starting_floor];
    }
    if ($model->number_of_suites_per_floor) {
        $unitItems[] = ['icon' => 'ti ti-layers-intersect', 'label' => __('Suites per Floor'), 'value' => $model->number_of_suites_per_floor];
    }
    if ($model->suite_size_from || $model->suite_size_to) {
        $sizeStr = '';
        if ($model->suite_size_from && $model->suite_size_to) {
            $sizeStr = $model->suite_size_from . ' - ' . $model->suite_size_to . ' ' . setting('real_estate_square_unit', 'sqft');
        } elseif ($model->suite_size_from) {
            $sizeStr = __('From :size', ['size' => $model->suite_size_from]) . ' ' . setting('real_estate_square_unit', 'sqft');
        } else {
            $sizeStr = __('Up to :size', ['size' => $model->suite_size_to]) . ' ' . setting('real_estate_square_unit', 'sqft');
        }
        $unitItems[] = ['icon' => 'ti ti-ruler-2', 'label' => __('Suite Size Range'), 'value' => $sizeStr];
    }
    if ($model->price_per_sqft_from) {
        $unitItems[] = ['icon' => 'ti ti-tag', 'label' => __('Price per Sqft'), 'value' => __('From :price', ['price' => format_price($model->price_per_sqft_from)])];
    }
    if ($val = $getCustom('number of floor plans', 'floor plans count', 'floor plans')) {
        $unitItems[] = ['icon' => 'ti ti-layout-2', 'label' => __('Number of Floor Plans'), 'value' => $val];
    }

    // 2. Building Attributes specs
    $attributeItems = [];
    if ($model->categories->isNotEmpty()) {
        $cats = $model->categories->map(fn($c) => '<a href="' . $c->url . '" class="text-dark">' . e($c->name) . '</a>')->join(', ');
        $attributeItems[] = ['icon' => 'ti ti-home-2', 'label' => __('Property Type'), 'value' => $cats];
    }
    if ($model->number_floor) {
        $attributeItems[] = ['icon' => 'ti ti-building-skyscraper', 'label' => __('Total Floors'), 'value' => number_format($model->number_floor)];
    }
    if ($model->investor && $model->investor->name) {
        $attributeItems[] = ['icon' => 'ti ti-user-check', 'label' => __('Developer'), 'value' => e($model->investor->name)];
    }
    if ($model->architects) {
        $attributeItems[] = ['icon' => 'ti ti-pencil', 'label' => __('Architects'), 'value' => e($model->architects)];
    }
    if ($model->neighbour) {
        $attributeItems[] = ['icon' => 'ti ti-map-pin', 'label' => __('Neighbourhood'), 'value' => e($model->neighbour)];
    }
    if ($model->intersection) {
        $attributeItems[] = ['icon' => 'ti ti-current-location', 'label' => __('Intersection'), 'value' => e($model->intersection)];
    }
    if ($model->date_finish) {
        $attributeItems[] = ['icon' => 'ti ti-calendar-event', 'label' => __('Estimated Completion'), 'value' => $model->date_finish->format('M Y')];
    } elseif ($val = $getCustom('estimated completion', 'completion date', 'completion')) {
        $attributeItems[] = ['icon' => 'ti ti-calendar-event', 'label' => __('Estimated Completion'), 'value' => e($val)];
    }
    if ($val = $getCustom('sales status', 'selling status')) {
        $attributeItems[] = ['icon' => 'ti ti-badge', 'label' => __('Sales Status'), 'value' => e($val)];
    }
    if ($val = $getCustom('construction status')) {
        $attributeItems[] = ['icon' => 'ti ti-crane', 'label' => __('Construction Status'), 'value' => e($val)];
    }
    if ($val = $getCustom('ceiling heights', 'ceiling height')) {
        $attributeItems[] = ['icon' => 'ti ti-arrow-autofit-height', 'label' => __('Ceiling Heights'), 'value' => e($val)];
    }

    // Remaining custom fields (Excluding Google Map, Google Drive, and Amenities which have dedicated presentation)
    $ignoredFieldNames = [
        'website', 'official website', 'url', 'link',
        'google map', 'google map link', 'map link', 'google maps', 'map', 'location link',
        'google drive portal', 'drive portal', 'google drive', 'drive link', 'drive folder', 'google drive link',
        'google drive archive portal', 'drive archive portal', 'archive portal', 'google drive archive',
        'amenities', 'building amenities', 'project amenities', 'amenity', 'features',
    ];
    foreach ($cfCollection as $cf) {
        $cfNameLower = strtolower(trim($cf->name));
        $cfValLower = strtolower(trim($cf->value ?? ''));
        if (
            in_array($cfNameLower, $ignoredFieldNames)
            || !empty($usedCustomFieldIds[$cf->id ?? $cfNameLower])
            || blank($cf->value)
            || str_contains($cfValLower, 'drive.google.com')
            || str_contains($cfValLower, 'maps.google.com')
            || str_contains($cfNameLower, 'map')
            || str_contains($cfNameLower, 'drive')
            || str_contains($cfNameLower, 'amenit')
        ) {
            continue;
        }
        $valFormatted = e($cf->value);
        if (\Illuminate\Support\Str::startsWith($cf->value, ['http://', 'https://'])) {
            $valFormatted = '<a href="' . e($cf->value) . '" target="_blank" rel="noopener noreferrer" class="text-primary text-break">' . e($cf->value) . '</a>';
        }
        $attributeItems[] = ['icon' => 'ti ti-info-circle', 'label' => __($cf->name), 'value' => $valFormatted];
    }

    // 3. Amenities Extraction & Parsing (Displaying in a structured, modern layout)
    $rawAmenities = $getCustom('amenities', 'building amenities', 'project amenities', 'amenity');
    $parsedAmenities = collect();

    if ($rawAmenities) {
        $rawItems = preg_split('/[\r\n]+|•|(?:\s+-\s*)|(?:^-\s*)/', $rawAmenities);
        foreach ($rawItems as $item) {
            $item = trim(ltrim(trim($item), '-•* '));
            if (!empty($item) && strlen($item) > 2) {
                $parsedAmenities->push($item);
            }
        }
    }

    if ($model->features && $model->features->isNotEmpty()) {
        foreach ($model->features as $f) {
            if (!$parsedAmenities->contains($f->name)) {
                $parsedAmenities->push($f->name);
            }
        }
    }

    $getAmenityIcon = function($amenity) {
        $lower = strtolower($amenity);
        if (str_contains($lower, 'concierge') || str_contains($lower, 'lobby')) return 'ti ti-bell-ringing';
        if (str_contains($lower, 'parcel')) return 'ti ti-package';
        if (str_contains($lower, 'mail')) return 'ti ti-mail';
        if (str_contains($lower, 'exercise') || str_contains($lower, 'gym') || str_contains($lower, 'fitness')) return 'ti ti-barbell';
        if (str_contains($lower, 'massage') || str_contains($lower, 'spa') || str_contains($lower, 'sauna')) return 'ti ti-bath';
        if (str_contains($lower, 'game') || str_contains($lower, 'ping pong') || str_contains($lower, 'foosball') || str_contains($lower, 'billiard')) return 'ti ti-device-gamepad-2';
        if (str_contains($lower, 'theatre') || str_contains($lower, 'theater') || str_contains($lower, 'cinema') || str_contains($lower, 'movie')) return 'ti ti-sparkles';
        if (str_contains($lower, 'dining') || str_contains($lower, 'kitchen') || str_contains($lower, 'bbq') || str_contains($lower, 'barbeque')) return 'ti ti-tools-kitchen-2';
        if (str_contains($lower, 'lounge') || str_contains($lower, 'social') || str_contains($lower, 'party')) return 'ti ti-armchair';
        if (str_contains($lower, 'terrace') || str_contains($lower, 'outdoor') || str_contains($lower, 'patio') || str_contains($lower, 'deck') || str_contains($lower, 'rooftop') || str_contains($lower, 'garden')) return 'ti ti-sun';
        return 'ti ti-circle-check';
    };

    // 4. Parking & Maintenance specs
    $parkingItems = [];
    if ($model->parking_price) {
        $parkingItems[] = ['icon' => 'ti ti-car', 'label' => __('Parking Price'), 'value' => is_numeric($model->parking_price) ? format_price($model->parking_price) : $model->parking_price];
    }
    if ($model->parking_maint) {
        $parkingItems[] = ['icon' => 'ti ti-tool', 'label' => __('Parking Maintenance'), 'value' => is_numeric($model->parking_maint) ? format_price($model->parking_maint) . '/mo' : $model->parking_maint];
    }
    if ($model->locker_price) {
        $parkingItems[] = ['icon' => 'ti ti-lock', 'label' => __('Locker Price'), 'value' => is_numeric($model->locker_price) ? format_price($model->locker_price) : $model->locker_price];
    }
    if ($model->locker_maint) {
        $parkingItems[] = ['icon' => 'ti ti-key', 'label' => __('Locker Maintenance'), 'value' => is_numeric($model->locker_maint) ? format_price($model->locker_maint) . '/mo' : $model->locker_maint];
    }
    if ($model->est_maint) {
        $parkingItems[] = ['icon' => 'ti ti-file-invoice', 'label' => __('Est. Maintenance Fee'), 'value' => is_numeric($model->est_maint) ? format_price($model->est_maint) . '/sqft' : $model->est_maint];
    }
    if ($model->est_property_tax) {
        $parkingItems[] = ['icon' => 'ti ti-percentage', 'label' => __('Est. Property Tax'), 'value' => is_numeric($model->est_property_tax) ? $model->est_property_tax . '%' : $model->est_property_tax];
    }

    // 5. Services & Policies specs
    $serviceItems = [];
    if ($model->total_min_deposit) {
        $depVal = is_numeric($model->total_min_deposit) ? ($model->total_min_deposit <= 1 ? ($model->total_min_deposit * 100) . '%' : $model->total_min_deposit . '%') : $model->total_min_deposit;
        $serviceItems[] = ['icon' => 'ti ti-wallet', 'label' => __('Total Min. Deposit'), 'value' => $depVal];
    }
    if ($model->development_levies) {
        $serviceItems[] = ['icon' => 'ti ti-coins', 'label' => __('Development Levies'), 'value' => e($model->development_levies)];
    }
    if ($model->assignment_policy) {
        $serviceItems[] = ['icon' => 'ti ti-file-certificate', 'label' => __('Assignment Policy'), 'value' => e($model->assignment_policy)];
    }

    // 6. Floor Plans
    $floorPlans = $model->formatted_floor_plans ?? collect();

    // 7. Documents & PDFs (Exclude Google Drive folders and Google Drive portals)
    $allDocs = $model->documents()->get();
    $activeDocs = $allDocs->where('is_historical', false)
        ->reject(function ($doc) {
            $lowerName = strtolower($doc->name);
            $lowerType = strtolower($doc->document_type ?? '');
            $lowerUrl = strtolower($doc->url ?? '');
            return str_contains($lowerName, 'portal')
                || str_contains($lowerName, 'drive folder')
                || str_contains($lowerName, 'drive archive')
                || str_contains($lowerType, 'folder')
                || str_contains($lowerUrl, 'drive.google.com/drive/folders')
                || str_contains($lowerUrl, 'drive.google.com/drive/u');
        })
        ->values();

    // Categorize document types helper
    $docCategory = function($name) {
        $lower = strtolower($name);
        if (str_contains($lower, 'brochure')) return 'Brochure';
        if (str_contains($lower, 'price list')) return 'Price List';
        if (str_contains($lower, 'incentive')) return 'Incentives';
        if (str_contains($lower, 'fact')) return 'Fast Facts';
        if (str_contains($lower, 'features') || str_contains($lower, 'finishes')) return 'Features & Finishes';
        if (str_contains($lower, 'floor plan')) return 'Floor Plans';
        if (str_contains($lower, 'presentation')) return 'Presentation';
        if (str_contains($lower, 'rendering')) return 'Renderings';
        return 'Document';
    };

    $docIcon = function($cat) {
        return match($cat) {
            'Brochure' => 'ti ti-book-2',
            'Price List' => 'ti ti-file-dollar',
            'Incentives' => 'ti ti-gift',
            'Fast Facts' => 'ti ti-bulb',
            'Features & Finishes' => 'ti ti-sparkles',
            'Floor Plans' => 'ti ti-layout-2',
            'Presentation' => 'ti ti-presentation',
            'Renderings' => 'ti ti-photo',
            default => 'ti ti-file-text',
        };
    };

    $docColor = function($cat) {
        return match($cat) {
            'Brochure' => 'doc-badge-blue',
            'Price List' => 'doc-badge-green',
            'Incentives' => 'doc-badge-coral',
            'Fast Facts' => 'doc-badge-purple',
            'Features & Finishes' => 'doc-badge-amber',
            'Floor Plans' => 'doc-badge-teal',
            default => 'doc-badge-slate',
        };
    };
@endphp

<style>
/* ==========================================================================
   Project Detail Tabs Navigation & Components
   ========================================================================== */
.project-detail-tabs-section {
    position: relative;
    margin-bottom: 2.5rem;
}

/* Nav Tabs Wrapper & Bar */
.project-nav-tabs-wrapper {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 4px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.project-nav-tabs-wrapper::-webkit-scrollbar {
    display: none;
}

.project-nav-pills {
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: stretch !important;
    gap: 4px !important;
    padding: 0 !important;
    margin: 0 !important;
    list-style: none !important;
    border: none !important;
    width: 100% !important;
}

.project-nav-pills .nav-item {
    margin: 0 !important;
    padding: 0 !important;
    flex: 1 1 auto !important;
    min-width: 0 !important;
    display: flex !important;
}

.project-nav-pills .nav-link {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 5px !important;
    width: 100% !important;
    padding: 8px 10px !important;
    border-radius: 8px !important;
    font-size: 12.5px !important;
    font-weight: 600 !important;
    letter-spacing: -0.15px !important;
    color: #475569 !important;
    background: transparent !important;
    border: 1px solid transparent !important;
    outline: none !important;
    box-shadow: none !important;
    white-space: nowrap !important;
    cursor: pointer !important;
    transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1) !important;
    line-height: 1.4 !important;
}

.project-nav-pills .nav-link svg,
.project-nav-pills .nav-link i,
.project-nav-pills .nav-link .icon {
    font-size: 14px !important;
    width: 14px !important;
    height: 14px !important;
    stroke-width: 1.8 !important;
    color: inherit !important;
    display: inline-block !important;
    vertical-align: middle !important;
    flex-shrink: 0 !important;
}

.project-nav-pills .nav-link:hover {
    color: #0f172a !important;
    background: #ffffff !important;
    border-color: #e2e8f0 !important;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04) !important;
}

.project-nav-pills .nav-link.active {
    color: #ffffff !important;
    background: #0f172a !important;
    border-color: #0f172a !important;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.18) !important;
}

.project-nav-pills .tab-count-badge {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 1px 5px !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    border-radius: 9999px !important;
    background: #e2e8f0 !important;
    color: #334155 !important;
    margin-left: 2px !important;
    line-height: 1.2 !important;
    transition: all 0.18s !important;
}

.project-nav-pills .nav-link.active .tab-count-badge {
    background: rgba(255, 255, 255, 0.25) !important;
    color: #ffffff !important;
}

@media (max-width: 991.98px) {
    .project-nav-pills {
        width: max-content !important;
        min-width: 100% !important;
        gap: 6px !important;
    }
    .project-nav-pills .nav-item {
        flex: 0 0 auto !important;
    }
    .project-nav-pills .nav-link {
        padding: 8px 14px !important;
    }
}

/* Highlights Grid */
.project-highlights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 12px;
}
.highlight-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
    transition: transform 0.2s, box-shadow 0.2s;
}
.highlight-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(0, 0, 0, 0.05);
}
.highlight-card .highlight-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.highlight-card .highlight-label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #64748b;
    line-height: 1.2;
    margin-bottom: 2px;
}
.highlight-card .highlight-value {
    display: block;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.3;
}

/* Spec Tile Cards */
.spec-tile-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    height: 100%;
    transition: all 0.2s ease;
}
.spec-tile-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}
.spec-tile-card.highlight-border {
    border-left: 4px solid var(--theme-primary-color, #1565c0);
}
.spec-tile-card .spec-tile-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.spec-tile-card .spec-tile-label {
    font-size: 12px;
    color: #64748b;
    line-height: 1.2;
    margin-bottom: 2px;
}
.spec-tile-card .spec-tile-value {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.3;
}

/* Floor Plans Item Card */
.floor-plan-item-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
    transition: transform 0.2s, box-shadow 0.2s;
}
.floor-plan-item-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.07);
    border-color: #cbd5e1;
}
.floor-plan-thumb-wrap {
    height: 200px;
    background: #f8fafc;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid #f1f5f9;
}
.floor-plan-thumb-wrap img {
    max-height: 100%;
    width: auto;
    object-fit: contain;
    padding: 12px;
    transition: transform 0.3s;
}
.floor-plan-thumb-wrap:hover img {
    transform: scale(1.05);
}
.floor-plan-thumb-wrap .zoom-overlay-icon {
    position: absolute;
    bottom: 10px;
    right: 10px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.75);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    pointer-events: none;
    opacity: 0.85;
}
.floor-plan-item-card:hover .zoom-overlay-icon {
    opacity: 1;
    background: #0f172a;
}
.placeholder-plan-drawing {
    width: 100%;
    height: 100%;
    color: #94a3b8;
    font-size: 32px;
}
.btn-floor-filter {
    border-radius: 9999px !important;
    padding: 6px 14px !important;
    font-weight: 600 !important;
    font-size: 13px !important;
    border: 1px solid #cbd5e1 !important;
    background: #ffffff !important;
    color: #334155 !important;
}
.btn-floor-filter:hover {
    background: #f1f5f9 !important;
    color: #0f172a !important;
}
.btn-floor-filter.active {
    background: #0f172a !important;
    color: #ffffff !important;
    border-color: #0f172a !important;
}
.btn-floor-filter.active .badge {
    background: rgba(255, 255, 255, 0.25) !important;
    color: #ffffff !important;
}

/* Documents & PDF Cards */
.doc-download-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
    transition: transform 0.2s, box-shadow 0.2s;
}
.doc-download-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.07);
    border-color: #cbd5e1;
}
.doc-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.doc-type-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 9999px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.doc-badge-blue { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.doc-badge-green { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.doc-badge-coral { background: #fff1f2; color: #be123c; border: 1px solid #fecdd3; }
.doc-badge-purple { background: #faf5ff; color: #6d28d9; border: 1px solid #ddd6fe; }
.doc-badge-amber { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.doc-badge-teal { background: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4; }
.doc-badge-slate { background: #f8fafc; color: #334155; border: 1px solid #e2e8f0; }
.doc-title {
    font-size: 15px;
    line-height: 1.4;
    min-height: 42px;
}

/* Building Amenities & Lifestyle */
.project-amenity-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    height: 100%;
}
.project-amenity-card:hover {
    transform: translateY(-2px);
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
    background: #f8fafc;
}
.amenity-icon-circle {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #eff6ff;
    color: #2563eb;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.amenity-icon-circle svg,
.amenity-icon-circle i,
.amenity-icon-circle .icon {
    width: 18px !important;
    height: 18px !important;
    font-size: 18px !important;
    stroke-width: 1.8 !important;
}
.amenity-name {
    font-size: 13.5px;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.35;
}
.amenity-pill-badge {
    transition: all 0.15s ease-in-out;
}
.amenity-pill-badge:hover {
    background: #ffffff !important;
    border-color: #cbd5e1 !important;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}

/* Floor Plans Table */
.floor-plans-project-table thead th {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #475569;
    white-space: nowrap;
}
.floor-plans-project-table tbody td {
    font-size: 13.5px;
    vertical-align: middle;
}
.floor-plan-view-icon {
    padding: 4px 10px !important;
    line-height: 1 !important;
}
.floor-plan-view-icon i {
    font-size: 15px;
    vertical-align: middle;
}
</style>

<div class="project-detail-tabs-section mb-5" id="project-tabs-container">
    {{-- Modern Tab Navigation Bar --}}
    <div class="project-nav-tabs-wrapper">
        <ul class="nav project-nav-pills" id="projectDetailsTabList" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pills-overview-tab" data-bs-toggle="pill" data-bs-target="#pills-overview" type="button" role="tab" aria-controls="pills-overview" aria-selected="true">
                    <x-core::icon name="ti ti-info-circle" />
                    <span>{{ __('Overview') }}</span>
                </button>
            </li>

            @if (!empty($unitItems) || !empty($attributeItems) || $parsedAmenities->isNotEmpty())
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-specs-tab" data-bs-toggle="pill" data-bs-target="#pills-specs" type="button" role="tab" aria-controls="pills-specs" aria-selected="false">
                        <x-core::icon name="ti ti-building-community" />
                        <span>{{ __('Units & Specs') }}</span>
                    </button>
                </li>
            @endif

            @if (!empty($parkingItems) || !empty($serviceItems) || $model->deposit_notes || $model->maintenance_notes)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-pricing-tab" data-bs-toggle="pill" data-bs-target="#pills-pricing" type="button" role="tab" aria-controls="pills-pricing" aria-selected="false">
                        <x-core::icon name="ti ti-wallet" />
                        <span>{{ __('Pricing & Policies') }}</span>
                    </button>
                </li>
            @endif

            @if ($floorPlans->isNotEmpty())
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-floorplans-tab" data-bs-toggle="pill" data-bs-target="#pills-floorplans" type="button" role="tab" aria-controls="pills-floorplans" aria-selected="false">
                        <x-core::icon name="ti ti-layout-2" />
                        <span>{{ __('Floor Plans') }}</span>
                        <span class="tab-count-badge">{{ $floorPlans->count() }}</span>
                    </button>
                </li>
            @endif

            @if ($activeDocs->isNotEmpty())
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-documents-tab" data-bs-toggle="pill" data-bs-target="#pills-documents" type="button" role="tab" aria-controls="pills-documents" aria-selected="false">
                        <x-core::icon name="ti ti-file-type-pdf" />
                        <span>{{ __('Brochures & PDFs') }}</span>
                        <span class="tab-count-badge">{{ $activeDocs->count() }}</span>
                    </button>
                </li>
            @endif
        </ul>
    </div>

    {{-- Tab Content Panes --}}
    <div class="tab-content project-tab-content-wrapper mt-4" id="projectDetailsTabContent">
        
        {{-- ==================== TAB 1: OVERVIEW & HIGHLIGHTS ==================== --}}
        <div class="tab-pane fade show active" id="pills-overview" role="tabpanel" aria-labelledby="pills-overview-tab">
            {{-- Quick Summary Highlight Cards --}}
            <div class="project-highlights-grid mb-4">
                @if ($model->investor && $model->investor->name)
                    <div class="highlight-card">
                        <div class="highlight-icon"><x-core::icon name="ti ti-user-check" /></div>
                        <div class="highlight-data">
                            <span class="highlight-label">{{ __('Developer') }}</span>
                            <span class="highlight-value">{{ $model->investor->name }}</span>
                        </div>
                    </div>
                @endif

                @if ($model->number_floor)
                    <div class="highlight-card">
                        <div class="highlight-icon"><x-core::icon name="ti ti-building-skyscraper" /></div>
                        <div class="highlight-data">
                            <span class="highlight-label">{{ __('Storeys / Floors') }}</span>
                            <span class="highlight-value">{{ number_format($model->number_floor) }}</span>
                        </div>
                    </div>
                @endif

                @if ($totalUnitsVal)
                    <div class="highlight-card">
                        <div class="highlight-icon"><x-core::icon name="ti ti-layout-grid" /></div>
                        <div class="highlight-data">
                            <span class="highlight-label">{{ __('Total Units') }}</span>
                            <span class="highlight-value">{{ $totalUnitsVal }}</span>
                        </div>
                    </div>
                @endif

                @if ($model->date_finish)
                    <div class="highlight-card">
                        <div class="highlight-icon"><x-core::icon name="ti ti-calendar-event" /></div>
                        <div class="highlight-data">
                            <span class="highlight-label">{{ __('Estimated Completion') }}</span>
                            <span class="highlight-value">{{ $model->date_finish->format('M Y') }}</span>
                        </div>
                    </div>
                @endif

                @if ($model->suite_size_from || $model->suite_size_to)
                    <div class="highlight-card">
                        <div class="highlight-icon"><x-core::icon name="ti ti-ruler-2" /></div>
                        <div class="highlight-data">
                            <span class="highlight-label">{{ __('Suite Sizes') }}</span>
                            <span class="highlight-value">
                                @if ($model->suite_size_from && $model->suite_size_to)
                                    {{ $model->suite_size_from }} - {{ $model->suite_size_to }} {{ setting('real_estate_square_unit', 'sqft') }}
                                @else
                                    {{ $model->suite_size_from ?: $model->suite_size_to }} {{ setting('real_estate_square_unit', 'sqft') }}
                                @endif
                            </span>
                        </div>
                    </div>
                @endif

                @if ($model->price_per_sqft_from)
                    <div class="highlight-card">
                        <div class="highlight-icon"><x-core::icon name="ti ti-tag" /></div>
                        <div class="highlight-data">
                            <span class="highlight-label">{{ __('Price per Sqft') }}</span>
                            <span class="highlight-value">{{ __('From :price', ['price' => format_price($model->price_per_sqft_from)]) }}</span>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Story & Description --}}
            <div class="project-overview-content mb-4">
                <h4 class="h5 fw-bold mb-3 text-dark">{{ $model->name ? $model->name . ' - ' : '' }}{{ __('Project Overview') }}</h4>
                <div class="body-2 text-variant-1">
                    @php
                        $developerName = $model->investor->name ?? null;
                        $address = $model->location ?: $model->short_address;
                    @endphp
                    <div class="mb-3 lead-story-box p-3 rounded bg-light border">
                        {{ __('Project Name:') }} <strong>{{ $model->name }}</strong>
                        @if ($developerName) • {{ __('Developed by:') }} <strong>{{ $developerName }}</strong> @endif
                        @if ($address) • {{ __('Location:') }} <strong>{{ $address }}</strong> @endif
                        @if ($model->neighbour) • {{ __('Neighbourhood:') }} <strong>{{ $model->neighbour }}</strong> @endif
                    </div>

                    @if ($model->content || $model->description)
                        <div class="ck-content single-detail text-dark" style="line-height: 1.8;">
                            {!! BaseHelper::clean($model->content ?: $model->description) !!}
                        </div>
                    @endif
                </div>

                @if (($model->can_see_private_notes ?? false) && ($model->private_notes ?? null))
                    <div class="alert alert-primary py-3 px-3 mt-4" role="alert">
                        <div class="fw-semibold mb-1 d-flex align-items-center gap-2">
                            <x-core::icon name="ti ti-lock" /> {{ __('Private Notes (Admin & Author Only)') }}
                        </div>
                        <div style="font-size: 0.875rem;">
                            {!! BaseHelper::clean(nl2br($model->private_notes)) !!}
                        </div>
                    </div>
                @endif
            </div>

            {{-- Overview Featured Amenities Showcase --}}
            @if ($parsedAmenities->isNotEmpty())
                <div class="project-overview-amenities mt-4 pt-4 border-top">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2" style="font-size: 1rem;">
                            <x-core::icon name="ti ti-sparkles" class="text-primary" /> {{ __('Building Amenities & Lifestyle') }}
                        </h5>
                        <button type="button" class="btn btn-sm btn-link text-primary text-decoration-none p-0 fw-semibold" onclick="document.getElementById('pills-specs-tab').click();" style="font-size: 0.85rem;">
                            {{ __('View all :count amenities', ['count' => $parsedAmenities->count()]) }} →
                        </button>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($parsedAmenities as $amenity)
                            @php
                                $aIcon = $getAmenityIcon($amenity);
                            @endphp
                            <div class="amenity-pill-badge d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill bg-light border text-dark" style="font-size: 12.5px; font-weight: 600;">
                                <x-core::icon name="{{ $aIcon }}" class="text-primary" style="width: 14px; height: 14px;" />
                                <span>{{ $amenity }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- ==================== TAB 2: UNITS & SPECIFICATIONS ==================== --}}
        @if (!empty($unitItems) || !empty($attributeItems) || $parsedAmenities->isNotEmpty())
            <div class="tab-pane fade" id="pills-specs" role="tabpanel" aria-labelledby="pills-specs-tab">
                {{-- Total Units Specs --}}
                @if (!empty($unitItems))
                    <div class="specs-group mb-5">
                        <h4 class="h5 fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                            <x-core::icon name="ti ti-layout-grid" /> {{ __('Units & Suites Breakdown') }}
                        </h4>
                        <div class="row g-3">
                            @foreach ($unitItems as $item)
                                <div class="col-md-6 col-lg-4">
                                    <div class="spec-tile-card">
                                        <div class="spec-tile-icon"><x-core::icon name="{{ $item['icon'] }}" /></div>
                                        <div class="spec-tile-body">
                                            <div class="spec-tile-label">{{ $item['label'] }}</div>
                                            <div class="spec-tile-value">{!! $item['value'] !!}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Building Attributes --}}
                @if (!empty($attributeItems))
                    <div class="specs-group">
                        <h4 class="h5 fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                            <x-core::icon name="ti ti-building" /> {{ __('Building Architecture & Details') }}
                        </h4>
                        <div class="row g-3">
                            @foreach ($attributeItems as $item)
                                <div class="col-md-6 col-lg-4">
                                    <div class="spec-tile-card">
                                        <div class="spec-tile-icon"><x-core::icon name="{{ $item['icon'] }}" /></div>
                                        <div class="spec-tile-body">
                                            <div class="spec-tile-label">{{ $item['label'] }}</div>
                                            <div class="spec-tile-value">{!! $item['value'] !!}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Building Amenities & Lifestyle --}}
                @if ($parsedAmenities->isNotEmpty())
                    <div class="specs-group mt-5 pt-4 border-top">
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <h4 class="h5 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                <x-core::icon name="ti ti-sparkles" class="text-primary" /> {{ __('Building Amenities & Lifestyle') }}
                            </h4>
                            <span class="badge bg-light text-primary border px-3 py-2 fw-semibold" style="font-size: 0.85rem;">
                                {{ $parsedAmenities->count() }} {{ __('Features & Amenities') }}
                            </span>
                        </div>
                        <div class="row g-3">
                            @foreach ($parsedAmenities as $amenity)
                                @php
                                    $aIcon = $getAmenityIcon($amenity);
                                @endphp
                                <div class="col-md-6 col-lg-4">
                                    <div class="project-amenity-card">
                                        <div class="amenity-icon-circle">
                                            <x-core::icon name="{{ $aIcon }}" />
                                        </div>
                                        <div class="amenity-name">
                                            {{ $amenity }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- ==================== TAB 3: PRICING & POLICIES ==================== --}}
        @if (!empty($parkingItems) || !empty($serviceItems) || $model->deposit_notes || $model->maintenance_notes)
            <div class="tab-pane fade" id="pills-pricing" role="tabpanel" aria-labelledby="pills-pricing-tab">
                {{-- Deposit Structure --}}
                @if ($model->deposit_notes || !empty($serviceItems))
                    <div class="pricing-group mb-5">
                        <h4 class="h5 fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                            <x-core::icon name="ti ti-wallet" /> {{ __('Deposit Structure & Payment Schedule') }}
                        </h4>

                        @if (!empty($serviceItems))
                            <div class="row g-3 mb-3">
                                @foreach ($serviceItems as $item)
                                    <div class="col-md-4">
                                        <div class="spec-tile-card highlight-border">
                                            <div class="spec-tile-icon"><x-core::icon name="{{ $item['icon'] }}" /></div>
                                            <div class="spec-tile-body">
                                                <div class="spec-tile-label">{{ $item['label'] }}</div>
                                                <div class="spec-tile-value fw-bold text-primary">{!! $item['value'] !!}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($model->deposit_notes)
                            <div class="deposit-timeline-box p-4 rounded bg-light border">
                                <h6 class="fw-bold text-dark mb-3"><x-core::icon name="ti ti-calendar-stats" /> {{ __('Payment Milestones & Details:') }}</h6>
                                <div class="deposit-notes-content text-dark" style="font-size: 0.925rem; line-height: 1.8; white-space: pre-line;">{!! e($model->deposit_notes) !!}</div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Parking & Lockers & Maintenance Fees --}}
                @if (!empty($parkingItems) || $model->maintenance_notes)
                    <div class="pricing-group">
                        <h4 class="h5 fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                            <x-core::icon name="ti ti-car" /> {{ __('Parking, Lockers & Maintenance Fees') }}
                        </h4>

                        @if (!empty($parkingItems))
                            <div class="row g-3 mb-4">
                                @foreach ($parkingItems as $item)
                                    <div class="col-md-6 col-lg-4">
                                        <div class="spec-tile-card">
                                            <div class="spec-tile-icon"><x-core::icon name="{{ $item['icon'] }}" /></div>
                                            <div class="spec-tile-body">
                                                <div class="spec-tile-label">{{ $item['label'] }}</div>
                                                <div class="spec-tile-value">{!! $item['value'] !!}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($model->maintenance_notes)
                            <div class="maintenance-notes-box p-4 rounded bg-light border">
                                <h6 class="fw-bold text-dark mb-2"><x-core::icon name="ti ti-notes" /> {{ __('Maintenance & Utility Inclusions:') }}</h6>
                                <div class="text-muted" style="font-size: 0.9rem; line-height: 1.7; white-space: pre-line;">{!! e($model->maintenance_notes) !!}</div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- ==================== TAB 4: FLOOR PLANS ==================== --}}
        @if ($floorPlans->isNotEmpty())
            <div class="tab-pane fade" id="pills-floorplans" role="tabpanel" aria-labelledby="pills-floorplans-tab">
                <div class="floor-plans-tab-content">
                    {{-- Bedroom Filter Pills --}}
                    @php
                        $bedGroups = [
                            'all' => ['label' => __('All Plans'), 'count' => $floorPlans->count()],
                            'studio' => ['label' => __('Studio'), 'count' => $floorPlans->filter(fn($p) => isset($p['bedrooms_num']) && $p['bedrooms_num'] == 0)->count()],
                            '1bed' => ['label' => __('1 Bed / 1+Den'), 'count' => $floorPlans->filter(fn($p) => isset($p['bedrooms_num']) && ($p['bedrooms_num'] == 1 || $p['bedrooms_num'] == 1.5))->count()],
                            '2bed' => ['label' => __('2 Bed / 2+Den'), 'count' => $floorPlans->filter(fn($p) => isset($p['bedrooms_num']) && ($p['bedrooms_num'] == 2 || $p['bedrooms_num'] == 2.5))->count()],
                            '3bed' => ['label' => __('3+ Beds'), 'count' => $floorPlans->filter(fn($p) => isset($p['bedrooms_num']) && $p['bedrooms_num'] >= 3)->count()],
                        ];
                    @endphp

                    <div class="floor-plan-filter-pills d-flex flex-wrap gap-2 mb-4" role="group">
                        @foreach ($bedGroups as $key => $group)
                            @if ($group['count'] > 0 || $key === 'all')
                                <button type="button" class="btn btn-sm btn-floor-filter {{ $key === 'all' ? 'active' : '' }}" data-filter="{{ $key }}">
                                    {{ $group['label'] }} <span class="badge bg-secondary-subtle text-dark ms-1">{{ $group['count'] }}</span>
                                </button>
                            @endif
                        @endforeach
                    </div>

                    {{-- Floor Plans Table --}}
                    <div class="table-responsive" id="floorPlansContainer">
                        <table class="table table-bordered align-middle floor-plans-project-table">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Plan Name') }}</th>
                                    <th>{{ __('Bedrooms') }}</th>
                                    <th>{{ __('Bathrooms') }}</th>
                                    <th>{{ __('Size') }}</th>
                                    <th>{{ __('Price') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="text-center">{{ __('View') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($floorPlans as $plan)
                                    @php
                                        $bNum = $plan['bedrooms_num'] ?? null;
                                        $filterCategory = 'other';
                                        if ($bNum !== null) {
                                            if ((float)$bNum == 0.0) $filterCategory = 'studio';
                                            elseif ((float)$bNum == 1.0 || (float)$bNum == 1.5) $filterCategory = '1bed';
                                            elseif ((float)$bNum == 2.0 || (float)$bNum == 2.5) $filterCategory = '2bed';
                                            elseif ((float)$bNum >= 3.0) $filterCategory = '3bed';
                                        }
                                    @endphp
                                    <tr class="floor-plan-card-col" data-category="{{ $filterCategory }}" data-name="{{ strtolower($plan['name']) }}" data-size="{{ $plan['size'] ?? '' }}">
                                        <td class="fw-semibold text-dark">{{ $plan['name'] }}</td>
                                        <td>
                                            @if (!empty($plan['bedrooms']))
                                                <span class="d-inline-flex align-items-center gap-1">
                                                    <x-core::icon name="ti ti-bed" /> {{ $plan['bedrooms'] }}
                                                </span>
                                            @else
                                                <span class="text-muted">&mdash;</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if (!empty($plan['bathrooms']))
                                                <span class="d-inline-flex align-items-center gap-1">
                                                    <x-core::icon name="ti ti-bath" /> {{ $plan['bathrooms'] }}
                                                </span>
                                            @else
                                                <span class="text-muted">&mdash;</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if (!empty($plan['size']))
                                                {{ number_format($plan['size']) }} {{ setting('real_estate_square_unit', 'sqft') }}
                                            @else
                                                <span class="text-muted">&mdash;</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if (!empty($plan['price']))
                                                <span class="fw-bold text-primary">{{ format_price($plan['price']) }}</span>
                                            @else
                                                <span class="text-muted small">{{ __('On Request') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if (!empty($plan['availability']))
                                                @php
                                                    $avail = strtolower($plan['availability']);
                                                    $badgeCls = str_contains($avail, 'sold') ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-success-subtle text-success border-success-subtle';
                                                @endphp
                                                <span class="badge border {{ $badgeCls }}">{{ $plan['availability'] }}</span>
                                            @else
                                                <span class="text-muted">&mdash;</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if (!empty($plan['image']))
                                                <a href="{{ RvMedia::getImageUrl($plan['image']) }}"
                                                   data-fancybox="project-floor-plans"
                                                   data-caption="{{ $plan['name'] }}{{ !empty($plan['bedrooms']) ? ' · ' . $plan['bedrooms'] : '' }}{{ !empty($plan['size']) ? ' · ' . number_format($plan['size']) . ' sqft' : '' }}"
                                                   class="btn btn-sm btn-outline-primary floor-plan-view-icon"
                                                   title="{{ __('View floor plan') }}">
                                                    <x-core::icon name="ti ti-eye" />
                                                </a>
                                            @else
                                                <span class="text-muted" title="{{ __('No image available') }}">
                                                    <x-core::icon name="ti ti-eye-off" />
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- ==================== TAB 5: BROCHURES & DOCUMENTS (PDFs) ==================== --}}
        @if ($activeDocs->isNotEmpty())
            <div class="tab-pane fade" id="pills-documents" role="tabpanel" aria-labelledby="pills-documents-tab">
                <div class="project-documents-tab-content">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle floor-plans-project-table">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Document Name') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Format') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th class="text-center">{{ __('Download') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($activeDocs as $doc)
                                    @php
                                        $category = $docCategory($doc->name);
                                        $icon = $docIcon($category);
                                        $badgeClass = $docColor($category);
                                        $isPdf = str_contains(strtolower($doc->document_type ?? ''), 'pdf') || str_ends_with(strtolower($doc->name), '.pdf');
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="d-inline-flex align-items-center gap-2">
                                                <span class="doc-icon-wrap {{ $badgeClass }}" style="width:34px;height:34px;border-radius:8px;font-size:16px;">
                                                    <x-core::icon name="{{ $icon }}" />
                                                </span>
                                                <span class="fw-semibold text-dark">{{ preg_replace('/ - Pro(\.pdf)?$/i', '', $doc->name) }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="doc-type-badge {{ $badgeClass }}">{{ $category }}</span>
                                        </td>
                                        <td>
                                            @if ($isPdf)
                                                <span class="badge bg-danger text-white d-inline-flex align-items-center gap-1">
                                                    <x-core::icon name="ti ti-file-type-pdf" /> PDF
                                                </span>
                                            @else
                                                <span class="badge bg-secondary text-white d-inline-flex align-items-center gap-1">
                                                    <x-core::icon name="ti ti-file" /> Document
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-muted small">
                                            {{ $doc->updated_at ? $doc->updated_at->format('M Y') : '&mdash;' }}
                                        </td>
                                        <td class="text-center">
                                            @if ($doc->url)
                                                <a href="{{ $doc->url }}" target="_blank" rel="noopener noreferrer"
                                                   class="btn btn-sm btn-outline-primary floor-plan-view-icon"
                                                   title="{{ __('View / Download') }}">
                                                    <x-core::icon name="ti ti-download" />
                                                </a>
                                            @else
                                                <span class="text-muted">
                                                    <x-core::icon name="ti ti-ban" />
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>

{{-- Dynamic Tab Interaction & Filter Script --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Bedroom Filter buttons for floor plans
    const filterButtons = document.querySelectorAll('.btn-floor-filter');
    const floorCols = document.querySelectorAll('.floor-plan-card-col');

    function applyFloorFilters() {
        const activeBtn = document.querySelector('.btn-floor-filter.active');
        const filterVal = activeBtn ? activeBtn.getAttribute('data-filter') : 'all';

        floorCols.forEach(col => {
            const cat = col.getAttribute('data-category');
            col.style.display = (filterVal === 'all' || cat === filterVal) ? '' : 'none';
        });
    }

    filterButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            applyFloorFilters();
        });
    });

    // 2. Hash routing for tabs (#tab-floorplans, #tab-documents, etc.)
    const hashMap = {
        '#floor-plans': 'pills-floorplans-tab',
        '#floorplans': 'pills-floorplans-tab',
        '#tab-floorplans': 'pills-floorplans-tab',
        '#documents': 'pills-documents-tab',
        '#tab-documents': 'pills-documents-tab',
        '#specs': 'pills-specs-tab',
        '#pricing': 'pills-pricing-tab',
        '#overview': 'pills-overview-tab',
    };

    if (window.location.hash && hashMap[window.location.hash]) {
        const targetTabBtn = document.getElementById(hashMap[window.location.hash]);
        if (targetTabBtn && window.bootstrap && window.bootstrap.Tab) {
            const tabTrigger = new bootstrap.Tab(targetTabBtn);
            tabTrigger.show();
        }
    }

    // Floating navigation links support (style-2 single layout)
    document.querySelectorAll('a[href="#floor-plans"]').forEach(link => {
        link.addEventListener('click', function (e) {
            const fpTab = document.getElementById('pills-floorplans-tab');
            if (fpTab && window.bootstrap && window.bootstrap.Tab) {
                const tabTrigger = new bootstrap.Tab(fpTab);
                tabTrigger.show();
                const container = document.getElementById('project-tabs-container');
                if (container) {
                    container.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    });
});
</script>
