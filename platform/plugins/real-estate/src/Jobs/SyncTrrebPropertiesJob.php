<?php

namespace Botble\RealEstate\Jobs;

use Botble\RealEstate\Models\ProjectSyncLog;
use Botble\RealEstate\Services\Trreb\TrrebApiException;
use Botble\RealEstate\Services\Trreb\TrrebPropertySyncer;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs the TRREB/PROPTX property sync off the request cycle.
 *
 * The ProjectSyncLog row is created FIRST — before the config and credential
 * checks — so that a misconfigured run still shows up on the admin API Sync page
 * as a failed run within seconds. The Buildify command returned early on a
 * missing key and never wrote a row, which left the page spinning "Running..."
 * with nothing to report; that is a compliance risk here, because a sync that
 * dies unnoticed silently breaches the 24-hour refresh requirement.
 *
 * IDX COMPLIANCE: never log a listing payload. Counts, ListingKeys and status
 * codes only.
 */
class SyncTrrebPropertiesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Image downloads across a large catalog take a long time. Zero means the
     * worker will not kill the job mid-run; run the worker with --timeout=0 too.
     */
    public int $timeout = 0;

    public int $tries = 1;

    public function __construct(public string $triggeredBy = 'manual')
    {
    }

    public function handle(TrrebPropertySyncer $syncer): void
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $log = ProjectSyncLog::query()->create([
            'source' => TrrebPropertySyncer::SOURCE,
            'status' => 'running',
            'triggered_by' => $this->triggeredBy === 'cron' ? 'cron' : 'manual',
            'started_at' => Carbon::now(),
        ]);

        Log::info('[TRREB Sync] Started', ['log_id' => $log->id, 'trigger' => $log->triggered_by]);

        if (! config('plugins.real-estate.trreb.enabled', config('plugins.real-estate.treeb.enabled'))) {
            $this->fail($log, 'TRREB sync is disabled (TRREB_SYNC_ENABLED=false).');

            return;
        }

        if (! config('plugins.real-estate.trreb.api_key', config('plugins.real-estate.treeb.api_key'))) {
            $this->fail($log, 'No PROPTX API token configured. Set TRREB_API_KEY in .env.');

            return;
        }

        try {
            $result = $syncer->sync();
        } catch (TrrebApiException $e) {
            $this->fail($log, $e->isAuthFailure()
                ? 'PROPTX rejected the API token (HTTP ' . $e->getStatus() . '). Check TRREB_API_KEY.'
                : $e->getMessage());

            return;
        } catch (\Throwable $e) {
            // Message only — an exception from deeper in the stack could otherwise
            // carry listing content into the log table.
            $this->fail($log, $e->getMessage());

            report($e);

            return;
        }

        $messages = [];

        if ($result['failed'] > 0) {
            $messages[] = $result['failed'] . ' listing(s) failed to import — see the application log.';
        }

        if (($result['delisted'] ?? 0) > 0) {
            $messages[] = sprintf(
                '%d listing(s) left the market and were %s.',
                $result['delisted'],
                config('plugins.real-estate.trreb.delisted_action', config('plugins.real-estate.treeb.delisted_action')) === 'delete' ? 'deleted' : 'hidden'
            );
        }

        if ($warning = $result['currency_warning'] ?? null) {
            $messages[] = sprintf(
                'Currency "%s" is not configured, so prices fall back to the site default — add it under Real Estate → Currencies.',
                $warning
            );
        }

        if ($result['cap_reached']) {
            $messages[] = sprintf(
                'Test cap: stopped after %d records (TRREB_MAX_RECORDS). Set it to 0 for a full sync.',
                (int) config('plugins.real-estate.trreb.max_records', config('plugins.real-estate.treeb.max_records'))
            );
        }

        $log->update([
            'status' => 'success',
            'created' => $result['created'],
            'updated' => $result['updated'],
            'unchanged' => $result['unchanged'],
            'failed' => $result['failed'],
            'total' => $result['created'] + $result['updated'] + $result['unchanged'] + $result['failed'],
            'message' => $messages === [] ? null : implode(' ', $messages),
            'finished_at' => Carbon::now(),
        ]);

        foreach ($result['items'] as $item) {
            $log->items()->create($item);
        }

        Log::info('[TRREB Sync] Completed', [
            'log_id' => $log->id,
            'created' => $result['created'],
            'updated' => $result['updated'],
            'unchanged' => $result['unchanged'],
            'failed' => $result['failed'],
            'cap_reached' => $result['cap_reached'],
        ]);
    }

    /**
     * Called by the queue when the job blows up outside handle()'s own guards,
     * so the log row never stays stuck on "running".
     */
    public function failed(\Throwable $e): void
    {
        $log = ProjectSyncLog::query()
            ->whereIn('source', TrrebPropertySyncer::SOURCES)
            ->where('status', 'running')
            ->latest('id')
            ->first();

        if ($log) {
            $this->fail($log, $e->getMessage());
        }
    }

    protected function fail(ProjectSyncLog $log, string $message): void
    {
        $log->update([
            'status' => 'failed',
            'message' => $message,
            'finished_at' => Carbon::now(),
        ]);

        Log::error('[TRREB Sync] Failed', ['log_id' => $log->id, 'message' => $message]);
    }
}
