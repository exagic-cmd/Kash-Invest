{{--
    INNER CIRCLE — Centered heading, grid of icon/label items, and a CTA button.
    Editable from the admin panel.
    Uses Tabler icons (<x-core::icon>) from the system icon library.
--}}
@php
    $innerCircle = $landing['innerCircle'] ?? [];

    $heading = $innerCircle['heading'] ?? __('JOIN OUR INNER CIRCLE TO GET FIRST ACCESS');
    $buttonText = $innerCircle['buttonText'] ?? __('Join Now');
    $buttonLink = $innerCircle['buttonLink'] ?? '#register';

    $items = $innerCircle['items'] ?? [
        ['label' => 'Prices', 'icon' => null],
        ['label' => 'Floor Plans', 'icon' => null],
        ['label' => 'Incentives', 'icon' => null],
        ['label' => 'Worksheet', 'icon' => null],
    ];

    if (!function_exists('getInnerCircleIconName')) {
        function getInnerCircleIconName(?string $icon, string $label): string {
            if (!empty($icon) && !str_contains($icon, '/')) {
                return $icon;
            }
            $lower = strtolower(trim($label));
            if (str_contains($lower, 'price')) return 'ti ti-tags';
            if (str_contains($lower, 'plan') || str_contains($lower, 'floor')) return 'ti ti-ruler-2';
            if (str_contains($lower, 'incentive') || str_contains($lower, 'offer') || str_contains($lower, 'bonus')) return 'ti ti-award';
            if (str_contains($lower, 'sheet') || str_contains($lower, 'work') || str_contains($lower, 'doc')) return 'ti ti-file-text';
            return 'ti ti-star';
        }
    }
@endphp

@if ($heading || $items || $buttonText)
<section class="kl-section" id="inner-circle">
    <div class="kl-wrap kl-reveal">
        @if ($heading)
            <div class="kl-head-center" style="margin-bottom: 2.75rem;">
                <h2 class="kl-h2" style="text-transform:uppercase; text-align:center;">{{ $heading }}</h2>
            </div>
        @endif

        @if ($items)
            <div class="kl-inner-circle-grid">
                @foreach ($items as $item)
                    @php
                        $iconValue = $item['icon'] ?? '';
                        $isImageUrl = !empty($iconValue) && (str_starts_with($iconValue, 'http') || str_starts_with($iconValue, '/'));
                        $iconName = getInnerCircleIconName($iconValue, $item['label'] ?? '');
                    @endphp
                    <div class="kl-inner-circle-item">
                        <div class="kl-inner-circle-item__icon">
                            @if ($isImageUrl)
                                <img src="{{ $iconValue }}" alt="{{ $item['label'] ?? '' }}" loading="lazy" decoding="async">
                            @else
                                <x-core::icon :name="$iconName" class="kl-tabler-icon" />
                            @endif
                        </div>
                        @if (!empty($item['label']))
                            <div class="kl-inner-circle-item__label">{{ $item['label'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($buttonText && $buttonLink)
            <div class="kl-center-cta" style="margin-top: 3rem;">
                <a href="{{ $buttonLink }}" class="kl-btn kl-btn--cta" style="min-width: 180px;">{{ $buttonText }}</a>
            </div>
        @endif
    </div>
</section>

<style>
    .kl-inner-circle-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 2.5rem 1.5rem;
        max-width: 1050px;
        margin-inline: auto;
        text-align: center;
    }
    .kl-inner-circle-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1rem;
    }
    .kl-inner-circle-item__icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 80px;
        height: 80px;
        color: var(--kl-accent, #0392A6);
    }
    .kl-inner-circle-item__icon img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    .kl-inner-circle-item__icon .kl-tabler-icon,
    .kl-inner-circle-item__icon svg {
        width: 64px !important;
        height: 64px !important;
        stroke-width: 1.5px;
    }
    .kl-inner-circle-item__label {
        font-family: var(--kl-display, 'Jost', sans-serif);
        font-weight: 500;
        font-size: 1.2rem;
        color: var(--kl-ink, #212529);
    }
    @media (max-width: 768px) {
        .kl-inner-circle-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem 1rem;
        }
        .kl-inner-circle-item__icon {
            width: 64px;
            height: 64px;
        }
        .kl-inner-circle-item__icon .kl-tabler-icon,
        .kl-inner-circle-item__icon svg {
            width: 48px !important;
            height: 48px !important;
        }
    }
</style>
@endif
