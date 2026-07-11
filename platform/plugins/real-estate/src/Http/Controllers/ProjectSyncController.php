<?php

namespace Botble\RealEstate\Http\Controllers;

use Botble\Base\Facades\PageTitle;
use Botble\Base\Http\Controllers\BaseController;
use Botble\RealEstate\Models\Project;
use Botble\RealEstate\Models\ProjectSyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Admin "API Sync" page — run each API sync on demand and see what it pulled.
 * Sources are dynamic (via the `real_estate_api_sync_sources` filter), so a new
 * API integration appears here on its own once it registers itself.
 */
class ProjectSyncController extends BaseController
{
    public function index()
    {
        PageTitle::setTitle(trans('plugins/real-estate::api-sync.name'));

        $sources = $this->sources();

        $logs = ProjectSyncLog::query()->latest('id')->limit(25)->get();

        return view('plugins/real-estate::api-sync.index', compact('sources', 'logs'));
    }

    public function run(Request $request): JsonResponse
    {
        $key = (string) $request->input('source');
        $sources = $this->sources();

        if (! isset($sources[$key])) {
            return response()->json(['error' => true, 'message' => trans('plugins/real-estate::api-sync.unknown_source')], 422);
        }

        // The sync is long-running (paging + image downloads). Keep it going even
        // if the browser navigates away or the connection drops — the page tracks
        // progress by polling the sync log, not this response.
        @ignore_user_abort(true);
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        Artisan::call($sources[$key]['command'], ['--trigger' => 'manual']);

        $log = ProjectSyncLog::query()->where('source', $key)->latest('id')->first();

        return response()->json([
            'error' => false,
            'message' => trans('plugins/real-estate::api-sync.run_finished'),
            'data' => $this->presentLog($log),
        ]);
    }

    public function status(): JsonResponse
    {
        $data = [];

        foreach (array_keys($this->sources()) as $key) {
            $log = ProjectSyncLog::query()->where('source', $key)->latest('id')->first();
            $data[$key] = $this->presentLog($log);
        }

        return response()->json(['error' => false, 'data' => $data]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function sources(): array
    {
        $sources = [
            'buildify' => [
                'key' => 'buildify',
                'label' => 'Buildify',
                'command' => 'cms:buildify:sync-projects',
                'enabled' => (bool) config('plugins.real-estate.buildify.enabled'),
                'schedule' => trans('plugins/real-estate::api-sync.daily_at', ['time' => '2:00 AM']),
                'meta' => [
                    trans('plugins/real-estate::api-sync.province') => strtoupper((string) config('plugins.real-estate.buildify.province')),
                    trans('plugins/real-estate::api-sync.version') => (string) config('plugins.real-estate.buildify.version'),
                ],
                'projects_count' => Project::query()->where('source', 'buildify')->count(),
                'projects_url' => route('project.index'),
                'last_log' => $this->presentLog(
                    ProjectSyncLog::query()->where('source', 'buildify')->latest('id')->first()
                ),
            ],
        ];

        return apply_filters('real_estate_api_sync_sources', $sources);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function presentLog(?ProjectSyncLog $log): ?array
    {
        if (! $log) {
            return null;
        }

        return [
            'id' => $log->id,
            'status' => $log->status,
            'created' => $log->created,
            'updated' => $log->updated,
            'failed' => $log->failed,
            'triggered_by' => $log->triggered_by,
            'message' => $log->message,
            'finished_at' => $log->finished_at?->diffForHumans(),
            'duration' => $log->duration_label,
        ];
    }
}
