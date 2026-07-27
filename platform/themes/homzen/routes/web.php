<?php

use Botble\Base\Http\Middleware\RequiresJsonRequestMiddleware;
use Botble\Theme\Facades\Theme;
use Illuminate\Support\Facades\Route;
use Theme\Homzen\Http\Controllers\HomzenController;
use Theme\Homzen\Http\Controllers\LandingPageController;
use Theme\Homzen\Http\Controllers\LandingPreviewController;


Route::middleware(['web', 'core'])
    ->controller(HomzenController::class)
    ->group(function (): void {
        Route::group(apply_filters(BASE_FILTER_GROUP_PUBLIC_ROUTE, []), function (): void {
            Route::get('wishlist', 'getWishlist')->name('public.wishlist');

            Route::prefix('ajax')->name('public.ajax.')->middleware(RequiresJsonRequestMiddleware::class)->group(function (): void {
                Route::get('properties', 'ajaxGetProperties')->name('properties');
                Route::get('properties/map', 'ajaxGetPropertiesForMap')->name('properties.map');
                Route::get('properties/map-all', 'ajaxGetAllPropertiesForMap')->name('properties.map-all');
                Route::get('projects', 'ajaxGetProjects')->name('projects');
                Route::get('projects/map', 'ajaxGetProjectsForMap')->name('projects.map');
                Route::get('projects/map-all', 'ajaxGetAllProjectsForMap')->name('projects.map-all');
                Route::get('projects/search', 'ajaxSearchProjects')->name('projects.search');
                Route::get('cities', 'ajaxGetCities')->name('cities');
            });
        });
    });

/*
 | Project landing pages.
 |
 | /landing/{project}            -> the assigned landing page for that project.
 |
 | These URLs are intentionally NOT linked from anywhere on the site. They exist
 | solely for Google Ads traffic; the only entry point is the Featured Projects
 | admin table (Preview / Copy Link). Honors is_published and the assignment.
 */
Route::middleware(['web', 'core'])->group(function (): void {
    // Bare URL -> the project's default landing page. Kept first and unchanged so
    // ad links created before multi-page support still resolve to the same page.
    Route::get('landing/{project}', [LandingPageController::class, 'show'])
        ->name('landing.page');

    // One URL per campaign page.
    Route::get('landing/{project}/{slug}', [LandingPageController::class, 'show'])
        ->name('landing.page.slug');
});

/*
 | Preconstruction landing template previews.
 |
 | /landing-preview/dark          -> most complete project, dark template
 | /landing-preview/light/57      -> project 57, light template
 |
 | Temporary: exists so both templates are viewable before the admin
 | "assign template to project" feature ships. Remove once that lands.
 */
Route::middleware(['web', 'core'])->group(function (): void {
    Route::get('landing-preview/{theme}/{project?}', [LandingPreviewController::class, 'show'])
        ->where('theme', 'dark|light')
        ->name('landing.preview');
});

Theme::routes();
