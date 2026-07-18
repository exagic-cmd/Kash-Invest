<?php

namespace Botble\RealEstate\Models;

use Botble\Base\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Editable content overrides for a project's preconstruction landing page.
 *
 * One row per project. The public landing page is still built by
 * LandingData::fromProject() from the project's own data; this row's `content`
 * JSON supplies per-section overrides (hero copy, logos, banners, gallery, ...)
 * that layer on top of those derived defaults. A blank field falls back to the
 * derived value, so a project without a row renders exactly as before.
 */
class ProjectLandingPage extends BaseModel
{
    protected $table = 're_project_landing_pages';

    protected $fillable = [
        'project_id',
        'template',
        'is_published',
        'content',
    ];

    protected $casts = [
        'content' => 'array',
        'is_published' => 'bool',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
