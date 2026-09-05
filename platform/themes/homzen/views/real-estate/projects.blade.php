@php
    Theme::layout('full-width');

    $crumbs = Theme::breadcrumb()->getCrumbs();
    $lastCrumb = end($crumbs);
    $pageTitle = ($lastCrumb && ! empty($lastCrumb['label']) && $lastCrumb['label'] !== 'Home') ? $lastCrumb['label'] : null;
    if (! $pageTitle) {
        $pageTitle = SeoHelper::getTitleOnly();
    }
    if (! $pageTitle || in_array($pageTitle, ['Projects', __('Projects'), trans('plugins/real-estate::real-estate.projects')])) {
        $pageTitle = __('New Homes');
    }

    Theme::set('pageTitle', $pageTitle);
    SeoHelper::setTitle($pageTitle);
@endphp

@if (Theme::get('breadcrumbEnabled', 'yes') !== 'yes' || Theme::get('breadcrumbStyle', 'default') === 'without-title')
    <h1 class="d-none">{{ $pageTitle }}</h1>
@endif

@include(Theme::getThemeNamespace('views.real-estate.partials.listing'), [
    'actionUrl' => $actionUrl ?? RealEstateHelper::getProjectsListPageUrl(),
    'ajaxUrl' => $ajaxUrl ?? route('public.projects'),
    'mapUrl' => $mapUrl ?? route('public.ajax.projects.map-all'),
    'perPages' => RealEstateHelper::getProjectsPerPageList(),
    'itemLayout' => request()->query('layout', 'grid'),
    'layout' => 'without-map',
    'filterViewPath' => Theme::getThemeNamespace('views.real-estate.partials.filters.project-search-box'),
    'itemsViewPath' => Theme::getThemeNamespace('views.real-estate.projects.index'),
])

@include(Theme::getThemeNamespace('views.real-estate.partials.project-map-content'))
