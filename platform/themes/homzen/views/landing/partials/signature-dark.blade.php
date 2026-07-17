{{--
    SIGNATURE — dark theme: the Deposit Ladder.

    In preconstruction you hand over money years before there's a building. The
    deposit schedule is the single most anxiety-inducing thing on the page and
    it's usually buried in a PDF. Here it IS the page's memorable object: each
    milestone is a floor plate on a tower section that lights as you scroll.
    Bold spend goes here; everything else stays quiet.

    Driven entirely by the project's real deposit fields — if the project has no
    deposit data, the section doesn't render at all.
--}}
@php
    $deposit = $landing['deposit'];
    $ladder = $deposit['ladder'];
    $isDeposit = $ladder['mode'] === 'deposit';
@endphp

@if ($ladder['rungs'] || $deposit['total'] || $deposit['notes'])
    <section class="kl-section" id="deposit">
        <div class="kl-wrap">
            <div class="kl-2col">
                <div class="kl-reveal">
                    <div class="kl-eyebrow">{{ $isDeposit ? 'Deposit Structure' : 'The Real Cost' }}</div>
                    <h2 class="kl-h2">
                        {{ $isDeposit ? 'What You Pay, And When' : 'What It Costs Beyond The Price' }}
                    </h2>
                    <p class="kl-lede">
                        @if ($isDeposit)
                            Every dollar of your deposit, laid out before you commit. No PDF, no phone call first.
                        @else
                            The sticker price is never the whole number. Here's what else this suite carries —
                            published up front, not discovered at closing.
                        @endif
                    </p>

                    @if ($ladder['rungs'])
                        <div class="kl-ladder" data-ladder>
                            @foreach ($ladder['rungs'] as $rung)
                                <div class="kl-rung" data-rung data-lit="false">
                                    <div class="kl-rung__amount kl-num">{{ $rung['amount'] ?: '—' }}</div>
                                    <div class="kl-rung__plate" aria-hidden="true"></div>
                                    <div class="kl-rung__label">{{ $rung['label'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    @elseif ($deposit['notes'])
                        <p style="margin-top:2rem;">{{ $deposit['notes'] }}</p>
                    @endif

                    {{-- Foot carries the figures the rungs don't, so nothing repeats. --}}
                    <div class="kl-ladder__foot">
                        @if ($landing['price']['fromFormatted'])
                            <div>
                                <span>Suite price from</span>
                                <strong class="kl-num">{{ $landing['price']['fromFormatted'] }}</strong>
                            </div>
                        @endif
                        @if ($landing['price']['perSqft'])
                            <div>
                                <span>Price per sq ft</span>
                                <strong class="kl-num">{{ $landing['price']['perSqft'] }}</strong>
                            </div>
                        @endif
                        @if ($deposit['total'])
                            <div>
                                <span>Total deposit</span>
                                <strong class="kl-num">{{ $deposit['total'] }}</strong>
                            </div>
                        @endif
                        @if (! $isDeposit && $deposit['maintenance'])
                            <div>
                                <span>Est. maintenance</span>
                                <strong class="kl-num">{{ $deposit['maintenance'] }}</strong>
                            </div>
                        @endif
                    </div>
                </div>

                <aside class="kl-reveal">
                    <div class="kl-card">
                        <h3 style="font-size:1rem;text-transform:uppercase;letter-spacing:.12em;">Before you commit</h3>
                        <ul class="kl-nearby" style="margin-top:1rem;">
                            @if ($isDeposit)
                                <li><span>Your deposit is held in trust under the Ontario <em>New Home Construction Licensing Act</em>.</span></li>
                            @else
                                <li><span>Maintenance is billed per sq ft, per month — it scales with your suite size.</span></li>
                            @endif
                            <li><span>Every purchase carries a 10-day statutory cooling-off period.</span></li>
                            <li><span>Occupancy dates on preconstruction projects can and do move.</span></li>
                        </ul>
                        <p class="kl-form__note" style="margin-top:1rem;">
                            Figures shown are the project's published figures and are subject to change without notice.
                            @if (! $isDeposit)
                                A deposit schedule has not been published for this project yet.
                            @endif
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endif
