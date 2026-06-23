    Theme::set('headerClass', '');

    $propertiesUrl = '#';
    $categories = [];
    $defaultCategoryId = '';
    $defaultCategoryName = __('Category');

    if (is_plugin_active('real-estate')) {
        $propertiesUrl = RealEstateHelper::getPropertiesListPageUrl();
        $categories = \Botble\RealEstate\Models\Category::query()->wherePublished()->pluck('name', 'id')->all();
        
        // Find the 'House' category dynamically
        $houseCategory = \Botble\RealEstate\Models\Category::query()
            ->where('name', 'like', '%House%')
            ->first();
        if ($houseCategory) {
            $defaultCategoryId = $houseCategory->id;
            $defaultCategoryName = $houseCategory->name;
        }
    }

    $selectedTabs = explode(',', $shortcode->tabs ?: 'project,rent,sale');
    $defaultSearchType = $shortcode->default_search_type ?: 'project';
    if ($defaultSearchType === 'project' && ! in_array('project', $selectedTabs)) {
        $defaultSearchType = count($selectedTabs) ? $selectedTabs[0] : 'sale';
    }

    $tabLabels = [
        'project' => __('Project'),
        'rent' => __('For Rent'),
        'sale' => __('For Sale'),
    ];
@endphp

<section class="flat-map hero-banner-4" style="background-color: #f7f7f7; padding: 60px 0;">
    @if(is_plugin_active('real-estate') && $shortcode->search_box_enabled)
        <div class="container">
            <div class="wrap-filter-search">
                @include(Theme::getThemeNamespace('views.real-estate.partials.search-box'), ['style' => 4, 'centeredTabs' => true])
            </div>

            {{-- Interactive Dropdown Filter Pills --}}
            <div class="quick-filters-row d-flex justify-content-between align-items-center mt-3 gap-2 flex-wrap">
                <div class="d-flex align-items-center gap-2 overflow-x-auto no-scrollbar py-1 flex-grow-1">
                    {{-- Type Filter --}}
                    @if (count($selectedTabs) > 1)
                        <div class="dropdown">
                            <button class="quick-filter-pill dropdown-toggle" type="button" id="filterTypeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                {{ $tabLabels[$defaultSearchType] ?? __('Type') }}
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="filterTypeDropdown">
                                @foreach ($selectedTabs as $tabKey)
                                    @if (isset($tabLabels[$tabKey]))
                                        <li><a class="dropdown-item filter-type-option" data-value="{{ $tabKey }}" href="javascript:void(0);">{{ $tabLabels[$tabKey] }}</a></li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Price Filter --}}
                    <div class="dropdown">
                        <button class="quick-filter-pill dropdown-toggle" type="button" id="filterPriceDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                            {{ __('Any Price') }}
                        </button>
                        <div class="dropdown-menu p-3" style="width: 250px;" aria-labelledby="filterPriceDropdown">
                            <h6 class="mb-2 fw-6">{{ __('Price Range') }}</h6>
                            <div class="d-flex gap-2 mb-3">
                                <input type="number" id="pill_min_price" class="form-control form-control-sm" placeholder="{{ __('Min') }}">
                                <input type="number" id="pill_max_price" class="form-control form-control-sm" placeholder="{{ __('Max') }}">
                            </div>
                            <button type="button" class="tf-btn primary w-100 btn-apply-price size-1" style="padding: 6px 12px; font-size: 13px;">{{ __('Apply') }}</button>
                        </div>
                    </div>

                    {{-- Bedrooms Filter --}}
                    <div class="dropdown">
                        <button class="quick-filter-pill dropdown-toggle" type="button" id="filterBedroomsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ __('0+ Bed') }}
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="filterBedroomsDropdown">
                            <li><a class="dropdown-item filter-bedroom-option" data-value="" href="javascript:void(0);">{{ __('Any Bedrooms') }}</a></li>
                            @foreach(range(1, 5) as $i)
                                <li>
                                    <a class="dropdown-item filter-bedroom-option" data-value="{{ $i }}" href="javascript:void(0);">
                                        {{ $i === 5 ? __(':number+ Bed', ['number' => $i]) : __(':number Bed', ['number' => $i]) }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Category Filter --}}
                    <div class="dropdown">
                        <button class="quick-filter-pill dropdown-toggle" type="button" id="filterCategoryDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ $defaultCategoryName }}
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="filterCategoryDropdown" style="max-height: 250px; overflow-y: auto;">
                            <li><a class="dropdown-item filter-category-option" data-value="" href="javascript:void(0);">{{ __('All Categories') }}</a></li>
                            @foreach($categories as $id => $name)
                                <li><a class="dropdown-item filter-category-option" data-value="{{ $id }}" href="javascript:void(0);">{{ $name }}</a></li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Save Search --}}
                    <button class="quick-filter-pill btn-save-search text-black border bg-white" @if(!auth('account')->check()) data-bs-toggle="modal" data-bs-target="#modalLogin" @endif>
                        <span>{{ __('Save Search') }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="#0d6efd" viewBox="0 0 24 24" class="ms-1" style="color: #0d6efd;"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                    </button>
                </div>
                
                <div class="d-flex align-items-center gap-2">
                    {{-- Sort Filter --}}
                    <div class="dropdown">
                        <button class="quick-filter-pill dropdown-toggle" type="button" id="filterSortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ __('Sort: Recommended') }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterSortDropdown">
                            @if(is_plugin_active('real-estate'))
                                @foreach (RealEstateHelper::getSortByList() as $key => $sortBy)
                                    <li><a class="dropdown-item filter-sort-option" data-value="{{ $key }}" href="javascript:void(0);">{{ $sortBy }}</a></li>
                                @endforeach
                            @endif
                        </ul>
                    </div>

                    {{-- Map Search Button --}}
                    <button type="button" class="quick-filter-pill d-flex align-items-center gap-1 btn-map-search border bg-white">
                        <span>{{ __('Map Search') }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="#0d6efd" viewBox="0 0 24 24" style="color: #0d6efd;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                    </button>
                </div>
            </div>
        </div>
    @endif
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var $ = window.jQuery;
        if (!$) return;

        function setFormValue(name, value) {
            var form = $('#hero-search-form');
            if (!form.length) return;
            var input = form.find('[name="' + name + '"]');
            if (input.length) {
                input.val(value).trigger('change');
            } else {
                form.append('<input type="hidden" name="' + name + '" value="' + value + '">');
            }
        }

        // Set default category on load
        var defaultCatId = '{{ $defaultCategoryId }}';
        if (defaultCatId) {
            setFormValue('category_id', defaultCatId);
        }
        
        // Set default type from shortcode configuration
        setFormValue('type', '{{ $defaultSearchType }}');

        // Type Filter Selection
        $('.filter-type-option').on('click', function(e) {
            e.preventDefault();
            var val = $(this).data('value');
            var text = $(this).text();
            setFormValue('type', val);
            $('#filterTypeDropdown').text(text);
            
            // Adjust search form action based on type selection
            var form = $('#hero-search-form');
            if (val === 'project') {
                form.attr('action', '{{ is_plugin_active("real-estate") ? RealEstateHelper::getProjectsListPageUrl() : "#" }}');
            } else {
                form.attr('action', '{{ is_plugin_active("real-estate") ? RealEstateHelper::getPropertiesListPageUrl() : "#" }}');
            }
        });

        // Bedroom Filter Selection
        $('.filter-bedroom-option').on('click', function(e) {
            e.preventDefault();
            var val = $(this).data('value');
            var text = $(this).text();
            setFormValue('bedroom', val);
            $('#filterBedroomsDropdown').text(val ? val + '+ Bed' : '{{ __("0+ Bed") }}');
        });

        // Category Filter Selection
        $('.filter-category-option').on('click', function(e) {
            e.preventDefault();
            var val = $(this).data('value');
            var text = $(this).text();
            setFormValue('category_id', val);
            $('#filterCategoryDropdown').text(val ? text : '{{ __("All Categories") }}');
        });

        // Price Filter Apply
        $('.btn-apply-price').on('click', function(e) {
            e.preventDefault();
            var min = $('#pill_min_price').val();
            var max = $('#pill_max_price').val();
            
            setFormValue('min_price', min);
            setFormValue('max_price', max);
            
            var label = '{{ __("Any Price") }}';
            if (min && max) {
                label = '$' + min + ' - $' + max;
            } else if (min) {
                label = '>= $' + min;
            } else if (max) {
                label = '<= $' + max;
            }
            $('#filterPriceDropdown').text(label);
            
            var dropdownElement = document.getElementById('filterPriceDropdown');
            if (dropdownElement) {
                var bsDropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                if (bsDropdown) bsDropdown.hide();
            }
        });

        // Sort Filter Selection
        $('.filter-sort-option').on('click', function(e) {
            e.preventDefault();
            var val = $(this).data('value');
            var text = $(this).text();
            setFormValue('sort_by', val);
            $('#filterSortDropdown').text(text);
        });

        // Map Search Action
        $('.btn-map-search').on('click', function(e) {
            e.preventDefault();
            setFormValue('layout', 'top-map');
            $('#hero-search-form').submit();
        });
    });
</script>

<style>
    .quick-filters-row {
        width: 100%;
    }
    .quick-filter-pill {
        background-color: #ffffff;
        border: 1px solid #e4e4e4;
        border-radius: 8px;
        padding: 6px 14px;
        color: #161e2d !important;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
        transition: all 0.2s ease;
        cursor: pointer;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }
    .quick-filter-pill:hover {
        background-color: #f7f7f7;
        border-color: #d1d1d1;
        color: #161e2d !important;
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .quick-filters-row .dropdown-menu {
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        border: 1px solid #eee;
        padding: 6px 0;
    }
    .quick-filters-row .dropdown-item {
        font-size: 13px;
        font-weight: 500;
        padding: 8px 16px;
    }
    .quick-filters-row .dropdown-item:hover {
        background-color: #f7f7f7;
    }
</style>
