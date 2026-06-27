@php
    $model = $model ?? $property ?? null;
@endphp

<div @class(['single-property-overview', $class ?? null])>
    <div class="h7 title fw-7 mb-3">{{ __('Overview') }}</div>
    <div class="row row-cols-1 row-cols-md-2 g-x-4 g-y-2" style="font-size: 0.875rem;">
        <div class="col d-flex justify-content-between border-bottom pb-2 mb-2">
            <span class="fw-semibold text-muted">{{ $model instanceof \Botble\RealEstate\Models\Project ? __('Project ID:') : __('Property ID:') }}</span>
            <span class="fw-bold text-dark">{{ $model->unique_id ?: $model->getKey() }}</span>
        </div>
        @if ($model->categories->isNotEmpty())
            <div class="col d-flex justify-content-between border-bottom pb-2 mb-2">
                <span class="fw-semibold text-muted">{{ __('Type:') }}</span>
                <span class="fw-bold text-dark">
                    @foreach ($model->categories as $category)
                        <a href="{{ $category->url }}" class="text-dark">{!! BaseHelper::clean($category->name) !!}</a>@if (!$loop->last),&nbsp;@endif
                    @endforeach
                </span>
            </div>
        @endif
        @if (($model->investor->name ?? null))
            <div class="col d-flex justify-content-between border-bottom pb-2 mb-2">
                <span class="fw-semibold text-muted">{{ __('Investor:') }}</span>
                <span class="fw-bold text-dark">{{ $model->investor->name }}</span>
            </div>
        @endif
        @if (($model->number_block ?? null))
            <div class="col d-flex justify-content-between border-bottom pb-2 mb-2">
                <span class="fw-semibold text-muted">{{ __('Blocks:') }}</span>
                <span class="fw-bold text-dark">{{ number_format($model->number_block) }}</span>
            </div>
        @endif
        @if (($model->number_flat ?? null))
            <div class="col d-flex justify-content-between border-bottom pb-2 mb-2">
                <span class="fw-semibold text-muted">{{ __('Flats:') }}</span>
                <span class="fw-bold text-dark">{{ number_format($model->number_flat) }}</span>
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
        @if (($model->date_finish ?? null))
            <div class="col d-flex justify-content-between border-bottom pb-2 mb-2">
                <span class="fw-semibold text-muted">{{ __('Finish Date:') }}</span>
                <span class="fw-bold text-dark">{{ $model->date_finish->format('M d, Y') }}</span>
            </div>
        @endif
        @if (($model->date_sell ?? null))
            <div class="col d-flex justify-content-between border-bottom pb-2 mb-2">
                <span class="fw-semibold text-muted">{{ __('Open Sell Date:') }}</span>
                <span class="fw-bold text-dark">{{ $model->date_sell->format('M d, Y') }}</span>
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

