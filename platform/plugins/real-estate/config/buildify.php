<?php

return [
    /*
     * Master on/off switch for the Buildify sync (command + schedule).
     */
    'enabled' => env('BUILDIFY_SYNC_ENABLED', true),

    /*
     * Buildify API credentials & scope. The API key is passed as the
     * "X-BLOBR-KEY" header (Buildify uses the BLOBR gateway).
     *
     * base_url  : API root, no trailing slash.
     * version   : v1 | v1-lite | v1-sandbox
     * province  : all | on | bc | ab | ... (we default to Ontario).
     * per_page  : listings per request when paging the full catalog.
     */
    'api_key' => env('BUILDIFY_API_KEY'),
    'base_url' => env('BUILDIFY_API_BASE_URL', 'https://api.getbuildify.com'),
    'version' => env('BUILDIFY_API_VERSION', 'v1'),
    'province' => env('BUILDIFY_API_PROVINCE', 'on'),
    'per_page' => (int) env('BUILDIFY_PER_PAGE', 50),

    /*
     * Image sync: download Buildify photos into Botble's media library.
     * max_images_per_project caps downloads per listing (cover + gallery).
     */
    'sync_images' => env('BUILDIFY_SYNC_IMAGES', true),
    'max_images_per_project' => (int) env('BUILDIFY_MAX_IMAGES_PER_PROJECT', 5),
];
