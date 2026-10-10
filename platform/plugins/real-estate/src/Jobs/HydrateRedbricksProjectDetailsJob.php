<?php

namespace Botble\RealEstate\Jobs;

use Botble\RealEstate\Models\Project;
use Botble\RealEstate\Services\Redbricks\RedbricksApiException;
use Botble\RealEstate\Services\Redbricks\RedbricksProjectSyncer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pass 2 of the Redbricks staged ingest: fetch floorplans + documents for one
 * project (2 API requests) and write the sub-resource rows. No media downloads
 * — those happen in DownloadRedbricksProjectMediaJob after this one succeeds.
 *
 * RATE LIMIT
 *   Redbricks' ceiling is 60 requests/minute per TEAM, so this queue must be
 *   worked by exactly ONE process. RedbricksClient paces itself per instance,
 *   and a second worker would silently breach the ceiling — Horizon's balancer
 *   or two `queue:work --queue=redbricks-details` processes will do that. The
 *   command the user is told to run reflects this.
 *
 * UNIQUENESS
 *   ShouldBeUnique keyed on project id for 1 hour: a reconciliation sweep that
 *   re-enqueues an already-pending project should not double its API cost. The
 *   lock is auto-released when the job finishes or fails.
 */
class HydrateRedbricksProjectDetailsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** The job may sit through 429 waits inside the client; no hard timeout. */
    public int $timeout = 0;

    /** Transient API failures retry; a hard quota exhaustion ends the attempt. */
    public int $tries = 3;

    /** Backoff for retries after an unexpected throw (seconds). */
    public int $backoff = 60;

    public function __construct(public int $projectId)
    {
    }

    public function uniqueId(): string
    {
        return 'redbricks-details-' . $this->projectId;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(RedbricksProjectSyncer $syncer): void
    {
        @set_time_limit(0);

        $project = Project::query()
            ->where('source', RedbricksProjectSyncer::SOURCE)
            ->whereKey($this->projectId)
            ->first();

        if (! $project) {
            // The project was purged between enqueue and work-up. Nothing to do.
            return;
        }

        try {
            $syncer->hydrateDetails($project);
        } catch (RedbricksApiException $e) {
            // A spent subscription quota surfaces here as a 429 that cannot be
            // waited out. Fail the job so it moves to failed_jobs rather than
            // retrying every minute against a limit that will not clear until
            // the billing period rolls over.
            if ($e->getCode() === 429) {
                Log::warning('[Redbricks Hydrate] Quota exhausted; stopping job.', [
                    'project_id' => $this->projectId,
                    'message' => $e->getMessage(),
                ]);

                $this->fail($e);

                return;
            }

            throw $e;
        } catch (Throwable $e) {
            Log::warning('[Redbricks Hydrate] Details hydration failed', [
                'project_id' => $this->projectId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
