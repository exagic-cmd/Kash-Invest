<?php

namespace Botble\RealEstate\Commands;

use Botble\RealEstate\Models\ProjectSyncLog;
use Botble\RealEstate\Services\Buildify\BuildifyProjectSyncer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('cms:buildify:sync-projects', 'Sync projects from the Buildify API')]
class SyncBuildifyProjectsCommand extends Command
{
    public function handle(BuildifyProjectSyncer $syncer): int
    {
        // Image downloads across the full catalog can exceed PHP's default 300s limit.
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        Log::info('[Buildify Sync] Starting at ' . Carbon::now()->toDateTimeString());

        if (! config('plugins.real-estate.buildify.enabled')) {
            $this->components->info('Buildify sync is disabled (BUILDIFY_SYNC_ENABLED=false).');

            return self::SUCCESS;
        }

        if (! config('plugins.real-estate.buildify.api_key')) {
            $this->components->error('No Buildify API key configured. Set BUILDIFY_API_KEY in .env.');

            return self::FAILURE;
        }

        // Record this run so the admin "API Sync" page can show what it pulled.
        $log = ProjectSyncLog::query()->create([
            'source' => BuildifyProjectSyncer::SOURCE,
            'status' => 'running',
            'triggered_by' => $this->option('trigger') === 'cron' ? 'cron' : 'manual',
            'started_at' => Carbon::now(),
        ]);

        $this->components->info('Fetching projects from Buildify...');

        try {
            $result = $syncer->sync(function (int $page, int $pages, int $total): void {
                $this->components->task(
                    sprintf('Page %d/%d (%d projects total)', $page, $pages, $total),
                    fn () => true
                );
            });
        } catch (\Throwable $e) {
            Log::error('[Buildify Sync] Aborted: ' . $e->getMessage(), ['exception' => $e]);
            $this->components->error('Buildify sync failed: ' . $e->getMessage());

            $log->update([
                'status' => 'failed',
                'message' => $e->getMessage(),
                'finished_at' => Carbon::now(),
            ]);

            return self::FAILURE;
        }

        $unchanged = $result['unchanged'] ?? 0;

        $this->newLine();
        $this->components->success(sprintf(
            'Buildify sync complete — %d created, %d updated, %d unchanged, %d failed.',
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
            'message' => $result['failed'] > 0
                ? ($result['failed'] . ' listing(s) failed to import — see the application log.')
                : null,
            'finished_at' => Carbon::now(),
        ]);

        // Store the per-project breakdown (created/updated/failed) for the
        // admin "Details" modal. Unchanged projects are counted, not stored.
        foreach ($result['items'] ?? [] as $item) {
            $log->items()->create($item);
        }

        if ($result['failed'] > 0) {
            $this->components->warn('Some listings failed to import; see the log for details.');
        }

        Log::info('[Buildify Sync] Completed', [
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
    }
}
