<?php

namespace Botble\RealEstate\Models;

use Botble\Base\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One project a sync run created / updated / failed on, with a field-level
 * breakdown of what changed (see ProjectSyncLog::items()).
 */
class ProjectSyncLogItem extends BaseModel
{
    protected $table = 're_project_sync_log_items';

    protected $fillable = [
        'sync_log_id',
        'project_id',
        'property_id',
        'external_id',
        'name',
        'action',
        'change_set',
    ];

    protected $casts = [
        'project_id' => 'int',
        'property_id' => 'int',
        'change_set' => 'array',
    ];

    public function syncLog(): BelongsTo
    {
        return $this->belongsTo(ProjectSyncLog::class, 'sync_log_id');
    }

    /**
     * The field-level diffs for an updated row: list of {field, from, to}.
     *
     * @return array<int, array{field: string, from: ?string, to: ?string}>
     */
    public function getFieldChangesAttribute(): array
    {
        return (array) ($this->change_set['fields'] ?? []);
    }

    public function getErrorAttribute(): ?string
    {
        return $this->change_set['error'] ?? null;
    }
}
