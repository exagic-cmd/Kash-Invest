<?php

namespace Theme\Homzen\Http\Controllers;

use Botble\RealEstate\Models\Project;
use Botble\Theme\Facades\Theme;
use Illuminate\Routing\Controller;
use Theme\Homzen\Support\LandingData;

/**
 * Renders a landing template for a real project.
 *
 * This exists so both templates are viewable now, ahead of the admin
 * "assign template to project" feature. The page is fully project-driven:
 * /landing-preview/dark/57 renders project 57 and nothing else. When the
 * assignment feature lands it calls the exact same two lines — resolve the
 * project, hand LandingData to the chosen template.
 */
class LandingPreviewController extends Controller
{
    public function show(string $theme, ?string $project = null)
    {
        abort_unless(in_array($theme, ['dark', 'light'], true), 404);

        $model = $this->resolveProject($project);

        abort_if(! $model, 404, 'No project available to preview.');

        return view(Theme::getThemeNamespace('views.landing.' . $theme), [
            'landing' => LandingData::fromProject($model),
        ]);
    }

    protected function resolveProject(?string $project): ?Project
    {
        $with = ['investor', 'features', 'facilities', 'customFields', 'city', 'state', 'country', 'categories'];

        if ($project) {
            return Project::query()->with($with)->find($project);
        }

        // No id given: fall back to the most complete project we have, so the
        // preview shows the templates carrying real data rather than blanks.
        return Project::query()
            ->with($with)
            ->whereNotNull('price_from')
            ->whereNotNull('images')
            ->orderByDesc('id')
            ->first()
            ?? Project::query()->with($with)->orderByDesc('id')->first();
    }
}
