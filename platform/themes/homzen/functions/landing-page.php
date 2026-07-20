<?php

/**
 * Landing pages live at their own dedicated URL (/landing/{project}) — see
 * routes/web.php -> LandingPageController@show. They are NOT served at the
 * project's own URL anymore: the project detail page renders as usual, and
 * the landing page is an isolated, unlinked page used only for Google Ads.
 *
 * The previous hook hijacked BASE_ACTION_PUBLIC_RENDER_SINGLE and replaced
 * the project detail page with the landing template. It has been removed so
 * regular site visitors always see the standard project page.
 */
