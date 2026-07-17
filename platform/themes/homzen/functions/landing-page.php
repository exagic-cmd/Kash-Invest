<?php

use Botble\RealEstate\Models\Project;
use Botble\Theme\Facades\Theme;
use Illuminate\Http\Exceptions\HttpResponseException;
use Theme\Homzen\Support\LandingData;

/**
 * Serve an assigned preconstruction landing template at the project's own URL.
 *
 * Set "Landing page template" on a project (Admin -> Real Estate -> Projects ->
 * edit -> Landing Page) and visiting that project's URL renders the landing page
 * built from that project's data, instead of the standard detail layout.
 * Leave it as "None" and nothing changes.
 *
 * We hook BASE_ACTION_PUBLIC_RENDER_SINGLE, which fires while the slug router is
 * assembling the project page but before the theme wraps it in a layout. The
 * landing templates are standalone HTML documents (they must not inherit the
 * theme's Bootstrap build, which would fight their tokens), so we short-circuit
 * with the rendered response rather than returning a view to be wrapped.
 */
add_action(BASE_ACTION_PUBLIC_RENDER_SINGLE, function ($screen, $model): void {
    if ($screen !== PROJECT_MODULE_SCREEN_NAME || ! $model instanceof Project) {
        return;
    }

    $template = $model->landing_template;

    if (! in_array($template, ['dark', 'light'], true)) {
        return;
    }

    throw new HttpResponseException(
        response(
            view(Theme::getThemeNamespace('views.landing.' . $template), [
                'landing' => LandingData::fromProject($model),
            ])->render()
        )
    );
}, 1, 2);
