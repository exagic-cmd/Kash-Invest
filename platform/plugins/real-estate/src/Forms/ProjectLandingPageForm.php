<?php

namespace Botble\RealEstate\Forms;

use Botble\Base\Forms\FieldOptions\EditorFieldOption;
use Botble\Base\Forms\FieldOptions\MediaImageFieldOption;
use Botble\Base\Forms\FieldOptions\MediaImagesFieldOption;
use Botble\Base\Forms\FieldOptions\OnOffFieldOption;
use Botble\Base\Forms\FieldOptions\RepeaterFieldOption;
use Botble\Base\Forms\FieldOptions\TextareaFieldOption;
use Botble\Base\Forms\FieldOptions\TextFieldOption;
use Botble\Base\Forms\Fields\EditorField;
use Botble\Base\Forms\Fields\MediaImageField;
use Botble\Base\Forms\Fields\MediaImagesField;
use Botble\Base\Forms\Fields\OnOffField;
use Botble\Base\Forms\Fields\RepeaterField;
use Botble\Base\Forms\Fields\TextareaField;
use Botble\Base\Forms\Fields\TextField;
use Botble\Base\Forms\FormAbstract;
use Botble\RealEstate\Models\Project;
use Botble\RealEstate\Models\ProjectLandingPage;
use Illuminate\Support\Arr;

/**
 * The CMS editor for a project's landing page. Every section is editable and
 * every field is optional. When a landing page has no saved content yet, the
 * fields are seeded from the project's own data so the editor opens populated
 * (not blank). Repeater rows are stored in Botble's native `[key][value]` shape
 * so they round-trip without transformation; LandingData unwraps the `.value`.
 */
class ProjectLandingPageForm extends FormAbstract
{
    protected ?array $seedCache = null;

    public function setup(): void
    {
        /** @var ProjectLandingPage $model */
        $model = $this->getModel();
        $project = $model?->project;
        $saved = $model && is_array($model->content) ? $model->content : [];

        $this
            ->model(ProjectLandingPage::class)
            ->setUrl(route('real-estate.landing-pages.pages.update', [$project?->getKey(), $model?->getKey()]))
            ->setFormOption('method', 'PUT')

            // Top toolbar + info note. Lives inside the form because renderForm()
            // renders the whole admin page itself — content written around it in a
            // wrapper blade view never shows (its section gets clobbered).
            ->add('lp_toolbar', 'html', ['html' => $this->toolbar($project)])

            // ---- This page --------------------------------------------------
            // Identity of this campaign page. The slug is the URL segment, so a
            // project can carry several pages: /landing/{project}/{slug}.
            ->add('page_divider', 'html', ['html' => $this->heading('This Landing Page', 'Name it for your own reference; the URL slug is what appears in the public link.')])
            ->add('page_name', TextField::class, $this->text('Page Name (internal)', $model?->name))
            ->add('page_slug', TextField::class, TextFieldOption::make()
                ->label('URL Slug')
                ->value($model?->slug)
                ->helperText($project ? 'Public URL: ' . route('landing.page', $project->getKey()) . '/<slug>' : '')
                ->toArray())

            // ---- Hero -------------------------------------------------------
            ->add('hero_divider', 'html', ['html' => $this->heading('Hero')])
            ->add('hero_logo', MediaImageField::class, MediaImageFieldOption::make()
                ->label('Logo')
                ->value(Arr::first((array) $this->content('hero.logos', [])) ?: $this->content('hero.logo'))
                ->toArray())
            ->add('hero_heading', TextField::class, $this->text('Headline', $this->content('hero.heading')))
            ->add('hero_tagline', TextField::class, $this->text('Subheading', $this->content('hero.tagline')))
            ->add('hero_badge', TextField::class, $this->text('Status Badge', $this->content('hero.badge')))
            ->add('hero_developer', TextField::class, $this->text('Developer Name', $this->content('hero.developer')))
            ->add('hero_price', TextField::class, $this->text('Starting Price', $this->content('hero.price')))
            ->add('hero_cta', TextField::class, $this->text('Button Text', $this->content('hero.cta')))
            ->add('hero_banner[]', MediaImagesField::class, MediaImagesFieldOption::make()
                ->label('Background Slider Images')
                ->values($this->content('hero.banner', []))
                ->toArray())
            ->add('hero_video', TextField::class, $this->text('Background Video URL', $this->content('hero.video')))

            // ---- Overview ---------------------------------------------------
            ->add('overview_divider', 'html', ['html' => $this->heading('Overview')])
            ->add('overview_show', OnOffField::class, $this->toggle('Show the overview section', $this->content('overview.show', true)))
            ->add('overview_heading', TextField::class, $this->text('Heading', $this->content('overview.heading')))
            ->add('overview_body', EditorField::class, EditorFieldOption::make()
                ->label('Body Text')
                ->value($this->content('overview.body'))
                ->toArray())

            // ---- Quick facts ------------------------------------------------
            ->add('quick_facts_divider', 'html', ['html' => $this->heading('Quick Facts')])
            ->add('quick_facts_show', OnOffField::class, $this->toggle('Show the quick-facts section', $this->content('quick_facts.show', true)))
            ->add('quick_facts', RepeaterField::class, RepeaterFieldOption::make()
                ->label('Facts')
                ->fields([
                    'label' => $this->repeaterText('Label'),
                    'text' => $this->repeaterText('Value'),
                ])
                ->value($this->content('quick_facts.items', []))
                ->toArray())

            // ---- Cheat sheet ------------------------------------------------
            ->add('cheat_sheet_divider', 'html', ['html' => $this->heading('Cheat Sheet')])
            ->add('cheat_sheet_show', OnOffField::class, $this->toggle('Show the cheat-sheet section', $this->content('cheat_sheet.show', true)))
            ->add('cheat_sheet_steps', RepeaterField::class, RepeaterFieldOption::make()
                ->label('Steps')
                ->fields(['step' => $this->repeaterTextarea('Step')])
                ->value($this->content('cheat_sheet.steps', []))
                ->toArray())
            ->add('cheat_sheet_hints', RepeaterField::class, RepeaterFieldOption::make()
                ->label('Hints')
                ->fields(['hint' => $this->repeaterTextarea('Hint')])
                ->value($this->content('cheat_sheet.hints', []))
                ->toArray())

            // ---- Location ---------------------------------------------------
            ->add('location_divider', 'html', ['html' => $this->heading('Location')])
            ->add('location_show', OnOffField::class, $this->toggle('Show the location section', $this->content('location.show', true)))
            ->add('location_address', TextField::class, $this->text('Address', $this->content('location.address')))
            ->add('location_intersection', TextField::class, $this->text('Intersection', $this->content('location.intersection')))
            ->add('location_neighbourhood', TextField::class, $this->text('Neighbourhood', $this->content('location.neighbourhood')))
            ->add('location_lat', TextField::class, $this->text('Latitude (Map)', $this->content('location.lat')))
            ->add('location_lng', TextField::class, $this->text('Longitude (Map)', $this->content('location.lng')));

            $locationBenefits = $this->content('location.benefits', []);
            if (empty($locationBenefits)) {
                $rawBenefits = [];
                if ($intersection = $this->content('location.intersection')) {
                    $rawBenefits[] = ['point' => 'Located at ' . $intersection];
                }
                if ($neighbourhood = $this->content('location.neighbourhood')) {
                    $rawBenefits[] = ['point' => 'In the ' . $neighbourhood . ' neighbourhood'];
                }
                $nearby = $this->content('location.nearby', []);
                if (is_array($nearby)) {
                    foreach ($nearby as $place) {
                        $name = $place['name'] ?? null;
                        $distance = $place['distance'] ?? null;
                        if ($name) {
                            $rawBenefits[] = ['point' => $distance ? $name . ' — ' . $distance : $name];
                        }
                    }
                }
                if ($project) {
                    $city = $project->city?->exists ? $project->city->name : null;
                    $state = $project->state?->exists ? $project->state->name : null;
                    $shortAddress = trim(implode(', ', array_filter([$city, $state]))) ?: null;
                    if ($shortAddress) {
                        $rawBenefits[] = ['point' => 'Minutes from the heart of ' . ($city ?: $shortAddress)];
                    }
                }
                $locationBenefits = static::repeaterize($rawBenefits);
            }

            $this
            ->add('location_benefits', RepeaterField::class, RepeaterFieldOption::make()
                ->label('Location Benefits')
                ->fields(['point' => $this->repeaterText('Benefit')])
                ->value($locationBenefits)
                ->toArray())
            ->add('location_nearby', RepeaterField::class, RepeaterFieldOption::make()
                ->label('Nearby Places')
                ->fields([
                    'name' => $this->repeaterText('Place'),
                    'distance' => $this->repeaterText('Distance'),
                ])
                ->value($this->content('location.nearby', []))
                ->toArray());

            // ---- Why Us -----------------------------------------------------
            $whyUsHeading = $this->content('why_us.heading', 'Why Kash Invest');
            $whyUsPoints = $this->content('why_us.points', []);
            if (empty($whyUsPoints)) {
                $whyUsPoints = static::repeaterize([
                    ['point' => 'High Return on Investment (ROI)'],
                    ['point' => 'Premium Locations'],
                    ['point' => 'Secure & Transparent'],
                    ['point' => 'Expert Guidance'],
                    ['point' => 'End-to-End Management'],
                ]);
            }

            $this
            ->add('why_us_divider', 'html', ['html' => $this->heading('Why Us')])
            ->add('why_us_show', OnOffField::class, $this->toggle('Show the "Why Us" section', $this->content('why_us.show', true)))
            ->add('why_us_heading', TextField::class, $this->text('Heading', $whyUsHeading))
            ->add('why_us_image', MediaImageField::class, MediaImageFieldOption::make()
                ->label('Image')
                ->value($this->content('why_us.image'))
                ->toArray())
            ->add('why_us_points', RepeaterField::class, RepeaterFieldOption::make()
                ->label('Bullet Points')
                ->fields(['point' => $this->repeaterText('Point')])
                ->value($whyUsPoints)
                ->toArray());

            // ---- Inner Circle -----------------------------------------------
            // Fixed, non-editable "Join our inner circle" block. It is OFF by
            // default; the only control is the toggle to turn the standard block
            // on. Its heading, items and button are hard-coded in LandingData.
            $this
            ->add('inner_circle_divider', 'html', ['html' => $this->heading('Inner Circle', 'A fixed call-to-action block. Turn it on to show the standard "Join our inner circle" section — there is nothing to edit.')])
            ->add('inner_circle_show', OnOffField::class, $this->toggle('Show the "Inner Circle" section', $this->content('inner_circle.show', false)));

            $this
            // ---- Hero background images -------------------------------------
            ->add('gallery_divider', 'html', ['html' => $this->heading('Hero Background Images')])
            ->add('gallery_images[]', MediaImagesField::class, MediaImagesFieldOption::make()
                ->label('Images')
                ->values($this->content('gallery.images', []))
                ->toArray())

            // ---- Register ---------------------------------------------------
            ->add('register_divider', 'html', ['html' => $this->heading('Register Section')])
            ->add('register_show', OnOffField::class, $this->toggle('Show the register section', $this->content('register.show', true)))
            ->add('register_heading', TextField::class, $this->text('Heading', $this->content('register.heading')))
            ->add('register_lede', TextareaField::class, $this->textarea('Subheading / Description', $this->content('register.lede')))

            // ---- Footer Disclaimer Card -------------------------------------
            ->add('disclaimer_divider', 'html', ['html' => $this->heading('Footer Disclaimer Card')])
            ->add('disclaimer_show', OnOffField::class, $this->toggle('Show the disclaimer card', $this->content('disclaimer.show', true)))
            ->add('disclaimer_logo', MediaImageField::class, MediaImageFieldOption::make()
                ->label('Partner / Developer Logo')
                ->value($this->content('disclaimer.logo'))
                ->toArray())
            ->add('disclaimer_text', TextareaField::class, $this->textarea('Disclaimer Text', $this->content('disclaimer.text')))
            ->add('disclaimer_copyright', TextField::class, $this->text('Copyright Line', $this->content('disclaimer.copyright')))

            // Sidebar: the Publish toggle sits beside the Save box. Everything
            // above renders in the wide main column (fields before the break).
            ->add('is_published', OnOffField::class, OnOffFieldOption::make()
                ->label('Published (visible on the project URL)')
                ->value($model?->is_published ?? true)
                ->toArray())
            ->setBreakFieldPoint('is_published');
    }

    /**
     * A value for a field: the saved override if the landing page has content,
     * otherwise the value seeded from the project's own data (so a fresh editor
     * opens populated). Once saved, blanks stay blank — the seed no longer runs.
     */
    protected function content(string $key, mixed $default = null): mixed
    {
        $model = $this->getModel();
        $saved = $model && is_array($model->content) ? $model->content : [];

        if (Arr::has($saved, $key)) {
            return Arr::get($saved, $key, $default);
        }

        return Arr::get($this->seed(), $key, $default);
    }

    /**
     * The project's current content, mapped into this form's schema, used to
     * pre-fill a landing page that has no saved content yet.
     *
     * @return array<string, mixed>
     */
    protected function seed(): array
    {
        if ($this->seedCache !== null) {
            return $this->seedCache;
        }

        $project = $this->getModel()?->project;

        if (! $project instanceof Project) {
            return $this->seedCache = [];
        }

        // Prefer the theme's own derivation so the editor matches the live page.
        // Guarded so the form still works if the theme is swapped.
        $landingDataClass = 'Theme\\Homzen\\Support\\LandingData';

        if (! class_exists($landingDataClass)) {
            return $this->seedCache = [];
        }

        $landing = $landingDataClass::fromProject($project);

        return $this->seedCache = [
            'hero' => [
                'logos' => [],
                'heading' => $landing['name'] ?? null,
                'tagline' => $landing['tagline'] ?? null,
                'badge' => $landing['status'] ?? null,
                'developer' => Arr::get($landing, 'developer.name'),
                'price' => Arr::get($landing, 'price.fromFormatted'),
                'cta' => Arr::get($landing, 'cta.secondary'),
                'banner' => [],
                'video' => Arr::get($landing, 'hero.video'),
            ],
            'overview' => [
                'show' => true,
                'heading' => Arr::get($landing, 'overview.heading'),
                'body' => Arr::get($landing, 'overview.description'),
            ],
            'quick_facts' => [
                'show' => true,
                'items' => static::repeaterize(array_map(
                    fn ($fact) => ['label' => $fact['label'], 'text' => $fact['value']],
                    $landing['quickFacts'] ?? []
                )),
            ],
            'location' => [
                'show' => true,
                'address' => Arr::get($landing, 'location.address'),
                'intersection' => Arr::get($landing, 'location.intersection'),
                'neighbourhood' => Arr::get($landing, 'location.neighbourhood'),
                'lat' => Arr::get($landing, 'location.lat'),
                'lng' => Arr::get($landing, 'location.lng'),
                'nearby' => static::repeaterize(array_map(
                    fn ($place) => ['name' => $place['name'] ?? null, 'distance' => $place['distance'] ?? null],
                    Arr::get($landing, 'location.nearby', [])
                )),
            ],
            'why_us' => [
                'show' => true,
                'heading' => null,
                'image' => null,
                'points' => [],
            ],
            // Inner Circle is off by default and non-editable (fixed content).
            'inner_circle' => [
                'show' => false,
            ],
            'floor_plans' => [
                'show' => true,
                'items' => static::repeaterize(array_map(fn ($plan) => [
                    'type' => $plan['type'] ?? null,
                    'size' => $plan['size'] ?? null,
                    'baths' => $plan['baths'] ?? null,
                    'price' => $plan['price'] ?? null,
                    'image' => null,
                ], $landing['floorPlans'] ?? [])),
            ],
            // Gallery uses the project's RAW image paths (not the derived URLs),
            // matching how the media picker stores/renders values.
            'gallery' => [
                'show' => true,
                'images' => array_values(array_filter((array) $project->images)),
            ],
            'register' => ['show' => true],
            'legal' => [
                'renderings' => Arr::get($landing, 'legal.renderings'),
                'brokerage' => Arr::get($landing, 'legal.brokerage'),
                'pricing' => Arr::get($landing, 'legal.pricing'),
            ],
            'disclaimer' => [
                'show' => true,
                'logo' => null,
                'text' => null,
                'copyright' => null,
            ],
        ];
    }

    /**
     * Wrap a list of [key => value] rows into the repeater's [key => ['value' => …]] shape.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, array<string, mixed>>>
     */
    protected static function repeaterize(array $rows): array
    {
        return array_values(array_map(function (array $row): array {
            $out = [];
            foreach ($row as $key => $value) {
                $out[$key] = ['value' => $value];
            }

            return $out;
        }, $rows));
    }

    /**
     * The bar above the fields: back link, Copy Link (ad-ready URL) and Preview
     * (draft-safe ?preview=1), plus the fallback explainer. Inline script because
     * html fields render inside the form — there's no blade stack to push to.
     */
    /**
     * One tab per campaign page, plus an "Add" tab.
     *
     * These are plain links rather than JS tab-panes: each tab loads that page's
     * own form, so there is no way to silently edit one page and save it over
     * another. Hidden while the project has only a single page, so the common
     * case stays uncluttered.
     */
    protected function tabs(Project $project, ?ProjectLandingPage $current): string
    {
        $pages = $project->landingPages()->get();
        $addUrl = e(route('real-estate.landing-pages.pages.create', $project->getKey()));

        if ($pages->count() < 2) {
            return <<<HTML
                <div class="mb-3">
                    <a href="{$addUrl}" class="btn btn-sm btn-outline-primary">
                        <i class="ti ti-plus"></i> Add another landing page
                    </a>
                </div>
HTML;
        }

        $items = '';
        foreach ($pages as $page) {
            $isActive = $current && $current->getKey() === $page->getKey();
            $url = e(route('real-estate.landing-pages.edit-page', [$project->getKey(), $page->getKey()]));
            $label = e($page->name ?: 'Untitled');
            $active = $isActive ? ' active' : '';
            $badge = $page->is_primary
                ? ' <span class="badge bg-blue-lt ms-1">Default</span>'
                : '';
            $draft = $page->is_published ? '' : ' <span class="badge bg-secondary-lt ms-1">Draft</span>';

            $items .= <<<HTML
                <li class="nav-item">
                    <a class="nav-link{$active}" href="{$url}">{$label}{$badge}{$draft}</a>
                </li>
HTML;
        }

        return <<<HTML
            <ul class="nav nav-tabs mb-3">
                {$items}
                <li class="nav-item ms-auto">
                    <a class="nav-link text-primary" href="{$addUrl}">
                        <i class="ti ti-plus"></i> Add page
                    </a>
                </li>
            </ul>
HTML;
    }

    protected function toolbar(?Project $project): string
    {
        if (! $project) {
            return '';
        }

        $backUrl = e(route('real-estate.landing-pages.index'));
        $name = e($project->name);

        /** @var ProjectLandingPage|null $model */
        $model = $this->getModel();
        $isSaved = ($project->landing_template === 'light') && ($model && $model->exists);

        // Preview/copy point at THIS campaign page, not just the project.
        $landingUrl = e($model && $model->exists
            ? $model->url
            : route('landing.page', $project->getKey()));

        $tabs = $this->tabs($project, $model);

        $actionButtons = '';
        if ($isSaved) {
            $actionButtons = <<<HTML
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary lp-copy-link" data-url="{$landingUrl}">
                        <i class="ti ti-link"></i> Copy Link
                    </button>
                    <a href="{$landingUrl}?preview=1" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                        <i class="ti ti-external-link"></i> Preview landing page
                    </a>
                </div>
HTML;
        }

        return <<<HTML
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <a href="{$backUrl}" class="text-muted">
                        <i class="ti ti-arrow-left"></i> Back to Landing Pages
                    </a>
                </div>
                {$actionButtons}
            </div>
            {$tabs}
            <div class="alert alert-info d-flex align-items-center gap-3 mb-4">
                <i class="ti ti-info-circle fs-2 text-info flex-shrink-0"></i>
                <div>
                    <div class="fw-bold mb-1">Customize Landing Page Content for {$name}</div>
                    <div class="text-muted small">
                        All fields below are optional. Any field left blank will automatically fall back to the project's standard details (e.g., if no custom logo is uploaded, the main project name will be displayed).
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.lp-copy-link').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var url = btn.getAttribute('data-url');
                            var original = btn.innerHTML;
                            var done = function () {
                                btn.innerHTML = '<i class="ti ti-check"></i> Copied';
                                setTimeout(function () { btn.innerHTML = original; }, 1500);
                            };
                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(url).then(done).catch(function () {
                                    window.prompt('Copy this URL:', url);
                                });
                            } else {
                                window.prompt('Copy this URL:', url);
                            }
                        });
                    });
                });
            </script>
        HTML;
    }

    protected function heading(string $title, ?string $help = null): string
    {
        $sub = $help ? '<p class="text-muted mb-0" style="font-size:.85rem;">' . e($help) . '</p>' : '';

        return '<hr class="my-4"><h4 class="mb-1">' . e($title) . '</h4>' . $sub;
    }

    protected function text(string $label, mixed $value): array
    {
        return TextFieldOption::make()->label($label)->value($value)->toArray();
    }

    protected function textarea(string $label, mixed $value): array
    {
        return TextareaFieldOption::make()->label($label)->value($value)->rows(2)->toArray();
    }

    protected function toggle(string $label, mixed $value): array
    {
        return OnOffFieldOption::make()->label($label)->value($value)->toArray();
    }

    /**
     * A repeater sub-field. Attributes are ordered [name, value, options] to match
     * how repeater-item.blade calls Form::{type}(...array_values($attributes)).
     */
    protected function repeaterText(string $label): array
    {
        return [
            'type' => 'text',
            'label' => $label,
            'attributes' => ['name' => strtolower($label), 'value' => null, 'options' => ['class' => 'form-control']],
        ];
    }

    protected function repeaterTextarea(string $label): array
    {
        return [
            'type' => 'textarea',
            'label' => $label,
            'attributes' => ['name' => strtolower($label), 'value' => null, 'options' => ['class' => 'form-control', 'rows' => 2]],
        ];
    }
}
