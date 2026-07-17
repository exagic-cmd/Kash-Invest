{{--
    Landing template — LIGHT.
    "The neighbourhood walk": an airy editorial spread where photography carries
    the page and text recedes into generous margins.

    Tokens: pale sage-limestone #EFF1EC / ravine green #2F5D50 / sand #E4DDD1
    Type:   Fraunces (display, low-contrast serif) + Inter (body, tabular figures)
    Signature: Today → Occupancy compare — see partials/signature-light.blade.php

    Structure and data contract are shared with dark.blade.php via base.
--}}
@include(Theme::getThemeNamespace('views.landing.base'), ['landing' => $landing, 'theme' => 'light'])
