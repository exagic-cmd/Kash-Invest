<?php

namespace Botble\RealEstate\Commands;

use Botble\RealEstate\Jobs\DownloadRedbricksProjectMediaJob;
use Botble\RealEstate\Jobs\HydrateRedbricksProjectDetailsJob;
use Botble\RealEstate\Models\Project;
use Botble\RealEstate\Services\Redbricks\RedbricksProjectSyncer;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Enqueue details + media hydration jobs for existing Redbricks projects.
 *
 * The fast listing-only sync (cms:redbricks:sync-projects) enqueues these
 * automatically for anything it touches. This command exists for two cases
 * the sync will not cover:
 *
 *   - a first-time rollout where thousands of rows already exist from the
 *     old single-pass syncer and need their timestamps backfilled;
 *   - a targeted re-hydration (--force) after a schema change or a bad run
 *     left some rows with stale sub-resources.
 *
 * By default only rows with a null timestamp are enqueued, so repeated runs
 * are safe.
 */
#[AsCommand('cms:redbricks:hydrate', 'Enqueue details + media hydration jobs for Redbricks projects')]
class HydrateRedbricksProjectsCommand extends Command
{
    public function handle(): int
    {
        @set_time_limit(0);

        $details = $this->enqueue('details');
        $media = $this->enqueue('media');

        $this->components->success(sprintf(
            'Enqueued %d details job(s) and %d media job(s).',
            $details,
            $media
        ));

        $this->components->info('Drain the queues with:');
        $this->components->bulletList([
            'php artisan queue:work --queue=' . RedbricksProjectSyncer::DETAILS_QUEUE
                . ' --tries=3 --timeout=0 --sleep=1   (ONE process — API rate limit is per team)',
            'php artisan queue:work --queue=' . RedbricksProjectSyncer::MEDIA_QUEUE
                . ' --tries=3 --timeout=0 --sleep=1     (safe to run multiple in parallel)',
        ]);

        return self::SUCCESS;
    }

    /**
     * Enqueue one pass for every project that still owes it (or all of them
     * when --force is given). Returns how many jobs were dispatched.
     */
    protected function enqueue(string $pass): int
    {
        $column = $pass === 'details' ? 'details_synced_at' : 'media_synced_at';
        $jobClass = $pass === 'details'
            ? HydrateRedbricksProjectDetailsJob::class
            : DownloadRedbricksProjectMediaJob::class;
        $queue = $pass === 'details'
            ? RedbricksProjectSyncer::DETAILS_QUEUE
            : RedbricksProjectSyncer::MEDIA_QUEUE;

        $query = Project::query()
            ->where('source', RedbricksProjectSyncer::SOURCE);

        if (! $this->option('force')) {
            $query->whereNull($column);
        }

        $count = 0;

        // Cursor so an 8k row sweep does not need to materialise every project
        // into memory just to hand its id to the queue.
        foreach ($query->select('id')->cursor() as $project) {
            $jobClass::dispatch($project->getKey())->onQueue($queue);
            $count++;
        }

        return $count;
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Re-enqueue every Redbricks project, including those already hydrated.'
        );
    }
}
