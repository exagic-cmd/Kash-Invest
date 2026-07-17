<?php

namespace Theme\Homzen\Support;

use Botble\Media\Facades\RvMedia;
use Botble\RealEstate\Models\Project;
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
     * @return array<string, mixed>
     */
    public static function fromProject(Project $project): array
    {
        $project->loadMissing(['investor', 'features', 'facilities', 'customFields', 'city', 'state', 'country', 'categories']);

        $custom = $project->customFields->pluck('value', 'name');
        $images = static::images($project);

        return [
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
