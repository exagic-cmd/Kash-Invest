<?php

namespace Botble\RealEstate\Commands;

use Botble\RealEstate\Jobs\SyncTrrebPropertiesJob;
use Botble\RealEstate\Services\Trreb\TrrebPropertySyncer;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('cms:trreb:sync-properties', 'Sync properties from the TRREB/PROPTX IDX feed', aliases: ['cms:treeb:sync-properties'])]
class SyncTrrebPropertiesCommand extends Command
{
    public function handle(): int
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $listingKey = $this->option('listing-key');
        if ($listingKey) {
            $this->components->info(sprintf('Fetching listing %s directly from TRREB API...', $listingKey));
            $syncer = app(TrrebPropertySyncer::class);
            $property = $syncer->syncSingleListing($listingKey);

            if ($property) {
                $this->components->success(sprintf('Listing %s (%s) imported successfully!', $listingKey, $property->name));

                return self::SUCCESS;
            }

            $this->components->error(sprintf('Listing %s could not be found or imported from TRREB API.', $listingKey));

            return self::FAILURE;
        }

        $trigger = $this->option('trigger') === 'cron' ? 'cron' : 'manual';
        $full = (bool) $this->option('full');
        $fresh = (bool) $this->option('fresh');
        $skip = $this->option('skip') !== null ? (int) $this->option('skip') : null;
        $since = $this->option('since') ? (string) $this->option('since') : null;
        $noImages = (bool) $this->option('no-images');
        $noRooms = (bool) $this->option('no-rooms');

        if ($noImages) {
            config(['plugins.real-estate.trreb.sync_images' => false]);
        }

        if ($noRooms) {
            config(['plugins.real-estate.trreb.sync_rooms' => false]);
        }

        if ($fresh) {
            TrrebPropertySyncer::clearCheckpoint();
            $skip = 0;
        } elseif ($skip === null && ($full || $since)) {
            $checkpoint = TrrebPropertySyncer::getCheckpoint();
            if ($checkpoint && ! empty($checkpoint['skip'])) {
                $skip = (int) $checkpoint['skip'];
                $this->components->info(sprintf(
                    'Resuming TRREB sync from checkpoint: skip offset %d (Page %d, %d/%d processed)...',
                    $skip,
                    $checkpoint['page'] ?? (int) floor($skip / 100),
                    $checkpoint['processed'] ?? $skip,
                    $checkpoint['total'] ?? 0
                ));
            }
        }

        $skip = $skip ?? 0;
        $job = new SyncTrrebPropertiesJob($trigger, $full, $skip, $since, $noImages);

        if ($this->option('now')) {
            $this->components->info(sprintf(
                'Running the TRREB sync inline (%s%s%s, starting from skip offset %d)...',
                $full ? 'FULL feed' : 'incremental',
                $since ? " since {$since}" : '',
                $noImages ? ' [no images]' : '',
                $skip
            ));

            $syncer = app(TrrebPropertySyncer::class);
            $result = $syncer->sync(function (int $page, int $processed, int $total): void {
                $this->components->task(
                    sprintf('Page %d (%d/%d listings processed)', $page, $processed, $total)
                );
            }, full: $full, startSkip: $skip, since: $since);

            $this->components->success(sprintf(
                'TRREB sync finished: %d created, %d updated, %d unchanged, %d failed.',
                $result['created'] ?? 0,
                $result['updated'] ?? 0,
                $result['unchanged'] ?? 0,
                $result['failed'] ?? 0
            ));

            return self::SUCCESS;
        }

        dispatch($job);

        if (config('queue.default') === 'sync') {
            $this->components->warn(
                'QUEUE_CONNECTION=sync, so this ran inline rather than on a worker. '
                . 'Set QUEUE_CONNECTION=database and run "php artisan queue:work" for real background syncs.'
            );
        } else {
            $this->components->info('TRREB sync queued. See Admin -> API Sync for progress.');
        }

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addOption(
            'listing-key',
            'k',
            InputOption::VALUE_REQUIRED,
            'Sync a specific listing by its ListingKey (e.g. E13060676).'
        );

        $this->addOption(
            'trigger',
            null,
            InputOption::VALUE_OPTIONAL,
            'Who triggered the run: manual (admin/CLI) or cron (scheduler).',
            'manual'
        );

        $this->addOption(
            'now',
            null,
            InputOption::VALUE_NONE,
            'Run the sync inline instead of pushing it onto the queue.'
        );

        $this->addOption(
            'full',
            'f',
            InputOption::VALUE_NONE,
            'Walk the entire active feed rather than using the incremental cutoff.'
        );

        $this->addOption(
            'since',
            null,
            InputOption::VALUE_REQUIRED,
            'Sync listings modified since this date/timestamp (e.g. 2026-10-10 or 2026-10-10T00:00:00Z).'
        );

        $this->addOption(
            'no-images',
            null,
            InputOption::VALUE_NONE,
            'Skip image downloading and S3 uploading during the sync (fast metadata only).'
        );

        $this->addOption(
            'no-rooms',
            null,
            InputOption::VALUE_NONE,
            'Skip room dimension API requests for new listings during the sync (super fast mode).'
        );

        $this->addOption(
            'skip',
            's',
            InputOption::VALUE_REQUIRED,
            'Start pagination from a specific offset (e.g. --skip=6300).'
        );

        $this->addOption(
            'fresh',
            null,
            InputOption::VALUE_NONE,
            'Start fresh from offset 0, ignoring any saved checkpoint.'
        );
    }
}
