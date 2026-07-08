<style>
    .zolo-blog-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        background-color: #fff;
    }
    .zolo-blog-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.06) !important;
    }
    .zolo-blog-card .img-wrapper {
        overflow: hidden;
        aspect-ratio: 16 / 9;
    }
    .zolo-blog-card .img-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    .zolo-blog-card:hover .img-wrapper img {
        transform: scale(1.05);
    }
    .zolo-category-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        background-color: var(--primary-color, #000);
        color: #fff;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        z-index: 2;
    }
    .zolo-category-badge:hover {
        color: #fff;
        background-color: #222;
    }
    .zolo-read-more {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--primary-color, #000);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: opacity 0.2s;
    }
    .zolo-read-more:hover {
        opacity: 0.8;
    }
    .zolo-sidebar {
        background-color: #f8f9fa;
        border-radius: 1rem;
        padding: 1.5rem;
    }
</style>

<section class="flat-section py-5 bg-light">
    <div class="container py-4">
        
        <div class="text-center mb-5">
            <h2 class="fw-bold mb-3">{{ __('Market Insights & News') }}</h2>
            <p class="text-muted max-w-700 mx-auto">{{ __('Stay up to date with the latest trends, guides, and pre-construction updates in the real estate market.') }}</p>
        </div>

        {!! apply_filters('ads_render', null, 'blog_list_before') !!}

        <div class="row g-5">
            <!-- Main Content: Blog Grid -->
            <div class="col-lg-8">
                <div class="row row-cols-1 row-cols-md-2 g-4">
                    @foreach($posts as $post)
                        <div class="col">
                            <div class="card h-100 border-0 shadow-sm rounded-4 zolo-blog-card position-relative">
                                
                                @if($category = $post->firstCategory)
                                    <a href="{{ $category->url }}" class="zolo-category-badge text-decoration-none">
                                        {{ $category->name }}
                                    </a>
                                @endif

                                @if ($post->image)
                                    <a href="{{ $post->url }}" class="img-wrapper d-block">
                                        {{ RvMedia::image($post->image, $post->name) }}
                                    </a>
                                @endif

                                <div class="card-body p-4 d-flex flex-column">
                                    <div class="text-muted small mb-2 d-flex align-items-center gap-3">
                                        <span>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                            {{ Theme::formatDate($post->created_at) }}
                                        </span>
                                        @if (theme_option('blog_show_author_name', 'yes') == 'yes' && class_exists($post->author_type) && ($author = $post->author ?? null) && trim($author->name))
                                            <span>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                                {{ $author->name }}
                                            </span>
                                        @endif
                                    </div>

                                    <h5 class="fw-bold mb-3 line-clamp-2">
                                        <a href="{{ $post->url }}" class="text-dark text-decoration-none">
                                            {!! BaseHelper::clean($post->name) !!}
                                        </a>
                                    </h5>
                                    
                                    @if($post->description)
                                        <p class="text-muted mb-4 small line-clamp-3 flex-grow-1">
                                            {!! BaseHelper::clean(Str::limit($post->description, 120)) !!}
                                        </p>
                                    @endif

                                    <div class="mt-auto pt-3 border-top">
                                        <a href="{{ $post->url }}" class="zolo-read-more">
                                            {{ __('Read Article') }}
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 d-flex justify-content-center">
                    {{ $posts->onEachSide(1)->links(Theme::getThemeNamespace('partials.pagination')) }}
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <aside class="zolo-sidebar sticky-top" style="top: 100px; z-index: 10;">
                    {!! dynamic_sidebar('blog_sidebar') !!}
                </aside>
            </div>
        </div>

        {!! apply_filters('ads_render', null, 'blog_list_after') !!}
    </div>
</section>
