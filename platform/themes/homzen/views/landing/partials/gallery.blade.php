{{-- Gallery. All below-the-fold, so every image lazy-loads. Lightbox is a
     native <dialog> — no plugin, keyboard-closable for free. --}}
@php($shots = array_slice($landing['gallery'], 1)) {{-- [0] is already the hero --}}

@if ($shots)
    <section class="kl-section" id="gallery">
        <div class="kl-wrap kl-reveal">
            <div class="kl-eyebrow">Gallery</div>
            <h2 class="kl-h2">Renderings</h2>
            <p class="kl-lede">Artist's concept. Subject to change without notice. E. &amp; O.E.</p>

            <div class="kl-gallery">
                @foreach ($shots as $i => $shot)
                    <button type="button"
                            @class(['kl-shot', 'kl-shot--wide' => $i % 5 === 0])
                            data-lightbox="{{ $shot }}"
                            aria-label="View rendering {{ $i + 1 }} of {{ count($shots) }} larger">
                        <img src="{{ $shot }}"
                             alt="{{ $landing['name'] }} rendering {{ $i + 1 }}"
                             loading="lazy" decoding="async">
                    </button>
                @endforeach
            </div>
        </div>
    </section>

    <dialog class="kl-lightbox" data-lightbox-dialog aria-label="Enlarged rendering">
        <button type="button" class="kl-lightbox__close" data-lightbox-close aria-label="Close">&times;</button>
        <img src="" alt="" data-lightbox-image>
    </dialog>
@endif
