@php
    $model = $model ?? $property ?? null;
    $isProject = $model instanceof \Botble\RealEstate\Models\Project;
@endphp

@if ($isProject)
    <!-- Grouped Zolo-Style Specs List -->
    <div class="row mb-4 style-zolo-overview" style="font-size: 0.95rem; line-height: 2.0; font-family: inherit;">
        <div class="col-md-6 col-12">
            @if ($model->categories->isNotEmpty())
                <div class="mb-2"><strong>{{ __('Type') }}:</strong> 
                    @foreach ($model->categories as $category)
                        <a href="{{ $category->url }}" class="text-dark">{!! BaseHelper::clean($category->name) !!}</a>@if (!$loop->last),&nbsp;@endif
                    @endforeach
                </div>
            @endif
            @if ($model->number_floor)
                <div class="mb-2"><strong>{{ __('Total Floors') }}:</strong> {{ number_format($model->number_floor) }}</div>
            @endif
            @if ($model->number_flat)
                <div class="mb-2"><strong>{{ __('Total Flats') }}:</strong> {{ number_format($model->number_flat) }}</div>
            @endif
            @if ($model->suites_starting_floor)
                <div class="mb-2"><strong>{{ __('Suites Starting Floor') }}:</strong> {{ $model->suites_starting_floor }}</div>
            @endif
            @if ($model->number_of_suites_per_floor)
                <div class="mb-2"><strong>{{ __('Suites per Floor') }}:</strong> {{ $model->number_of_suites_per_floor }}</div>
            @endif
            @if ($model->suite_size_from || $model->suite_size_to)
                <div class="mb-2"><strong>{{ __('Suite Size') }}:</strong> 
                    @if($model->suite_size_from && $model->suite_size_to)
                        {{ $model->suite_size_from }} - {{ $model->suite_size_to }} {{ setting('real_estate_square_unit', 'sqft') }}
                    @elseif($model->suite_size_from)
                        {{ __('From :size', ['size' => $model->suite_size_from]) }} {{ setting('real_estate_square_unit', 'sqft') }}
                    @else
                        {{ __('Up to :size', ['size' => $model->suite_size_to]) }} {{ setting('real_estate_square_unit', 'sqft') }}
                    @endif
                </div>
            @endif
            @if ($model->price_per_sqft_from)
                <div class="mb-2"><strong>{{ __('Price per Sqft') }}:</strong> {{ __('From :price', ['price' => format_price($model->price_per_sqft_from)]) }}</div>
            @endif
            @if ($model->est_maint)
                <div class="mb-2"><strong>{{ __('Est. Maintenance') }}:</strong> {{ is_numeric($model->est_maint) ? format_price($model->est_maint) . '/sqft' : $model->est_maint }}</div>
            @endif
            @if ($model->est_property_tax)
                <div class="mb-2"><strong>{{ __('Est. Property Tax') }}:</strong> {{ is_numeric($model->est_property_tax) ? $model->est_property_tax . '%' : $model->est_property_tax }}</div>
            @endif
            @if ($model->parking_price)
                <div class="mb-2"><strong>{{ __('Parking Price') }}:</strong> {{ is_numeric($model->parking_price) ? format_price($model->parking_price) : $model->parking_price }}</div>
            @endif
        </div>
        
        <div class="col-md-6 col-12">
            @if (($model->investor->name ?? null))
                <div class="mb-2"><strong>{{ __('Developer') }}:</strong> {{ $model->investor->name }}</div>
            @endif
            @if ($model->architects)
                <div class="mb-2"><strong>{{ __('Architects') }}:</strong> {{ $model->architects }}</div>
            @endif
            @if ($model->intersection)
                <div class="mb-2"><strong>{{ __('Intersection') }}:</strong> {{ $model->intersection }}</div>
            @endif
            @if ($model->neighbour)
                <div class="mb-2"><strong>{{ __('Neighbourhood') }}:</strong> {{ $model->neighbour }}</div>
            @endif
            @if ($model->unique_id)
                <div class="mb-2"><strong>{{ __('Project ID') }}:</strong> {{ $model->unique_id }}</div>
            @endif
            @if ($model->assignment_policy)
                <div class="mb-2"><strong>{{ __('Assignment Policy') }}:</strong> {{ $model->assignment_policy }}</div>
            @endif
            @if ($model->total_min_deposit)
                <div class="mb-2"><strong>{{ __('Total Min. Deposit') }}:</strong> {{ is_numeric($model->total_min_deposit) ? ($model->total_min_deposit <= 1 ? ($model->total_min_deposit * 100) . '%' : $model->total_min_deposit . '%') : $model->total_min_deposit }}</div>
            @endif
            @if ($model->development_levies)
                <div class="mb-2"><strong>{{ __('Development Levies') }}:</strong> {{ $model->development_levies }}</div>
            @endif
            @if ($model->date_finish)
                <div class="mb-2"><strong>{{ __('Finish Date') }}:</strong> {{ $model->date_finish->format('M Y') }}</div>
            @endif
        </div>
    </div>

    @if ($model->deposit_notes || $model->maintenance_notes)
        <div class="row mb-4 style-zolo-notes" style="font-size: 0.95rem; line-height: 1.6; border-top: 1px solid #eaeaea; pt-3;">
            @if ($model->deposit_notes)
                <div class="col-md-6 col-12 mt-3">
                    <strong class="text-dark d-block mb-1">{{ __('Deposit Structure & Notes') }}:</strong>
                    <div class="text-variant-1">{!! BaseHelper::clean(nl2br($model->deposit_notes)) !!}</div>
                </div>
            @endif
            @if ($model->maintenance_notes)
                <div class="col-md-6 col-12 mt-3">
                    <strong class="text-dark d-block mb-1">{{ __('Maintenance Fees Notes') }}:</strong>
                    <div class="text-variant-1">{!! BaseHelper::clean(nl2br($model->maintenance_notes)) !!}</div>
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
@endif

@if ($model->content || (($model->can_see_private_notes ?? false) && ($model->private_notes ?? null)))
    <div @class(['single-property-desc', $class ?? null])>
        @if($model->content)
            <h4 class="h5 fw-bold mb-3 mt-4 text-dark">{{ __('About this home') }}</h4>
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
