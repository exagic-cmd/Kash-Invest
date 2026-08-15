@php
    use Botble\Location\Models\City;
    use Botble\Location\Models\State;
    use Botble\RealEstate\Models\Project;

    // Detect search input
    $rawSearch = request('location') ?: request('k') ?: request('keyword') ?: request('city');
    $currentCity = null;

    if (isset($city) && $city instanceof City) {
        $currentCity = $city;
    } elseif ($cityId = request('city_id')) {
        $currentCity = City::query()->find($cityId);
    } elseif ($rawSearch) {
        $cleanSearch = trim(explode(',', $rawSearch)[0]);
        $currentCity = City::query()
            ->where(function($q) use ($cleanSearch) {
                $q->where('name', 'LIKE', $cleanSearch)
                  ->orWhere('slug', \Illuminate\Support\Str::slug($cleanSearch))
                  ->orWhere('name', 'LIKE', '%' . $cleanSearch . '%');
            })
            ->first();
    }

    // Dynamic State detection from current city or from projects in DB
    $currentState = null;
    if ($currentCity && $currentCity->state_id) {
        $currentState = State::query()->find($currentCity->state_id);
    }

    if (!$currentState) {
        $stateIdFromProjects = Project::query()->whereNotNull('state_id')->value('state_id');
        if ($stateIdFromProjects) {
            $currentState = State::query()->find($stateIdFromProjects);
        }
    }

    $stateName = $currentState ? $currentState->name : '';
    $stateSlug = $currentState ? $currentState->slug : \Illuminate\Support\Str::slug($stateName);

    $cityName = $currentCity ? $currentCity->name : ($rawSearch ? ucwords(trim(explode(',', $rawSearch)[0])) : null);
    $citySlug = $cityName ? ($currentCity ? $currentCity->slug : \Illuminate\Support\Str::slug($cityName)) : null;

    $projectsUrl = RealEstateHelper::getProjectsListPageUrl();

    // Base query scoped to city or state for counting
    $baseProjectQuery = Project::query();
    if ($currentCity) {
        $baseProjectQuery->where('city_id', $currentCity->id);
    } elseif ($currentState) {
        $baseProjectQuery->where('state_id', $currentState->id);
    }

    // Build only popular searches that have actual matching projects (> 0)
    $popularSearchLinks = [];

    if ($stateName && $stateSlug) {
        $stateProjectsCount = Project::query()->where('state_id', $currentState?->id)->count();
        if ($stateProjectsCount > 0) {
            $popularSearchLinks[] = [
                'label' => __(':state Real Estate', ['state' => $stateName]),
                'url' => url($stateSlug . '-real-estate'),
            ];
        }
    }

    if ($cityName && $citySlug) {
        // Open Houses
        $openHousesCount = (clone $baseProjectQuery)->where('status', 'selling')->count();
        if ($openHousesCount > 0) {
            $popularSearchLinks[] = [
                'label' => __(':city Open Houses', ['city' => $cityName]),
                'url' => url($citySlug . '-real-estate') . '?sort_by=date_desc',
            ];
        }

        // Condos
        $condosCount = (clone $baseProjectQuery)->where(fn($q) => $q->whereHas('categories', fn($c) => $c->where('name', 'LIKE', '%condo%'))->orWhere('name', 'LIKE', '%condo%'))->count();
        if ($condosCount > 0) {
            $popularSearchLinks[] = [
                'label' => __(':city Condos', ['city' => $cityName]),
                'url' => url($citySlug . '-real-estate/condos'),
            ];
        }

        // Townhouses
        $townhousesCount = (clone $baseProjectQuery)->where(fn($q) => $q->whereHas('categories', fn($c) => $c->where('name', 'LIKE', '%town%'))->orWhere('name', 'LIKE', '%town%'))->count();
        if ($townhousesCount > 0) {
            $popularSearchLinks[] = [
                'label' => __(':city Townhouses', ['city' => $cityName]),
                'url' => url($citySlug . '-real-estate/townhouses'),
            ];
        }

        // Houses
        $housesCount = (clone $baseProjectQuery)->where(fn($q) => $q->whereHas('categories', fn($c) => $c->where('name', 'LIKE', '%house%'))->orWhere('name', 'LIKE', '%house%')->orWhere('name', 'LIKE', '%home%'))->count();
        if ($housesCount > 0) {
            $popularSearchLinks[] = [
                'label' => __(':city Houses', ['city' => $cityName]),
                'url' => url($citySlug . '-real-estate/houses'),
            ];
        }

        // New Developments
        $newDevCount = (clone $baseProjectQuery)->count();
        if ($newDevCount > 0) {
            $popularSearchLinks[] = [
                'label' => __(':city New Developments', ['city' => $cityName]),
                'url' => url($citySlug . '-real-estate') . '?sort_by=date_desc',
            ];
        }

        // Under 750k
        $under750kCount = (clone $baseProjectQuery)->where(fn($q) => $q->where(fn($s) => $s->where('price_to', '<=', 750000)->where('price_to', '>', 0))->orWhere(fn($s) => $s->where('price_from', '<=', 750000)->where('price_from', '>', 0)))->count();
        if ($under750kCount > 0) {
            $popularSearchLinks[] = [
                'label' => __(':city Projects Under $750K', ['city' => $cityName]),
                'url' => url($citySlug . '-real-estate') . '?max_price=750000',
            ];
        }

        // Luxury 1M+
        $luxuryCount = (clone $baseProjectQuery)->where(fn($q) => $q->where('price_from', '>=', 1000000)->orWhere('price_to', '>=', 1000000))->count();
        if ($luxuryCount > 0) {
            $popularSearchLinks[] = [
                'label' => __(':city Luxury Projects $1M+', ['city' => $cityName]),
                'url' => url($citySlug . '-real-estate') . '?min_price=1000000',
            ];
        }

        // 1+ Bed
        $oneBedCount = (clone $baseProjectQuery)->where('number_flat', '>=', 1)->count();
        if ($oneBedCount > 0) {
            $popularSearchLinks[] = [
                'label' => __(':city 1+ Bedroom Units', ['city' => $cityName]),
                'url' => url($citySlug . '-real-estate') . '?min_flat=1',
            ];
        }

        // 2+ Bed
        $twoBedCount = (clone $baseProjectQuery)->where('number_flat', '>=', 2)->count();
        if ($twoBedCount > 0) {
            $popularSearchLinks[] = [
                'label' => __(':city 2+ Bedroom Units', ['city' => $cityName]),
                'url' => url($citySlug . '-real-estate') . '?min_flat=2',
            ];
        }

        // 3+ Bed
        $threeBedCount = (clone $baseProjectQuery)->where('number_flat', '>=', 3)->count();
        if ($threeBedCount > 0) {
            $popularSearchLinks[] = [
                'label' => __(':city 3+ Bedroom Units', ['city' => $cityName]),
                'url' => url($citySlug . '-real-estate') . '?min_flat=3',
            ];
        }
    } else {
        // Landing page (/projects): show only categories/filters with projects
        $allCount = Project::query()->count();
        if ($allCount > 0) {
            $popularSearchLinks[] = [
                'label' => __('Open Houses & New Developments'),
                'url' => $projectsUrl . '?sort_by=date_desc',
            ];
        }

        $condosCount = Project::query()->where(fn($q) => $q->whereHas('categories', fn($c) => $c->where('name', 'LIKE', '%condo%'))->orWhere('name', 'LIKE', '%condo%'))->count();
        if ($condosCount > 0) {
            $popularSearchLinks[] = [
                'label' => __('Condos & Pre-Construction'),
                'url' => $projectsUrl . '?k=condo',
            ];
        }

        $townCount = Project::query()->where(fn($q) => $q->whereHas('categories', fn($c) => $c->where('name', 'LIKE', '%town%'))->orWhere('name', 'LIKE', '%town%'))->count();
        if ($townCount > 0) {
            $popularSearchLinks[] = [
                'label' => __('Townhouses & Townhomes'),
                'url' => $projectsUrl . '?k=townhouse',
            ];
        }

        $houseCount = Project::query()->where(fn($q) => $q->whereHas('categories', fn($c) => $c->where('name', 'LIKE', '%house%'))->orWhere('name', 'LIKE', '%house%')->orWhere('name', 'LIKE', '%home%'))->count();
        if ($houseCount > 0) {
            $popularSearchLinks[] = [
                'label' => __('Single Family Houses'),
                'url' => $projectsUrl . '?k=house',
            ];
        }

        $under750kCount = Project::query()->where(fn($q) => $q->where(fn($s) => $s->where('price_to', '<=', 750000)->where('price_to', '>', 0))->orWhere(fn($s) => $s->where('price_from', '<=', 750000)->where('price_from', '>', 0)))->count();
        if ($under750kCount > 0) {
            $popularSearchLinks[] = [
                'label' => __('Projects Under $750,000'),
                'url' => $projectsUrl . '?max_price=750000',
            ];
        }

        $luxuryCount = Project::query()->where(fn($q) => $q->where('price_from', '>=', 1000000)->orWhere('price_to', '>=', 1000000))->count();
        if ($luxuryCount > 0) {
            $popularSearchLinks[] = [
                'label' => __('Luxury Projects Over $1,000,000'),
                'url' => $projectsUrl . '?min_price=1000000',
            ];
        }

        $oneBedCount = Project::query()->where('number_flat', '>=', 1)->count();
        if ($oneBedCount > 0) {
            $popularSearchLinks[] = [
                'label' => __('1+ Bedroom Suites'),
                'url' => $projectsUrl . '?min_flat=1',
            ];
        }

        $twoBedCount = Project::query()->where('number_flat', '>=', 2)->count();
        if ($twoBedCount > 0) {
            $popularSearchLinks[] = [
                'label' => __('2+ Bedroom Suites'),
                'url' => $projectsUrl . '?min_flat=2',
            ];
        }

        $threeBedCount = Project::query()->where('number_flat', '>=', 3)->count();
        if ($threeBedCount > 0) {
            $popularSearchLinks[] = [
                'label' => __('3+ Bedroom Suites'),
                'url' => $projectsUrl . '?min_flat=3',
            ];
        }
    }

    // Active cities that actually have projects in database
    $citiesWithProjects = City::query()
        ->whereIn('id', function($sub) {
            $sub->select('city_id')->from('re_projects')->whereNotNull('city_id');
        })
        ->get();

    // Group cities into Nearby and Popular dynamically
    $nearbyCities = collect();
    $popularCities = collect();

    if ($currentCity) {
        // Nearby: Same state, excluding current city
        $nearbyCities = $citiesWithProjects
            ->filter(fn($c) => $c->id != $currentCity->id && (!$currentCity->state_id || $c->state_id == $currentCity->state_id))
            ->take(10);

        $nearbyCityIds = $nearbyCities->pluck('id')->all();

        // Popular: Other cities
        $popularCities = $citiesWithProjects
            ->filter(fn($c) => $c->id != $currentCity->id && !in_array($c->id, $nearbyCityIds))
            ->take(10);

        if ($popularCities->isEmpty()) {
            $popularCities = $nearbyCities->slice(5, 5);
            $nearbyCities = $nearbyCities->slice(0, 5);
        }
    } else {
        // Landing page (/projects): Split active cities between column 3 & 4
        $nearbyCities = $citiesWithProjects->slice(0, 10);
        $popularCities = $citiesWithProjects->slice(10, 10);
    }

    // Dynamic Neighborhoods with active project count > 0
    $neighborhoodQuery = Project::query()
        ->whereNotNull('neighbour')
        ->where('neighbour', '!=', '');

    if ($currentCity) {
        $neighborhoodQuery->where('city_id', $currentCity->id);
    }

    $neighborhoods = $neighborhoodQuery
        ->selectRaw('neighbour, count(*) as count')
        ->groupBy('neighbour')
        ->having('count', '>', 0)
        ->orderByDesc('count')
        ->limit(10)
        ->pluck('neighbour')
        ->all();

    // If current city has no specific neighbourhoods in DB, fallback to platform top neighbourhoods
    if (empty($neighborhoods)) {
        $neighborhoods = Project::query()
            ->whereNotNull('neighbour')
            ->where('neighbour', '!=', '')
            ->selectRaw('neighbour, count(*) as count')
            ->groupBy('neighbour')
            ->having('count', '>', 0)
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('neighbour')
            ->all();
    }
@endphp

<div class="popular-searches-section mt-5 pt-4 pb-5 border-top" style="border-color: #eaedf1 !important;">
    <div class="container-fluid px-0">
        <div class="row g-4">
            {{-- Column 1: Popular Searches (Zero-result links filtered out) --}}
            <div class="col-12 col-sm-6 col-lg-3">
                <h5 class="fw-bold text-dark mb-3" style="font-size: 1.05rem; letter-spacing: -0.2px;">
                    {{ __('Popular Searches') }}
                </h5>
                <ul class="list-unstyled mb-0 d-flex flex-column gap-2" style="font-size: 0.9rem;">
                    @forelse ($popularSearchLinks as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="text-muted text-hover-primary text-decoration-none d-inline-block py-1">
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @empty
                        <li class="text-muted">{{ __('No searches available') }}</li>
                    @endforelse
                </ul>
            </div>

            {{-- Column 2: Neighborhoods (Only active with projects) --}}
            <div class="col-12 col-sm-6 col-lg-3">
                <h5 class="fw-bold text-dark mb-3" style="font-size: 1.05rem; letter-spacing: -0.2px;">
                    {{ __('Neighborhoods') }}
                </h5>
                <ul class="list-unstyled mb-0 d-flex flex-column gap-2" style="font-size: 0.9rem;">
                    @forelse ($neighborhoods as $neighborhood)
                        @php
                            $targetUrl = ($cityName && $citySlug)
                                ? url($citySlug . '-real-estate') . '?k=' . urlencode($neighborhood)
                                : $projectsUrl . '?k=' . urlencode($neighborhood);
                        @endphp
                        <li>
                            <a href="{{ $targetUrl }}" class="text-muted text-hover-primary text-decoration-none d-inline-block py-1 text-truncate" style="max-width: 100%;" title="{{ $neighborhood }} Projects For Sale">
                                {{ __(':name Projects For Sale', ['name' => $neighborhood]) }}
                            </a>
                        </li>
                    @empty
                        <li class="text-muted">{{ __('No neighborhoods available') }}</li>
                    @endforelse
                </ul>
            </div>

            {{-- Column 3: Nearby Cities (Only with projects) --}}
            <div class="col-12 col-sm-6 col-lg-3">
                <h5 class="fw-bold text-dark mb-3" style="font-size: 1.05rem; letter-spacing: -0.2px;">
                    {{ __('Nearby Cities') }}
                </h5>
                <ul class="list-unstyled mb-0 d-flex flex-column gap-2" style="font-size: 0.9rem;">
                    @forelse ($nearbyCities as $nearbyCity)
                        <li>
                            <a href="{{ url($nearbyCity->slug . '-real-estate') }}" class="text-muted text-hover-primary text-decoration-none d-inline-block py-1">
                                {{ __(':city Projects For Sale', ['city' => $nearbyCity->name]) }}
                            </a>
                        </li>
                    @empty
                        <li class="text-muted">{{ __('No nearby cities available') }}</li>
                    @endforelse
                </ul>
            </div>

            {{-- Column 4: Popular Cities (Only with projects) --}}
            <div class="col-12 col-sm-6 col-lg-3">
                <h5 class="fw-bold text-dark mb-3" style="font-size: 1.05rem; letter-spacing: -0.2px;">
                    {{ __('Popular Cities') }}
                </h5>
                <ul class="list-unstyled mb-0 d-flex flex-column gap-2" style="font-size: 0.9rem;">
                    @forelse ($popularCities as $popularCity)
                        <li>
                            <a href="{{ url($popularCity->slug . '-real-estate') }}" class="text-muted text-hover-primary text-decoration-none d-inline-block py-1">
                                {{ __(':city Projects For Sale', ['city' => $popularCity->name]) }}
                            </a>
                        </li>
                    @empty
                        <li class="text-muted">{{ __('No popular cities available') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
    .popular-searches-section a {
        color: #555e68;
        transition: color 0.15s ease-in-out, transform 0.15s ease-in-out;
    }
    .popular-searches-section a:hover {
        color: var(--primary-color, #1565c0) !important;
        transform: translateX(2px);
    }
</style>
