@php
    $class ??= null;
    $itemsPerRow ??= 3;
    $author = $project->author;
    $investor = $project->investor;
@endphp

<div @class(['property-item homeya-box modern-card', $class]) @if ($project->latitude && $project->longitude) data-lat="{{ $project->latitude }}" data-lng="{{ $project->longitude }}" @endif>
    <div class="archive-top">
        <a href="{{ $project->url }}" class="images-group">
            <div class="images-style">
                @include(Theme::getThemeNamespace('partials.real-estate.card-image-slider'), [
                    'item' => $project,
                    'alt' => $project->name,
                    'size' => 'medium-rectangle',
                ])
            </div>
            
            <div class="modern-overlays">
                <span class="overlay-tag tag-status">
                    <i class="icon icon-home"></i> 
                    @if($project->status)
                        {{ $project->status->label() }}
                    @else
                        {{ __('Selling') }}
                    @endif
                </span>
                {{-- "Last updated" from the data feed: the Buildify sync only saves a
                     project when something actually changed, so updated_at reflects the
                     last real data change from the API (or a manual admin edit). --}}
                @if($project->updated_at)
                    <span class="overlay-tag tag-time">
                        {{ __('Updated :time', ['time' => $project->updated_at->diffForHumans()]) }}
                    </span>
                @endif
            </div>
        </a>
        
        <div class="content modern-content">
            <div class="price-row mb-2">
                @if (!setting('real_estate_hide_price', false))
                    <div class="modern-price">{{ $project->formatted_price }}</div>
                @endif
                
                @if (RealEstateHelper::isEnabledWishlist())
                    <button type="button" class="modern-wishlist-btn"
                            data-type="project"
                            data-bb-toggle="add-to-wishlist"
                            data-id="{{ $project->getKey() }}"
                            data-add-message="{{ __('Added ":name" to wishlist successfully!', ['name' => $project->name]) }}"
                            data-remove-message="{{ __('Removed ":name" from wishlist successfully!', ['name' => $project->name]) }}"
                            aria-label="{{ __('Add to wishlist') }}"
                    >
                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                            <path d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572"></path>
                        </svg>
                    </button>
                @endif
            </div>

            <div class="modern-specs mb-2">
                @if($project->number_block)
                    <span class="spec-item">{{ $project->number_block }} {{ __('blocks') }}</span>
                @endif
                @if($project->number_floor)
                    <span class="spec-item">{{ $project->number_floor }} {{ __('floors') }}</span>
                @endif
                @if($project->number_flat)
                    <span class="spec-item">{{ $project->number_flat }} {{ __('flats') }}</span>
                @endif
                @if($project->category)
                    <span class="spec-item">{{ $project->category->name }}</span>
                @endif
            </div>

            <div class="modern-address mb-1">
                <a href="{{ $project->url }}" class="line-clamp-1" title="{{ $project->name }}">
                    {!! BaseHelper::clean($project->name) !!}
                </a>
            </div>

            @php($cardLocation = $project->short_address ?: trim(implode(', ', array_filter([$project->city_name ?? null, $project->state_name ?? null]))))
            @if ($cardLocation)
                <div class="modern-location">
                    <i class="icon icon-mapPin"></i>
                    <span class="line-clamp-1">{{ $cardLocation }}</span>
                </div>
            @endif

        </div>
    </div>
</div>
