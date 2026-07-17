{{-- Overview — reference layout: centred title-case heading, prose block, then
     the incentives as a two-column teal checkmark list. All project-driven. --}}
@php
    // description + content are both real fields; show content only when it adds
    // something (the Buildify sync writes the same summary to both).
    $paras = [];
    if ($landing['overview']['description']) {
        $paras[] = $landing['overview']['description'];
    }
    if ($landing['overview']['content'] && $landing['overview']['content'] !== $landing['overview']['description']) {
        $paras[] = $landing['overview']['content'];
    }

    // Trust/credibility points beyond the raw incentives.
    $points = $landing['incentives'];
    if ($landing['developer']['name']) {
        $points[] = 'Developed by ' . $landing['developer']['name'];
    }
    if ($landing['constructionStatus']) {
        $points[] = 'Construction status: ' . $landing['constructionStatus'];
    }
@endphp

<section class="kl-section" id="overview">
    <div class="kl-wrap kl-reveal">
        <div class="kl-head-center kl-head-center--title">
            <h2 class="kl-h2">{{ $landing['tagline'] ?: ($landing['overview']['heading'] ?: 'About this project') }}</h2>
        </div>

        @if ($paras)
            <div class="kl-prose">
                @foreach ($paras as $para)
                    <p>{!! BaseHelper::clean($para) !!}</p>
                @endforeach
            </div>
        @endif

        @if ($points)
            <ul class="kl-checks kl-prose" style="margin-top:2.5rem;">
                @foreach ($points as $point)
                    <li>{{ $point }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
