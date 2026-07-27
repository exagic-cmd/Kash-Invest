<?php

namespace Theme\Homzen\Http\Controllers;

use Botble\RealEstate\Models\Project;
use Botble\Theme\Facades\Theme;
use Illuminate\Routing\Controller;
use Theme\Homzen\Support\LandingData;

/**
 * Serves a project's assigned landing page at its own dedicated URL.
 *
 * The URL is intentionally NOT linked anywhere on the website — landing pages
 * exist solely for Google Ads traffic. The only entry point is the "Preview"
 * / "Copy Link" buttons in the admin Featured Projects table.
 *
 * Rules enforced here:
 *  - Only renders the Light template (the dark template is discontinued).
 *  - Only renders projects that have an assigned landing page
 *    (re_projects.landing_template = 'light') AND are published.
 *  - Anything else 404s, so dead/unpublished URLs can never be reached even
 *    if a stale ad URL is clicked.
 */
class LandingPageController extends Controller
{
    /**
     * @param  string|null  $slug  which campaign page; null = the project's default page
     */
    public function show(int|string $project, ?string $slug = null)
    {
        $model = Project::query()
            ->whereKey($project)
            ->with(['investor', 'features', 'facilities', 'customFields', 'city', 'state', 'country', 'categories', 'landingPage'])
            ->first();

        abort_unless($model, 404);

        // Must be assigned a landing page.
        abort_unless($model->landing_template === 'light', 404);

        // A slug selects one specific campaign page; without one we serve the
        // project's default page (the pre-multi-page behaviour).
        $landingPage = $slug
            ? $model->landingPages()->where('slug', $slug)->first()
            : $model->landingPage;

        abort_unless($landingPage, 404);

        // Admins can preview unpublished pages via ?preview=1 (linked from the
        // edit screen). Public visitors and ad traffic get a 404 for drafts.
        $isPreview = (bool) request()->query('preview');

        if (! $isPreview) {
            abort_unless($landingPage->is_published !== false, 404);
        }

        return view(Theme::getThemeNamespace('views.landing.light'), [
            'landing' => LandingData::fromProject($model, $landingPage),
        ]);
    }
}
