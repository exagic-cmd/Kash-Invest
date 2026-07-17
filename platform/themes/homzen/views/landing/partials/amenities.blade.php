{{-- Amenities come from the project's real `features` relation. --}}
@if ($landing['amenities'])
    <section class="kl-section" id="amenities">
        <div class="kl-wrap kl-reveal">
            <div class="kl-eyebrow">Amenities</div>
            <h2 class="kl-h2">Building Features</h2>
            <p class="kl-lede">{{ count($landing['amenities']) }} amenities planned for residents.</p>

            <div class="kl-amenities">
                @foreach ($landing['amenities'] as $amenity)
                    <div class="kl-amenity">{{ $amenity }}</div>
                @endforeach
            </div>
        </div>
    </section>
@endif
