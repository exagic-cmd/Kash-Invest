@php
    $model = $model ?? $property ?? null;
    $isProject = $model instanceof \Botble\RealEstate\Models\Project;
@endphp

@if ($isProject)
    @php
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
            $unitItems[] = ['icon' => 'ti ti-layout-grid', 'label' => __('Studio'), 'value' => $val];
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
            $unitItems[] = ['icon' => 'ti ti-ruler-2', 'label' => __('Suite Size'), 'value' => $sizeStr];
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

        // Catch any remaining custom fields (excluding website links)
        $ignoredFieldNames = ['website', 'official website', 'url', 'link'];
        foreach ($cfCollection as $cf) {
            $cfNameLower = strtolower(trim($cf->name));
            if (in_array($cfNameLower, $ignoredFieldNames) || !empty($usedCustomFieldIds[$cf->id ?? $cfNameLower]) || blank($cf->value)) {
                continue;
            }
            $valFormatted = e($cf->value);
            if (\Illuminate\Support\Str::startsWith($cf->value, ['http://', 'https://'])) {
                $valFormatted = '<a href="' . e($cf->value) . '" target="_blank" rel="noopener noreferrer" class="text-primary text-break">' . e($cf->value) . '</a>';
            }
            $attributeItems[] = ['icon' => 'ti ti-info-circle', 'label' => __($cf->name), 'value' => $valFormatted];
        }

        // 3. Parking & Access specs
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

        // 4. Services & Policies specs
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
    @endphp

    <style>
        .box-project-card {
            background: #ffffff;
            border: 1px solid #eaedf1;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .box-project-card .project-section-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0;
        }
        .box-project-card hr.project-section-divider {
            border-color: #eaedf1;
            opacity: 1;
            margin: 16px 0 24px 0;
        }
        .spec-icon-wrap {
            width: 44px;
            height: 44px;
            color: #8c98a4;
        }
        .spec-icon-wrap svg {
            width: 32px;
            height: 32px;
            stroke-width: 1.5;
        }
        @media (min-width: 768px) {
            .border-end-md {
                border-right: 1px solid #eaedf1 !important;
            }
        }
    </style>

    {{-- Section 1: Project Description / About this home --}}
    @php
        $developerName = $model->investor->name ?? null;
        $address = $model->location ?: $model->short_address;

        $storiesUnitsList = [];
        if ($model->number_floor) {
            $storiesUnitsList[] = number_format($model->number_floor) . ' Stories';
        }
        if ($model->number_flat) {
            $storiesUnitsList[] = number_format($model->number_flat) . ' Units';
        } elseif ($tu = $getCustom('total number of units', 'total units', 'number of units')) {
            $storiesUnitsList[] = $tu . ' Units';
        }
        $storiesUnitsText = !empty($storiesUnitsList) ? implode(' / ', $storiesUnitsList) : '';
    @endphp

    <div class="box-project-card">
        <h3 class="h5 fw-bold text-dark project-section-title">{{ $model->name ? $model->name . ' - ' : '' }}{{ __('Project Description') }}</h3>
        <hr class="project-section-divider">
        
        <div class="body-2 text-variant-1">
            <div class="mb-3 ck-content single-detail" >
                Project Name is <strong>{{ $model->name }}</strong>@if ($storiesUnitsText), {{ $storiesUnitsText }}@endif.@if ($developerName) Developed By: <strong>{{ $developerName }}</strong>@endif @if ($address)and is located at <strong>{{ $address }}</strong>@endif.
            </div>

            @if ($model->content || $model->description)
                <div class="ck-content single-detail">
                    {!! BaseHelper::clean($model->content ?: $model->description) !!}
                </div>
            @endif
        </div>

        @if (($model->can_see_private_notes ?? false) && ($model->private_notes ?? null))
            <div class="alert alert-primary py-2 px-3 mt-3" role="alert">
                <div class="fw-semibold mb-1" style="font-size: 0.875rem;">{{ __('Private Notes') }}</div>
                <div style="font-size: 0.8125rem;">
                    {!! BaseHelper::clean(nl2br($model->private_notes)) !!}
                </div>
            </div>
        @endif
    </div>

    {{-- Section 2: Total Units --}}
    @if (!empty($unitItems))
        @php
            $half = (int) ceil(count($unitItems) / 2);
            $leftCol = array_slice($unitItems, 0, $half);
            $rightCol = array_slice($unitItems, $half);
        @endphp
        <div class="box-project-card">
            <h3 class="h5 fw-bold text-dark project-section-title">{{ $model->name ? $model->name . ' - ' : '' }}{{ __('Total Units') }}</h3>
            <hr class="project-section-divider">
            <div class="row g-4 style-project-specs-grid">
                <div class="col-md-6 d-flex flex-column gap-4 pe-md-4 border-end-md">
                    @foreach ($leftCol as $item)
                        <div class="d-flex align-items-center gap-3 spec-item">
                            <div class="spec-icon-wrap d-flex align-items-center justify-content-center flex-shrink-0 text-muted">
                                @if (\Botble\Icon\Facades\Icon::has($item['icon']))
                                    <x-core::icon name="{{ $item['icon'] }}" />
                                @else
                                    <x-core::icon name="ti ti-info-circle" />
                                @endif
                            </div>
                            <div class="spec-content">
                                <div class="spec-label text-muted" style="font-size: 0.85rem; line-height: 1.2;">{{ $item['label'] }}</div>
                                <div class="spec-value fw-bold text-dark mt-1" style="font-size: 1rem; line-height: 1.3;">{!! $item['value'] !!}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="col-md-6 d-flex flex-column gap-4 ps-md-4">
                    @foreach ($rightCol as $item)
                        <div class="d-flex align-items-center gap-3 spec-item">
                            <div class="spec-icon-wrap d-flex align-items-center justify-content-center flex-shrink-0 text-muted">
                                @if (\Botble\Icon\Facades\Icon::has($item['icon']))
                                    <x-core::icon name="{{ $item['icon'] }}" />
                                @else
                                    <x-core::icon name="ti ti-info-circle" />
                                @endif
                            </div>
                            <div class="spec-content">
                                <div class="spec-label text-muted" style="font-size: 0.85rem; line-height: 1.2;">{{ $item['label'] }}</div>
                                <div class="spec-value fw-bold text-dark mt-1" style="font-size: 1rem; line-height: 1.3;">{!! $item['value'] !!}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Section 3: Building Attributes --}}
    @if (!empty($attributeItems))
        @php
            $half = (int) ceil(count($attributeItems) / 2);
            $leftCol = array_slice($attributeItems, 0, $half);
            $rightCol = array_slice($attributeItems, $half);
        @endphp
        <div class="box-project-card">
            <h3 class="h5 fw-bold text-dark project-section-title">{{ $model->name ? $model->name . ' - ' : '' }}{{ __('Building Attributes') }}</h3>
            <hr class="project-section-divider">
            <div class="row g-4 style-project-specs-grid">
                <div class="col-md-6 d-flex flex-column gap-4 pe-md-4 border-end-md">
                    @foreach ($leftCol as $item)
                        <div class="d-flex align-items-center gap-3 spec-item">
                            <div class="spec-icon-wrap d-flex align-items-center justify-content-center flex-shrink-0 text-muted">
                                @if (\Botble\Icon\Facades\Icon::has($item['icon']))
                                    <x-core::icon name="{{ $item['icon'] }}" />
                                @else
                                    <x-core::icon name="ti ti-info-circle" />
                                @endif
                            </div>
                            <div class="spec-content">
                                <div class="spec-label text-muted" style="font-size: 0.85rem; line-height: 1.2;">{{ $item['label'] }}</div>
                                <div class="spec-value fw-bold text-dark mt-1" style="font-size: 1rem; line-height: 1.3;">{!! $item['value'] !!}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="col-md-6 d-flex flex-column gap-4 ps-md-4">
                    @foreach ($rightCol as $item)
                        <div class="d-flex align-items-center gap-3 spec-item">
                            <div class="spec-icon-wrap d-flex align-items-center justify-content-center flex-shrink-0 text-muted">
                                @if (\Botble\Icon\Facades\Icon::has($item['icon']))
                                    <x-core::icon name="{{ $item['icon'] }}" />
                                @else
                                    <x-core::icon name="ti ti-info-circle" />
                                @endif
                            </div>
                            <div class="spec-content">
                                <div class="spec-label text-muted" style="font-size: 0.85rem; line-height: 1.2;">{{ $item['label'] }}</div>
                                <div class="spec-value fw-bold text-dark mt-1" style="font-size: 1rem; line-height: 1.3;">{!! $item['value'] !!}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Section 4: Parking & Access --}}
    @if (!empty($parkingItems) || $model->maintenance_notes)
        @php
            $half = (int) ceil(count($parkingItems) / 2);
            $leftCol = array_slice($parkingItems, 0, $half);
            $rightCol = array_slice($parkingItems, $half);
        @endphp
        <div class="box-project-card">
            <h3 class="h5 fw-bold text-dark project-section-title">{{ $model->name ? $model->name . ' - ' : '' }}{{ __('Parking & Access') }}</h3>
            <hr class="project-section-divider">
            @if (!empty($parkingItems))
                <div class="row g-4 style-project-specs-grid">
                    <div class="col-md-6 d-flex flex-column gap-4 pe-md-4 border-end-md">
                        @foreach ($leftCol as $item)
                            <div class="d-flex align-items-center gap-3 spec-item">
                                <div class="spec-icon-wrap d-flex align-items-center justify-content-center flex-shrink-0 text-muted">
                                    @if (\Botble\Icon\Facades\Icon::has($item['icon']))
                                        <x-core::icon name="{{ $item['icon'] }}" />
                                    @else
                                        <x-core::icon name="ti ti-info-circle" />
                                    @endif
                                </div>
                                <div class="spec-content">
                                    <div class="spec-label text-muted" style="font-size: 0.85rem; line-height: 1.2;">{{ $item['label'] }}</div>
                                    <div class="spec-value fw-bold text-dark mt-1" style="font-size: 1rem; line-height: 1.3;">{!! $item['value'] !!}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-6 d-flex flex-column gap-4 ps-md-4">
                        @foreach ($rightCol as $item)
                            <div class="d-flex align-items-center gap-3 spec-item">
                                <div class="spec-icon-wrap d-flex align-items-center justify-content-center flex-shrink-0 text-muted">
                                    @if (\Botble\Icon\Facades\Icon::has($item['icon']))
                                        <x-core::icon name="{{ $item['icon'] }}" />
                                    @else
                                        <x-core::icon name="ti ti-info-circle" />
                                    @endif
                                </div>
                                <div class="spec-content">
                                    <div class="spec-label text-muted" style="font-size: 0.85rem; line-height: 1.2;">{{ $item['label'] }}</div>
                                    <div class="spec-value fw-bold text-dark mt-1" style="font-size: 1rem; line-height: 1.3;">{!! $item['value'] !!}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($model->maintenance_notes)
                <div class="mt-4 pt-3 border-top" style="border-color: #eaedf1 !important;">
                    <div class="fw-semibold text-dark mb-1" style="font-size: 0.9rem;">{{ __('Maintenance Fees & Notes:') }}</div>
                    <div class="text-muted" style="font-size: 0.875rem; line-height: 1.6;">
                        {!! BaseHelper::clean(nl2br($model->maintenance_notes)) !!}
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Section 5: Services & Policies --}}
    @if (!empty($serviceItems) || $model->deposit_notes)
        @php
            $half = (int) ceil(count($serviceItems) / 2);
            $leftCol = array_slice($serviceItems, 0, $half);
            $rightCol = array_slice($serviceItems, $half);
        @endphp
        <div class="box-project-card">
            <h3 class="h5 fw-bold text-dark project-section-title">{{ $model->name ? $model->name . ' - ' : '' }}{{ __('Services & Policies') }}</h3>
            <hr class="project-section-divider">
            @if (!empty($serviceItems))
                <div class="row g-4 style-project-specs-grid">
                    <div class="col-md-6 d-flex flex-column gap-4 pe-md-4 border-end-md">
                        @foreach ($leftCol as $item)
                            <div class="d-flex align-items-center gap-3 spec-item">
                                <div class="spec-icon-wrap d-flex align-items-center justify-content-center flex-shrink-0 text-muted">
                                    @if (\Botble\Icon\Facades\Icon::has($item['icon']))
                                        <x-core::icon name="{{ $item['icon'] }}" />
                                    @else
                                        <x-core::icon name="ti ti-info-circle" />
                                    @endif
                                </div>
                                <div class="spec-content">
                                    <div class="spec-label text-muted" style="font-size: 0.85rem; line-height: 1.2;">{{ $item['label'] }}</div>
                                    <div class="spec-value fw-bold text-dark mt-1" style="font-size: 1rem; line-height: 1.3;">{!! $item['value'] !!}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-6 d-flex flex-column gap-4 ps-md-4">
                        @foreach ($rightCol as $item)
                            <div class="d-flex align-items-center gap-3 spec-item">
                                <div class="spec-icon-wrap d-flex align-items-center justify-content-center flex-shrink-0 text-muted">
                                    @if (\Botble\Icon\Facades\Icon::has($item['icon']))
                                        <x-core::icon name="{{ $item['icon'] }}" />
                                    @else
                                        <x-core::icon name="ti ti-info-circle" />
                                    @endif
                                </div>
                                <div class="spec-content">
                                    <div class="spec-label text-muted" style="font-size: 0.85rem; line-height: 1.2;">{{ $item['label'] }}</div>
                                    <div class="spec-value fw-bold text-dark mt-1" style="font-size: 1rem; line-height: 1.3;">{!! $item['value'] !!}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($model->deposit_notes)
                <div class="mt-4 pt-3 border-top" style="border-color: #eaedf1 !important;">
                    <div class="fw-semibold text-dark mb-1" style="font-size: 0.9rem;">{{ __('Deposit Structure & Notes:') }}</div>
                    <div class="text-muted" style="font-size: 0.875rem; line-height: 1.6;">
                        {!! BaseHelper::clean(nl2br($model->deposit_notes)) !!}
                    </div>
                </div>
            @endif
        </div>
    @endif

@else
    <!-- Original Property Overview (for Properties) -->
    <div @class(['single-property-overview', $class ?? null])>
        <div class="h7 title fw-7 mb-3">{{ __('Overview') }}</div>
        <div class="row row-cols-1 row-cols-md-2 g-x-4 g-y-2" style="font-size: 0.875rem;">
            <div class="col d-flex justify-content-between border-bottom pb-2 mb-2">
                <span class="fw-semibold text-muted">{{ __('Property ID:') }}</span>
                <span class="fw-bold text-dark">{{ $model->unique_id ?: $model->getKey() }}</span>
            </div>
            @if ($model->categories->isNotEmpty())
                <div class="col d-flex justify-content-between border-bottom pb-2 mb-2">
                    <span class="fw-semibold text-muted{{ $model->categories->isNotEmpty() ? ' w-50' : '' }}">{{ __('Type:') }}</span>
                    <span class="fw-bold text-dark">
                        @foreach ($model->categories as $category)
                            <a href="{{ $category->url }}" class="text-dark">{!! BaseHelper::clean($category->name) !!}</a>@if (!$loop->last),&nbsp;@endif
                        @endforeach
                    </span>
                </div>
            @endif
            @if (($model->number_bedroom ?? null))
                <div class="col d-flex justify-content-between border-bottom pb-2 mb-2">
                    <span class="fw-semibold text-muted">{{ __('Bedrooms:') }}</span>
                    <span class="fw-bold text-dark">{{ fmod($model->number_bedroom, 1) == 0 ? number_format($model->number_bedroom) : $model->number_bedroom }}</span>
                </div>
            @endif
            @if (($model->number_bathroom ?? null))
                <div class="col d-flex justify-content-between border-bottom pb-2 mb-2">
                    <span class="fw-semibold text-muted">{{ __('Bathrooms:') }}</span>
                    <span class="fw-bold text-dark">{{ fmod($model->number_bathroom, 1) == 0 ? number_format($model->number_bathroom) : $model->number_bathroom }}</span>
                </div>
            @endif
            @if (($model->number_floor ?? null))
                <div class="col d-flex justify-content-between border-bottom pb-2 mb-2">
                    <span class="fw-semibold text-muted">{{ __('Floors:') }}</span>
                    <span class="fw-bold text-dark">{{ number_format($model->number_floor) }}</span>
                </div>
            @endif
            @if (($model->square ?? null))
                <div class="col d-flex justify-content-between border-bottom pb-2 mb-2">
                    <span class="fw-semibold text-muted">{{ __('Square:') }}</span>
                    <span class="fw-bold text-dark">{{ $model->square_text }}</span>
                </div>
            @endif
            @foreach ($model->customFields as $customField)
                @continue(! $customField->value)
                <div class="col d-flex justify-content-between border-bottom pb-2 mb-2">
                    <span class="fw-semibold text-muted">{!! BaseHelper::clean($customField->name) !!}:</span>
                    <span class="fw-bold text-dark">{!! BaseHelper::clean($customField->value) !!}</span>
                </div>
            @endforeach
        </div>
    </div>

    @if ($model->content || (($model->can_see_private_notes ?? false) && ($model->private_notes ?? null)))
        <div @class(['single-property-desc', $class ?? null])>
            @if($model->content)
                <h4 class="h5 fw-bold mb-3 mt-4 text-dark">{{ $model->name ? $model->name . ' - ' : '' }}{{ __('About this home') }}</h4>
                <div class="body-2 text-variant-1">
                    <div class="ck-content single-detail">
                        {!! BaseHelper::clean($model->content) !!}
                    </div>
                </div>
            @endif

            @if(($model->can_see_private_notes ?? false) && ($model->private_notes ?? null))
                <div class="alert alert-primary py-2 px-3 mt-3" role="alert">
                    <div class="fw-semibold mb-1" style="font-size: 0.875rem;">{{ __('Private Notes') }}</div>
                    <div style="font-size: 0.8125rem;">
                        {!! BaseHelper::clean(nl2br($model->private_notes)) !!}
                    </div>
                </div>
            @endif
        </div>
    @endif
@endif
