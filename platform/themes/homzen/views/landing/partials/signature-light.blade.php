{{--
    SIGNATURE — light theme: Today → Occupancy.

    The defining fact of preconstruction is that the thing doesn't exist yet.
    Every other site hides that behind glossy renderings; this one puts it in
    your hand. Drag between the site as it stands today and the rendering of
    what's coming. It turns the E&OE disclaimer from fine print into the point,
    which is exactly the trust the page has to earn.

    Uses the project's own images: [0] the rendering, and the last image as the
    "today" frame. TODO(phase 2): give projects a dedicated site-photo field so
    "today" is always a real site photo rather than the last gallery image.
--}}
@php
    $rendering = $landing['gallery'][0] ?? null;
    $today = count($landing['gallery']) > 1 ? end($landing['gallery']) : null;
    $occupancy = collect($landing['quickFacts'])->firstWhere('label', 'Occupancy')['value'] ?? null;
@endphp

@if ($rendering && $today)
    <section class="kl-section" id="today">
        <div class="kl-wrap kl-reveal">
            <div class="kl-eyebrow">Today → {{ $occupancy ?: 'Occupancy' }}</div>
            <h2 class="kl-h2">It Doesn't Exist Yet. Here's The Site Today.</h2>
            <p class="kl-lede">
                Drag to compare the site as it stands with the approved rendering. Renderings are an
                artist's concept — this is the honest version.
            </p>

            <figure class="kl-compare" data-compare style="margin:2rem 0 0;">
                {{-- base layer: today --}}
                <img src="{{ $today }}" alt="The site of {{ $landing['name'] }} as it stands today"
                     loading="lazy" decoding="async" style="filter:grayscale(1) contrast(.95);">

                {{-- top layer: the rendering, revealed by the slider --}}
                <div class="kl-compare__after" data-compare-after>
                    <img src="{{ $rendering }}" alt="Rendering of {{ $landing['name'] }} at completion"
                         loading="lazy" decoding="async">
                </div>

                <input type="range" min="0" max="100" value="50" step="1"
                       class="kl-compare__range" data-compare-range
                       aria-label="Reveal the rendering: 0 is the site today, 100 is the completed rendering">
                <div class="kl-compare__handle" data-compare-handle aria-hidden="true"></div>

                <span class="kl-compare__tag kl-compare__tag--l">Site today</span>
                <span class="kl-compare__tag kl-compare__tag--r">{{ $occupancy ?: 'At completion' }}</span>
            </figure>

            <figcaption class="kl-form__note" style="margin-top:.75rem;">
                Artist's concept. Specifications, finishes and completion dates are subject to change
                without notice. E. &amp; O.E.
            </figcaption>
        </div>
    </section>
@endif
