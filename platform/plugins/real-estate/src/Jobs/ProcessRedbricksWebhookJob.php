<?php

namespace Botble\RealEstate\Jobs;

use Botble\RealEstate\Services\Redbricks\RedbricksApiException;
use Botble\RealEstate\Services\Redbricks\RedbricksProjectSyncer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes one Redbricks webhook delivery off the request thread.
 *
 * The controller validates the signature and dispatches this immediately so
 * that Redbricks sees a sub-second 2xx and never retries on slow processing.
 * The actual API re-pull (which is rate-limited and may stall on 429) happens
 * here on the queue.
 *
 * RATE LIMIT
 *   Shares Redbricks' 60/min team ceiling with the scheduled sync and the
 *   details hydration job. Routed to the dedicated details queue so the
 *   single-worker constraint continues to hold — adding a second worker
 *   anywhere on that queue would silently breach the ceiling.
 *
 * EVENT SHAPE
 *   The event string is treated as a dotted prefix, so "project.created",
 *   "project.updated" and anything newer under "project.*" all route to a
 *   refresh. "project.deleted" / ".removed" / ".archived" route to a delete.
 *   Floor plan / document events also route to a refresh of the owning
 *   project, since those sub-resources are hydrated through the same code
 *   path as the project itself.
 */
class ProcessRedbricksWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 0;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public string $event,
        public string $externalId,
        public ?string $deliveryId = null,
    ) {
        $this->onQueue(RedbricksProjectSyncer::DETAILS_QUEUE);
    }

    public function handle(RedbricksProjectSyncer $syncer): void
    {
        @set_time_limit(0);

        $event = strtolower($this->event);

        try {
            if ($this->isDeleteEvent($event)) {
                $removed = $syncer->deleteOne($this->externalId);

                Log::info('[Redbricks Webhook] Delete processed', [
                    'event' => $this->event,
                    'external_id' => $this->externalId,
                    'delivery_id' => $this->deliveryId,
                    'removed' => $removed,
                ]);

                return;
            }

            $project = $syncer->refreshOne($this->externalId);

            Log::info('[Redbricks Webhook] Refresh processed', [
                'event' => $this->event,
                'external_id' => $this->externalId,
                'delivery_id' => $this->deliveryId,
                'project_id' => $project?->getKey(),
            ]);
        } catch (RedbricksApiException $e) {
            if ($e->getCode() === 429) {
                Log::warning('[Redbricks Webhook] Quota exhausted; stopping job.', [
                    'event' => $this->event,
                    'external_id' => $this->externalId,
                    'delivery_id' => $this->deliveryId,
                    'message' => $e->getMessage(),
                ]);

                $this->fail($e);

                return;
            }

            throw $e;
        } catch (Throwable $e) {
            Log::warning('[Redbricks Webhook] Processing failed', [
                'event' => $this->event,
                'external_id' => $this->externalId,
                'delivery_id' => $this->deliveryId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function isDeleteEvent(string $event): bool
    {
        if (! str_starts_with($event, 'project.')) {
            return false;
        }

        foreach (['deleted', 'removed', 'archived'] as $suffix) {
            if (str_ends_with($event, '.' . $suffix)) {
                return true;
            }
        }

        return false;
    }
}
