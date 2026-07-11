<?php

namespace Botble\RealEstate\Services\Buildify;

use Botble\ACL\Models\User;
use Botble\Base\Events\CreatedContentEvent;
use Botble\Base\Facades\BaseHelper;
use Botble\Location\Models\City;
use Botble\Location\Models\Country;
use Botble\Location\Models\State;
use Botble\RealEstate\Enums\ProjectStatusEnum;
use Botble\RealEstate\Facades\RealEstateHelper;
use Botble\Media\Facades\RvMedia;
use Botble\Media\Models\MediaFile;
use Botble\RealEstate\Models\Feature;
use Botble\RealEstate\Models\Project;
use Botble\Slug\Facades\SlugHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

/**
 * Pulls projects from the Buildify API and mirrors them into re_projects.
 *
 * Ownership model (agreed with the client):
 *  - Every project this syncer creates is tagged with unique_id = "buildify-<id>".
 *  - On each run we match ONLY on that tag:
 *      * found     -> update it (Buildify is the source of truth for its own rows)
 *      * not found -> create it
 *  - Because Excel-imported and manually-added projects never carry the tag,
 *    they are invisible to this syncer and are never touched.
 */
class BuildifyProjectSyncer
{
    /** Value written to re_projects.source for every project this syncer owns. */
    public const SOURCE = 'buildify';

    protected int $created = 0;

    protected int $updated = 0;

    protected int $failed = 0;

    /** @var array<int, array{id: mixed, error: string}> */
    protected array $errors = [];

    protected ?int $defaultAuthorId = null;

    protected int|string|null $projectsFolderId = null;

    public function __construct(protected BuildifyClient $client)
    {
    }

    /**
     * Page through the whole Ontario catalog and upsert every listing.
     *
     * @param  callable|null  $onProgress  fn(int $page, int $pages, int $total): void
     * @return array{created: int, updated: int, failed: int, errors: array}
     */
    public function sync(?callable $onProgress = null): array
    {
        $perPage = (int) config('plugins.real-estate.buildify.per_page', 50);
        $page = 0;

        do {
            $response = $this->client->searchListings($page, $perPage);

            $results = Arr::get($response, 'results', []);
            $pages = max((int) Arr::get($response, 'pages', 1), 1);
            $total = (int) Arr::get($response, 'total', count($results));

            foreach ($results as $listing) {
                @set_time_limit(300);

                try {
                    $this->importListing($listing);
                } catch (\Throwable $e) {
                    $this->failed++;
                    $this->errors[] = [
                        'id' => Arr::get($listing, 'objectID') ?: Arr::get($listing, 'listingId'),
                        'error' => $e->getMessage(),
                    ];
                    report($e);
                }
            }

            if ($onProgress) {
                $onProgress($page + 1, $pages, $total);
            }

            $page++;
        } while ($page < $pages);

        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'failed' => $this->failed,
            'errors' => $this->errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    protected function importListing(array $listing): void
    {
        $externalId = Arr::get($listing, 'objectID') ?: Arr::get($listing, 'listingId');
        $name = trim((string) Arr::get($listing, 'name'));

        if (empty($externalId) || $name === '') {
            // Nothing we can safely key on / display — skip quietly.
            return;
        }

        // Match ONLY our own Buildify rows (source + raw id). Excel/manual
        // projects have a different source, so they're never matched or touched.
        $project = Project::query()
            ->where('source', self::SOURCE)
            ->where('unique_id', (string) $externalId)
            ->first();
        $isNew = ! $project;

        if ($isNew) {
            $project = new Project();
        }

        $data = $this->mapListing($listing, $name, (string) $externalId, $isNew ? null : $project);

        $this->resolveLocation($listing, $data);

        $project->forceFill($data);
        $project->save();

        // Amenities -> features
        $project->features()->sync($this->resolveFeatureIds($listing));

        // Preserve the rich extras that have no dedicated column as custom fields
        // so nothing Buildify sends is silently dropped.
        $project->customFields()->delete();
        foreach ($this->buildCustomFields($listing) as $field) {
            $project->customFields()->create($field);
        }

        // Video (Buildify "videos" is an array of URLs)
        $project->metadata()->where('meta_key', 'video_url')->delete();
        if ($video = Arr::get($listing, 'videos.0')) {
            $project->metadata()->create(['meta_key' => 'video_url', 'meta_value' => $video]);
        }

        // Fire the slug/creation event. Only allow a fresh slug for brand-new
        // projects — re-firing for existing ones would pile up duplicate slugs
        // (Botble's CreatedContentListener inserts with no dedup) and 404 the
        // detail page.
        $request = new Request();
        $request->merge([
            ...$data,
            'slug' => Str::slug($name),
            'is_slug_editable' => $isNew,
        ]);

        event(new CreatedContentEvent(PROJECT_MODULE_SCREEN_NAME, $request, $project));

        // Ensure translation record exists so the project is searchable in the admin panel
        if (\Illuminate\Support\Facades\Schema::hasTable('re_projects_translations')) {
            $langCode = is_plugin_active('language') ? \Botble\Language\Facades\Language::getDefaultLocaleCode() : 'en_US';
            $translationExists = \Illuminate\Support\Facades\DB::table('re_projects_translations')
                ->where('re_projects_id', $project->id)
                ->where('lang_code', $langCode)
                ->exists();

            if (!$translationExists) {
                \Illuminate\Support\Facades\DB::table('re_projects_translations')->insert([
                    're_projects_id' => $project->id,
                    'lang_code'      => $langCode,
                    'name'           => $project->name ?? '',
                    'description'    => $project->description ?? '',
                    'content'        => $project->content ?? '',
                    'location'       => $project->location ?? '',
                ]);
            }
        }

        $isNew ? $this->created++ : $this->updated++;
    }

    /**
     * Map a Buildify listing onto the re_projects columns.
     *
     * @param  array<string, mixed>  $listing
     * @return array<string, mixed>
     */
    protected function mapListing(array $listing, string $name, string $externalId, ?Project $existingProject = null): array
    {
        $summary = (string) Arr::get($listing, 'summary');

        $data = [
            'name' => Str::limit($name, 250, ''),
            'unique_id' => $externalId,
            'source' => self::SOURCE,
            'description' => $summary !== '' ? $this->fitToColumn($summary, 400) : null,
            'content' => $summary ?: null,
            'location' => $this->fitToColumn((string) Arr::get($listing, 'fullAddress'), 255) ?: null,
            'status' => $this->resolveStatus($listing),
            'is_featured' => (bool) Arr::get($listing, 'isFeatured', false),

            'price_from' => $this->num(Arr::get($listing, 'startPrice')),
            'price_to' => $this->num(Arr::get($listing, 'endPrice')),
            'price_per_sqft_from' => $this->num(Arr::get($listing, 'averagePricePerSqFeet')),

            'suite_size_from' => $this->num(Arr::get($listing, 'minSize')),
            'suite_size_to' => $this->num(Arr::get($listing, 'maxSize')),
            'number_floor' => $this->intOrNull(Arr::get($listing, 'numberOfFloors')),
            'number_flat' => $this->intOrNull(Arr::get($listing, 'numberOfUnits')),

            'parking_price' => $this->num(Arr::get($listing, 'parkingCost')),
            'locker_price' => $this->num(Arr::get($listing, 'storageCost')),
            'est_maint' => $this->num(Arr::get($listing, 'ccOrMaintFee')),
            'parking_maint' => $this->num(Arr::get($listing, 'parkingMaintainance')),
            'locker_maint' => $this->num(Arr::get($listing, 'lockerMaintainance')),
            'development_levies' => Str::limit((string) Arr::get($listing, 'developmentLevies'), 255, '') ?: null,

            'neighbour' => Str::limit((string) Arr::get($listing, 'neighbourhood.0'), 255, '') ?: null,
            'intersection' => trim(Arr::get($listing, 'streetNumber', '') . ' ' . Arr::get($listing, 'streetName', '')) ?: null,

            'date_finish' => $this->resolveCompletionDate($listing),
            'date_sell' => $this->normalizeDate(Arr::get($listing, 'salesStarted')),

            'latitude' => $this->coord(Arr::get($listing, '_geoloc.lat') ?? Arr::get($listing, 'lat')),
            'longitude' => $this->coord(Arr::get($listing, '_geoloc.lng') ?? Arr::get($listing, 'long')),

            'images' => $this->resolveImages($listing, $externalId, $existingProject),

            'author_id' => $this->defaultAuthorId(),
            'author_type' => User::class,
        ];

        if (RealEstateHelper::isEnabledZipCode()) {
            $data['zip_code'] = Str::limit((string) Arr::get($listing, 'postalCode'), 20, '') ?: null;
        }

        return $data;
    }

    /**
     * Buildify "constructionStatus" -> ProjectStatusEnum.
     * ("Pre Construction" is checked before "Construction" since it contains both.)
     */
    protected function resolveStatus(array $listing): string
    {
        $raw = strtolower(trim((string) Arr::get($listing, 'constructionStatus')));

        return match (true) {
            str_contains($raw, 'pre') => ProjectStatusEnum::PRE_SALE,
            str_contains($raw, 'construction') => ProjectStatusEnum::BUILDING,
            str_contains($raw, 'complete') => ProjectStatusEnum::SOLD,
            default => ProjectStatusEnum::SELLING,
        };
    }

    protected function resolveImages(array $listing, string $externalId, ?Project $existingProject = null): array
    {
        $urls = $this->collectImageUrls($listing);

        if (! config('plugins.real-estate.buildify.sync_images', true)) {
            return $urls;
        }

        if ($existingProject && $this->hasLocalImages($existingProject)) {
            return $existingProject->images ?? [];
        }

        $maxImages = max((int) config('plugins.real-estate.buildify.max_images_per_project', 5), 1);
        $urls = array_slice($urls, 0, $maxImages);

        $images = [];

        foreach ($urls as $index => $url) {
            if ($localPath = $this->syncImageFromUrl($url, $externalId, $index)) {
                $images[] = $localPath;
            }
        }

        return $images;
    }

    /**
     * @return array<int, string>
     */
    protected function collectImageUrls(array $listing): array
    {
        $urls = [];

        if ($cover = Arr::get($listing, 'coverPhoto.url')) {
            $urls[] = $cover;
        }

        foreach (Arr::get($listing, 'photos', []) as $photo) {
            if ($url = Arr::get($photo, 'url')) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    protected function hasLocalImages(Project $project): bool
    {
        $images = array_filter($project->images ?? []);

        if ($images === []) {
            return false;
        }

        foreach ($images as $image) {
            if (Str::contains($image, ['http://', 'https://'])) {
                return false;
            }
        }

        return true;
    }

    protected function getProjectsFolderId(): int|string
    {
        return $this->projectsFolderId ??= RvMedia::createFolder('projects');
    }

    /**
     * Download a remote Buildify image and store it in Botble's media library.
     * Uses a deterministic filename so repeat syncs skip already-imported files.
     */
    protected function syncImageFromUrl(string $imageUrl, string $externalId, int $index): ?string
    {
        $folderId = $this->getProjectsFolderId();

        $extension = strtolower(pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $extension = 'jpg';
        }

        $imageFileName = sprintf('buildify-%s-%d.%s', $externalId, $index, $extension);
        $imageBaseName = pathinfo($imageFileName, PATHINFO_FILENAME);

        $existingMedia = MediaFile::query()
            ->where('name', $imageBaseName)
            ->where('folder_id', $folderId)
            ->first();

        if ($existingMedia) {
            return $existingMedia->url;
        }

        try {
            $response = Http::connectTimeout(10)->timeout(20)->get($imageUrl);
        } catch (\Throwable) {
            return $imageUrl;
        }

        if (! $response->successful() || $response->body() === '') {
            return $imageUrl;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'buildify_');

        if ($tempPath === false) {
            return $imageUrl;
        }

        file_put_contents($tempPath, $response->body());

        $mimeTypes = (new MimeTypes())->getMimeTypes($extension);
        $mimeType = Arr::first($mimeTypes) ?: 'image/jpeg';

        $uploadedFile = new UploadedFile(
            $tempPath,
            $imageFileName,
            $mimeType,
            null,
            true
        );

        $uploadResult = RvMedia::handleUpload($uploadedFile, $folderId);

        @unlink($tempPath);

        if (! $uploadResult['error']) {
            return $uploadResult['data']->url;
        }

        return $imageUrl;
    }

    /**
     * @return array<int, int>
     */
    protected function resolveFeatureIds(array $listing): array
    {
        $ids = [];

        foreach (Arr::get($listing, 'amenities', []) as $amenity) {
            $featureName = Str::limit(trim((string) $amenity), 120, '');
            if ($featureName === '') {
                continue;
            }

            $feature = Feature::query()->firstOrCreate(['name' => $featureName]);
            if (SlugHelper::isSupportedModel(Feature::class) && $feature->wasRecentlyCreated) {
                SlugHelper::createSlug($feature);
            }

            $ids[] = $feature->getKey();
        }

        return array_values(array_unique($ids));
    }

    /**
     * Resolve/auto-create country, state and city and set the *_id columns.
     */
    protected function resolveLocation(array $listing, array &$data): void
    {
        if (! is_plugin_active('location')) {
            return;
        }

        $countryId = $stateId = $cityId = null;

        if ($countryName = trim((string) Arr::get($listing, 'country'))) {
            $country = Country::query()->firstOrCreate(['name' => Str::limit($countryName, 120, '')]);
            if (SlugHelper::isSupportedModel(Country::class) && $country->wasRecentlyCreated) {
                SlugHelper::createSlug($country);
            }
            $countryId = $country->id;
        }

        if ($stateName = trim((string) Arr::get($listing, 'state'))) {
            $state = State::query()->where('name', $stateName)->first();
            if (! $state) {
                $state = State::query()->create([
                    'name' => Str::limit($stateName, 120, ''),
                    'country_id' => $countryId,
                ]);
                if (SlugHelper::isSupportedModel(State::class)) {
                    SlugHelper::createSlug($state);
                }
            }
            $stateId = $state->id;
        }

        if ($cityName = trim((string) Arr::get($listing, 'cityOrDistrict'))) {
            $city = City::query()->firstOrCreate(
                ['name' => Str::limit($cityName, 120, '')],
                ['state_id' => $stateId, 'country_id' => $countryId]
            );
            if (SlugHelper::isSupportedModel(City::class) && $city->wasRecentlyCreated) {
                SlugHelper::createSlug($city);
            }
            $cityId = $city->id;
        }

        $data['country_id'] = $countryId;
        $data['state_id'] = $stateId;
        $data['city_id'] = $cityId;
    }

    /**
     * Useful data with no dedicated column -> custom fields (name/value),
     * so it's visible in admin and can be surfaced on the front end later.
     *
     * @return array<int, array{name: string, value: string}>
     */
    protected function buildCustomFields(array $listing): array
    {
        $fields = [
            $this->customField('Sales Status', Arr::get($listing, 'sellingStatus')),
            $this->customField('Construction Status', Arr::get($listing, 'constructionStatus')),
            $this->customField('Ceiling Heights', Arr::get($listing, 'ceilingHeights')),
            $this->customField('Number of Floor Plans', Arr::get($listing, 'numberOfFloorPlans')),
            $this->customField('Bedrooms', $this->range(Arr::get($listing, 'minBeds'), Arr::get($listing, 'maxBeds'))),
            $this->customField('Bathrooms', $this->range(Arr::get($listing, 'minBaths'), Arr::get($listing, 'maxBaths'))),
            $this->customField('Website', Arr::get($listing, 'website')),
            $this->customField('Estimated Completion', Arr::get($listing, 'estimatedCompletionDate')),
        ];

        return array_values(array_filter($fields));
    }

    /**
     * @return array{name: string, value: string}|null
     */
    protected function customField(string $name, mixed $value): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return [
            'name' => $name,
            'value' => Str::limit((string) $value, 120, ''),
        ];
    }

    protected function range(mixed $min, mixed $max): ?string
    {
        $min = $this->num($min);
        $max = $this->num($max);

        if ($min === null && $max === null) {
            return null;
        }

        if ($min !== null && $max !== null && $min != $max) {
            return $this->trimNumber($min) . ' - ' . $this->trimNumber($max);
        }

        return $this->trimNumber($max ?? $min);
    }

    protected function resolveCompletionDate(array $listing): ?string
    {
        $year = Arr::get($listing, 'estimatedCompletionYear');
        if (is_numeric($year) && $year > 1990 && $year < 2100) {
            return Carbon::create((int) $year, 1, 1)->format('Y-m-d');
        }

        return $this->normalizeDate(Arr::get($listing, 'estimatedCompletionDate'));
    }

    /**
     * Handle Buildify's mixed date strings: "2026", "September 2024",
     * "March 2027", "Apr 12, 2021", "Late 2025".
     */
    protected function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        if (preg_match('/^\d{4}$/', $value)) {
            return Carbon::create((int) $value, 1, 1)->format('Y-m-d');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            // Fall back to any 4-digit year we can find (e.g. "Late 2025").
            if (preg_match('/(19|20)\d{2}/', $value, $matches)) {
                return Carbon::create((int) $matches[0], 1, 1)->format('Y-m-d');
            }

            return null;
        }
    }

    protected function num(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $clean = str_replace([',', '$', ' '], '', trim((string) $value));

        if (! is_numeric($clean)) {
            return null;
        }

        return str_contains($clean, '.') ? (float) $clean : (int) $clean;
    }

    protected function intOrNull(mixed $value): ?int
    {
        $num = $this->num($value);

        return $num === null ? null : (int) $num;
    }

    protected function coord(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = trim((string) $value);

        return is_numeric($clean) ? (float) $clean : null;
    }

    protected function trimNumber(int|float $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /**
     * Truncate to fit a varchar column. Botble's SafeContent cast runs the value
     * through BaseHelper::clean() (HTMLPurifier) on save, which entity-encodes
     * special characters and can push a naively-truncated string past the column
     * limit and fail the insert. We shrink until the *cleaned* length fits, using
     * the exact same cleaner the cast uses so the estimate can't drift.
     */
    protected function fitToColumn(string $value, int $maxLength): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $limit = $maxLength;

        do {
            $candidate = Str::limit($value, $limit, '');
            $cleanedLength = mb_strlen((string) BaseHelper::clean($candidate));
            $limit -= 16;
        } while ($cleanedLength > $maxLength && $limit > 0);

        return $candidate;
    }

    protected function defaultAuthorId(): ?int
    {
        return $this->defaultAuthorId ??= User::query()->orderBy('id')->value('id');
    }
}
