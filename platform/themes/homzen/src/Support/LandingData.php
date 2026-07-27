<?php

namespace Theme\Homzen\Support;

use Botble\Media\Facades\RvMedia;
use Botble\RealEstate\Models\Project;
use Botble\RealEstate\Models\ProjectLandingPage;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Builds the single data object that every landing template renders from.
 *
 * The templates NEVER touch the Project model directly — they only read the
 * array returned here. That keeps one data contract for both themes and means
 * assigning a template to a project (phase 2) is just: pick a project, pick a
 * theme, render. Nothing in the markup is project-specific.
 *
 * Every value is derived from the real project. Fields the model doesn't carry
 * yet (tagline, brochure, structured floor plans) fall back to something sane
 * and are marked below so phase 2 knows where to plug real sources in.
 */
class LandingData
{
    /**
     * Brokerage copy shown when the editor hasn't overridden it. Kept here (not
     * only in the admin form) so the sections render on a project that has just
     * been assigned and never edited. ProjectLandingPageForm seeds the same
     * values, so the editor and the live page always agree.
     */
    public const DEFAULT_WHY_US_HEADING = 'Why Kash Invest';

    public const DEFAULT_WHY_US_POINTS = [
        'High Return on Investment (ROI)',
        'Premium Locations',
        'Secure & Transparent',
        'Expert Guidance',
        'End-to-End Management',
    ];

    public const DEFAULT_INNER_CIRCLE_HEADING = 'JOIN OUR INNER CIRCLE TO GET FIRST ACCESS';

    public const DEFAULT_INNER_CIRCLE_LABELS = ['Prices', 'Floor Plans', 'Incentives', 'Worksheet'];

    public const DEFAULT_INNER_CIRCLE_BUTTON_TEXT = 'Join Now';

    public const DEFAULT_INNER_CIRCLE_BUTTON_LINK = '#register';

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  ProjectLandingPage|null  $landingPage  the campaign page to render;
     *                                                null falls back to the project's default page
     */
    public static function fromProject(Project $project, ?ProjectLandingPage $landingPage = null): array
    {
        $project->loadMissing(['investor', 'features', 'facilities', 'customFields', 'city', 'state', 'country', 'categories', 'landingPage']);

        $custom = $project->customFields->pluck('value', 'name');
        $images = static::images($project);

        $data = [
            'id' => $project->getKey(),
            'name' => $project->name,
            // TODO(phase 2): give projects a real tagline field / custom field.
            'tagline' => static::tagline($project, $custom),
            'status' => $custom->get('Sales Status') ?: ($project->status?->label() ?: null),
            'constructionStatus' => $custom->get('Construction Status'),
            'url' => $project->url ?? null,
            'website' => $custom->get('Website'),

            'developer' => [
                'name' => $project->investor?->exists ? $project->investor->name : null,
                'blurb' => $project->investor?->exists ? $project->investor->description : null,
                'logo' => $project->investor?->exists && $project->investor->avatar
                    ? RvMedia::getImageUrl($project->investor->avatar, 'thumb', false, null)
                    : null,
            ],

            'hero' => [
                'image' => Arr::first($images),
                'video' => $project->getMetaData('video_url', true) ?: null,
            ],

            'price' => [
                'from' => $project->price_from,
                'fromFormatted' => $project->price_from ? $project->price_from_formatted : null,
                'range' => $project->formatted_price ?: null,
                'perSqft' => $project->price_per_sqft_from
                    ? '$' . number_format((float) $project->price_per_sqft_from) . ' / sq ft'
                    : null,
            ],

            'quickFacts' => static::quickFacts($project, $custom),
            'incentives' => static::incentives($project),
            'deposit' => static::deposit($project),

            'overview' => [
                'heading' => $project->name ? 'About ' . $project->name : null,
                'description' => $project->description ?: null,
                'content' => $project->content ?: null,
            ],

            'amenities' => $project->features->pluck('name')->filter()->values()->all(),
            'floorPlans' => static::floorPlans($project, $custom),
            'gallery' => $images,
            'location' => static::location($project),
            'legal' => static::legal($project),

            'cta' => [
                'primary' => 'Register for VIP Access',
                // TODO(phase 2): real brochure asset per project.
                'secondary' => 'Download Brochure',
            ],
        ];

        return static::applyOverrides($data, $project, $landingPage);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function applyOverrides(array $data, Project $project, ?ProjectLandingPage $landingPage = null): array
    {
        // An explicit page wins (one project can have several campaign pages);
        // otherwise fall back to the project's default page.
        $landingPage ??= $project->relationLoaded('landingPage')
            ? $project->landingPage
            : $project->landingPage()->first();

        $content = $landingPage && is_array($landingPage->content) ? $landingPage->content : [];

        // Per-section visibility (honoured by views/landing/base.blade.php).
        $data['show'] = [
            'overview' => (bool) Arr::get($content, 'overview.show', true),
            'quickFacts' => (bool) Arr::get($content, 'quick_facts.show', true),
            'cheatSheet' => (bool) Arr::get($content, 'cheat_sheet.show', true),
            'location' => (bool) Arr::get($content, 'location.show', true),
            'whyUs' => (bool) Arr::get($content, 'why_us.show', true),
            // Inner Circle is off unless explicitly turned on in the editor.
            'innerCircle' => (bool) Arr::get($content, 'inner_circle.show', false),
            'floorPlans' => (bool) Arr::get($content, 'floor_plans.show', true),
            'gallery' => (bool) Arr::get($content, 'gallery.show', true),
            'register' => (bool) Arr::get($content, 'register.show', true),
            'disclaimer' => (bool) Arr::get($content, 'disclaimer.show', true),
        ];

        // New keys the templates read — seed sane defaults so partials never
        // reference a missing index, whether or not overrides exist.
        $data['hero']['logos'] = $data['developer']['logo'] ? [$data['developer']['logo']] : [];
        $data['hero']['heading'] = null;
        $data['hero']['slides'] = null;
        $data['hero']['priceText'] = null;
        $data['overview']['customHeading'] = null;
        $data['cheatSheet'] = ['steps' => [], 'hints' => []];
        $data['register'] = ['heading' => null, 'lede' => null];
        // "Why Us" and "Inner Circle" are brokerage copy, not project data, so
        // they carry render-time defaults. Without these a freshly-assigned
        // project dropped Inner Circle entirely and showed an empty Why Us band.
        // The editor seeds the same values, so editor and live page agree.
        $data['whyUs'] = [
            'heading' => static::DEFAULT_WHY_US_HEADING,
            'points' => static::DEFAULT_WHY_US_POINTS,
            'image' => null,
        ];
        $data['innerCircle'] = [
            'heading' => static::DEFAULT_INNER_CIRCLE_HEADING,
            'items' => array_map(fn ($label) => ['icon' => null, 'label' => $label], static::DEFAULT_INNER_CIRCLE_LABELS),
            'buttonText' => static::DEFAULT_INNER_CIRCLE_BUTTON_TEXT,
            'buttonLink' => static::DEFAULT_INNER_CIRCLE_BUTTON_LINK,
        ];
        $data['disclaimer'] = ['logo' => null, 'text' => null, 'copyright' => null];
        $data['floorPlans'] = array_map(fn ($plan) => $plan + ['image' => null], $data['floorPlans']);

        if ($content === []) {
            return $data;
        }

        // ---- Hero -----------------------------------------------------------
        $logos = static::imageUrls(Arr::get($content, 'hero.logos', []));
        if ($logos) {
            $data['hero']['logos'] = $logos;
        }
        $data['hero']['heading'] = static::getText($content, 'hero.heading') ?: null;
        $data['tagline'] = static::getText($content, 'hero.tagline') ?: $data['tagline'];
        $data['status'] = static::getText($content, 'hero.badge') ?: $data['status'];
        $data['developer']['name'] = static::getText($content, 'hero.developer') ?: $data['developer']['name'];
        $data['hero']['priceText'] = static::getText($content, 'hero.price') ?: null;
        $data['cta']['secondary'] = static::getText($content, 'hero.cta') ?: $data['cta']['secondary'];
        $banner = static::imageUrls(Arr::get($content, 'hero.banner', []));
        $data['hero']['slides'] = $banner ?: null;
        $data['hero']['video'] = static::getText($content, 'hero.video') ?: $data['hero']['video'];

        // ---- Overview -------------------------------------------------------
        // Keep the derived `heading` as the fallback; a custom heading only wins
        // when the editor sets one (the partial preserves the original order).
        $data['overview']['customHeading'] = static::getText($content, 'overview.heading') ?: null;
        if ($body = static::getText($content, 'overview.body')) {
            $data['overview']['description'] = $body;
            $data['overview']['content'] = null; // avoid the duplicate-paragraph guard
        }
        // (No overview image — the section is text-only.)

        // ---- Quick facts ----------------------------------------------------
        if ($facts = static::repeaterRows(Arr::get($content, 'quick_facts.items', []), ['label', 'text'])) {
            $data['quickFacts'] = array_map(fn ($row) => ['label' => $row['label'], 'value' => $row['text']], $facts);
        }

        // ---- Cheat sheet ----------------------------------------------------
        $steps = static::repeaterRows(Arr::get($content, 'cheat_sheet.steps', []), ['step']);
        $hints = static::repeaterRows(Arr::get($content, 'cheat_sheet.hints', []), ['hint']);
        $data['cheatSheet'] = [
            'steps' => array_values(array_filter(array_column($steps, 'step'))),
            'hints' => array_values(array_filter(array_column($hints, 'hint'))),
        ];



        // ---- Floor plans ----------------------------------------------------
        if ($plans = static::repeaterRows(Arr::get($content, 'floor_plans.items', []), ['type', 'size', 'baths', 'price', 'image'])) {
            $data['floorPlans'] = array_map(fn ($row) => [
                'type' => $row['type'],
                'size' => $row['size'] ?: null,
                'baths' => $row['baths'] ?: null,
                'price' => $row['price'] ?: null,
                'image' => $row['image'] ? static::imageUrl($row['image']) : null,
            ], $plans);
        }

        // ---- Gallery --------------------------------------------------------
        if ($gallery = static::imageUrls(Arr::get($content, 'gallery.images', []))) {
            $data['gallery'] = $gallery;
            $data['hero']['image'] = $gallery[0] ?? $data['hero']['image'];
        }

        // ---- Location -------------------------------------------------------
        $data['location']['address'] = static::getText($content, 'location.address') ?: $data['location']['address'];
        $data['location']['intersection'] = static::getText($content, 'location.intersection') ?: $data['location']['intersection'];
        $data['location']['neighbourhood'] = static::getText($content, 'location.neighbourhood') ?: $data['location']['neighbourhood'];
        $data['location']['lat'] = static::getText($content, 'location.lat') ?: $data['location']['lat'];
        $data['location']['lng'] = static::getText($content, 'location.lng') ?: $data['location']['lng'];
        if ($nearby = static::repeaterRows(Arr::get($content, 'location.nearby', []), ['name', 'distance'])) {
            $data['location']['nearby'] = array_map(fn ($row) => ['name' => $row['name'], 'distance' => $row['distance'] ?: null], $nearby);
        }
        if ($benefits = static::repeaterRows(Arr::get($content, 'location.benefits', []), ['point'])) {
            $data['location']['benefits'] = array_map(fn ($row) => ['point' => $row['point']], $benefits);
        }

        // ---- Why Us ---------------------------------------------------------
        $data['whyUs']['heading'] = static::getText($content, 'why_us.heading') ?: $data['whyUs']['heading'];
        $data['whyUs']['image'] = static::imageUrl(Arr::get($content, 'why_us.image')) ?: $data['whyUs']['image'];
        if ($points = static::repeaterRows(Arr::get($content, 'why_us.points', []), ['point'])) {
            $data['whyUs']['points'] = array_values(array_filter(array_column($points, 'point')));
        }

        // ---- Inner Circle ---------------------------------------------------
        // Non-editable: content is always the fixed defaults set above; only the
        // section's show/hide toggle (handled in $data['show']) is honoured.

        // ---- Register -------------------------------------------------------
        $data['register'] = [
            'heading' => static::getText($content, 'register.heading') ?: null,
            'lede' => static::getText($content, 'register.lede') ?: null,
        ];

        // ---- Legal ----------------------------------------------------------
        $data['legal']['renderings'] = static::getText($content, 'legal.renderings') ?: $data['legal']['renderings'];
        $data['legal']['brokerage'] = static::getText($content, 'legal.brokerage') ?: $data['legal']['brokerage'];
        $data['legal']['pricing'] = static::getText($content, 'legal.pricing') ?: $data['legal']['pricing'];

        // ---- Disclaimer Card ------------------------------------------------
        $data['disclaimer']['logo'] = static::imageUrl(Arr::get($content, 'disclaimer.logo')) ?: null;
        $data['disclaimer']['text'] = static::getText($content, 'disclaimer.text') ?: null;
        $data['disclaimer']['copyright'] = static::getText($content, 'disclaimer.copyright') ?: null;

        return $data;
    }

    protected static function imageUrl(string|array|null $path): ?string
    {
        if (is_array($path)) {
            $path = \Illuminate\Support\Arr::first($path);
        }

        return $path && is_string($path) ? RvMedia::getImageUrl($path, null, false, RvMedia::getDefaultImage()) : null;
    }

    /**
     * @param  mixed  $paths
     * @return array<int, string>
     */
    protected static function imageUrls($paths): array
    {
        return collect((array) $paths)
            ->filter()
            ->map(fn ($path) => static::imageUrl($path))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Unwrap Botble repeater rows ([i => [key => ['value' => x]]]) into a plain
     * list of [key => value] rows, dropping rows that are entirely empty.
     *
     * @param  mixed  $rows
     * @param  array<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    protected static function repeaterRows($rows, array $keys): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)
            ->map(function ($row) use ($keys) {
                $out = [];
                foreach ($keys as $key) {
                    $val = is_array($row) ? (Arr::get($row, "$key.value") ?? Arr::get($row, $key)) : null;
                    
                    while (is_array($val)) {
                        $val = \Illuminate\Support\Arr::first($val);
                    }
                    
                    $out[$key] = $val;
                }

                return $out;
            })
            ->filter(fn ($row) => collect($row)->filter(fn ($v) => filled($v))->isNotEmpty())
            ->values()
            ->all();
    }

    /**
     * Get a text value from the given array, safely extracting it if it's erroneously wrapped in an array.
     */
    protected static function getText(array $content, string $key, mixed $default = null): mixed
    {
        $val = \Illuminate\Support\Arr::get($content, $key, $default);
        
        while (is_array($val)) {
            $val = \Illuminate\Support\Arr::first($val);
        }
        
        return $val;
    }

    /**
     * Absolute, render-ready image URLs from the project's media.
     *
     * @return array<int, string>
     */
    protected static function images(Project $project): array
    {
        return collect($project->images ?: [])
            ->filter()
            ->map(fn ($image) => RvMedia::getImageUrl($image, null, false, RvMedia::getDefaultImage()))
            ->values()
            ->all();
    }

    protected static function tagline(Project $project, $custom): ?string
    {
        if ($tagline = $custom->get('Tagline')) {
            return $tagline;
        }

        // Derive something honest from real fields rather than inventing copy.
        $where = $project->neighbour ?: ($project->city?->exists ? $project->city->name : null);

        return $where ? 'A new preconstruction address in ' . $where : null;
    }

    /**
     * The quick-facts strip. Only facts the project actually has are returned,
     * so the strip never renders an empty cell.
     *
     * @return array<int, array{label: string, value: string}>
     */
    protected static function quickFacts(Project $project, $custom): array
    {
        $beds = $custom->get('Bedrooms');

        $facts = [
            'Developer' => $project->investor?->exists ? $project->investor->name : null,
            'Price from' => $project->price_from ? $project->price_from_formatted : null,
            'Occupancy' => $custom->get('Estimated Completion')
                ?: ($project->date_finish?->format('F Y')),
            // stored as a fraction (0.21 == 21%)
            'Deposit' => $project->total_min_deposit
                ? static::trimNumber((float) $project->total_min_deposit * 100) . '%'
                : null,
            'Suite types' => $beds ? static::bedsLabel($beds) : null,
            'Suite sizes' => static::sizeRange($project),
            'Storeys' => $project->number_floor ?: null,
            'Suites' => $project->number_flat ?: null,
            'Neighbourhood' => $project->neighbour ?: null,
            'Intersection' => $project->intersection ?: null,
            'Ceiling heights' => $custom->get('Ceiling Heights'),
        ];

        return collect($facts)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value, $label) => ['label' => $label, 'value' => (string) $value])
            ->values()
            ->all();
    }

    protected static function bedsLabel(string $beds): string
    {
        // "1 - 3" -> "1 - 3 Bedroom"; "Studio - 2" is already descriptive.
        return Str::contains(strtolower($beds), ['studio', 'bed'])
            ? $beds
            : $beds . ' Bedroom';
    }

    protected static function sizeRange(Project $project): ?string
    {
        $from = $project->suite_size_from ? (int) $project->suite_size_from : null;
        $to = $project->suite_size_to ? (int) $project->suite_size_to : null;

        return match (true) {
            $from && $to && $from !== $to => number_format($from) . ' - ' . number_format($to) . ' sq ft',
            (bool) ($from ?: $to) => number_format($from ?: $to) . ' sq ft',
            default => null,
        };
    }

    /**
     * Buyer-facing incentives pulled from the real cost fields. This is the
     * trust content — it's only shown when the project actually has the data.
     *
     * @return array<int, string>
     */
    protected static function incentives(Project $project): array
    {
        return collect([
            $project->total_min_deposit
                ? static::trimNumber((float) $project->total_min_deposit * 100) . '% total deposit'
                : null,
            $project->development_levies ? 'Development levies: ' . $project->development_levies : null,
            $project->assignment_policy ? 'Assignment: ' . $project->assignment_policy : null,
            $project->parking_price ? 'Parking from $' . number_format((float) $project->parking_price) : null,
            $project->locker_price ? 'Locker from $' . number_format((float) $project->locker_price) : null,
        ])->filter()->values()->all();
    }

    /**
     * The money section — spine of the dark theme's signature "Cost Ladder".
     *
     * Two important facts about the real data, both verified against the DB:
     *  - `total_min_deposit` is stored as a FRACTION (0.21 == 21%), while
     *    `est_property_tax` is already a percent (1.0 == 1%). They must not be
     *    formatted the same way.
     *  - `deposit_notes` separates milestones with <br/>, not commas.
     *
     * Only 10 of 171 projects carry a deposit schedule (all Excel-sourced; the
     * Buildify feed has none), but 143 carry other cost data. So the ladder
     * falls back to the carrying/add-on costs — the money buyers actually get
     * blindsided by — and therefore still renders for the bulk of the catalog.
     *
     * @return array<string, mixed>
     */
    protected static function deposit(Project $project): array
    {
        $notes = trim((string) $project->deposit_notes);
        $schedule = static::parseSchedule($notes);

        return [
            // fraction -> percent
            'total' => $project->total_min_deposit
                ? static::trimNumber((float) $project->total_min_deposit * 100) . '%'
                : null,
            'notes' => $notes ? trim(strip_tags(str_ireplace(['<br/>', '<br>', '<br />'], ' · ', $notes))) : null,
            'schedule' => $schedule,
            'ladder' => static::ladder($project, $schedule),
            'maintenance' => $project->est_maint
                ? '$' . static::trimNumber((float) $project->est_maint) . ' / sq ft / month'
                : null,
            // already a percent, unlike total_min_deposit
            'propertyTax' => $project->est_property_tax
                ? static::trimNumber((float) $project->est_property_tax) . '%'
                : null,
        ];
    }

    /**
     * Split `deposit_notes` into milestones. Real values look like:
     *   "5% on Signing<br/>5% in 180 Days<br/>1% at Occupancy"
     *   "$10,000 on Signing<br/>Balance to 5% in 30 Days"
     *
     * @return array<int, array{amount: ?string, label: string}>
     */
    protected static function parseSchedule(string $notes): array
    {
        if ($notes === '' || ! Str::contains($notes, ['%', '$'])) {
            return [];
        }

        $parts = preg_split('/\s*(?:<br\s*\/?>|[\r\n]+|;)\s*/i', $notes) ?: [];
        $schedule = [];

        foreach ($parts as $part) {
            $part = trim(strip_tags($part));
            if ($part === '') {
                continue;
            }

            // Lead with the figure ("5%", "$10,000") and keep the rest as the label.
            preg_match('/^(\$[\d.,]+|[\d.,]+\s*%)\s*(.*)$/i', $part, $m);

            $schedule[] = [
                'amount' => isset($m[1]) ? trim($m[1]) : null,
                'label' => isset($m[2]) && trim($m[2]) !== '' ? trim($m[2]) : $part,
            ];
        }

        return $schedule;
    }

    /**
     * The rungs the signature renders.
     *
     * mode=deposit  -> what you pay and when (the ideal, when we have it)
     * mode=carrying -> what it costs beyond the sticker price (the fallback,
     *                  and the thing preconstruction buyers most underestimate)
     *
     * @param  array<int, array{amount: ?string, label: string}>  $schedule
     * @return array{mode: string, rungs: array<int, array{amount: ?string, label: string}>}
     */
    protected static function ladder(Project $project, array $schedule): array
    {
        if ($schedule) {
            return ['mode' => 'deposit', 'rungs' => $schedule];
        }

        $rungs = [];

        if ($project->parking_price) {
            $rungs[] = ['amount' => '$' . number_format((float) $project->parking_price), 'label' => 'Parking space, one-time'];
        }
        if ($project->locker_price) {
            $rungs[] = ['amount' => '$' . number_format((float) $project->locker_price), 'label' => 'Storage locker, one-time'];
        }
        if ($project->est_maint) {
            $rungs[] = ['amount' => '$' . static::trimNumber((float) $project->est_maint), 'label' => 'Maintenance, per sq ft each month'];
        }
        if ($project->parking_maint) {
            $rungs[] = ['amount' => '$' . static::trimNumber((float) $project->parking_maint), 'label' => 'Parking maintenance, each month'];
        }
        if ($project->locker_maint) {
            $rungs[] = ['amount' => '$' . static::trimNumber((float) $project->locker_maint), 'label' => 'Locker maintenance, each month'];
        }
        if ($project->est_property_tax) {
            $rungs[] = ['amount' => static::trimNumber((float) $project->est_property_tax) . '%', 'label' => 'Estimated property tax, annual'];
        }
        if ($project->development_levies) {
            $rungs[] = ['amount' => null, 'label' => 'Development levies — ' . Str::limit($project->development_levies, 140)];
        }

        return ['mode' => 'carrying', 'rungs' => $rungs];
    }

    /** 0.60 -> "0.6", 60.00 -> "60", 21.0 -> "21" */
    protected static function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ','), '0'), '.');
    }

    /**
     * Unit types derived from the project's real bedroom range + suite sizes.
     *
     * TODO(phase 2): the `floor_plans` column exists but currently only holds a
     * count, so there is no per-unit-type source yet. When real floor plans land,
     * map them here — the template already renders whatever this returns.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function floorPlans(Project $project, $custom): array
    {
        $beds = trim((string) $custom->get('Bedrooms'));

        if ($beds === '') {
            return [];
        }

        // "Studio - 3" / "1 - 3" / "2"
        $parts = array_map('trim', preg_split('/\s*-\s*/', $beds));
        $labels = [];

        $toIndex = fn (string $v) => strtolower($v) === 'studio' ? 0 : (is_numeric($v) ? (int) $v : null);

        $start = $toIndex($parts[0] ?? '');
        $end = $toIndex($parts[1] ?? $parts[0] ?? '');

        if ($start === null || $end === null || $end < $start) {
            return [];
        }

        for ($i = $start; $i <= $end; $i++) {
            $labels[] = $i === 0 ? 'Studio' : $i . ' Bedroom';
        }

        $sizeFrom = $project->suite_size_from ? (int) $project->suite_size_from : null;
        $sizeTo = $project->suite_size_to ? (int) $project->suite_size_to : null;
        $steps = max(count($labels) - 1, 1);

        return collect($labels)->map(function (string $label, int $i) use ($sizeFrom, $sizeTo, $steps, $project, $custom) {
            // Spread the project's real size range across its real unit types.
            $size = null;
            if ($sizeFrom && $sizeTo && $sizeTo > $sizeFrom) {
                $lo = (int) round($sizeFrom + ($sizeTo - $sizeFrom) * ($i / ($steps + 1)));
                $hi = (int) round($sizeFrom + ($sizeTo - $sizeFrom) * (($i + 1) / ($steps + 1)));
                $size = number_format($lo) . ' - ' . number_format($hi) . ' sq ft';
            } elseif ($sizeFrom) {
                $size = 'from ' . number_format($sizeFrom) . ' sq ft';
            }

            return [
                'type' => $label,
                'size' => $size,
                'baths' => $custom->get('Bathrooms'),
                // Price is only truthful at the project level, so only the
                // entry unit type carries the "from" price.
                'price' => $i === 0 && $project->price_from ? $project->price_from_formatted : null,
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected static function location(Project $project): array
    {
        $city = $project->city?->exists ? $project->city->name : null;
        $state = $project->state?->exists ? $project->state->name : null;

        return [
            'address' => $project->location ?: null,
            'intersection' => $project->intersection ?: null,
            'neighbourhood' => $project->neighbour ?: null,
            'city' => $city,
            'state' => $state,
            'shortAddress' => trim(implode(', ', array_filter([$city, $state]))) ?: null,
            'lat' => $project->latitude ?: null,
            'lng' => $project->longitude ?: null,
            // Facilities carry a real distance on the pivot — this is genuine
            // walkability/transit data, not invented copy.
            'nearby' => $project->facilities->map(fn ($facility) => [
                'name' => $facility->name,
                'distance' => $facility->pivot->distance ?? null,
            ])->filter(fn ($item) => filled($item['name']))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function legal(Project $project): array
    {
        $developer = $project->investor?->exists ? $project->investor->name : 'the developer';

        return [
            'renderings' => 'Renderings are artist\'s concept only. Specifications, sizes, features and finishes are subject to change without notice. E. & O.E.',
            'brokerage' => sprintf(
                'Kash Invest Realty is a third-party brokerage and does not represent %s. Brokers protected. All brand names, logos, images and text are the copyright of their respective owners.',
                $developer
            ),
            'pricing' => 'Prices, deposit structures and incentives are subject to change or withdrawal without notice and are correct at time of publication.',
        ];
    }
}
