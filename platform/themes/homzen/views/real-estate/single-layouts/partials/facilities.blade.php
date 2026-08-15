@php
    $model = $model ?? $property ?? null;
    $isProject = $model instanceof \Botble\RealEstate\Models\Project;
@endphp

@if ($model->facilities->isNotEmpty())
    @if ($isProject)
        <div class="box-project-card">
            <h3 class="h5 fw-bold text-dark project-section-title">{{ $model->name ? $model->name . ' - ' : '' }}{{ __('What\'s Nearby') }}</h3>
            <hr class="project-section-divider">
            <p class="body-2 text-muted mb-3" style="font-size: 0.875rem;">{{ __("Explore nearby amenities to precisely locate your property and identify surrounding conveniences.") }}</p>
            <ul class="row row-cols-1 row-cols-md-2 g-3 list-unstyled mb-0 box-nearby">
                @foreach ($model->facilities as $facility)
                    <li class="col item-nearby d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #f1f3f5 !important;">
                        <span class="label d-flex align-items-center gap-2 text-muted" style="font-size: 0.9rem;">
                            <span class="text-secondary d-flex align-items-center">
                                @if($facility->icon)
                                    {!! BaseHelper::renderIcon($facility->icon) !!}
                                @else
                                    <x-core::icon name="ti ti-map-pin" style="width: 18px; height: 18px;" />
                                @endif
                            </span>
                            {{ $facility->name }}:
                        </span>
                        <span class="fw-bold text-dark" style="font-size: 0.9rem;">{{ $facility->pivot->distance }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <div @class(['single-property-nearby', $class ?? null])>
            <div class="h7 title fw-7">{{ __('What\'s nearby?') }}</div>
            <p class="body-2">{{ __("Explore nearby amenities to precisely locate your property and identify surrounding conveniences, providing a comprehensive overview of the living environment and the property's convenience.") }}</p>
            <ul class="grid-3 box-nearby">
                @foreach ($model->facilities as $facility)
                    <li class="item-nearby">
                        <span class="label">
                            @if($facility->icon)
                                {!! BaseHelper::renderIcon($facility->icon) !!}
                            @else
                                {!! BaseHelper::renderIcon('ti ti-square-dot') !!}
                            @endif
                            {{ $facility->name }}:
                        </span>
                        <span class="fw-7">{{ $facility->pivot->distance }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endif
