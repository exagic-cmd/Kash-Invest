<?php

namespace Botble\RealEstate\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\RealEstate\Forms\ProjectLandingPageForm;
use Botble\RealEstate\Models\Project;
use Botble\RealEstate\Models\ProjectLandingPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin "Landing Pages" page — assign a preconstruction landing page to a project
 * and edit its content like a CMS. Assignment lives on re_projects.landing_template
 * (so the public router keeps working); the editable overrides live on the
 * re_project_landing_pages row and are merged in by LandingData::fromProject().
 */
class ProjectLandingPageController extends BaseController
{
    public function index()
    {
        $this->pageTitle('Landing Pages');

        $assigned = Project::query()
            ->where('landing_template', 'light')
            ->with('landingPage')
            ->orderBy('name')
            ->get();

        return view('plugins/real-estate::landing-pages.index', compact('assigned'));
    }

    /**
     * Project search for the top search bar (name match).
     */
    public function search(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->input('q'));

        $projects = Project::query()
            ->when($keyword !== '', fn ($query) => $query->where('name', 'like', "%{$keyword}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'landing_template']);

        return response()->json([
            'error' => false,
            'data' => $projects->map(fn (Project $project) => [
                'id' => $project->getKey(),
                'name' => $project->name,
                'assigned' => $project->landing_template === 'light',
            ])->all(),
        ]);
    }

    /**
     * Assign a landing page to the chosen project, then jump to the editor.
     */
    public function assign(Request $request)
    {
        $request->validate(['project_id' => 'required|exists:re_projects,id']);

        $project = Project::query()->findOrFail($request->input('project_id'));
        $project->landing_template = 'light';
        $project->save();

        ProjectLandingPage::query()->firstOrCreate(
            ['project_id' => $project->getKey()],
            ['template' => 'light', 'is_published' => true],
        );

        return $this
            ->httpResponse()
            ->setNextUrl(route('real-estate.landing-pages.edit', $project->getKey()))
            ->setMessage('Featured project assigned. Now edit its content below.');
    }

    public function edit(int|string $id)
    {
        $project = Project::query()->findOrFail($id);

        $landingPage = ProjectLandingPage::query()->firstOrNew(['project_id' => $project->getKey()]);
        $landingPage->setRelation('project', $project);

        $this->pageTitle('Landing page — ' . $project->name);

        // renderForm() produces the whole admin page (its template extends the
        // layout), so return it directly — wrapping it in another blade view
        // silently discards that view's own content. The toolbar/notice live
        // inside the form (see ProjectLandingPageForm::toolbar()).
        return ProjectLandingPageForm::createFromModel($landingPage)->renderForm();
    }

    public function update(int|string $id, Request $request)
    {
        $project = Project::query()->findOrFail($id);

        $landingPage = ProjectLandingPage::query()->firstOrNew(['project_id' => $project->getKey()]);
        $landingPage->template = 'light';
        $landingPage->is_published = $request->boolean('is_published');
        $landingPage->content = $this->buildContent($request);
        $landingPage->save();

        // Keep the assignment in sync so the public router serves the landing page.
        if ($project->landing_template !== 'light') {
            $project->landing_template = 'light';
            $project->save();
        }

        return $this
            ->httpResponse()
            ->setNextUrl(route('real-estate.landing-pages.edit', $project->getKey()))
            ->withUpdatedSuccessMessage();
    }

    /**
     * Unassign: revert to the standard project page and drop the content row.
     */
    public function destroy(int|string $id)
    {
        $project = Project::query()->findOrFail($id);
        $project->landing_template = null;
        $project->save();

        ProjectLandingPage::query()->where('project_id', $project->getKey())->delete();

        return $this
            ->httpResponse()
            ->setNextUrl(route('real-estate.landing-pages.index'))
            ->withDeletedSuccessMessage();
    }

    /**
     * Assemble the flat editor fields into the section-keyed content JSON.
     * Repeater rows are kept in Botble's native [key][value] shape so they
     * round-trip unchanged; LandingData unwraps `.value` when reading them.
     *
     * @return array<string, mixed>
     */
    protected function buildContent(Request $request): array
    {
        return [
            'hero' => [
                'logos' => array_values(array_filter([$request->input('hero_logo') ?: \Illuminate\Support\Arr::first((array) $request->input('hero_logos', []))])),
                'heading' => $request->input('hero_heading'),
                'tagline' => $request->input('hero_tagline'),
                'badge' => $request->input('hero_badge'),
                'developer' => $request->input('hero_developer'),
                'price' => $request->input('hero_price'),
                'cta' => $request->input('hero_cta'),
                'banner' => $this->images($request->input('hero_banner', [])),
                'video' => $request->input('hero_video'),
            ],
            // Overview is text-only — no image field.
            'overview' => [
                'show' => $request->boolean('overview_show'),
                'heading' => $request->input('overview_heading'),
                'body' => $request->input('overview_body'),
            ],
            'quick_facts' => [
                'show' => $request->boolean('quick_facts_show'),
                'items' => $this->rows($request->input('quick_facts', [])),
            ],
            'cheat_sheet' => [
                'show' => $request->boolean('cheat_sheet_show'),
                'steps' => $this->rows($request->input('cheat_sheet_steps', [])),
                'hints' => $this->rows($request->input('cheat_sheet_hints', [])),
            ],
            'location' => [
                'show' => $request->boolean('location_show'),
                'address' => $request->input('location_address'),
                'intersection' => $request->input('location_intersection'),
                'neighbourhood' => $request->input('location_neighbourhood'),
                'lat' => $request->input('location_lat'),
                'lng' => $request->input('location_lng'),
                'benefits' => $this->rows($request->input('location_benefits', [])),
                'nearby' => $this->rows($request->input('location_nearby', [])),
            ],
            'why_us' => [
                'show' => $request->boolean('why_us_show'),
                'heading' => $request->input('why_us_heading'),
                'image' => $request->input('why_us_image'),
                'points' => $this->rows($request->input('why_us_points', [])),
            ],
            // Inner Circle is non-editable: only its on/off state is stored;
            // its content is fixed in LandingData.
            'inner_circle' => [
                'show' => $request->boolean('inner_circle_show'),
            ],
            // No standalone gallery/floor-plan sections on the page any more; these
            // images remain because the hero slider falls back to them.
            'gallery' => [
                'images' => $this->images($request->input('gallery_images', [])),
            ],
            'register' => [
                'show' => $request->boolean('register_show'),
                'heading' => $request->input('register_heading'),
                'lede' => $request->input('register_lede'),
            ],
            // 'legal' intentionally dropped — the site footer it fed is no longer
            // rendered; the Disclaimer card below carries that copy instead.
            'disclaimer' => [
                'show' => $request->boolean('disclaimer_show'),
                'logo' => $request->input('disclaimer_logo'),
                'text' => $request->input('disclaimer_text'),
                'copyright' => $request->input('disclaimer_copyright'),
            ],
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    protected function images($value): array
    {
        return array_values(array_filter((array) $value, fn ($item) => filled($item)));
    }

    /**
     * Drop repeater rows where every field is empty; keep the native shape.
     *
     * @param  mixed  $value
     * @return array<int, mixed>
     */
    protected function rows($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, function ($row): bool {
            if (! is_array($row)) {
                return false;
            }

            foreach ($row as $field) {
                $inner = is_array($field) ? ($field['value'] ?? null) : $field;
                if (filled($inner)) {
                    return true;
                }
            }

            return false;
        }));
    }
}
