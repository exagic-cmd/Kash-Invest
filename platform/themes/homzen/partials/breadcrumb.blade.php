@php
    $backgroundColor = Theme::get('breadcrumbBackgroundColor', theme_option('breadcrumb_background_color', '#f7f7f7'));
    $textColor = Theme::get('breadcrumbTextColor', theme_option('breadcrumb_text_color', '#161e2d'));
    $backgroundImage = Theme::get('breadcrumbBackgroundImage', theme_option('breadcrumb_background_image') ?: null);

    $backgroundImage = $backgroundImage ? RvMedia::getImageUrl($backgroundImage) : null;

    $showBreadcrumb = Theme::get('breadcrumbEnabled', 'yes');
    $breadcrumbStyle = Theme::get('breadcrumbStyle', 'default');
@endphp

@if ($showBreadcrumb === 'yes')
    <section class="flat-title-page style-2" @style(["background-color: $backgroundColor" => $backgroundColor != 'transparent', "background-image: url($backgroundImage); background-size: cover; background-position: center" => $backgroundImage])>
        <div class="container">
            <ul class="breadcrumb">
                @foreach(Theme::breadcrumb()->getCrumbs() as $crumb)
                    <li>
                        @if($loop->last)
                            {!! BaseHelper::clean($crumb['label']) !!}
                        @else
                            <a href="{{ $crumb['url'] }}" @style(["color: $textColor" => $textColor != 'transparent'])>{!! BaseHelper::clean($crumb['label']) !!}</a>
                            <span class="ms-1" @style(["color: $textColor" => $textColor != 'transparent'])>/</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($breadcrumbStyle !== 'without-title')
                @php
                    $crumbs = Theme::breadcrumb()->getCrumbs();
                    $lastCrumb = end($crumbs);
                    $pageTitle = Theme::get('pageTitle');
                    if (! $pageTitle || in_array($pageTitle, ['Projects', __('Projects'), trans('plugins/real-estate::real-estate.projects')])) {
                        if ($lastCrumb && ! empty($lastCrumb['label']) && $lastCrumb['label'] !== 'Home') {
                            $pageTitle = $lastCrumb['label'];
                        }
                    }
                    $pageTitle = $pageTitle ?: (Theme::get('pageTitle') ?: SeoHelper::getTitleOnly());
                @endphp
                <h1 class="text-center page-title mt-3 mb-0" @style(["color: $textColor" => $textColor && $textColor != 'transparent'])>{!! BaseHelper::clean($pageTitle) !!}</h1>
            @endif
        </div>
    </section>
@endif
