<?php

namespace Botble\RealEstate\Commands;

use Botble\RealEstate\Services\Buildify\BuildifyProjectSyncer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Attribute\AsCommand;

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

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->success(sprintf(
            'Buildify sync complete — %d created, %d updated, %d failed.',
            $result['created'],
            $result['updated'],
            $result['failed'],
        ));

        if ($result['failed'] > 0) {
            $this->components->warn('Some listings failed to import; see the log for details.');
        }

        Log::info('[Buildify Sync] Completed', [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'failed' => $result['failed'],
            'errors' => $result['errors'],
            'completed_at' => Carbon::now()->toDateTimeString(),
        ]);

        return self::SUCCESS;
    }
}
