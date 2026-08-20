<?php

namespace Botble\RealEstate\Services\Treeb;

use Botble\ACL\Models\User;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Facades\BaseHelper;
use Botble\Location\Models\City;
use Botble\Location\Models\Country;
use Botble\Location\Models\State;
use Botble\Media\Facades\RvMedia;
use Botble\Media\Models\MediaFile;
use Botble\RealEstate\Enums\ModerationStatusEnum;
use Botble\RealEstate\Enums\PropertyPeriodEnum;
use Botble\RealEstate\Enums\PropertyStatusEnum;
use Botble\RealEstate\Enums\PropertyTypeEnum;
use Botble\RealEstate\Facades\RealEstateHelper;
use Botble\RealEstate\Models\Category;
use Botble\RealEstate\Models\Currency;
use Botble\RealEstate\Models\Feature;
use Botble\RealEstate\Models\Property;
use Botble\RealEstate\Models\ProjectSyncLog;
use Botble\Slug\Facades\SlugHelper;
use Botble\Slug\Models\Slug;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

/**
 * Pulls listings from the TRREB/PROPTX IDX feed (AMPRE RESO Web API) and mirrors
 * them into re_properties. Deliberately shaped like BuildifyProjectSyncer so the
 * two read the same way, but it writes properties rather than projects.
 *
 * Ownership model (same contract as Buildify):
 *  - Every property this syncer creates carries source = "treeb".
 *  - On each run we match ONLY on (source, unique_id = ListingKey):
 *      * found     -> update it (PROPTX is the source of truth for its own rows)
 *      * not found -> create it
 *  - Manually entered and imported properties carry a different source, so they
 *    are invisible here and are never touched.
 *
 * IDX COMPLIANCE NOTES (PROPTX/TRREB IDX Agreement):
 *  - Never log a listing payload. Errors record the ListingKey and a message only.
 *  - Listing content is stored verbatim; only length-fitting for varchar columns
 *    and the description excerpt are applied, which are permitted formatting changes.
 *  - Brokerage attribution (ListOfficeName / ListAgentFullName) is carried into
 *    custom fields so the theme can display it distinctly from listing content.
 *  - Every row is tagged source="treeb" so the termination clause can be honoured
 *    in one command (see PurgeTreebDataCommand).
 */
class TreebPropertySyncer
{
    /** Value written to re_properties.source for every property this syncer owns. */
    public const SOURCE = 'treeb';

    /** Columns whose changes are noise/too bulky to surface in the detail modal. */
    protected const DIFF_IGNORE_COLUMNS = ['updated_at', 'created_at', 'content', 'images'];

    protected int $created = 0;

    protected int $updated = 0;

    protected int $unchanged = 0;

    protected int $failed = 0;

    /** Existing rows taken off the site this run because they left Active status. */
    protected int $delisted = 0;

    protected ?int $currencyId = null;

    /** Set to the configured currency code when it could not be found. */
    protected ?string $currencyWarning = null;

    /** @var array<int, array{id: mixed, error: string}> */
    protected array $errors = [];

    /** @var array<int, array<string, mixed>> */
    protected array $items = [];

    protected ?int $defaultAuthorId = null;

    protected int|string|null $propertiesFolderId = null;

    /** Set when the run stopped early because of the testing cap. */
    protected bool $capReached = false;

    public function __construct(protected TreebClient $client)
    {
    }

    /**
     * Page through the feed and upsert every listing.
     *
     * @param  callable|null  $onProgress  fn(int $page, int $processed, int $total): void
     * @return array{created:int, updated:int, unchanged:int, failed:int, errors:array, items:array, cap_reached:bool}
     */
    public function sync(?callable $onProgress = null): array
    {
        $perPage = max((int) config('plugins.real-estate.treeb.per_page', 100), 1);
        $maxRecords = max((int) config('plugins.real-estate.treeb.max_records', 0), 0);

        // A testing run should not ask the feed for more than it intends to keep.
        if ($maxRecords > 0) {
            $perPage = min($perPage, $maxRecords);
        }

        $filter = $this->buildFilter();

        $page = 0;
        $skip = 0;
        $processed = 0;
        $total = 0;
        $nextLink = null;

        do {
            $response = $this->client->properties($perPage, $skip, $filter, $nextLink);

            $results = Arr::get($response, 'value', []);
            $total = (int) Arr::get($response, '@odata.count', $total ?: count($results));
            $nextLink = Arr::get($response, '@odata.nextLink');

            foreach ($results as $listing) {
                if ($maxRecords > 0 && $processed >= $maxRecords) {
                    $this->capReached = true;

                    break 2;
                }

                // Long feeds plus image downloads outlive the default limit.
                @set_time_limit(300);

                try {
                    $this->importListing($listing);
                } catch (\Throwable $e) {
                    $this->recordFailure($listing, $e);
                }

                $processed++;
            }

            $page++;
            $skip += $perPage;

            if ($onProgress) {
                $onProgress($page, $processed, $total);
            }

            // Stop when the server says there is no more, or a short page implies the end.
            $hasMore = $nextLink !== null || count($results) === $perPage;
        } while ($hasMore && $results !== []);

        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
            'failed' => $this->failed,
            'delisted' => $this->delisted,
            'errors' => $this->errors,
            'items' => $this->items,
            'cap_reached' => $this->capReached,
            'currency_warning' => $this->currencyWarning,
        ];
    }

    /**
     * The OData $filter for this run: incremental watermark + scope from config.
     */
    protected function buildFilter(): ?string
    {
        $clauses = [];

        // Incremental: only what changed since the last successful run.
        $since = ProjectSyncLog::query()
            ->where('source', self::SOURCE)
            ->where('status', 'success')
            ->latest('finished_at')
            ->value('finished_at');

        if ($since) {
            $clauses[] = sprintf('ModificationTimestamp gt %s', $since->clone()->utc()->format('Y-m-d\TH:i:s\Z'));
        }

        if ($types = $this->transactionTypeClause()) {
            $clauses[] = $types;
        }

        // Status filter on the SEED run only. On incremental runs we must also
        // receive listings that just went Sold/Leased/Expired — that transition is
        // the only signal the feed gives us that something has left the market,
        // and missing it means displaying a stale listing as available.
        if (! $since && $statuses = $this->standardStatusClause()) {
            $clauses[] = $statuses;
        }

        if ($extra = trim((string) config('plugins.real-estate.treeb.extra_filter'))) {
            $clauses[] = '(' . $extra . ')';
        }

        return $clauses === [] ? null : implode(' and ', $clauses);
    }

    protected function transactionTypeClause(): ?string
    {
        $configured = array_filter(array_map(
            'trim',
            explode(',', (string) config('plugins.real-estate.treeb.transaction_types', 'sale,lease'))
        ));

        if ($configured === []) {
            return null;
        }

        $values = [];
        foreach ($configured as $type) {
            $values[] = str_contains(strtolower($type), 'lease') || str_contains(strtolower($type), 'rent')
                ? 'For Lease'
                : 'For Sale';
        }

        $values = array_unique($values);

        // Both types selected means no restriction is needed.
        if (count($values) > 1) {
            return null;
        }

        return sprintf("TransactionType eq '%s'", Arr::first($values));
    }

    protected function standardStatusClause(): ?string
    {
        $statuses = array_filter(array_map(
            'trim',
            explode(',', (string) config('plugins.real-estate.treeb.standard_statuses', 'Active'))
        ));

        if ($statuses === []) {
            return null;
        }

        $parts = array_map(
            fn (string $status): string => sprintf("StandardStatus eq '%s'", str_replace("'", "''", $status)),
            $statuses
        );

        return '(' . implode(' or ', $parts) . ')';
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    protected function importListing(array $listing): void
    {
        $listingKey = trim((string) Arr::get($listing, 'ListingKey'));
        $name = $this->resolveName($listing);

        if ($listingKey === '' || $name === '') {
            // Nothing safe to key on / display — skip quietly.
            return;
        }

        // Match ONLY our own PROPTX rows. Manual and imported properties have a
        // different source, so they are never matched or overwritten.
        $property = Property::query()
            ->where('source', self::SOURCE)
            ->where('unique_id', $listingKey)
            ->first();

        // A listing that is no longer Active must come off the site. If we never
        // held it, there is nothing to do — we don't import off-market listings.
        if (! $this->isActive($listing)) {
            if ($property) {
                $this->delist($property, $listing, $listingKey, $name);
            }

            return;
        }

        $isNew = ! $property;

        if ($isNew) {
            $property = new Property();
        }

        $data = $this->mapListing($listing, $name, $listingKey, $isNew ? null : $property);

        $this->resolveLocation($listing, $data);

        $oldCustomFields = $isNew ? [] : $property->customFields()->pluck('value', 'name')->all();

        $property->forceFill($data);

        // Column diff must be read BEFORE save() clears the dirty state.
        $columnChanges = $isNew ? [] : $this->diffColumns($property);

        $property->save();

        $property->features()->sync($this->resolveFeatureIds($listing));
        $property->categories()->sync($this->resolveCategoryIds($listing));

        $newCustomFields = $this->buildCustomFields($listing);
        $property->customFields()->delete();
        foreach ($newCustomFields as $field) {
            $property->customFields()->create($field);
        }

        $customFieldChanges = $isNew ? [] : $this->diffCustomFields($oldCustomFields, $newCustomFields);

        if ($isNew && SlugHelper::isSupportedModel(Property::class)) {
            $this->createSlug($property, $name, $listingKey);
        }

        $this->ensureTranslation($property);

        $this->recordOutcome(
            $isNew,
            $property,
            $listingKey,
            $name,
            array_merge($columnChanges, $customFieldChanges)
        );
    }

    /**
     * Give a freshly imported property a unique, stable public URL.
     *
     * SlugHelper::createSlug() keys its firstOrNew on (reference_type,
     * reference_id, prefix) and does NOT check whether the slug *key* is already
     * taken — so two listings at the same address, or a re-import after a purge,
     * happily create duplicate keys. SlugHelper::getSlug() then resolves the
     * lowest-id match, which may point at a property that no longer exists, and
     * the detail page 404s.
     *
     * The MLS number makes the key unique by construction (addresses are not:
     * unit numbers, relists and re-imports all collide), and any stale row
     * holding the same key is cleared first so the URL is self-healing.
     */
    protected function createSlug(Property $property, string $name, string $listingKey): void
    {
        $prefix = (string) SlugHelper::getPrefix(Property::class, 'properties');
        $key = Str::slug($name . '-' . $listingKey);

        Slug::query()
            ->where('key', $key)
            ->where('prefix', $prefix)
            ->where(function ($query) use ($property): void {
                $query
                    ->where('reference_type', '!=', Property::class)
                    ->orWhere('reference_id', '!=', $property->getKey());
            })
            ->delete();

        Slug::query()->updateOrCreate(
            [
                'reference_type' => Property::class,
                'reference_id' => $property->getKey(),
                'prefix' => $prefix,
            ],
            ['key' => $key]
        );
    }

    /**
     * Whether the board still considers this listing live.
     */
    protected function isActive(array $listing): bool
    {
        $status = strtolower(trim((string) (
            Arr::get($listing, 'StandardStatus') ?: Arr::get($listing, 'MlsStatus')
        )));

        // An empty status means the feed didn't tell us; treat that as live rather
        // than silently pulling a listing off the site on missing data.
        return $status === '' || $status === 'active';
    }

    /**
     * Take a listing off the site once it leaves Active status.
     *
     * This is what stops a sold or leased property from being advertised as
     * available indefinitely — the feed stops returning it under the Active
     * filter, so the status transition is the only notice we ever get.
     */
    protected function delist(Property $property, array $listing, string $listingKey, string $name): void
    {
        $action = strtolower((string) config('plugins.real-estate.treeb.delisted_action', 'hide'));
        $newStatus = $this->resolveStatus($listing, $this->isLease($listing));
        $wasStatus = $this->formatDiffValue($property->getRawOriginal('status'));

        if ($action === 'delete') {
            $this->deleteProperty($property);
        } else {
            $property->forceFill([
                'status' => $newStatus,
                // Drop out of the approved set so the front end stops showing it
                // while the row itself is retained.
                'moderation_status' => ModerationStatusEnum::PENDING,
            ])->save();
        }

        $this->delisted++;
        $this->updated++;

        $this->items[] = [
            'property_id' => $action === 'delete' ? null : $property->getKey(),
            'external_id' => $listingKey,
            'name' => $name,
            'action' => 'delisted',
            'change_set' => ['fields' => [[
                'field' => $action === 'delete' ? 'Removed' : 'Status',
                'from' => $wasStatus,
                'to' => $action === 'delete' ? 'deleted' : $newStatus,
            ]]],
        ];
    }

    /**
     * Hard-delete a property and everything the syncer attached to it. Mirrors
     * PurgeTreebDataCommand so both paths clean up the same way.
     */
    protected function deleteProperty(Property $property): void
    {
        $id = $property->getKey();

        DB::table('re_custom_field_values')
            ->where('reference_type', Property::class)
            ->where('reference_id', $id)
            ->delete();

        DB::table('re_property_features')->where('property_id', $id)->delete();
        DB::table('re_property_categories')->where('property_id', $id)->delete();

        // Leaving the slug behind would strand a URL that resolves to nothing.
        Slug::query()
            ->where('reference_type', Property::class)
            ->where('reference_id', $id)
            ->delete();

        if (Schema::hasTable('re_properties_translations')) {
            DB::table('re_properties_translations')->where('re_properties_id', $id)->delete();
        }

        $property->delete();
    }

    /**
     * Map a RESO Property record onto the re_properties columns.
     *
     * @param  array<string, mixed>  $listing
     * @return array<string, mixed>
     */
    protected function mapListing(array $listing, string $name, string $listingKey, ?Property $existing = null): array
    {
        $remarks = trim((string) Arr::get($listing, 'PublicRemarks'));
        $isLease = $this->isLease($listing);

        $data = [
            'name' => $this->fitToColumn($name, 300),
            'unique_id' => $listingKey,
            'source' => self::SOURCE,

            // Full remarks live in `content` (longtext) so nothing supplied by
            // PROPTX is lost; `description` is a varchar(400) excerpt of the same
            // text — a permitted formatting change, not an edit.
            'description' => $remarks !== '' ? $this->fitToColumn($remarks, 400) : null,
            'content' => $remarks ?: null,

            'location' => $this->fitToColumn($this->resolveAddress($listing), 255) ?: null,
            'type' => $isLease ? PropertyTypeEnum::RENT : PropertyTypeEnum::SALE,
            'status' => $this->resolveStatus($listing, $isLease),
            // TRREB quotes lease prices monthly; for sale rows the column is
            // unused but non-nullable, so it keeps the table default.
            'period' => PropertyPeriodEnum::MONTH,

            // Feed listings are board-verified; leaving them pending would hide
            // them from the site and break the 24-hour display requirement.
            'moderation_status' => ModerationStatusEnum::APPROVED,

            // PropertyBuilder::active() (the front-end scope) requires
            // "expire_date >= now OR never_expired" — with both unset the listing
            // is silently filtered off the site. IDX listings must not expire on a
            // local timer anyway: the board decides when one leaves the market and
            // delist() acts on that, so they never expire locally.
            'never_expired' => true,
            'expire_date' => null,

            'currency_id' => $this->currencyId(),

            'price' => $this->priceString($listing),
            'number_bedroom' => $this->resolveBedrooms($listing),
            'number_bathroom' => $this->resolveBathrooms($listing),
            'number_floor' => $this->intOrNull(Arr::get($listing, 'LegalStories')),
            'square' => $this->resolveArea($listing),

            // NOTE: TRREB's Property resource carries no Latitude/Longitude at
            // all (verified absent across a 50-record sample), so map coordinates
            // are left null rather than faked. Geocoding the addresses would mean
            // sending IDX content to a third-party service, which needs PROPTX
            // sign-off before it could be considered.

            'images' => $this->resolveImages($listingKey, $existing),

            'author_id' => $this->defaultAuthorId(),
            'author_type' => User::class,
        ];

        if (RealEstateHelper::isEnabledZipCode()) {
            $data['zip_code'] = Str::limit((string) Arr::get($listing, 'PostalCode'), 20, '') ?: null;
        }

        return $data;
    }

    /**
     * Listings have no "name" in RESO — build a display title from the address,
     * falling back to the MLS number so a row is never nameless.
     */
    protected function resolveName(array $listing): string
    {
        if ($address = $this->resolveAddress($listing)) {
            return $address;
        }

        // ListingId is absent from this feed; ListingKey is the MLS number.
        $mls = trim((string) Arr::get($listing, 'ListingKey'));

        return $mls !== '' ? 'MLS# ' . $mls : '';
    }

    protected function resolveAddress(array $listing): string
    {
        if ($unparsed = trim((string) Arr::get($listing, 'UnparsedAddress'))) {
            return $unparsed;
        }

        $parts = array_filter([
            trim((string) Arr::get($listing, 'StreetNumber')),
            trim((string) Arr::get($listing, 'StreetName')),
            trim((string) Arr::get($listing, 'StreetSuffix')),
        ]);

        $street = trim(implode(' ', $parts));
        $city = trim((string) Arr::get($listing, 'City'));

        return trim(implode(', ', array_filter([$street, $city])));
    }

    protected function isLease(array $listing): bool
    {
        $type = strtolower(trim((string) Arr::get($listing, 'TransactionType')));

        return str_contains($type, 'lease') || str_contains($type, 'rent');
    }

    /**
     * RESO StandardStatus/MlsStatus -> PropertyStatusEnum, split by sale vs lease.
     */
    protected function resolveStatus(array $listing, bool $isLease): string
    {
        $raw = strtolower(trim((string) (
            Arr::get($listing, 'StandardStatus') ?: Arr::get($listing, 'MlsStatus')
        )));

        $closed = str_contains($raw, 'closed') || str_contains($raw, 'sold') || str_contains($raw, 'leased');

        if ($closed) {
            return $isLease ? PropertyStatusEnum::RENTED : PropertyStatusEnum::SOLD;
        }

        if (str_contains($raw, 'expired') || str_contains($raw, 'withdrawn') || str_contains($raw, 'terminated')) {
            return PropertyStatusEnum::NOT_AVAILABLE;
        }

        return $isLease ? PropertyStatusEnum::RENTING : PropertyStatusEnum::SELLING;
    }

    /**
     * TRREB splits bedrooms above/below grade and often sends BedroomsTotal as 0
     * rather than null, so a null-coalescing chain silently picks the zero. Take
     * the largest sensible figure and treat 0 as "not supplied".
     */
    protected function resolveBedrooms(array $listing): ?int
    {
        $total = (int) $this->num(Arr::get($listing, 'BedroomsTotal'));
        $split = (int) $this->num(Arr::get($listing, 'BedroomsAboveGrade'))
            + (int) $this->num(Arr::get($listing, 'BedroomsBelowGrade'));

        $value = max($total, $split);

        return $value > 0 ? $value : null;
    }

    /**
     * Same story for bathrooms: BathroomsTotalInteger is frequently 0, while the
     * board's own WashroomsType1..5 counts carry the real figure.
     */
    protected function resolveBathrooms(array $listing): ?int
    {
        $total = (int) $this->num(Arr::get($listing, 'BathroomsTotalInteger'));

        $washrooms = 0;
        for ($i = 1; $i <= 5; $i++) {
            $washrooms += (int) $this->num(Arr::get($listing, 'WashroomsType' . $i));
        }

        $value = max($total, $washrooms);

        return $value > 0 ? $value : null;
    }

    /**
     * TRREB has no LivingArea/AboveGradeFinishedArea. Floor area arrives either as
     * BuildingAreaTotal (a number, mostly on commercial) or LivingAreaRange (a
     * banded string, mostly on residential) in the forms "1500-2000" and "5000 +".
     */
    protected function resolveArea(array $listing): int|float|null
    {
        if ($total = $this->num(Arr::get($listing, 'BuildingAreaTotal'))) {
            return $total > 0 ? $total : null;
        }

        return $this->parseAreaRange((string) Arr::get($listing, 'LivingAreaRange'));
    }

    /**
     * "1500-2000" -> 1750 (midpoint), "5000 +" -> 5000 (floor of the band).
     * A banded figure is an approximation either way; the exact band is preserved
     * verbatim as a custom field so nothing PROPTX supplied is lost.
     */
    protected function parseAreaRange(string $range): int|float|null
    {
        if (! preg_match_all('/\d+(?:\.\d+)?/', $range, $matches)) {
            return null;
        }

        $numbers = array_map('floatval', $matches[0]);

        if ($numbers === []) {
            return null;
        }

        if (count($numbers) >= 2) {
            return round(($numbers[0] + $numbers[1]) / 2, 2);
        }

        return $numbers[0] > 0 ? $numbers[0] : null;
    }

    /**
     * re_properties.price is a varchar, so store a clean numeric string.
     */
    protected function priceString(array $listing): ?string
    {
        $price = $this->num(
            Arr::get($listing, 'ListPrice')
                ?? Arr::get($listing, 'ClosePrice')
                ?? Arr::get($listing, 'OriginalListPrice')
        );

        return $price === null ? null : $this->trimNumber($price);
    }

    /**
     * Photos come from the separate Media resource, one request per listing.
     * Re-runs short-circuit when the images are already in the media library.
     *
     * @return array<int, string>
     */
    protected function resolveImages(string $listingKey, ?Property $existing = null): array
    {
        if (! config('plugins.real-estate.treeb.sync_images', true)) {
            return $existing?->images ?? [];
        }

        if ($existing && $this->hasLocalImages($existing)) {
            return $existing->images ?? [];
        }

        $maxImages = max((int) config('plugins.real-estate.treeb.max_images_per_property', 5), 1);

        $media = $this->client->mediaFor($listingKey, $maxImages);

        $images = [];

        foreach (array_slice($media, 0, $maxImages) as $index => $item) {
            $url = trim((string) (Arr::get($item, 'MediaURL') ?: Arr::get($item, 'MediaUrl')));

            if ($url === '') {
                continue;
            }

            if ($localPath = $this->syncImageFromUrl($url, $listingKey, $index)) {
                $images[] = $localPath;
            }
        }

        return $images;
    }

    protected function hasLocalImages(Property $property): bool
    {
        $images = array_filter($property->images ?? []);

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

    protected function getPropertiesFolderId(): int|string
    {
        return $this->propertiesFolderId ??= RvMedia::createFolder('properties');
    }

    /**
     * Download a listing photo into Botble's media library. Deterministic
     * filenames mean repeat syncs skip files already imported.
     *
     * Note: on failure this returns null rather than the remote URL. Hotlinking
     * the feed's CDN would put IDX imagery on the page from a third-party host,
     * which is outside "display on the approved website".
     */
    protected function syncImageFromUrl(string $imageUrl, string $listingKey, int $index): ?string
    {
        $folderId = $this->getPropertiesFolderId();

        $extension = strtolower(pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $extension = 'jpg';
        }

        $imageFileName = sprintf('treeb-%s-%d.%s', Str::slug($listingKey), $index, $extension);
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
            return null;
        }

        if (! $response->successful() || $response->body() === '') {
            return null;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'treeb_');

        if ($tempPath === false) {
            return null;
        }

        file_put_contents($tempPath, $response->body());

        $mimeTypes = (new MimeTypes())->getMimeTypes($extension);
        $mimeType = Arr::first($mimeTypes) ?: 'image/jpeg';

        $uploadResult = RvMedia::handleUpload(
            new UploadedFile($tempPath, $imageFileName, $mimeType, null, true),
            $folderId
        );

        @unlink($tempPath);

        return $uploadResult['error'] ? null : $uploadResult['data']->url;
    }

    /**
     * RESO feature arrays -> re_features.
     *
     * @return array<int, int>
     */
    protected function resolveFeatureIds(array $listing): array
    {
        $raw = [];

        foreach (['InteriorFeatures', 'ExteriorFeatures', 'PoolFeatures', 'CommunityFeatures', 'AssociationAmenities'] as $key) {
            $value = Arr::get($listing, $key);

            if (is_array($value)) {
                $raw = array_merge($raw, $value);
            } elseif (is_string($value) && $value !== '') {
                $raw = array_merge($raw, explode(',', $value));
            }
        }

        $ids = [];

        foreach ($raw as $item) {
            $featureName = Str::limit(trim((string) $item), 120, '');

            if ($featureName === '') {
                continue;
            }

            $ids[] = $this->findOrCreateByName(Feature::class, $featureName)->getKey();
        }

        return array_values(array_unique($ids));
    }

    /**
     * PropertyType / PropertySubType -> re_categories, so every kind of listing
     * the feed carries (Residential Freehold, Residential Condo & Other,
     * Commercial, ...) is browsable rather than lumped together untyped.
     *
     * @return array<int, int>
     */
    protected function resolveCategoryIds(array $listing): array
    {
        if (! config('plugins.real-estate.treeb.sync_categories', true)) {
            return [];
        }

        $ids = [];

        // Parent first, so the sub type can hang off it.
        $parentId = 0;

        foreach (['PropertyType', 'PropertySubType'] as $key) {
            $name = Str::limit(trim((string) Arr::get($listing, $key)), 120, '');

            if ($name === '') {
                continue;
            }

            $category = $this->findOrCreateByName(
                Category::class,
                $name,
                ['status' => BaseStatusEnum::PUBLISHED, 'parent_id' => $parentId]
            );

            $ids[] = $category->getKey();

            if ($key === 'PropertyType') {
                $parentId = $category->getKey();
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Look up a lookup-table row by name, creating it once if missing.
     *
     * Why not firstOrCreate(): these models cast `name` through SafeContent, which
     * runs HTMLPurifier on save — so "Residential Condo & Other" is STORED as
     * "Residential Condo &amp; Other". A where() clause is not cast, so matching on
     * the raw name never finds the stored row and firstOrCreate() would insert a
     * fresh duplicate on every single sync. Matching on the cleaned form (and the
     * raw form, for models without the cast) is what makes this idempotent.
     *
     * @param  class-string  $modelClass
     * @param  array<string, mixed>  $attributes
     */
    protected function findOrCreateByName(string $modelClass, string $name, array $attributes = []): mixed
    {
        $name = trim($name);
        $cleaned = (string) BaseHelper::clean($name);

        $model = $modelClass::query()
            ->whereIn('name', array_unique([$cleaned, $name]))
            ->first();

        if ($model) {
            return $model;
        }

        $model = $modelClass::query()->create([...$attributes, 'name' => $name]);

        if (SlugHelper::isSupportedModel($modelClass)) {
            SlugHelper::createSlug($model);
        }

        return $model;
    }

    /**
     * TRREB quotes in CAD. There is no currency in the feed, so this resolves the
     * configured code against re_currencies and falls back to the site default —
     * which is why the sync warns when CAD is missing rather than silently
     * labelling Canadian prices as USD.
     */
    protected function currencyId(): ?int
    {
        if ($this->currencyId !== null) {
            return $this->currencyId ?: null;
        }

        $code = trim((string) config('plugins.real-estate.treeb.currency', 'CAD'));

        $id = $code !== ''
            ? Currency::query()->whereRaw('UPPER(title) = ?', [strtoupper($code)])->value('id')
            : null;

        if (! $id) {
            $id = Currency::query()->where('is_default', true)->value('id')
                ?: Currency::query()->orderBy('id')->value('id');

            $this->currencyWarning = $code;
        }

        return ($this->currencyId = (int) $id) ?: null;
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

        $countryName = trim((string) Arr::get($listing, 'Country')) ?: 'Canada';
        $country = Country::query()->firstOrCreate(['name' => Str::limit($countryName, 120, '')]);
        if (SlugHelper::isSupportedModel(Country::class) && $country->wasRecentlyCreated) {
            SlugHelper::createSlug($country);
        }
        $countryId = $country->id;

        if ($stateName = trim((string) Arr::get($listing, 'StateOrProvince'))) {
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

        if ($cityName = trim((string) (Arr::get($listing, 'City') ?: Arr::get($listing, 'CityRegion')))) {
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
     * Data with no dedicated column -> custom fields.
     *
     * The brokerage attribution fields are mandatory display items under the IDX
     * agreement; carrying them here is what lets the theme show them separately
     * from the listing content itself.
     *
     * @return array<int, array{name: string, value: string}>
     */
    protected function buildCustomFields(array $listing): array
    {
        $fields = [
            // TRREB sends no ListingId — ListingKey is the MLS number here.
            $this->customField('MLS Number', Arr::get($listing, 'ListingKey')),
            // Mandatory attribution. Present on 100% of sampled records.
            $this->customField('Listing Brokerage', Arr::get($listing, 'ListOfficeName')),
            $this->customField('Co-Listing Brokerage', Arr::get($listing, 'CoListOfficeName')),
            // ListAgentFullName is absent from this IDX feed by design — agent
            // details are not licensed for display, only the brokerage.
            $this->customField('Property Type', Arr::get($listing, 'PropertyType')),
            $this->customField('Property Sub Type', Arr::get($listing, 'PropertySubType')),
            $this->customField('Transaction Type', Arr::get($listing, 'TransactionType')),
            $this->customField('Standard Status', Arr::get($listing, 'StandardStatus')),
            // Keeps the exact band PROPTX supplied, since `square` stores only the
            // midpoint we derived from it.
            $this->customField('Living Area Range', Arr::get($listing, 'LivingAreaRange')),
            $this->customField('Square Foot Source', Arr::get($listing, 'SquareFootSource')),
            $this->customField('Lot Size', Arr::get($listing, 'LotSizeArea')),
            $this->customField('Year Built', Arr::get($listing, 'YearBuilt')),
            $this->customField('Parking Spaces', Arr::get($listing, 'ParkingTotal')),
            $this->customField('Tax Annual Amount', Arr::get($listing, 'TaxAnnualAmount')),
            $this->customField('Association Fee', Arr::get($listing, 'AssociationFee')),
            $this->customField('Neighbourhood', Arr::get($listing, 'CityRegion')),
            // OnMarketDate and ListingContractDate are absent from this feed;
            // OriginalEntryTimestamp is the one that is always populated.
            $this->customField('Listed On', $this->normalizeDate(Arr::get($listing, 'OriginalEntryTimestamp'))),
            $this->customField('Last Updated', $this->normalizeDate(Arr::get($listing, 'ModificationTimestamp'))),
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

        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        return [
            'name' => $name,
            'value' => Str::limit((string) $value, 120, ''),
        ];
    }

    /**
     * Keep the property searchable in the admin panel.
     */
    protected function ensureTranslation(Property $property): void
    {
        if (! Schema::hasTable('re_properties_translations')) {
            return;
        }

        $langCode = is_plugin_active('language')
            ? \Botble\Language\Facades\Language::getDefaultLocaleCode()
            : 'en_US';

        $exists = DB::table('re_properties_translations')
            ->where('re_properties_id', $property->id)
            ->where('lang_code', $langCode)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('re_properties_translations')->insert([
            're_properties_id' => $property->id,
            'lang_code' => $langCode,
            'name' => $property->name ?? '',
            'description' => $property->description ?? '',
            'content' => $property->content ?? '',
            'location' => $property->location ?? '',
        ]);
    }

    /**
     * Tally a processed listing and, when something changed, record a per-property
     * item for the sync-log detail modal.
     *
     * @param  array<int, array{field: string, from: ?string, to: ?string}>  $changes
     */
    protected function recordOutcome(bool $isNew, Property $property, string $listingKey, string $name, array $changes): void
    {
        if ($isNew) {
            $this->created++;
            $this->items[] = [
                'property_id' => $property->getKey(),
                'external_id' => $listingKey,
                'name' => $name,
                'action' => 'created',
                'change_set' => null,
            ];

            return;
        }

        if ($changes === []) {
            $this->unchanged++;

            return;
        }

        $this->updated++;
        $this->items[] = [
            'property_id' => $property->getKey(),
            'external_id' => $listingKey,
            'name' => $name,
            'action' => 'updated',
            'change_set' => ['fields' => $changes],
        ];
    }

    /**
     * Record a failed listing.
     *
     * IDX COMPLIANCE: stores the ListingKey and the exception message only. The
     * listing payload is never written to the log table or the application log.
     */
    protected function recordFailure(array $listing, \Throwable $e): void
    {
        $this->failed++;

        $listingKey = trim((string) Arr::get($listing, 'ListingKey')) ?: null;

        $this->errors[] = [
            'id' => $listingKey,
            'error' => $e->getMessage(),
        ];

        $this->items[] = [
            'property_id' => null,
            'external_id' => $listingKey,
            // Deliberately not the address — that is licensed listing content and
            // failure rows outlive the listing itself.
            'name' => $listingKey ? 'ListingKey ' . $listingKey : null,
            'action' => 'failed',
            'change_set' => ['error' => Str::limit($e->getMessage(), 500)],
        ];
    }

    /**
     * Column-level changes on an existing property, from Eloquent's dirty state.
     * Call after forceFill() and before save().
     *
     * @return array<int, array{field: string, from: ?string, to: ?string}>
     */
    protected function diffColumns(Property $property): array
    {
        $changes = [];

        foreach ($property->getDirty() as $key => $new) {
            if (in_array($key, self::DIFF_IGNORE_COLUMNS, true)) {
                continue;
            }

            $old = $property->getOriginal($key);

            if ($this->valuesEquivalent($old, $new)) {
                continue;
            }

            $changes[] = [
                'field' => Str::headline($key),
                'from' => $this->formatDiffValue($old),
                'to' => $this->formatDiffValue($new),
            ];
        }

        return $changes;
    }

    protected function valuesEquivalent(mixed $a, mixed $b): bool
    {
        $aEmpty = $a === null || $a === '';
        $bEmpty = $b === null || $b === '';

        if ($aEmpty || $bEmpty) {
            return $aEmpty && $bEmpty;
        }

        if ($a instanceof \BackedEnum) {
            $a = $a->value;
        }

        if ($b instanceof \BackedEnum) {
            $b = $b->value;
        }

        if (is_object($a) && method_exists($a, 'getValue')) {
            $a = $a->getValue();
        }

        if (is_object($b) && method_exists($b, 'getValue')) {
            $b = $b->getValue();
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        if ($a instanceof \DateTimeInterface || $b instanceof \DateTimeInterface) {
            return $this->formatDiffValue($a) === $this->formatDiffValue($b);
        }

        return (string) $a === (string) $b;
    }

    /**
     * @param  array<string, string>  $old  name => value
     * @param  array<int, array{name: string, value: string}>  $new
     * @return array<int, array{field: string, from: ?string, to: ?string}>
     */
    protected function diffCustomFields(array $old, array $new): array
    {
        $newMap = [];
        foreach ($new as $field) {
            $newMap[$field['name']] = $field['value'];
        }

        $changes = [];

        foreach (array_keys($newMap + $old) as $name) {
            $before = $old[$name] ?? null;
            $after = $newMap[$name] ?? null;

            if ((string) $before === (string) $after) {
                continue;
            }

            $changes[] = [
                'field' => (string) $name,
                'from' => $this->formatDiffValue($before),
                'to' => $this->formatDiffValue($after),
            ];
        }

        return $changes;
    }

    protected function formatDiffValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if (is_object($value) && method_exists($value, 'getValue')) {
            return (string) $value->getValue();
        }

        if (is_array($value)) {
            return count($value) . ' item(s)';
        }

        return Str::limit(trim((string) $value), 80);
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
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

    protected function trimNumber(int|float $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /**
     * Truncate to fit a varchar column. Botble's SafeContent cast runs the value
     * through BaseHelper::clean() (HTMLPurifier) on save, which entity-encodes
     * special characters and can push a naively-truncated string past the column
     * limit and fail the insert. Shrink until the *cleaned* length fits.
     */
    protected function fitToColumn(string $value, int $maxLength): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $limit = $maxLength;
        $candidate = $value;

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
