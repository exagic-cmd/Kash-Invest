<?php

namespace Botble\RealEstate\Services\Redbricks;

use Botble\ACL\Models\User;
use Botble\Base\Facades\BaseHelper;
use Botble\Location\Models\City;
use Botble\Location\Models\Country;
use Botble\Location\Models\State;
use Botble\Media\Facades\RvMedia;
use Botble\RealEstate\Enums\ProjectStatusEnum;
use Botble\RealEstate\Jobs\HydrateRedbricksProjectDetailsJob;
use Botble\RealEstate\Jobs\DownloadRedbricksProjectMediaJob;
use Botble\RealEstate\Models\Project;
use Botble\RealEstate\Models\ProjectDocument;
use Botble\RealEstate\Models\ProjectFloorPlan;
use Botble\RealEstate\Models\ProjectSyncLog;
use Botble\Slug\Facades\SlugHelper;
use Botble\Slug\Models\Slug;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pulls projects from the Redbricks Data API and mirrors them into re_projects.
 *
 * STAGED INGEST
 *
 * The feed is 8k+ projects, each with its own floorplans + documents endpoints
 * (two extra API requests per project) and 5+ images + several PDFs behind
 * CDN URLs. A single-pass sync against Redbricks' 60 req/min team ceiling
 * blocks the admin for ~5 hours, so the work is split into three independent
 * passes:
 *
 *   1. sync()           — listing-only. Walks /projects pages, upserts rows
 *                         from each page's embedded payload. No sub-resource
 *                         fetches, no media downloads. 40 requests, ~1 min.
 *                         Dispatches a HydrateRedbricksProjectDetailsJob for
 *                         each project not yet hydrated.
 *
 *   2. hydrateDetails() — two API calls per project (floorplans + documents),
 *                         merged into raw_payload. Writes the floor_plans
 *                         repeater column and the re_project_floor_plans /
 *                         re_project_documents child rows. No media downloads
 *                         (image URLs + file URLs are stored verbatim). Capped
 *                         by Redbricks' rate limit, so the queue runs on a
 *                         single worker.
 *
 *   3. hydrateMedia()   — downloads project images, floor plan images, PDFs.
 *                         Not against Redbricks' quota (CDN URLs), so the
 *                         queue runs on many workers in parallel.
 *
 * Each pass stamps a dedicated timestamp (details_synced_at, media_synced_at)
 * so the sweep knows what still owes work and the admin can see which rows
 * are "basic only" vs. fully hydrated.
 *
 * OWNERSHIP MODEL (unchanged)
 *
 *   Every project this syncer creates carries source = 'redbricks' and
 *   unique_id = the raw Redbricks id, and each run matches ONLY on that pair.
 *   Manually added and Excel-imported projects are invisible here and never
 *   touched.
 *
 * NUMERIC NORMALISATION (unchanged)
 *
 *   Every numeric read goes through intOrNull()/floatOrNull(). Redbricks
 *   documents transit_score, neighbourhood_id, underground_levels,
 *   residential_parking and visitor_parking as integers on the list endpoint
 *   and nullable strings on the single-project endpoint, so the same field
 *   genuinely arrives as both.
 */
class RedbricksProjectSyncer
{
    /** Value written to re_projects.source for every project this syncer owns. */
    public const SOURCE = 'redbricks';

    /** Queue dedicated to the throttled per-project API hydration pass. */
    public const DETAILS_QUEUE = 'redbricks-details';

    /** Queue dedicated to media downloads (not rate-limited by Redbricks). */
    public const MEDIA_QUEUE = 'redbricks-media';

    /**
     * API field => the re_projects column it lands in. Drives the "Redbricks API
     * payload" panel on the project edit screen, so what that panel claims is
     * saved stays true as the mapping changes. Anything absent here is retained
     * in raw_payload but has no column of its own.
     *
     * @var array<string, string>
     */
    public const FIELD_MAP = [
        'id' => 'unique_id',
        'name' => 'name',
        'description' => 'description + content',
        'address' => 'location',
        'current_sales_status' => 'status',
        'storeys' => 'number_floor',
        'suites' => 'number_flat',
        'current_price_from' => 'price_from',
        'current_price_to' => 'price_to',
        'price_per_sqft' => 'price_per_sqft_from',
        'size' => 'suite_size_from + suite_size_to',
        'parking_price' => 'parking_price',
        'locker_price' => 'locker_price',
        'maintenance_fees' => 'est_maint',
        'maintenance_fees_details' => 'maintenance_notes',
        'development_charges' => 'development_levies',
        'deposit_with_time' => 'total_min_deposit',
        'deposit_full' => 'deposit_notes',
        'neighbourhood_name' => 'neighbour',
        'architect_name' => 'architects',
        'location' => 'latitude + longitude',
        'occupancy_date' => 'date_finish',
        'launch_date' => 'date_sell',
        'city_name' => 'city_id',
        'district_name' => 'state_id',
        'media' => 'images',
    ];

    /** Columns whose changes are noise or too bulky for the detail modal. */
    protected const DIFF_IGNORE_COLUMNS = [
        'updated_at', 'created_at', 'content', 'images', 'raw_payload', 'floor_plans',
        'details_synced_at', 'media_synced_at',
    ];

    protected int $created = 0;

    protected int $updated = 0;

    protected int $unchanged = 0;

    protected int $failed = 0;

    protected bool $capReached = false;

    /** @var array<int, array{id: mixed, error: string}> */
    protected array $errors = [];

    /** @var array<int, array<string, mixed>> */
    protected array $items = [];

    /** Project IDs that need details hydration enqueued at the end of the run. */
    protected array $pendingDetailsHydration = [];

    protected ?int $defaultAuthorId = null;

    protected int|string|null $projectsFolderId = null;

    protected int|string|null $documentsFolderId = null;

    protected int|string|null $floorPlansFolderId = null;

    public function __construct(protected RedbricksClient $client)
    {
    }

    public function client(): RedbricksClient
    {
        return $this->client;
    }

    /**
     * Re-derive one project entirely from the payload already stored on it,
     * without touching the API.
     *
     * The request quota is capped per subscription period, so once a payload is
     * held locally every mapping change should be replayed from it rather than
     * re-crawled. This is also the only way to rebuild while the quota is spent.
     *
     * Media files already on local storage are left in place; the media timestamp
     * is NOT touched here because a rebuild says nothing about download state.
     */
    public function rebuildFromPayload(Project $project): bool
    {
        $payload = $project->raw_payload ?: [];
        $listing = Arr::get($payload, 'project', []);

        if ($listing === []) {
            return false;
        }

        $name = $this->fit(
            (string) (Arr::get($listing, 'name') ?: 'Project ' . Arr::get($listing, 'id')),
            300
        );

        $data = $this->mapListingColumns($listing, $name, (string) $project->unique_id);
        $data['raw_payload'] = $payload;

        $project->fill($data)->save();

        if (! $project->slugable()->exists()) {
            $this->createSlug($project, $name);
        }

        $this->syncCustomFields($project, $listing);

        // Replay stored sub-resources without touching the API or media.
        $floorPlans = (array) Arr::get($payload, 'floor_plans', []);

        if ($floorPlans !== []) {
            $project->fill(['floor_plans' => $this->toRepeater($floorPlans)])->save();
            $this->syncFloorPlanRows($project, $floorPlans, downloadImages: false);
        }

        $allDocuments = $this->collectDocuments($listing, $payload);

        if ($allDocuments !== []) {
            $this->syncDocumentRows($project, $allDocuments, downloadFiles: false);
        }

        if (($floorPlans !== [] || $allDocuments !== []) && ! $project->details_synced_at) {
            $project->forceFill(['details_synced_at' => Carbon::now()])->saveQuietly();
        }

        return true;
    }

    /**
     * Fast listing-only pass. Writes the project row and its custom fields from
     * each page's embedded payload, then enqueues a details hydration job for
     * any project that is not yet hydrated. No sub-resource API calls and no
     * media downloads happen here.
     *
     * @param  callable|null  $onProgress  fn(int $page, int $pages, int $total): void
     * @param  bool  $full  ignore the incremental cutoff and walk the whole catalogue
     * @param  bool  $enqueueHydration  dispatch details jobs for projects that need them
     * @return array<string, mixed>
     */
    public function sync(?callable $onProgress = null, bool $full = false, bool $enqueueHydration = true): array
    {
        $perPage = (int) config('plugins.real-estate.redbricks.per_page', 200);
        $cap = (int) config('plugins.real-estate.redbricks.max_records', 0);
        $filters = $this->filters();

        // A mapping change makes every stored row stale even though the feed has
        // not moved, and the incremental cutoff would skip the lot — so a backfill
        // needs to be able to ignore it.
        $since = $full ? null : ProjectSyncLog::query()
            ->where('source', self::SOURCE)
            ->where('status', 'success')
            ->latest('finished_at')
            ->value('finished_at');

        $this->pendingDetailsHydration = [];

        $page = 1;
        $pages = 1;

        do {
            $response = $this->client->projects($page, $perPage, $filters);

            $projects = Arr::get($response, 'data', []);
            $pages = max((int) Arr::get($response, 'meta.last_page', 1), 1);
            $total = (int) Arr::get($response, 'meta.total', count($projects));

            // Ordered updated_at desc, so once an entire page predates the last
            // successful run there is nothing newer further back. Only applied
            // when we already hold a successful run to measure against.
            if ($since && $projects !== [] && $this->pageIsOlderThan($projects, $since)) {
                break;
            }

            foreach ($projects as $project) {
                if ($cap > 0 && ($this->created + $this->updated + $this->unchanged) >= $cap) {
                    $this->capReached = true;

                    break 2;
                }

                @set_time_limit(60);

                try {
                    $this->importProject($project);
                } catch (Throwable $e) {
                    $this->recordFailure($project, $e);
                }
            }

            if ($onProgress) {
                $onProgress($page, $pages, $total);
            }

            $page++;
        } while ($page <= $pages);

        if ($enqueueHydration) {
            $this->enqueuePendingHydration();
        }

        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
            'failed' => $this->failed,
            'errors' => $this->errors,
            'items' => $this->items,
            'cap_reached' => $this->capReached,
            'queued_for_hydration' => count($this->pendingDetailsHydration),
        ];
    }

    /**
     * Pass 2: fetch floorplans + documents for one project, merge them into
     * raw_payload, write the floor_plans repeater and the child-table rows.
     * No media downloads happen here — URLs are stored verbatim and the media
     * pass pulls them down afterwards.
     *
     * Idempotent: a re-run against an already hydrated project refreshes its
     * sub-resources in place.
     */
    public function hydrateDetails(Project $project, bool $enqueueMedia = true): bool
    {
        $externalId = $project->unique_id;

        if (! $externalId) {
            return false;
        }

        $payload = $project->raw_payload ?: [];
        $listing = (array) Arr::get($payload, 'project', []);

        $floorPlans = $this->wantsSubResource('sync_floor_plans')
            ? $this->fetchSubResource(fn (): array => $this->client->floorPlansFor($externalId))
            : [];

        $documents = $this->wantsSubResource('sync_documents')
            ? $this->fetchSubResource(fn (): array => $this->client->documentsFor($externalId))
            : [];

        if ($floorPlans !== []) {
            $payload['floor_plans'] = $floorPlans;
        }

        if ($documents !== []) {
            $payload['documents'] = $documents;
        }

        $updates = ['raw_payload' => $payload, 'details_synced_at' => Carbon::now()];

        if ($floorPlans !== []) {
            $updates['floor_plans'] = $this->toRepeater($floorPlans);
        }

        $project->fill($updates)->save();

        if ($floorPlans !== []) {
            $this->syncFloorPlanRows($project, $floorPlans, downloadImages: false);
        }

        $allDocuments = $this->collectDocuments($listing, $payload);

        if ($allDocuments !== []) {
            $this->syncDocumentRows($project, $allDocuments, downloadFiles: false);
        }

        if ($enqueueMedia) {
            DownloadRedbricksProjectMediaJob::dispatch($project->getKey())
                ->onQueue(self::MEDIA_QUEUE);
        }

        return true;
    }

    /**
     * Pass 3: pull every CDN asset referenced by a hydrated project into the
     * media library. Idempotent: already-downloaded files are left in place.
     *
     * Separated from hydrateDetails() because CDN downloads are not against
     * Redbricks' 60/min team ceiling, so this queue can run many workers in
     * parallel without breaching the API quota.
     */
    public function hydrateMedia(Project $project): bool
    {
        $listing = (array) Arr::get($project->raw_payload ?: [], 'project', []);

        if (empty($project->images)) {
            $images = $this->resolveImages($listing, null);

            if ($images !== []) {
                $project->fill(['images' => $images])->save();
            }
        }

        foreach ($project->floorPlanRows()->whereNull('local_image')->get() as $row) {
            $imageUrl = $row->image_full ?: $row->image_medium ?: $row->image_thumbnail;

            if (! $imageUrl) {
                continue;
            }

            $localPath = $this->downloadFloorPlanImage($imageUrl);

            if ($localPath) {
                $row->local_image = $localPath;
                $row->saveQuietly();
            }
        }

        // Only pull PDFs we do not already have a local copy for. Detecting a
        // rotated remote URL is explicitly out of scope here — the details pass
        // overwrites file_url with the fresh one, so by the time this runs the
        // DB column is current and there is no older value to compare against.
        foreach ($project->documents()->whereNull('local_path')->get() as $document) {
            if (! $document->file_url) {
                continue;
            }

            $localPath = $this->downloadDocument($document->file_url);

            if ($localPath) {
                $document->local_path = $localPath;
                $document->saveQuietly();
            }
        }

        $project->forceFill(['media_synced_at' => Carbon::now()])->saveQuietly();

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $projects
     */
    protected function pageIsOlderThan(array $projects, mixed $since): bool
    {
        $cutoff = Carbon::parse($since);

        foreach ($projects as $project) {
            $updatedAt = Arr::get($project, 'updated_at');

            if (! $updatedAt) {
                // No timestamp means we cannot prove it is old — keep going.
                return false;
            }

            try {
                if (Carbon::parse($updatedAt)->greaterThan($cutoff)) {
                    return false;
                }
            } catch (Throwable) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        $filters = [];

        if ($cities = config('plugins.real-estate.redbricks.cities')) {
            $filters['city'] = $cities;
        }

        if ($extra = config('plugins.real-estate.redbricks.extra_query')) {
            parse_str((string) $extra, $parsed);
            $filters = array_merge($filters, $parsed);
        }

        return $filters;
    }

    /**
     * One project in the fast pass: upsert the row and its custom fields,
     * record it as needing details hydration if it has not been hydrated yet.
     *
     * @param  array<string, mixed>  $listing
     */
    protected function importProject(array $listing): void
    {
        $externalId = Arr::get($listing, 'id');

        if (! $externalId) {
            throw new RedbricksApiException('Project arrived without an id.');
        }

        $uniqueId = self::SOURCE . '-' . $externalId;
        $name = $this->fit((string) (Arr::get($listing, 'name') ?: 'Project ' . $externalId), 300);

        $existing = Project::query()->where('unique_id', $uniqueId)->first();

        $data = $this->mapListingColumns($listing, $name, $uniqueId);

        // Preserve sub-resources already present on raw_payload. The fast pass
        // only ever writes the listing portion, so a rehydrated project keeps
        // its floor_plans/documents blocks from the previous details run.
        $existingPayload = $existing?->raw_payload ?: [];
        $payload = ['project' => $listing]
            + array_intersect_key($existingPayload, array_flip(['floor_plans', 'documents']));

        $data['raw_payload'] = $payload;

        if (! $existing) {
            // Create and slug together: a failure between the two used to leave a
            // project row with no slug, which is invisible to the front end and
            // is then reported as "failed" while actually sitting in the table.
            $project = DB::transaction(function () use ($data, $name): Project {
                $project = Project::query()->create($data);
                $this->createSlug($project, $name);

                return $project;
            });

            $this->syncCustomFields($project, $listing);
            $this->pendingDetailsHydration[] = $project->getKey();

            $this->created++;
            $this->items[] = [
                'project_id' => $project->getKey(),
                'external_id' => (string) $externalId,
                'name' => $name,
                'action' => 'created',
                'change_set' => ['new' => true],
            ];

            return;
        }

        // Self-healing: anything created by an earlier run that died before its
        // slug was written gets one now, rather than 404ing forever.
        if (! $existing->slugable()->exists()) {
            $this->createSlug($existing, $name);
        }

        $changes = $this->diff($existing, $data);

        // raw_payload is excluded from the diff because it is too bulky for the
        // detail modal — but that means a project whose mapped columns are
        // identical would never be written, and a row never backfilled would
        // stay empty. Treat "we have it, the row does not" as a reason to save.
        $needsBackfill = ! empty($data['raw_payload']) && empty($existing->raw_payload);

        // A project without a details timestamp still owes its sub-resource
        // hydration, regardless of whether the listing columns moved.
        if (! $existing->details_synced_at) {
            $this->pendingDetailsHydration[] = $existing->getKey();
        }

        if ($changes === [] && ! $needsBackfill) {
            $this->syncCustomFields($existing, $listing);
            $this->unchanged++;

            return;
        }

        $existing->fill($data)->save();
        $this->syncCustomFields($existing, $listing);

        // A save that only backfilled raw_payload is not a content change, and
        // counting it as one would overstate what the feed actually did.
        if ($changes === []) {
            $this->unchanged++;

            return;
        }

        $this->updated++;
        $this->items[] = [
            'project_id' => $existing->getKey(),
            'external_id' => (string) $externalId,
            'name' => $name,
            'action' => 'updated',
            'change_set' => ['fields' => $changes],
        ];
    }

    /**
     * Dispatch details jobs for every project the current run flagged. Called
     * once at the end so a cap-reached run only enqueues what it saved.
     */
    protected function enqueuePendingHydration(): void
    {
        foreach (array_unique($this->pendingDetailsHydration) as $projectId) {
            try {
                HydrateRedbricksProjectDetailsJob::dispatch($projectId)
                    ->onQueue(self::DETAILS_QUEUE);
            } catch (Throwable $e) {
                // Dispatch failures must not blow up the whole sync run.
                report($e);
            }
        }
    }

    /**
     * Everything the feed sends that has no column of its own, written as custom
     * fields so it is visible and editable in admin rather than buried in JSON.
     *
     * Values are capped at 255 by re_custom_field_values.value, so nested objects
     * that do not summarise cleanly are deliberately left to raw_payload instead
     * of being stored truncated and misleading.
     *
     * @param  array<string, mixed>  $listing
     */
    protected function syncCustomFields(Project $project, array $listing): void
    {
        $fields = [];

        foreach ($listing as $key => $value) {
            if (isset(self::FIELD_MAP[$key]) || $this->isEmpty($value)) {
                continue;
            }

            $rendered = $this->renderForCustomField($key, $value);

            if ($rendered === null) {
                continue;
            }

            $fields[] = [
                'name' => $this->humanise($key),
                'value' => mb_substr($rendered, 0, 255, 'UTF-8'),
            ];
        }

        // Replace wholesale: the feed is authoritative for its own project, and a
        // field that disappears upstream should disappear here too.
        $project->customFields()->delete();

        foreach ($fields as $field) {
            $project->customFields()->create($field);
        }
    }

    /**
     * @return string|null  null when the value cannot be summarised in 255 chars
     */
    protected function renderForCustomField(string $key, mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (! is_array($value)) {
            return null;
        }

        // Lists of named things (developers, architects, incentives) read well as
        // a comma-separated list of their names.
        $names = array_filter(array_map(
            fn ($item) => is_array($item) ? ($item['name'] ?? null) : (is_scalar($item) ? $item : null),
            $value
        ));

        if ($names !== []) {
            return implode(', ', $names);
        }

        // Flat maps of scalars (gfa, size, launch_price) summarise as key: value.
        $pairs = [];

        foreach ($value as $subKey => $subValue) {
            if (! is_scalar($subValue)) {
                return null;
            }

            $pairs[] = $this->humanise((string) $subKey) . ': ' . $subValue;
        }

        return $pairs === [] ? null : implode(' · ', $pairs);
    }

    protected function humanise(string $key): string
    {
        return Str::of($key)
            ->replace('_', ' ')
            ->title()
            ->replace(['Gfam', 'Psf', 'Id'], ['GFA', 'PSF', 'ID'])
            ->toString();
    }

    /**
     * Floor plans as queryable rows. Upserted on (project_id, external_id) so a
     * re-sync updates prices in place instead of duplicating the catalogue.
     *
     * @param  array<int, array<string, mixed>>  $floorPlans
     */
    protected function syncFloorPlanRows(Project $project, array $floorPlans, bool $downloadImages = false): void
    {
        $seen = [];

        foreach ($floorPlans as $plan) {
            if (! is_array($plan) || ! ($externalId = Arr::get($plan, 'id'))) {
                continue;
            }

            $seen[] = (string) $externalId;

            [$floorMin, $floorMax] = $this->floorRange($plan);

            $row = ProjectFloorPlan::query()->updateOrCreate(
                ['project_id' => $project->getKey(), 'external_id' => (string) $externalId],
                [
                    'floorplan_uuid' => Arr::get($plan, 'floorplan_uuid'),
                    'name' => Arr::get($plan, 'name'),
                    'bedrooms' => $this->stringOrNull(Arr::get($plan, 'bedrooms')),
                    'bathrooms' => $this->stringOrNull(Arr::get($plan, 'bathrooms')),
                    'size' => $this->floatOrNull(Arr::get($plan, 'size')),
                    'exposure' => Arr::get($plan, 'exposure'),
                    'availability' => Arr::get($plan, 'availability'),
                    'current_price' => $this->floatOrNull(Arr::get($plan, 'current_price')),
                    'current_psf' => $this->floatOrNull(Arr::get($plan, 'current_psf')),
                    'launch_price' => $this->floatOrNull(Arr::get($plan, 'launch_price')),
                    'floor_min' => $floorMin,
                    'floor_max' => $floorMax,
                    'image_full' => Arr::get($plan, 'images.full'),
                    'image_medium' => Arr::get($plan, 'images.medium'),
                    'image_thumbnail' => Arr::get($plan, 'images.thumbnail'),
                    'price_history' => Arr::get($plan, 'price_history', []),
                ]
            );

            if ($downloadImages && empty($row->local_image)) {
                $imageUrl = Arr::get($plan, 'images.full')
                    ?: Arr::get($plan, 'images.medium')
                    ?: Arr::get($plan, 'images.thumbnail');

                if ($imageUrl && ($localPath = $this->downloadFloorPlanImage($imageUrl))) {
                    $row->local_image = $localPath;
                    $row->saveQuietly();
                }
            }
        }

        // A plan pulled from sale upstream should not linger on the site.
        $project->floorPlanRows()
            ->when($seen !== [], fn ($query) => $query->whereNotIn('external_id', $seen))
            ->delete();
    }

    protected function downloadFloorPlanImage(string $url): ?string
    {
        try {
            $result = RvMedia::uploadFromUrl($url, $this->floorPlansFolderId(), 'floor-plans');

            if (Arr::get($result, 'error')) {
                return null;
            }

            return Arr::get($result, 'data')?->resource?->url;
        } catch (Throwable $e) {
            // One bad image must not abort the entire floor plan sync.
            report($e);

            return null;
        }
    }

    protected function floorPlansFolderId(): int|string
    {
        if ($this->floorPlansFolderId === null) {
            $this->floorPlansFolderId = RvMedia::createFolder('floor-plans', 0, true);
        }

        return $this->floorPlansFolderId;
    }

    /**
     * Every document the feed exposes for a project, from all three places it
     * publishes them, de-duplicated on the document id.
     *
     * The project object carries complete latest_documents and
     * historical_documents lists, while /projects/{id}/documents is paginated —
     * so the embedded lists are often the fuller source and the endpoint alone
     * understates what exists.
     *
     * @param  array<string, mixed>  $listing
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function collectDocuments(array $listing, array $payload): array
    {
        $documents = [];

        foreach ([
            Arr::get($payload, 'documents', []),
            Arr::get($listing, 'latest_documents', []),
            Arr::get($listing, 'historical_documents', []),
        ] as $source) {
            foreach ((array) $source as $document) {
                if (is_array($document) && ($id = Arr::get($document, 'id'))) {
                    $documents[(string) $id] = $document;
                }
            }
        }

        return array_values($documents);
    }

    /**
     * Documents as rows. In the fast / details passes the PDF URL alone is
     * stored; the media pass actually downloads the file. The remote URL is
     * kept alongside our copy because Redbricks' CDN links can rotate.
     *
     * @param  array<int, array<string, mixed>>  $documents
     */
    protected function syncDocumentRows(Project $project, array $documents, bool $downloadFiles = false): void
    {
        foreach ($documents as $document) {
            // Each entry declares its own "type" (latest | historical), which is
            // more reliable than inferring it from which list it came out of.
            $historical = strtolower((string) Arr::get($document, 'type')) === 'historical';

            if (! is_array($document) || ! ($externalId = Arr::get($document, 'id'))) {
                continue;
            }

            $fileUrl = Arr::get($document, 'file_url');

            $existing = ProjectDocument::query()
                ->where('project_id', $project->getKey())
                ->where('external_id', (string) $externalId)
                ->first();

            $localPath = $existing?->local_path;

            if ($downloadFiles && $fileUrl && (! $localPath || $existing?->file_url !== $fileUrl)) {
                $localPath = $this->downloadDocument($fileUrl) ?? $localPath;
            }

            ProjectDocument::query()->updateOrCreate(
                ['project_id' => $project->getKey(), 'external_id' => (string) $externalId],
                [
                    'name' => $this->fit((string) Arr::get($document, 'name', ''), 255) ?: null,
                    'document_type' => Arr::get($document, 'document_type') ?: Arr::get($document, 'type'),
                    'file_url' => $fileUrl,
                    'local_path' => $localPath,
                    'is_historical' => $historical,
                ]
            );
        }
    }

    /**
     * Redbricks serves most documents as Google Drive "/view" pages rather than
     * direct files. Fetching one of those returns Drive's HTML viewer, which we
     * would happily store as a .pdf that opens to nothing — so those links are
     * kept as links, and only real file URLs are downloaded.
     */
    protected function isDownloadableDocument(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return ! str_contains($host, 'drive.google.com')
            && ! str_contains($host, 'docs.google.com');
    }

    protected function downloadDocument(string $url): ?string
    {
        if (! $this->isDownloadableDocument($url)) {
            return null;
        }

        try {
            $result = RvMedia::uploadFromUrl($url, $this->documentsFolderId(), 'project-documents');

            if (Arr::get($result, 'error')) {
                return null;
            }

            return Arr::get($result, 'data')?->resource?->url;
        } catch (Throwable $e) {
            // A PDF we cannot fetch still gets its row, pointing at the remote URL.
            report($e);

            return null;
        }
    }

    protected function documentsFolderId(): int|string
    {
        if ($this->documentsFolderId === null) {
            $this->documentsFolderId = RvMedia::createFolder('project-documents', 0, true);
        }

        return $this->documentsFolderId;
    }

    /**
     * Listing-only column mapping. No API calls, no media. Produces the data
     * array that writes the project row.
     *
     * @param  array<string, mixed>  $listing
     * @return array<string, mixed>
     */
    protected function mapListingColumns(array $listing, string $name, string $uniqueId): array
    {
        $description = trim(strip_tags((string) Arr::get($listing, 'description', '')));

        $data = [
            'name' => $name,
            'unique_id' => $uniqueId,
            'source' => self::SOURCE,
            'description' => $description !== '' ? $this->fit($description, 400) : null,
            'content' => Arr::get($listing, 'description') ?: null,
            'location' => $this->fit((string) Arr::get($listing, 'address', ''), 255) ?: null,
            'status' => $this->resolveStatus($listing),

            'number_floor' => $this->intOrNull(Arr::get($listing, 'storeys')),
            'number_flat' => $this->intOrNull(Arr::get($listing, 'suites')),

            'price_from' => $this->stringOrNull(Arr::get($listing, 'current_price_from')),
            'price_to' => $this->stringOrNull(Arr::get($listing, 'current_price_to')),
            'price_per_sqft_from' => $this->floatOrNull(Arr::get($listing, 'price_per_sqft')),

            'suite_size_from' => $this->floatOrNull(Arr::get($listing, 'size.min')),
            'suite_size_to' => $this->floatOrNull(Arr::get($listing, 'size.max')),

            'parking_price' => $this->floatOrNull(Arr::get($listing, 'parking_price')),
            'locker_price' => $this->floatOrNull(Arr::get($listing, 'locker_price')),
            'est_maint' => $this->stringOrNull(Arr::get($listing, 'maintenance_fees')),
            'maintenance_notes' => $this->stringOrNull(Arr::get($listing, 'maintenance_fees_details')),
            'development_levies' => $this->stringOrNull(Arr::get($listing, 'development_charges')),

            'total_min_deposit' => $this->stringOrNull(Arr::get($listing, 'deposit_with_time')),
            'deposit_notes' => $this->stringOrNull(Arr::get($listing, 'deposit_full')),

            'neighbour' => $this->fit((string) Arr::get($listing, 'neighbourhood_name', ''), 255) ?: null,
            'architects' => $this->fit((string) Arr::get($listing, 'architect_name', ''), 255) ?: null,

            'latitude' => $this->coordinate($listing, 1),
            'longitude' => $this->coordinate($listing, 0),

            'date_finish' => $this->occupancyDate($listing),
            'date_sell' => $this->dateOrNull(Arr::get($listing, 'launch_date')),

            'author_id' => $this->defaultAuthorId(),
            'author_type' => User::class,
        ];

        $this->resolveLocation($listing, $data);

        return array_filter($data, fn ($value): bool => $value !== null);
    }

    /**
     * Botble stores floor plans as repeater rows. The shape is exact: each row is
     * an array KEYED BY FIELD NAME whose entries are ['key' => ..., 'value' => ...],
     * because forms/partials/repeater-item.blade.php reads
     * Arr::get($values, "$index.$key.value") to populate the edit form. A plain
     * list of {key, value} pairs satisfies Project::formattedFloorPlans() on the
     * front end but leaves every field in the admin form blank.
     *
     * Only the five fields the repeater declares are written. Anything else the
     * feed sends — price_history, current_psf, availability, exposure, the image
     * variants — lives in raw_payload, which is not at the mercy of an admin save
     * round-trip that would drop keys the form does not know about.
     *
     * @param  array<int, array<string, mixed>>  $floorPlans
     * @return array<int, array<string, array{key: string, value: mixed}>>
     */
    protected function toRepeater(array $floorPlans): array
    {
        $rows = [];

        foreach ($floorPlans as $plan) {
            if (! is_array($plan)) {
                continue;
            }

            // The theme prints this under the plan name; size and exposure are the
            // two things a buyer actually scans for.
            $description = trim(implode(' · ', array_filter([
                ($size = Arr::get($plan, 'size')) ? rtrim(rtrim((string) $size, '0'), '.') . ' sq ft' : null,
                ($exposure = Arr::get($plan, 'exposure')) ? $exposure . ' exposure' : null,
                Arr::get($plan, 'availability'),
                Arr::get($plan, 'current_price'),
            ])));

            $values = [
                'name' => Arr::get($plan, 'name'),
                'description' => $description ?: null,
                'image' => Arr::get($plan, 'images.full')
                    ?: Arr::get($plan, 'images.medium')
                    ?: Arr::get($plan, 'images.thumbnail'),
                'bedrooms' => Arr::get($plan, 'bedrooms'),
                'bathrooms' => Arr::get($plan, 'bathrooms'),
            ];

            $row = [];

            foreach ($values as $key => $value) {
                $row[$key] = ['key' => $key, 'value' => $value];
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Redbricks ships occupancy as a bare year ("2027") far more often than a
     * full date, and re_projects.date_finish is a DATE column.
     *
     * @param  array<string, mixed>  $listing
     */
    protected function occupancyDate(array $listing): ?string
    {
        $value = trim((string) Arr::get($listing, 'occupancy_date', ''));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}$/', $value)) {
            return $value . '-12-31';
        }

        return $this->dateOrNull($value);
    }

    protected function dateOrNull(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * GeoJSON Point, so coordinates arrive as [longitude, latitude] — index 0 is
     * the longitude, which is the opposite order to how we store them.
     *
     * @param  array<string, mixed>  $listing
     */
    protected function coordinate(array $listing, int $index): ?string
    {
        $value = Arr::get($listing, 'location.coordinates.' . $index);

        return is_numeric($value) ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    protected function resolveStatus(array $listing): string
    {
        $status = strtolower(trim((string) (
            Arr::get($listing, 'current_sales_status') ?: Arr::get($listing, 'status', '')
        )));

        return match (true) {
            str_contains($status, 'sold') => ProjectStatusEnum::SOLD,
            str_contains($status, 'complete') => ProjectStatusEnum::FINISHED,
            default => ProjectStatusEnum::SELLING,
        };
    }

    /**
     * @param  array<string, mixed>  $listing
     * @param  array<string, mixed>  $data
     */
    protected function resolveLocation(array $listing, array &$data): void
    {
        $cityName = trim((string) Arr::get($listing, 'city_name', ''));

        if ($cityName === '') {
            return;
        }

        try {
            $country = Country::query()->firstOrCreate(['name' => 'Canada']);
            $data['country_id'] = $country->getKey();

            // Redbricks has no province field; district is the closest thing it
            // carries, and for the GTA it is the right granularity anyway.
            $stateName = trim((string) Arr::get($listing, 'district_name', '')) ?: 'Ontario';

            $state = State::query()->firstOrCreate(
                ['name' => Str::limit($stateName, 120, '')],
                ['country_id' => $country->getKey()]
            );
            $data['state_id'] = $state->getKey();

            $city = City::query()->firstOrCreate(
                ['name' => Str::limit($cityName, 120, '')],
                ['state_id' => $state->getKey(), 'country_id' => $country->getKey()]
            );
            $data['city_id'] = $city->getKey();
        } catch (Throwable $e) {
            // A location we cannot resolve must not cost us the whole project.
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $listing
     * @return array<int, string>
     */
    protected function resolveImages(array $listing, ?Project $existing): array
    {
        // Re-downloading images every run is the slowest thing this syncer does
        // and the feed rarely changes them, so an existing set is kept as-is.
        if ($existing && ! empty($existing->images)) {
            return (array) $existing->images;
        }

        $urls = array_values(array_filter(
            (array) Arr::get($listing, 'media', []),
            fn ($url): bool => is_string($url) && $url !== ''
        ));

        if ($urls === []) {
            return [];
        }

        $limit = max((int) config('plugins.real-estate.redbricks.max_images_per_project', 5), 1);
        $images = [];

        foreach (array_slice($urls, 0, $limit) as $url) {
            try {
                // Same folder Buildify writes to, so imported media stays in one
                // place. media_folders.parent_id is NOT NULL, hence 0 and not null.
                $result = RvMedia::uploadFromUrl($url, $this->projectsFolderId(), 'projects');

                if (Arr::get($result, 'error')) {
                    continue;
                }

                // handleUpload() hands back a FileResource, not an array, so the
                // stored relative path has to come off the wrapped MediaFile —
                // Arr::get($result, 'data.url') silently yields null.
                $path = Arr::get($result, 'data')?->resource?->url;

                if ($path) {
                    $images[] = $path;
                }
            } catch (Throwable $e) {
                // One bad image URL must not fail the project.
                report($e);
            }
        }

        return $images;
    }

    protected function projectsFolderId(): int|string
    {
        if ($this->projectsFolderId === null) {
            $this->projectsFolderId = RvMedia::createFolder('projects', 0, true);
        }

        return $this->projectsFolderId;
    }

    /**
     * Give a freshly imported project a unique, stable public URL.
     *
     * SlugHelper::createSlug() keys its firstOrNew on (reference_type,
     * reference_id, prefix) and does not check whether the slug *key* is already
     * taken, so two projects with the same name — or a re-import after a purge —
     * happily create duplicate keys. SlugHelper::getSlug() then resolves the
     * lowest-id match, which may point at a project that no longer exists.
     * Counting up until the key is free keeps the URL self-healing.
     */
    protected function createSlug(Project $project, string $name): void
    {
        $prefix = (string) SlugHelper::getPrefix(Project::class, 'projects');
        $baseKey = Str::slug($name) ?: ('project-' . $project->getKey());
        $key = $baseKey;
        $counter = 1;

        while (
            Slug::query()
                ->where('key', $key)
                ->where('prefix', $prefix)
                ->where(function ($query) use ($project): void {
                    $query
                        ->where('reference_type', '!=', Project::class)
                        ->orWhere('reference_id', '!=', $project->getKey());
                })
                ->exists()
        ) {
            $key = $baseKey . '-' . (++$counter);
        }

        Slug::query()->updateOrCreate(
            [
                'reference_type' => Project::class,
                'reference_id' => $project->getKey(),
                'prefix' => $prefix,
            ],
            ['key' => $key]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{field: string, from: mixed, to: mixed}>
     */
    protected function diff(Project $project, array $data): array
    {
        $changes = [];

        foreach ($data as $field => $value) {
            if (in_array($field, self::DIFF_IGNORE_COLUMNS, true)) {
                continue;
            }

            $current = $project->getAttribute($field);

            // Loose compare: the DB hands back strings for decimal columns that
            // we set as floats, and a strict check would report every row changed.
            if ($this->normalise($current) === $this->normalise($value)) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'from' => is_scalar($current) ? $current : null,
                'to' => is_scalar($value) ? $value : null,
            ];
        }

        return $changes;
    }

    protected function normalise(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
        }

        if (is_array($value)) {
            return (string) json_encode($value);
        }

        return trim((string) $value);
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    protected function recordFailure(array $listing, Throwable $e): void
    {
        $this->failed++;
        $externalId = Arr::get($listing, 'id');

        $this->errors[] = ['id' => $externalId, 'error' => $e->getMessage()];

        $this->items[] = [
            'project_id' => null,
            'external_id' => $externalId ? (string) $externalId : null,
            'name' => trim((string) Arr::get($listing, 'name')) ?: null,
            'action' => 'failed',
            'change_set' => ['error' => Str::limit($e->getMessage(), 500)],
        ];

        report($e);
    }

    protected function defaultAuthorId(): ?int
    {
        if ($this->defaultAuthorId === null) {
            $this->defaultAuthorId = (int) User::query()->orderBy('id')->value('id');
        }

        return $this->defaultAuthorId ?: null;
    }

    protected function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    protected function floatOrNull(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        // Values like "$1,250" or "1,250 sq ft" show up in the string-typed
        // variants of these fields; keep the digits rather than dropping the row.
        if (is_string($value)) {
            $digits = preg_replace('/[^0-9.]/', '', $value);

            return $digits !== '' && is_numeric($digits) ? (float) $digits : null;
        }

        return null;
    }

    protected function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * floor_range is neither {min,max} nor [min,max] — it arrives as
     * [{"range": "9-46", "unit": "15"}], so the bounds have to be parsed out of
     * a hyphenated string.
     *
     * @param  array<string, mixed>  $plan
     * @return array{0: int|null, 1: int|null}
     */
    protected function floorRange(array $plan): array
    {
        $range = Arr::get($plan, 'floor_range.0.range') ?? Arr::get($plan, 'floor_range.range');

        if (! is_string($range) || ! preg_match('/(\d+)\s*-\s*(\d+)/', $range, $matches)) {
            // A single storey ("12") is still a usable lower bound.
            return is_numeric($range) ? [(int) $range, (int) $range] : [null, null];
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }

        return $this->fit((string) $value, 255);
    }

    /**
     * Truncate to fit a varchar column.
     *
     * Botble's SafeContent cast runs the value through BaseHelper::clean()
     * (HTMLPurifier) on save, which entity-encodes special characters — one
     * apostrophe becomes "&#039;" — so a string cut to exactly the column width
     * can still overflow it by the time it reaches the insert. Shrinking until
     * the *cleaned* length fits, with the same cleaner the cast uses, is the only
     * measure that cannot drift.
     */
    protected function fit(string $value, int $maxLength): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $limit = $maxLength;

        do {
            $candidate = Str::limit($value, $limit, '');
            $cleanedLength = mb_strlen((string) BaseHelper::clean($candidate), 'UTF-8');
            $limit -= 16;
        } while ($cleanedLength > $maxLength && $limit > 0);

        return $candidate;
    }

    /**
     * Floor plans and documents are separate per-project endpoints, so one bad
     * or tier-gated call must not cost us the project itself.
     */
    protected function wantsSubResource(string $key): bool
    {
        return (bool) config('plugins.real-estate.redbricks.' . $key, false);
    }

    /**
     * @param  callable(): array<int, array<string, mixed>>  $fetch
     * @return array<int, array<string, mixed>>
     */
    protected function fetchSubResource(callable $fetch): array
    {
        try {
            return $fetch();
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }
}
