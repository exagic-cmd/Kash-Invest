@php
    // Compute a fixed window of up to 5 pages centred on the current page.
    // Laravel's $elements is ignored here because its default first/last page
    // slots can expand the visible list well past 5 numbers.
    $windowSize = 5;
    $current = $paginator->currentPage();
    $lastPage = $paginator->lastPage();
    $halfWindow = (int) floor($windowSize / 2);

    $windowStart = max(1, $current - $halfWindow);
    $windowEnd = min($lastPage, $windowStart + $windowSize - 1);
    $windowStart = max(1, $windowEnd - $windowSize + 1);
@endphp

@if ($paginator->hasPages())
    <nav class="homzen-pagination" aria-label="{{ trans('pagination.previous') }} / {{ trans('pagination.next') }}">
        @if ($paginator->total() > 0)
            <div class="homzen-pagination__summary">
                <div class="homzen-pagination__label">{{ __('Showing results') }}</div>
                <div class="homzen-pagination__range">
                    <strong>{{ number_format($paginator->firstItem()) }}</strong>
                    <span class="homzen-pagination__dash">—</span>
                    <strong>{{ number_format($paginator->lastItem()) }}</strong>
                    {{ __('of') }}
                    <strong>{{ number_format($paginator->total()) }}</strong>
                </div>
            </div>
        @endif

        <ul class="homzen-pagination__list">
            @if (! $paginator->onFirstPage())
                <li>
                    <a href="{{ $paginator->previousPageUrl() }}" class="homzen-page-btn" aria-label="{{ trans('pagination.previous') }}" rel="prev">
                        <x-core::icon name="ti ti-chevron-left" />
                    </a>
                </li>
            @endif

            {{-- Leading "1 …" when the window has moved past the first page --}}
            @if ($windowStart > 1)
                <li>
                    <a href="{{ $paginator->url(1) }}" class="homzen-page-btn" aria-label="{{ __('Go to page :page', ['page' => 1]) }}">1</a>
                </li>
                @if ($windowStart > 2)
                    <li>
                        <span class="homzen-page-btn homzen-page-btn--ellipsis" aria-hidden="true">…</span>
                    </li>
                @endif
            @endif

            {{-- The fixed-size sliding window of up to 5 pages --}}
            @for ($page = $windowStart; $page <= $windowEnd; $page++)
                <li>
                    @if ($page == $current)
                        <span class="homzen-page-btn homzen-page-btn--current" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $paginator->url($page) }}" class="homzen-page-btn" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                    @endif
                </li>
            @endfor

            {{-- Trailing "… last" when the window hasn't reached the last page --}}
            @if ($windowEnd < $lastPage)
                @if ($windowEnd < $lastPage - 1)
                    <li>
                        <span class="homzen-page-btn homzen-page-btn--ellipsis" aria-hidden="true">…</span>
                    </li>
                @endif
                <li>
                    <a href="{{ $paginator->url($lastPage) }}" class="homzen-page-btn" aria-label="{{ __('Go to page :page', ['page' => $lastPage]) }}">{{ $lastPage }}</a>
                </li>
            @endif

            @if ($paginator->hasMorePages())
                <li>
                    <a href="{{ $paginator->nextPageUrl() }}" class="homzen-page-btn" aria-label="{{ trans('pagination.next') }}" rel="next">
                        <x-core::icon name="ti ti-chevron-right" />
                    </a>
                </li>
            @endif
        </ul>
    </nav>
@endif

<style>
.homzen-pagination {
    text-align: center;
    margin: 2.5rem 0 1rem;
}
.homzen-pagination__summary {
    margin-bottom: 1rem;
    color: #475569;
    font-size: 14px;
    line-height: 1.5;
}
.homzen-pagination__label {
    color: #64748b;
    font-weight: 500;
}
.homzen-pagination__range {
    color: #0f172a;
    font-size: 15px;
    margin-top: 2px;
}
.homzen-pagination__range strong {
    font-weight: 700;
}
.homzen-pagination__dash {
    margin: 0 4px;
    color: #94a3b8;
}
.homzen-pagination__list {
    display: inline-flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
    list-style: none;
    padding: 0;
    margin: 0;
}
.homzen-page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 44px;
    height: 44px;
    padding: 0 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #ffffff;
    color: #0f172a;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}
.homzen-page-btn:hover {
    border-color: #cbd5e1;
    color: #0f172a;
    background: #f8fafc;
    text-decoration: none;
}
.homzen-page-btn:focus-visible {
    outline: 2px solid #0f172a;
    outline-offset: 2px;
}
.homzen-page-btn--current {
    background: #f1f5f9;
    border-color: #e2e8f0;
    color: #0f172a;
    cursor: default;
}
.homzen-page-btn--ellipsis {
    background: transparent;
    border-color: transparent;
    box-shadow: none;
    color: #94a3b8;
    cursor: default;
}
.homzen-page-btn--ellipsis:hover {
    background: transparent;
    border-color: transparent;
}
.homzen-page-btn svg,
.homzen-page-btn i {
    width: 18px;
    height: 18px;
    stroke-width: 2;
}
@media (max-width: 575.98px) {
    .homzen-page-btn {
        min-width: 38px;
        height: 38px;
        padding: 0 10px;
        font-size: 13px;
    }
    .homzen-pagination__list {
        gap: 6px;
    }
}
</style>
