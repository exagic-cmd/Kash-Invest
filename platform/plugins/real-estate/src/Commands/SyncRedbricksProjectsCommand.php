<?php

namespace Botble\RealEstate\Commands;

use Botble\RealEstate\Models\ProjectSyncLog;
use Botble\RealEstate\Services\Redbricks\RedbricksProjectSyncer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

#[AsCommand('cms:redbricks:sync-projects', 'Sync projects from the Redbricks API')]
class SyncRedbricksProjectsCommand extends Command
{
    public function handle(RedbricksProjectSyncer $syncer): int
    {
        // Image downloads plus the client's own rate pacing put a full run well
        // past PHP's default 300s limit.
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        Log::info('[Redbricks Sync] Starting at ' . Carbon::now()->toDateTimeString());

        if (! config('plugins.real-estate.redbricks.enabled')) {
            $this->components->info('Redbricks sync is disabled (REDBRICKS_SYNC_ENABLED=false).');

            return self::SUCCESS;
        }

        if (! config('plugins.real-estate.redbricks.api_key')) {
            $this->components->error('No Redbricks API key configured. Set REDBRICKS_API_KEY in .env.');

            return self::FAILURE;
        }

        $log = ProjectSyncLog::query()->create([
            'source' => RedbricksProjectSyncer::SOURCE,
            'status' => 'running',
            'triggered_by' => $this->option('trigger') === 'cron' ? 'cron' : 'manual',
            'started_at' => Carbon::now(),
        ]);

        $this->components->info('Fetching projects from Redbricks...');

        // The rate ceiling is per team, so another consumer can throttle us even
        // when our own pacing is correct. Say so rather than appearing to hang.
        // Must be the syncer's own client — app() would build a second instance.
        $syncer->client()->onThrottle(function (int $seconds, int $attempt): void {
            $this->components->warn(sprintf(
                'Rate limited by Redbricks — waiting %ds before retry %d.',
                $seconds,
                $attempt
            ));
        });

        try {
            $result = $syncer->sync(function (int $page, int $pages, int $total): void {
                $this->components->task(
                    sprintf('Page %d/%d (%d projects total)', $page, $pages, $total),
                    fn () => true
                );
            }, (bool) $this->option('full'));
        } catch (Throwable $e) {
            Log::error('[Redbricks Sync] Aborted: ' . $e->getMessage(), ['exception' => $e]);
            $this->components->error('Redbricks sync failed: ' . $e->getMessage());

            $log->update([
                'status' => 'failed',
                'message' => $e->getMessage(),
                'finished_at' => Carbon::now(),
            ]);

            return self::FAILURE;
        }

        $unchanged = $result['unchanged'] ?? 0;

        $message = null;

        if ($result['failed'] > 0) {
            $message = $result['failed'] . ' project(s) failed to import — see the application log.';
        } elseif (! empty($result['cap_reached'])) {
            $message = sprintf(
                'Test cap: stopped after %d records (REDBRICKS_MAX_RECORDS). Set it to 0 for a full sync.',
                (int) config('plugins.real-estate.redbricks.max_records')
            );
        }

        $this->newLine();
        $this->components->success(sprintf(
            'Redbricks sync complete — %d created, %d updated, %d unchanged, %d failed.',
            $result['created'],
            $result['updated'],
            $unchanged,
            $result['failed'],
        ));

        $log->update([
            'status' => 'success',
            'created' => $result['created'],
            'updated' => $result['updated'],
            'unchanged' => $unchanged,
            'failed' => $result['failed'],
            'total' => $result['created'] + $result['updated'] + $unchanged + $result['failed'],
            'message' => $message,
            'finished_at' => Carbon::now(),
        ]);

        foreach ($result['items'] ?? [] as $item) {
            $log->items()->create($item);
        }

        if ($result['failed'] > 0) {
            $this->components->warn('Some projects failed to import; see the log for details.');
        }

        Log::info('[Redbricks Sync] Completed', [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'unchanged' => $unchanged,
            'failed' => $result['failed'],
            'errors' => $result['errors'],
            'completed_at' => Carbon::now()->toDateTimeString(),
        ]);

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
            'full',
            null,
            InputOption::VALUE_NONE,
            'Ignore the incremental cutoff and re-pull every project. Use after a mapping change.'
        );
    }
}
