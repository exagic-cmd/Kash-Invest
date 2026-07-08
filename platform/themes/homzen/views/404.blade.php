@php
    SeoHelper::setTitle(__('404 - Page Not found'));
    Theme::fireEventGlobalAssets();
@endphp

@extends(Theme::getThemeNamespace('layouts.base'))

@section('content')
    <div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="row justify-content-center text-center w-100">
            <div class="col-lg-6 col-md-8">
                <div class="mb-4" style="color: var(--primary-color, #000);">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
                <h1 class="display-1 fw-bold mb-2">404</h1>
                <h4 class="mb-3 fw-semibold">{{ __('Page Not Found') }}</h4>
                <p class="text-muted mb-4">{{ __('We couldn\'t find the page you were looking for. It may have been moved, deleted, or never existed in the first place.') }}</p>
                <div class="d-flex justify-content-center gap-3 mt-4">
                    <a href="{{ BaseHelper::getHomepageUrl() }}" class="tf-btn primary">
                        {{ __('Return Home') }}
                    </a>
                    <a href="javascript:history.back()" class="tf-btn style-border">
                        {{ __('Go Back') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
