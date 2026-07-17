{{--
    Landing template — DARK.
    "The sales gallery at dusk": a narrow lit column ascending through warm
    graphite, content spotlit like a scale model in a presentation centre.

    Tokens: warm graphite #171513 / champagne bronze #B08D57 / blue-steel #5B7B8A
    Type:   Jost (display, uppercase, tracked) + Inter (body, tabular figures)
    Signature: the Deposit Ladder — see partials/signature-dark.blade.php

    Structure and data contract are shared with light.blade.php via base.
--}}
@include(Theme::getThemeNamespace('views.landing.base'), ['landing' => $landing, 'theme' => 'dark'])
