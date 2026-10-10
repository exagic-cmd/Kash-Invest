<?php

namespace Botble\RealEstate\Jobs;

use Botble\RealEstate\Models\Project;
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
 * Pass 3 of the Redbricks staged ingest: pull every CDN asset referenced by a
 * hydrated project (project gallery images, floor plan images, PDFs) into the
 * media library.
 *
 * NOT RATE-LIMITED BY REDBRICKS
 *   These are CDN URLs stored in raw_payload — they do not touch Redbricks'
 *   /api/v1 endpoints and do not count against the 60/min team ceiling. The
 *   queue runs with many workers in parallel; the only ceiling is the shared
 *   bandwidth and the S3/local-disk write throughput.
 *
 * UNIQUENESS
 *   Keyed on project id for 1 hour so a re-enqueue cannot start a second
 *   worker on the same project halfway through writing floor plan images.
 */
class DownloadRedbricksProjectMediaJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Media downloads for one project can be long; the worker owns the timeout. */
    public int $timeout = 0;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $projectId)
    {
    }

    public function uniqueId(): string
    {
        return 'redbricks-media-' . $this->projectId;
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
            return;
        }

        try {
            $syncer->hydrateMedia($project);
        } catch (Throwable $e) {
            Log::warning('[Redbricks Hydrate] Media download failed', [
                'project_id' => $this->projectId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
