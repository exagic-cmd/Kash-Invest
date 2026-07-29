<?php

namespace Botble\RealEstate\Models;

use Botble\Base\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
        'name',
        'slug',
        'template',
        'is_published',
        'is_primary',
        'content',
    ];

    protected $casts = [
        'content' => 'array',
        'is_published' => 'bool',
        'is_primary' => 'bool',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * A URL-safe slug that is unique within the project (the public URL is
     * /landing/{project}/{slug}, so the project id already scopes it).
     * Falls back to a generic base when the name has no sluggable characters.
     */
    public static function generateSlug(string $name, int|string $projectId, int|string|null $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'page';
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->where('project_id', $projectId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    /**
     * Public URL for this landing page. The primary page keeps the bare
     * /landing/{project} form so ad links created before multi-page support
     * still resolve.
     */
    public function getUrlAttribute(): string
    {
        if ($this->is_primary || empty($this->slug)) {
            return route('landing.page', $this->project_id);
        }

        return route('landing.page.slug', [$this->project_id, $this->slug]);
    }
}
