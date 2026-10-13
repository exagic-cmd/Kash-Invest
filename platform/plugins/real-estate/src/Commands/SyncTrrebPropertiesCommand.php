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
        $job = new SyncTrrebPropertiesJob($trigger, $full);

        if ($this->option('now')) {
            $this->components->info(sprintf(
                'Running the TRREB sync inline (%s)...',
                $full ? 'FULL feed' : 'incremental'
            ));

            $syncer = app(TrrebPropertySyncer::class);
            $result = $syncer->sync(function (int $page, int $processed, int $total): void {
                $this->components->task(
                    sprintf('Page %d (%d/%d listings processed)', $page, $processed, $total)
                );
            }, full: $full);

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
    }
}
