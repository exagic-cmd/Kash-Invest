@php
    $defaultImage = RvMedia::getDefaultImage();
    $validImages = [];

    if ($model->images && is_array($model->images)) {
        // Re-index array values so sparse/1-indexed DB keys (e.g. ['1' => 'path.jpg']) don't break iteration
        $rawImages = array_values($model->images);
        foreach ($rawImages as $img) {
            if (empty($img)) continue;
            if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) {
                $validImages[] = $img;
            } else {
                $cleanPath = ltrim($img, '/');
                $storagePath = public_path('storage/' . $cleanPath);
                $publicPath = public_path($cleanPath);
                if (file_exists($storagePath) || file_exists($publicPath)) {
                    $validImages[] = $img;
                }
            }
        }
    }

    $imagesCount = count($validImages);

    // Build exactly 3 grid display slots using valid images or falling back to default image
    $gridImages = [
        $validImages[0] ?? $defaultImage,
        $validImages[1] ?? $defaultImage,
        $validImages[2] ?? $defaultImage,
    ];

    // Any remaining valid images beyond index 2 for Fancybox lightbox navigation
    $remainingImages = array_slice($validImages, 3);
@endphp

<section class="flat-gallery-three-cols px-md-4 px-2 mt-4">
    <div class="gallery-grid-row">
        {{-- Slot 1: Main (Left 2-col span) --}}
        <div class="gallery-col col-main position-relative">
            <a href="{{ RvMedia::getImageUrl($gridImages[0]) ?: $defaultImage }}" data-fancybox="gallery" data-thumb="{{ RvMedia::getImageUrl($gridImages[0], 'thumb') ?: $defaultImage }}" class="gallery-img-link d-block">
                {{ RvMedia::image($gridImages[0], $model->name, null, attributes: ['fetchpriority' => 'high', 'loading' => 'eager'], lazy: false) }}
            </a>
            
            {{-- Mobile View All Photos Button --}}
            <a href="{{ $model->url . '?view-gallery=1' }}" class="col-main-mobile-btn d-none text-decoration-none" style="pointer-events: auto !important; z-index: 10;">
                <x-core::icon name="ti ti-camera" class="icon" />
                <span>{{ $imagesCount > 0 ? __('See all :count Photos', ['count' => $imagesCount]) : __('See all Photos') }}</span>
            </a>
        </div>

        {{-- Slot 2: Second (Center 1-col) --}}
        <div class="gallery-col col-second">
            <a href="{{ RvMedia::getImageUrl($gridImages[1]) ?: $defaultImage }}" data-fancybox="gallery" data-thumb="{{ RvMedia::getImageUrl($gridImages[1], 'thumb') ?: $defaultImage }}" class="gallery-img-link d-block">
                {{ RvMedia::image($gridImages[1], $model->name, null, attributes: ['loading' => 'eager'], lazy: false) }}
            </a>
        </div>

        {{-- Slot 3: Third (Right 1-col with dark overlay) --}}
        <div class="gallery-col col-third">
            <a href="{{ $model->url . '?view-gallery=1' }}" class="gallery-img-link d-block position-relative">
                {{ RvMedia::image($gridImages[2], $model->name, null, attributes: ['loading' => 'eager'], lazy: false) }}
                
                {{-- Dark Overlay --}}
                <div class="gallery-overlay d-flex flex-column align-items-center justify-content-center">
                    <div class="overlay-content text-center text-white">
                        <x-core::icon name="ti ti-camera" class="overlay-icon mb-2" />
                        <span class="overlay-text d-block">{{ $imagesCount > 0 ? __('See all :count Photos', ['count' => $imagesCount]) : __('See all Photos') }}</span>
                    </div>
                </div>
            </a>
        </div>

        {{-- Hidden remaining images for Fancybox lightbox modal navigation --}}
        @foreach ($remainingImages as $extraImage)
            <a href="{{ RvMedia::getImageUrl($extraImage) }}" data-fancybox="gallery" data-thumb="{{ RvMedia::getImageUrl($extraImage, 'thumb') }}" class="d-none"></a>
        @endforeach
    </div>
</section>

<style>
    .flat-gallery-three-cols {
        margin-top: 1.5rem;
    }
    .gallery-grid-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr;
        gap: 12px;
        border-radius: 12px;
        overflow: hidden;
        height: 440px;
    }
    .gallery-col {
        position: relative;
        overflow: hidden;
        height: 100%;
        background-color: #f3f4f6;
    }
    .gallery-img-link {
        display: block;
        width: 100%;
        height: 100%;
        position: relative;
    }
    .gallery-img-link img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.3s ease;
    }
    .gallery-img-link:hover img {
        transform: scale(1.03);
    }
    .gallery-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        backdrop-filter: blur(2px);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        transition: background 0.2s ease;
    }
    .gallery-img-link:hover .gallery-overlay {
        background: rgba(0, 0, 0, 0.6);
    }
    .overlay-icon {
        font-size: 28px;
    }
    .overlay-text {
        font-size: 15px;
        font-weight: 600;
        letter-spacing: 0.02em;
    }
    @media (max-width: 768px) {
        .gallery-grid-row {
            grid-template-columns: 1fr;
            height: 280px;
        }
        .col-second, .col-third {
            display: none !important;
        }
        .col-main-mobile-btn {
            display: inline-flex !important;
            position: absolute;
            bottom: 16px;
            right: 16px;
            background: rgba(0, 0, 0, 0.7);
            color: #fff !important;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
    }
</style>
