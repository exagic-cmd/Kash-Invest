<section class="flat-project-style8 bg-surface">
    <div class="container">
        {!! Theme::partial('shortcode-heading', compact('shortcode')) !!}
        <div class="style8-grid">
            <!-- Left Card -->
            <div class="left-card">
                <div class="icon-box">
                    @if($leftImage = $shortcode->left_image)
                        {{ RvMedia::image($leftImage, $shortcode->left_title ?: __('Building Icon'), 'thumb') }}
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#248844" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="9" y1="22" x2="9" y2="16"></line><line x1="15" y1="22" x2="15" y2="16"></line><line x1="9" y1="16" x2="15" y2="16"></line><path d="M8 6h.01"></path><path d="M16 6h.01"></path><path d="M8 10h.01"></path><path d="M16 10h.01"></path></svg>
                    @endif
                </div>
                <h4>{!! BaseHelper::clean($shortcode->left_title ?: __('New Homes in Ontario')) !!}</h4>
                <p>{!! BaseHelper::clean($shortcode->left_description ?: __('Check our handpicked listing of new construction homes and experience our superior services during the entire property buying journey.')) !!}</p>
                @if($shortcode->button_url && $shortcode->button_label)
                    <a href="{{ $shortcode->button_url }}" class="btn-view-all">{!! BaseHelper::clean($shortcode->button_label) !!}</a>
                @endif
            </div>

            <!-- Project Cards -->
            @foreach($projects->take(3) as $project)
                <div class="project-card-style8">
                    <a href="{{ $project->url }}" class="card-image-link d-block position-relative">
                        <div class="images-style card-image-wrapper">
                            @include(Theme::getThemeNamespace('partials.real-estate.card-image-slider'), [
                                'item' => $project,
                                'alt' => $project->name,
                                'size' => 'thumb',
                            ])
                        </div>
                        @if($project->category)
                            <span class="card-badge">{{ $project->category->name }}</span>
                        @endif
                    </a>
                    <div class="card-content-wrapper">
                        <h5 class="card-title text-truncate">
                            <a href="{{ $project->url }}" title="{{ $project->name }}">{!! BaseHelper::clean($project->name) !!}</a>
                        </h5>
                        @if($project->short_address)
                            <div class="card-address text-truncate d-flex align-items-center gap-1">
                                <i class="icon icon-mapPin"></i>
                                <span>{{ $project->short_address }}</span>
                            </div>
                        @endif
                        @if (!setting('real_estate_hide_price', false))
                            <div class="card-price">
                                {{ $project->formatted_price ?: __('Price On Request') }}
                            </div>
                        @endif
                        <div class="card-meta d-flex gap-3">
                            @if($project->number_block)
                                <span class="d-flex align-items-center gap-1">
                                    <i class="icon icon-bed"></i>
                                    <span>{{ trans_choice(':count Building|:count Buildings', $project->number_block) }}</span>
                                </span>
                            @endif
                            @if($project->number_flat)
                                <span class="d-flex align-items-center gap-1">
                                    <i class="icon icon-ruler"></i>
                                    <span>{{ trans_choice(':count Unit|:count Units', $project->number_flat) }}</span>
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="card-bottom">
                        <a href="{{ $project->url }}" class="btn-request-info w-100">
                            <i class="icon icon-phone"></i>
                            <span>{{ __('Request Info') }}</span>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
