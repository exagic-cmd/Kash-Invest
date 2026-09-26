@php
    $model = $model ?? $property ?? null;
    $isProject = $model instanceof \Botble\RealEstate\Models\Project;

    if ($isProject) {
        return;
    }
@endphp

@if (($model->formatted_floor_plans ?? collect())->isNotEmpty())
    <div @class([$isProject ? 'box-project-card' : 'single-property-floor', $class ?? null])>
        @if ($isProject)
            <h3 class="h5 fw-bold text-dark project-section-title">{{ $model->name ? $model->name . ' - ' : '' }}{{ __('Floor Plans') }}</h3>
            <hr class="project-section-divider">
        @else
            <div class="h7 title fw-7">{{ __('Floor plans') }}</div>
        @endif

        <div class="table-responsive mt-3">
            <table class="table table-bordered floor-plans-table">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Bedrooms') }}</th>
                        <th>{{ __('Bathrooms') }}</th>
                        <th class="text-center">{{ __('View') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($model->formatted_floor_plans as $floorPlan)
                        <tr>
                            <td class="fw-6">{!! BaseHelper::clean($floorPlan['name']) !!}</td>
                            <td>
                                @if ($floorPlan['bedrooms'])
                                    <span class="d-flex align-items-center gap-8">
                                        <x-core::icon name="ti ti-bed" />
                                        {{ $floorPlan['bedrooms'] }}
                                    </span>
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td>
                                @if ($floorPlan['bathrooms'])
                                    <span class="d-flex align-items-center gap-8">
                                        <x-core::icon name="ti ti-bath" />
                                        {{ $floorPlan['bathrooms'] }}
                                    </span>
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($floorPlan['image'])
                                    <a href="{{ RvMedia::getImageUrl($floorPlan['image']) }}"
                                       data-fancybox="floor-plan-{{ $model->slug }}"
                                       data-caption="{!! BaseHelper::clean($floorPlan['name']) !!}{{ $floorPlan['description'] ? ' — ' . BaseHelper::clean($floorPlan['description']) : '' }}"
                                       class="floor-plan-view-btn"
                                       title="{{ __('View floor plan') }}"
                                       style="display:none;">
                                    </a>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary floor-plan-eye-btn"
                                            data-index="{{ $loop->index }}"
                                            data-group="floor-plan-{{ $model->slug }}"
                                            title="{{ __('View floor plan') }}">
                                        <i class="ti ti-eye"></i>
                                    </button>
                                @else
                                    <span class="text-muted" title="{{ __('No image available') }}">
                                        <i class="ti ti-eye-off"></i>
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.floor-plan-eye-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var group = btn.getAttribute('data-group');
                    var index = parseInt(btn.getAttribute('data-index'));
                    var links = document.querySelectorAll('a[data-fancybox="' + group + '"]');
                    if (links.length && links[index]) {
                        links[index].click();
                    }
                });
            });
        });
    </script>
@endif
