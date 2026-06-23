@if ($model->images && is_array($model->images))
    @php
        $imagesCount = count($model->images);
    @endphp
    <section class="flat-gallery-three-cols px-md-4 px-2 mt-4">
        <div class="gallery-grid-row">
            {{-- Loop through all images --}}
            @foreach ($model->images as $image)
                @if ($loop->first)
                    {{-- First image (Left column) --}}
                    <div class="gallery-col col-main">
                        <a href="{{ RvMedia::getImageUrl($image) }}" data-fancybox="gallery" data-thumb="{{ RvMedia::getImageUrl($image, 'thumb') }}" class="gallery-img-link d-block">
                            {{ RvMedia::image($image, $model->name, 'medium-rectangle', attributes: ['fetchpriority' => 'high', 'loading' => 'eager'], lazy: false) }}
                            
                            {{-- Mobile View All Photos Button --}}
                            <div class="col-main-mobile-btn d-none">
                                <x-core::icon name="ti ti-camera" class="icon" />
                                <span>{{ __('See all :count Photos', ['count' => $imagesCount]) }}</span>
                            </div>
                        </a>
                    </div>
                @elseif ($loop->iteration == 2)
                    {{-- Second image (Center column) --}}
                    <div class="gallery-col col-second">
                        <a href="{{ RvMedia::getImageUrl($image) }}" data-fancybox="gallery" data-thumb="{{ RvMedia::getImageUrl($image, 'thumb') }}" class="gallery-img-link d-block">
                            {{ RvMedia::image($image, $model->name, 'medium-rectangle', attributes: ['loading' => 'eager'], lazy: false) }}
                        </a>
                    </div>
                @elseif ($loop->iteration == 3)
                    {{-- Third image (Right column) with Overlay --}}
                    <div class="gallery-col col-third">
                        <a href="{{ $model->url . '?view-gallery=1' }}" class="gallery-img-link d-block position-relative">
                            {{ RvMedia::image($image, $model->name, 'medium-rectangle', attributes: ['loading' => 'eager'], lazy: false) }}
                            
                            {{-- Dark Overlay --}}
                            <div class="gallery-overlay d-flex flex-column align-items-center justify-content-center">
                                <div class="overlay-content text-center text-white">
                                    <x-core::icon name="ti ti-camera" class="overlay-icon mb-2" />
                                    <span class="overlay-text d-block">{{ __('See all :count Photos', ['count' => $imagesCount]) }}</span>
                                </div>
                            </div>
                        </a>
                    </div>
                @else
                    {{-- Hidden images for Fancybox navigation --}}
                    <a href="{{ RvMedia::getImageUrl($image) }}" data-fancybox="gallery" data-thumb="{{ RvMedia::getImageUrl($image, 'thumb') }}" class="d-none"></a>
                @endif
            @endforeach
        </div>
    </section>
@endif
