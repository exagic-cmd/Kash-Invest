<?php

namespace Botble\RealEstate\Models;

use Botble\Base\Models\BaseModel;

/**
 * One row per run of an API sync (cron or manual), so the admin "API Sync"
 * page can show what each run pulled.
 */
class ProjectSyncLog extends BaseModel
{
    protected $table = 're_project_sync_logs';

    protected $fillable = [
        'source',
        'status',
        'triggered_by',
        'created',
        'updated',
        'failed',
        'total',
        'message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'created' => 'int',
        'updated' => 'int',
        'failed' => 'int',
        'total' => 'int',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Human-readable run duration, e.g. "1m 12s".
     */
    public function getDurationLabelAttribute(): ?string
    {
        if (! $this->started_at || ! $this->finished_at) {
            return null;
        }

        $seconds = max(0, $this->finished_at->diffInSeconds($this->started_at));

        if ($seconds < 60) {
            return $seconds . 's';
        }

        return intdiv($seconds, 60) . 'm ' . ($seconds % 60) . 's';
    }
}
