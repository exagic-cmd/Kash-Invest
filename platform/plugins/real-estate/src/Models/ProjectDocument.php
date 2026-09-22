<?php

namespace Botble\RealEstate\Models;

use Botble\Base\Models\BaseModel;
use Botble\Media\Facades\RvMedia;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDocument extends BaseModel
{
    protected $table = 're_project_documents';

    protected $fillable = [
        'project_id',
        'external_id',
        'name',
        'document_type',
        'file_url',
        'local_path',
        'is_historical',
    ];

    protected $casts = [
        'is_historical' => 'bool',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Prefer our own copy; fall back to the Redbricks CDN link when the download
     * failed or has not run yet, so a document is never unreachable just because
     * the local fetch did not happen.
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->local_path
            ? RvMedia::url($this->local_path)
            : $this->file_url);
    }
}
