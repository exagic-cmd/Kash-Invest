@php
    $model = $model ?? $property ?? null;
    if (!$model) {
        return;
    }

    $openHouses = $model->upcoming_open_houses;
    $cityName = $model->city ? $model->city->name : null;
    $cityUrl = $model->city_id ? route('public.properties', ['city_id' => $model->city_id]) : route('public.properties');
@endphp

<div @class(['single-property-element', 'open-houses-box', $class ?? null]) id="property-open-houses">
    <div class="open-houses-header d-flex flex-wrap align-items-baseline gap-2 mb-4">
        <h4 class="open-houses-title mb-0 fw-bold">
            {{ __('Upcoming Open Houses') }}
        </h4>
    </div>

    <div class="open-houses-list d-flex flex-column gap-3">
        @if ($openHouses && $openHouses->isNotEmpty())
            @foreach ($openHouses as $oh)
                <div class="open-house-item d-flex align-items-center gap-3">
                    <div class="open-house-icon-wrap flex-shrink-0">
                        <svg class="open-house-cal-icon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="18" x="3" y="4" rx="2"/>
                            <path d="M16 2v4"/>
                            <path d="M8 2v4"/>
                            <path d="M3 10h18"/>
                            <rect width="4" height="4" x="8" y="14" rx="1"/>
                        </svg>
                    </div>
                    <div class="open-house-details">
                        <div class="open-house-date fw-bold text-dark">
                            {{ $oh->formatted_date }}
                        </div>
                        <div class="open-house-time text-muted">
                            {{ $oh->formatted_time ?: __('TBD') }}
                        </div>
                    </div>
                </div>
            @endforeach
        @endif

        {{-- Always show "Schedule a Private Tour / Contact Us" as shown in the reference screenshot --}}
        <div class="open-house-item d-flex align-items-center gap-3">
            <div class="open-house-icon-wrap flex-shrink-0">
                <svg class="open-house-cal-icon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="18" height="18" x="3" y="4" rx="2"/>
                    <path d="M16 2v4"/>
                    <path d="M8 2v4"/>
                    <path d="M3 10h18"/>
                    <rect width="4" height="4" x="8" y="14" rx="1"/>
                </svg>
            </div>
            <div class="open-house-details">
                <div class="open-house-date fw-bold text-dark">
                    {{ __('Schedule a Private Tour') }}
                </div>
                <div class="open-house-action">
                    <a href="#contact" onclick="document.querySelector('.wrapper-sidebar-right, #contact, .form-contact')?.scrollIntoView({behavior: 'smooth'}); return false;" class="open-house-contact-link">
                        {{ __('Contact Us') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.open-houses-box {
    padding: 24px;
    background: #ffffff;
    border: 1px solid #eef0f3;
    border-radius: 12px;
    margin-bottom: 30px;
}
.open-houses-title {
    font-size: 1.35rem;
    color: #111827;
    letter-spacing: -0.01em;
}
.open-houses-city-link {
    font-size: 0.95rem;
    color: #0284c7;
    text-decoration: none;
    transition: color 0.2s ease;
}
.open-houses-city-link:hover {
    color: #0369a1;
    text-decoration: underline;
}
.open-house-item {
    padding: 6px 0;
}
.open-house-icon-wrap {
    width: 46px;
    height: 46px;
    background-color: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1e293b;
}
.open-house-cal-icon {
    width: 22px;
    height: 22px;
    stroke: #1e293b;
}
.open-house-date {
    font-size: 1.05rem;
    line-height: 1.3;
    color: #0f172a;
}
.open-house-time {
    font-size: 0.95rem;
    line-height: 1.3;
    color: #475569;
}
.open-house-contact-link {
    font-size: 0.95rem;
    color: #0284c7;
    text-decoration: none;
    font-weight: 500;
}
.open-house-contact-link:hover {
    color: #0369a1;
    text-decoration: underline;
}
</style>
