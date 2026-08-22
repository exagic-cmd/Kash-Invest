<?php

namespace Botble\RealEstate\Commands;

use Botble\RealEstate\Jobs\SyncTreebPropertiesJob;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('cms:treeb:sync-properties', 'Sync properties from the TRREB/PROPTX IDX feed')]
class SyncTreebPropertiesCommand extends Command
{
    public function handle(): int
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $trigger = $this->option('trigger') === 'cron' ? 'cron' : 'manual';
        $job = new SyncTreebPropertiesJob($trigger);

        if ($this->option('now')) {
            // Run inline. Useful from the CLI when there is no worker up; the
            // admin page never uses this path.
            $this->components->info('Running the Treeb sync inline...');
            dispatch_sync($job);
            $this->components->success('Treeb sync finished. See Admin -> API Sync for the result.');

            return self::SUCCESS;
        }

        dispatch($job);

        if (config('queue.default') === 'sync') {
            $this->components->warn(
                'QUEUE_CONNECTION=sync, so this ran inline rather than on a worker. '
                . 'Set QUEUE_CONNECTION=database and run "php artisan queue:work" for real background syncs.'
            );
        } else {
            $this->components->info('Treeb sync queued. See Admin -> API Sync for progress.');
        }

        return self::SUCCESS;
    }

    protected function configure(): void
    {
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
    }
}
