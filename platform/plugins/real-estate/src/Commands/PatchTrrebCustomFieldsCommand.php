<?php

namespace Botble\RealEstate\Commands;

use Botble\RealEstate\Models\Property;
use Botble\RealEstate\Services\Trreb\TrrebClient;
use Botble\RealEstate\Services\Trreb\TrrebPropertySyncer;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Backfills / refreshes ONLY the re_custom_field_values rows for existing TRREB
 * properties. It does NOT touch images, does NOT dispatch any queued jobs, and
 * does NOT run a full sync � safe to execute alongside the media-hydration workers.
 *
 * Usage:
 *   php artisan cms:trreb:patch-custom-fields              # all TRREB properties
 *   php artisan cms:trreb:patch-custom-fields --sleep=0    # no throttle (fast, more API load)
 *   php artisan cms:trreb:patch-custom-fields --limit=500  # process first 500 only
 *   php artisan cms:trreb:patch-custom-fields --subtype="Industrial"
 */
class PatchTrrebCustomFieldsCommand extends Command
{
    protected $signature = 'cms:trreb:patch-custom-fields
                            {--limit=0          : Max properties to process (0 = all)}
                            {--sleep=200        : Milliseconds to sleep between API calls (default 200)}
                            {--subtype=         : Only patch properties whose stored Property Sub Type matches}
                            {--type=            : Only patch properties of a given PropertyType (e.g. Commercial)}
                            {--missing-only     : Only process properties that do not yet have the new patched fields}
                            {--dry-run          : Show what would be updated without writing to DB}';

    protected $description = 'Refresh custom fields for existing TRREB properties without touching images or queued jobs.';

    public function __construct(
        protected TrrebClient $client,
        protected TrrebPropertySyncer $syncer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit       = max(0, (int) $this->option('limit'));
        $sleepMs     = max(0, (int) $this->option('sleep'));
        $subtype     = trim((string) $this->option('subtype'));
        $type        = trim((string) $this->option('type'));
        $missingOnly = (bool) $this->option('missing-only');
        $dry         = (bool) $this->option('dry-run');

        $this->info('TRREB Custom Fields Patch');
        $this->info('-----------------------------------------');
        $this->info('Mode        : ' . ($dry ? 'DRY RUN (no writes)' : 'LIVE'));
        $this->info('Limit       : ' . ($limit > 0 ? $limit : 'all'));
        $this->info("Sleep       : {$sleepMs}ms between API calls");
        $this->info('Subtype     : ' . ($subtype ?: 'all'));
        $this->info('Type        : ' . ($type ?: 'all'));
        $this->info('Missing-only: ' . ($missingOnly ? 'YES (skipping already patched)' : 'no'));
        $this->newLine();

        // -- Build property query -----------------------------------------------
        $query = Property::query()
            ->whereIn('source', TrrebPropertySyncer::SOURCES)
            ->select(['id', 'unique_id', 'name']);

        if ($missingOnly) {
            $query->whereNotIn('id', function ($sub) {
                $sub->select('reference_id')
                    ->from('re_custom_field_values')
                    ->where('reference_type', Property::class)
                    ->where('name', 'Contract Status');
            });
        }

        if ($subtype !== '') {
            $query->whereHas('customFields', function ($q) use ($subtype) {
                $q->where('name', 'Property Sub Type')
                  ->where('value', 'LIKE', '%' . $subtype . '%');
            });
        }

        if ($type !== '') {
            $query->whereHas('customFields', function ($q) use ($type) {
                $q->where('name', 'Property Type')
                  ->where('value', 'LIKE', '%' . $type . '%');
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->warn('No matching TRREB properties found.');
            return self::SUCCESS;
        }

        $this->info("Properties to process: {$total}");
        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% | %message%');
        $bar->start();

        $updated   = 0;
        $failed    = 0;
        $unchanged = 0;

        $query->orderBy('id')->chunk(50, function ($properties) use (
            &$updated, &$failed, &$unchanged, $bar, $sleepMs, $dry
        ) {
            foreach ($properties as $property) {
                $listingKey = trim((string) $property->unique_id);

                if ($listingKey === '') {
                    $bar->setMessage("Skipped (no ListingKey): #{$property->id}");
                    $bar->advance();
                    continue;
                }

                try {
                    $bar->setMessage("Fetching {$listingKey}...");

                    $response = $this->client->properties(
                        top: 1,
                        filter: sprintf("ListingKey eq '%s'", str_replace("'", "''", $listingKey))
                    );

                    $listing = Arr::get($response, 'value.0');

                    if (! $listing) {
                        $bar->setMessage("Not found in API: {$listingKey}");
                        $bar->advance();
                        $unchanged++;
                        continue;
                    }

                    // Fetch rooms for this listing
                    $rooms = [];
                    try {
                        $rooms = $this->client->roomsFor($listingKey);
                    } catch (\Throwable) {
                        // Non-fatal
                    }

                    // Build custom fields using the same method the syncer uses
                    $newFields = $this->syncer->buildCustomFieldsPublic($listing, $rooms);

                    if (! $dry) {
                        DB::table('re_custom_field_values')
                            ->where('reference_type', Property::class)
                            ->where('reference_id', $property->id)
                            ->delete();

                        foreach ($newFields as $field) {
                            DB::table('re_custom_field_values')->insert([
                                'reference_type' => Property::class,
                                'reference_id'   => $property->id,
                                'name'           => $field['name'],
                                'value'          => $field['value'],
                            ]);
                        }
                    }

                    $bar->setMessage("OK {$listingKey} (" . count($newFields) . ' fields)');
                    $updated++;
                } catch (\Throwable $e) {
                    $bar->setMessage("ERR {$listingKey}: " . $e->getMessage());
                    $failed++;
                }

                $bar->advance();

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }
        });

        $bar->setMessage('Done.');
        $bar->finish();

        $this->newLine(2);
        $this->table(
            ['Metric', 'Count'],
            [
                ['Patched',   $updated],
                ['Not found', $unchanged],
                ['Failed',    $failed],
                ['Total',     $updated + $unchanged + $failed],
            ]
        );

        if ($dry) {
            $this->newLine();
            $this->warn('DRY RUN � no changes were written to the database.');
        }

        return self::SUCCESS;
    }
}
