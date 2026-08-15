@php
    $model = $model ?? $property ?? null;
    $isProject = $model instanceof \Botble\RealEstate\Models\Project;

    $cfCollection = $model->customFields ?? collect();
    $customFields = $cfCollection->keyBy(fn($item) => strtolower(trim($item->name)));

    $getCustom = function(...$keys) use ($customFields) {
        foreach ($keys as $key) {
            $lower = strtolower(trim($key));
            if ($customFields->has($lower)) {
                $field = $customFields->get($lower);
                if (filled($field->value)) {
                    return $field->value;
                }
            }
        }
        return null;
    };

    $faqs = [];

    // 1. Overview / What is this project?
    $type = $model->categories->isNotEmpty() ? $model->categories->pluck('name')->join(', ') : null;
    $location = $model->location ?: $model->short_address;
    $dev = $model->investor->name ?? null;

    if ($model->name) {
        $ans1 = sprintf(
            '%s is a %s development%s%s.',
            $model->name,
            $type ? strtolower($type) : 'real estate',
            $location ? ' located at ' . $location : '',
            $dev ? ', developed by ' . $dev : ''
        );
        $faqs[] = [
            'question' => __('What is :name?', ['name' => $model->name]),
            'answer' => $ans1,
        ];
    }

    // 2. Developer & Completion Date
    $completion = $model->date_finish ? $model->date_finish->format('F Y') : $getCustom('estimated completion', 'completion date', 'completion');
    if ($dev || $completion) {
        $ansDev = sprintf(
            '%s is developed by %s%s.',
            $model->name,
            $dev ?: 'the developer',
            $completion ? ' with an estimated completion date of ' . $completion : ''
        );
        $faqs[] = [
            'question' => __('Who is the developer of :name and when will it be completed?', ['name' => $model->name]),
            'answer' => $ansDev,
        ];
    }

    // 3. Units & Floors
    $floors = $model->number_floor ? number_format($model->number_floor) : null;
    $units = $model->number_flat ? number_format($model->number_flat) : $getCustom('total number of units', 'total units', 'number of units');
    if ($floors || $units) {
        $parts = [];
        if ($floors) $parts[] = $floors . ' ' . __('storeys');
        if ($units) $parts[] = $units . ' ' . __('total units');
        $faqs[] = [
            'question' => __('How many storeys and units will :name have?', ['name' => $model->name]),
            'answer' => sprintf('%s will feature %s offering a variety of living spaces.', $model->name, implode(' and ', $parts)),
        ];
    }

    // 4. Suite Sizes & Pricing
    $sizeFrom = $model->suite_size_from;
    $sizeTo = $model->suite_size_to;
    $priceFrom = $model->price_from_formatted ?: ($model->price_per_sqft_from ? format_price($model->price_per_sqft_from) . '/sqft' : null);
    if ($sizeFrom || $sizeTo || $priceFrom) {
        $sizeText = '';
        if ($sizeFrom && $sizeTo) {
            $sizeText = sprintf('range from %s to %s %s', $sizeFrom, $sizeTo, setting('real_estate_square_unit', 'sqft'));
        } elseif ($sizeFrom) {
            $sizeText = sprintf('start from %s %s', $sizeFrom, setting('real_estate_square_unit', 'sqft'));
        } elseif ($sizeTo) {
            $sizeText = sprintf('range up to %s %s', $sizeTo, setting('real_estate_square_unit', 'sqft'));
        }
        $ansPrice = sprintf(
            'Suites at %s %s%s.',
            $model->name,
            $sizeText ?: 'feature versatile floor plans',
            $priceFrom ? ', with prices starting from ' . $priceFrom : ''
        );
        $faqs[] = [
            'question' => __('What are the suite sizes and starting prices at :name?', ['name' => $model->name]),
            'answer' => $ansPrice,
        ];
    }

    // 5. Parking & Lockers
    $parking = $model->parking_price ? (is_numeric($model->parking_price) ? format_price($model->parking_price) : $model->parking_price) : null;
    $locker = $model->locker_price ? (is_numeric($model->locker_price) ? format_price($model->locker_price) : $model->locker_price) : null;
    if ($parking || $locker) {
        $pkParts = [];
        if ($parking) {
            $pkParts[] = 'parking is available starting at ' . $parking . ($model->parking_maint ? ' (maintenance ' . (is_numeric($model->parking_maint) ? format_price($model->parking_maint) . '/mo' : $model->parking_maint) . ')' : '');
        }
        if ($locker) {
            $pkParts[] = 'locker storage is available starting at ' . $locker . ($model->locker_maint ? ' (maintenance ' . (is_numeric($model->locker_maint) ? format_price($model->locker_maint) . '/mo' : $model->locker_maint) . ')' : '');
        }
        $faqs[] = [
            'question' => __('Is parking and locker storage available at :name?', ['name' => $model->name]),
            'answer' => sprintf('Yes, at %s, %s.', $model->name, implode(' and ', $pkParts)),
        ];
    }

    // 6. Deposit Structure & Maintenance Fees
    $deposit = $model->total_min_deposit ? (is_numeric($model->total_min_deposit) ? ($model->total_min_deposit <= 1 ? ($model->total_min_deposit * 100) . '%' : $model->total_min_deposit . '%') : $model->total_min_deposit) : null;
    $estMaint = $model->est_maint ? (is_numeric($model->est_maint) ? format_price($model->est_maint) . '/sqft' : $model->est_maint) : null;
    if ($deposit || $estMaint || $model->deposit_notes) {
        $depAnswer = [];
        if ($deposit) {
            $depAnswer[] = 'The total minimum deposit is ' . $deposit . '.';
        }
        if ($model->deposit_notes) {
            $depAnswer[] = $model->deposit_notes;
        }
        if ($estMaint) {
            $depAnswer[] = 'Estimated maintenance fee is ' . $estMaint . '.';
        }
        $faqs[] = [
            'question' => __('What is the deposit structure and estimated maintenance fee for :name?', ['name' => $model->name]),
            'answer' => implode(' ', $depAnswer),
        ];
    }

    // 7. Amenities
    if ($model->features->isNotEmpty()) {
        $featureNames = $model->features->pluck('name')->slice(0, 8)->join(', ');
        $faqs[] = [
            'question' => __('What amenities and features are offered at :name?', ['name' => $model->name]),
            'answer' => sprintf('%s offers a wide array of amenities including %s%s.', $model->name, $featureNames, $model->features->count() > 8 ? ', and more' : ''),
        ];
    }
@endphp

@if (!empty($faqs))
    <div class="box-project-card bg-white rounded-3 p-4 mb-4 border">
        <h3 class="h5 fw-bold text-dark project-section-title">
            {{ __('FAQs About :name', ['name' => $model->name . ($model->city->name ? ', ' . $model->city->name : '')]) }}
        </h3>
        <hr class="project-section-divider">

        <div class="accordion accordion-flush" id="projectFaqAccordion">
            @foreach ($faqs as $index => $faq)
                <div class="accordion-item border-bottom py-1" style="border-color: #f1f3f5 !important;">
                    <h2 class="accordion-header" id="faqHeading{{ $index }}">
                        <button class="accordion-button collapsed px-0 py-3 bg-transparent text-dark fw-semibold d-flex justify-content-between align-items-center shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse{{ $index }}" aria-expanded="false" aria-controls="faqCollapse{{ $index }}" style="font-size: 1rem;">
                            <span>{{ $faq['question'] }}</span>
                        </button>
                    </h2>
                    <div id="faqCollapse{{ $index }}" class="accordion-collapse collapse" aria-labelledby="faqHeading{{ $index }}" data-bs-parent="#projectFaqAccordion">
                        <div class="accordion-body px-0 pt-0 pb-3 text-muted" style="font-size: 0.95rem; line-height: 1.7; color: #495057 !important;">
                            {!! BaseHelper::clean($faq['answer']) !!}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <style>
        #projectFaqAccordion .accordion-button {
            box-shadow: none !important;
            color: #212529;
        }
        #projectFaqAccordion .accordion-button:not(.collapsed) {
            color: var(--primary-color, #1565c0);
        }
        #projectFaqAccordion .accordion-button::after {
            font-family: inherit;
            content: "+";
            font-size: 1.35rem;
            font-weight: 500;
            line-height: 1;
            background-image: none !important;
            transform: none !important;
            transition: color 0.2s ease;
            color: #212529;
            margin-left: auto;
        }
        #projectFaqAccordion .accordion-button:not(.collapsed)::after {
            content: "−";
            font-size: 1.35rem;
            color: var(--primary-color, #1565c0);
        }
    </style>
@endif
