@php
    $model = $model ?? $property ?? null;
    $isProject = $model instanceof \Botble\RealEstate\Models\Project;
@endphp

@if ($isProject)
    @include(Theme::getThemeNamespace('views.real-estate.single-layouts.partials.project-tabs'), ['model' => $model, 'class' => $class ?? null])
@else
    @php
        $isUnknown = function ($value): bool {
            if ($value === null || $value === '') {
                return true;
            }
            $str = strtolower(trim(strip_tags((string) $value)));
            return in_array($str, ['unknown', 'n/a', 'na', 'null', 'none', '-'], true);
        };

        // Only these fields belong in the overview strip.
        // All RESO detail fields (Style, Heating, Basement, Parking …) live
        // in the Property Details section below.
        $overviewFields = [
            'mls number', 'listing brokerage', 'co-listing brokerage',
            'listed on', 'last updated',
        ];

        // Date-valued custom fields render as "Sep 5, 2025 (340 days ago)".
        $dateCustomFields = ['listed on', 'last updated'];

        $renderCustomFieldValue = function ($field) use ($dateCustomFields) {
            if (! in_array(strtolower(trim((string) $field->name)), $dateCustomFields, true)) {
                return BaseHelper::clean($field->value);
            }

            try {
                $date = \Illuminate\Support\Carbon::parse($field->value);
            } catch (\Throwable) {
                return BaseHelper::clean($field->value);
            }

            $days = (int) abs($date->copy()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::now()->startOfDay()));

            $relative = match (true) {
                $days === 0 => __('today'),
                $days === 1 => __('1 day ago'),
                default => __(':count days ago', ['count' => number_format($days)]),
            };

            return e($date->format('M j, Y'))
                . ' <span class="fw-normal text-muted">(' . e($relative) . ')</span>';
        };
    @endphp

    <!-- Original Property Overview (for Properties) -->
    <div @class(['single-property-overview', $class ?? null])>
        <div class="h7 title fw-7 mb-3">{{ __('Overview') }}</div>
        <div class="row row-cols-1 row-cols-md-2" style="font-size: 0.95rem;">
            <!-- @if ($model->unique_id && !$isUnknown($model->unique_id))
                <div class="col d-flex align-items-baseline gap-2 mb-2">
                    <span class="fw-bold text-dark flex-shrink-0">{{ __('Property ID:') }}</span>
                    <span class="fw-normal text-dark">{{ $model->unique_id }}</span>
                </div>
            @endif -->
            @if ($model->categories->isNotEmpty())
                <div class="col d-flex align-items-baseline gap-2 mb-2">
                    <span class="fw-bold text-dark flex-shrink-0">{{ __('Type:') }}</span>
                    <span class="fw-normal text-dark">
                        @foreach ($model->categories as $category)
                            <a href="{{ $category->url }}" class="text-dark">{!! BaseHelper::clean($category->name) !!}</a>@if (!$loop->last),&nbsp;@endif
                        @endforeach
                    </span>
                </div>
            @endif
            @if (($model->number_bedroom ?? null) && !$isUnknown($model->number_bedroom))
                <div class="col d-flex align-items-baseline gap-2 mb-2">
                    <span class="fw-bold text-dark flex-shrink-0">{{ __('Bedrooms:') }}</span>
                    <span class="fw-normal text-dark">{{ fmod($model->number_bedroom, 1) == 0 ? number_format($model->number_bedroom) : $model->number_bedroom }}</span>
                </div>
            @endif
            @if (($model->number_bathroom ?? null) && !$isUnknown($model->number_bathroom))
                <div class="col d-flex align-items-baseline gap-2 mb-2">
                    <span class="fw-bold text-dark flex-shrink-0">{{ __('Bathrooms:') }}</span>
                    <span class="fw-normal text-dark">{{ fmod($model->number_bathroom, 1) == 0 ? number_format($model->number_bathroom) : $model->number_bathroom }}</span>
                </div>
            @endif
            @if (($model->number_floor ?? null) && !$isUnknown($model->number_floor))
                <div class="col d-flex align-items-baseline gap-2 mb-2">
                    <span class="fw-bold text-dark flex-shrink-0">{{ __('Floors:') }}</span>
                    <span class="fw-normal text-dark">{{ number_format($model->number_floor) }}</span>
                </div>
            @endif
            @if (($model->square ?? null) && !$isUnknown($model->square))
                <div class="col d-flex align-items-baseline gap-2 mb-2">
                    <span class="fw-bold text-dark flex-shrink-0">{{ __('Square:') }}</span>
                    <span class="fw-normal text-dark">{{ $model->square_text }}</span>
                </div>
            @endif
            @foreach ($model->customFields as $customField)
                @continue(! $customField->value)
                @continue($isUnknown($customField->value))
                @continue(! in_array(strtolower(trim($customField->name)), $overviewFields, true))
                <div class="col d-flex align-items-baseline gap-2 mb-2">
                    <span class="fw-bold text-dark flex-shrink-0">{!! BaseHelper::clean($customField->name) !!}:</span>
                    <span class="fw-normal text-dark">{!! $renderCustomFieldValue($customField) !!}</span>
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

            @php
                // Dynamic Summary Paragraph computation
                $cfMap = [];
                foreach ($model->customFields as $cfItem) {
                    if ($cfItem->name && $cfItem->value !== null && $cfItem->value !== '') {
                        $cfMap[strtolower(trim($cfItem->name))] = trim($cfItem->value);
                    }
                }

                // 1. [type]
                $propType = $cfMap['property sub type'] ?? ($cfMap['property type'] ?? ($model->categories->first()?->name ?? 'property'));

                // 2. [address]
                $propAddress = $model->location ?: ($model->short_address ?: $model->name);

                // 3. [sale/lease]
                $isRent = ($model->type && strtolower($model->type->getValue()) === 'rent') || strtolower($cfMap['transaction type'] ?? '') === 'for lease';
                $forStatus = $isRent ? __('lease') : __('sale');

                // 4. [today-updated] days
                $daysVal = 0;
                $listedDateVal = $cfMap['listed on'] ?? null;
                if ($listedDateVal) {
                    try {
                        $daysVal = (int) abs(\Illuminate\Support\Carbon::parse($listedDateVal)->startOfDay()->diffInDays(now()->startOfDay()));
                    } catch (\Throwable) {}
                }
                if (!$daysVal && $model->created_at) {
                    $daysVal = (int) abs($model->created_at->startOfDay()->diffInDays(now()->startOfDay()));
                }

                // 5. [city] and [neighbourhood]
                $propCity = $model->city?->name ?? ($cfMap['city'] ?? '');
                $propNeighbourhood = $cfMap['neighbourhood'] ?? ($cfMap['area minor'] ?? '');

                // 6. [$price] and mortgage
                $priceNum = (float) $model->price;
                $formattedPrice = $model->price_format ?: ('$' . number_format($priceNum));
                
                // Estimated mortgage: standard Canadian 20% down, 5% annual interest, 25-yr amortization
                $estMortgage = 0;
                if ($priceNum > 0) {
                    $principalLoan = $priceNum * 0.80;
                    $rMonthly = (0.05) / 12;
                    $nMonths = 25 * 12;
                    $estMortgage = ($principalLoan * $rMonthly * pow(1 + $rMonthly, $nMonths)) / (pow(1 + $rMonthly, $nMonths) - 1);
                }
                $estMortgageStr = '$' . number_format(round($estMortgage));

                // 7. [AxB] sqft
                $lotW = $cfMap['lot width'] ?? null;
                $lotD = $cfMap['lot depth'] ?? null;
                $livingRange = $cfMap['living area range'] ?? null;
                $lotAreaVal = $cfMap['lot size'] ?? null;
                $lotUnitsVal = $cfMap['lot size units'] ?? '';

                if ($lotW && $lotD && (float)$lotW > 0 && (float)$lotD > 0) {
                    $areaDimStr = rtrim(rtrim(number_format((float)$lotW, 2), '0'), '.') . 'x' . rtrim(rtrim(number_format((float)$lotD, 2), '0'), '.');
                } elseif ($livingRange && !in_array(strtolower($livingRange), ['unknown', 'n/a', 'na', 'null', 'none', '-'], true)) {
                    $areaDimStr = $livingRange;
                } elseif ($model->square) {
                    $areaDimStr = number_format($model->square);
                } elseif ($lotAreaVal && (float)$lotAreaVal > 0) {
                    $unitLabel = (strtolower($lotUnitsVal) === 'acres' || (float)$lotAreaVal < 10) ? 'ac' : 'sqft';
                    $areaDimStr = rtrim(rtrim(number_format((float)$lotAreaVal, 2), '0'), '.') . ' ' . $unitLabel;
                } else {
                    $areaDimStr = 'N/A';
                }

                // 8. [beds] and [BR] (bathrooms)
                $bAbove = $cfMap['bedrooms above grade'] ?? null;
                $bBelow = $cfMap['bedrooms below grade'] ?? null;
                if ($bAbove !== null && $bBelow !== null && (int)$bBelow > 0) {
                    $bedsOutput = sprintf('%d+%d beds', (int)$bAbove, (int)$bBelow);
                } elseif ($bAbove !== null && (int)$bAbove > 0) {
                    $bedsOutput = sprintf('%d beds', (int)$bAbove);
                } elseif ($model->number_bedroom) {
                    $bedsOutput = (fmod($model->number_bedroom, 1) == 0 ? number_format($model->number_bedroom) : $model->number_bedroom) . ' beds';
                } else {
                    $bedsOutput = '0 beds';
                }

                $bathsTotal = $cfMap['total bathrooms'] ?? ($model->number_bathroom ?? 0);
                $bathsOutput = (fmod($bathsTotal, 1) == 0 ? number_format($bathsTotal) : $bathsTotal) . ' BR';

                // 9. [A1], [A2] and [A3] are nearby neighbourhoods
                $nearbyNeighbourhoods = \Botble\RealEstate\Models\Property::query()
                    ->where('id', '!=', $model->id)
                    ->whereHas('customFields', function($q) use ($propNeighbourhood) {
                        $q->whereIn('name', ['Neighbourhood', 'Area Minor']);
                        if ($propNeighbourhood) {
                            $q->where('value', '!=', $propNeighbourhood);
                        }
                    })
                    ->with('customFields')
                    ->limit(15)
                    ->get()
                    ->map(function($p) {
                        foreach ($p->customFields as $f) {
                            if (in_array(strtolower($f->name), ['neighbourhood', 'area minor']) && filled($f->value)) {
                                return trim($f->value);
                            }
                        }
                        return null;
                    })
                    ->filter()
                    ->unique()
                    ->values()
                    ->take(3)
                    ->all();

                if (count($nearbyNeighbourhoods) >= 3) {
                    $nearbyStr = sprintf('%s, %s and %s', $nearbyNeighbourhoods[0], $nearbyNeighbourhoods[1], $nearbyNeighbourhoods[2]);
                } elseif (count($nearbyNeighbourhoods) === 2) {
                    $nearbyStr = sprintf('%s and %s', $nearbyNeighbourhoods[0], $nearbyNeighbourhoods[1]);
                } elseif (count($nearbyNeighbourhoods) === 1) {
                    $nearbyStr = $nearbyNeighbourhoods[0];
                } else {
                    $nearbyStr = $propCity ? $propCity . ' East and West' : 'surrounding areas';
                }
            @endphp

            {{-- Dynamic Summary Paragraph --}}
            <div class="dynamic-property-summary mt-4 pt-3">
                <p class="body-2 text-variant-1 mb-0">
                    This {{ $propType }} located at {{ $propAddress }} is currently for {{ $forStatus }} and has been available at Kash Invest for {{ $daysVal }} days. This property, in the city of {{ $propCity ?: 'the area' }} and {{ $propNeighbourhood ?: 'local' }} neighborhood, is listed at {{ $formattedPrice }} with an estimated mortgage of {{ $estMortgageStr }}* per month. It has an area of {{ $areaDimStr }} sqft with {{ $bedsOutput }} and {{ $bathsOutput }}. {{ $nearbyStr }} are nearby neighbourhoods.
                </p>
            </div>

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
