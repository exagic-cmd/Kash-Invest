<div class="gallery-fullscreen-layout">
    {{-- Sticky Header Bar --}}
    <div class="gallery-header-bar d-flex justify-content-between align-items-center px-4 py-2 bg-white border-bottom sticky-top">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ $model->url }}" class="text-black text-decoration-none d-flex align-items-center justify-content-center gallery-back-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icon-tabler-arrow-left"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0" /><path d="M5 12l6 6" /><path d="M5 12l6 -6" /></svg>
            </a>
            <span class="h5 fw-6 mb-0 text-truncate max-width-md">{{ $model->name }}</span>
        </div>
        
        <div class="d-flex align-items-center gap-2">
            <a href="{{ $model->url . '#contact-form' }}" class="btn-ask-home text-decoration-none">
                {{ __('Ask About this Home') }}
            </a>
            <span class="btn-gallery-tab active">
                {{ __('Photos') }}
            </span>
            <a href="{{ $model->url }}" class="btn-gallery-tab text-decoration-none text-black">
                {{ __('Map') }}
            </a>
        </div>
    </div>

    {{-- Compact 2-Column Image List --}}
    <div class="container-fluid p-2">
        @if ($model->images && is_array($model->images))
            <div class="row row-cols-1 row-cols-md-2 g-2">
                @foreach ($model->images as $image)
                    <div class="col">
                        <div class="gallery-item-wrapper overflow-hidden">
                            <a href="{{ RvMedia::getImageUrl($image) }}" data-fancybox="gallery-page" data-thumb="{{ RvMedia::getImageUrl($image, 'thumb') }}" class="d-block w-100 h-100">
                                <div class="ratio ratio-16x9">
                                    {{ RvMedia::image($image, $model->name, 'large-rectangle', attributes: ['class' => 'img-fluid object-fit-cover w-100 h-100'], lazy: true) }}
                                </div>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-5">
                <p class="text-variant-1">{{ __('No images found for this property.') }}</p>
            </div>
        @endif
    </div>
</div>

<style>
    body.listing-no-map {
        background-color: #ffffff !important;
    }
    .gallery-fullscreen-layout {
        background-color: #ffffff;
        min-height: 100vh;
    }
    .gallery-header-bar {
        height: 60px;
        z-index: 1100;
        box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    }
    .gallery-back-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        transition: background-color 0.2s;
    }
    .gallery-back-btn:hover {
        background-color: #f1f2f4;
    }
    .btn-ask-home {
        background-color: #70b54e;
        color: #ffffff !important;
        font-weight: 600;
        padding: 8px 18px;
        border-radius: 50px;
        font-size: 14px;
        transition: background-color 0.2s;
        border: none;
    }
    .btn-ask-home:hover {
        background-color: #5da03d;
    }
    .btn-gallery-tab {
        color: #161e2d;
        font-weight: 600;
        padding: 8px 18px;
        border-radius: 8px;
        font-size: 14px;
        transition: background-color 0.2s;
        cursor: pointer;
        display: inline-block;
    }
    .btn-gallery-tab:hover {
        background-color: #f1f2f4;
    }
    .btn-gallery-tab.active {
        background-color: #f1f2f4;
        cursor: default;
    }
    .gallery-item-wrapper {
        border-radius: 4px;
    }
    .gallery-item-wrapper img {
        transition: opacity 0.2s;
    }
    .gallery-item-wrapper:hover img {
        opacity: 0.95;
    }
    .max-width-md {
        max-width: 300px;
    }
    @media (max-width: 768px) {
        .max-width-md {
            max-width: 150px;
        }
        .gallery-header-bar {
            padding-left: 12px !important;
            padding-right: 12px !important;
        }
        .btn-ask-home, .btn-gallery-tab {
            padding: 6px 12px;
            font-size: 12px;
        }
    }
</style>
