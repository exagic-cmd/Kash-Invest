@php
    $model = $model ?? $property ?? null;
    $isProject = $model instanceof \Botble\RealEstate\Models\Project;
@endphp

@if ($model->features->isNotEmpty())
    @if ($isProject)
        <div class="box-project-card">
            <h3 class="h5 fw-bold text-dark project-section-title">{{ $model->name ? $model->name . ' - ' : '' }}{{ __('Amenities') }}</h3>
            <hr class="project-section-divider">
            <div class="box-feature">
                <ul class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3 list-unstyled mb-0">
                    @foreach ($model->features as $feature)
                        <li class="col d-flex align-items-center gap-2 feature-item" style="font-size: 0.95rem; color: #212529;">
                            <span class="text-secondary d-flex align-items-center flex-shrink-0" style="color: #6c757d;">
                                <x-core::icon name="ti ti-circle-check" style="width: 20px; height: 20px;" />
                            </span>
                            <span>{{ $feature->name }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @else
        <div @class(['single-property-feature', $class ?? null])>
            <div class="h7 title fw-7">{{ __('Amenities and features') }}</div>
            <div class="box-feature">
                <ul class="list-unstyled d-flex flex-wrap gap-3 mb-0">
                    @foreach ($model->features as $feature)
                        <li class="feature-item d-flex align-items-center gap-2" style="font-size: 0.95rem;">
                            <span class="text-secondary d-flex align-items-center flex-shrink-0" style="color: #6c757d;">
                                <x-core::icon name="ti ti-circle-check" style="width: 18px; height: 18px;" />
                            </span>
                            <span>{{ $feature->name }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
@endif
