<?php

return [
    /*
     * Master on/off switch for the Redbricks sync (command + schedule).
     */
    'enabled' => env('REDBRICKS_SYNC_ENABLED', true),

    /*
     * Redbricks Data API.
     *
     *   GET {base_url}/projects?page=1&per_page=200
     *   Header: Authorization: Bearer rb_live_...
     *
     * Note the MCP server lives on a different host (api.redbricksdata.com) and
     * is not used here — that is an assistant-facing interface, not a data feed.
     */
    'api_key' => env('REDBRICKS_API_KEY'),
    'base_url' => env('REDBRICKS_API_BASE_URL', 'https://api.redbricks.ca/api/v1'),

    /*
     * Page size. Redbricks caps per_page at 200 and pages are 1-indexed.
     */
    'per_page' => (int) env('REDBRICKS_PER_PAGE', 200),

    /*
     * Testing cap: stop the run once this many projects have been saved.
     * 0 = uncapped (the real, full-catalogue sync).
     */
    'max_records' => (int) env('REDBRICKS_MAX_RECORDS', 0),

    /*
     * Documented rate limit is 60 requests per minute per team. The client
     * paces itself against this; lower it if we share the token with another
     * consumer, because the ceiling is per team and not per process.
     */
    'requests_per_minute' => (int) env('REDBRICKS_REQUESTS_PER_MINUTE', 55),

    /*
     * Server-side scoping. Redbricks filters by city/district/neighbourhood
     * rather than by province, so Ontario has to be expressed as a city list.
     * Comma-separated IDs or names; empty means the whole catalogue.
     */
    'cities' => env('REDBRICKS_CITIES'),

    /*
     * Extra query parameters appended to the projects request, as a query
     * string (e.g. "construction_status=Pre-Construction&type=Condo").
     * Escape hatch for scoping without a code change.
     */
    'extra_query' => env('REDBRICKS_EXTRA_QUERY'),

    /*
     * Sub-resource hydration. Floor plans and documents are separate per-project
     * endpoints, so each one costs one request per project. Across the full
     * catalogue that is hours at the documented rate limit.
     *
     * OFF by default, and the reason is the request quota rather than the clock:
     * the plan caps total API requests per subscription period (100 on the trial
     * token). With both of these on, one full sync of 15 projects costs 31
     * requests instead of 1 — three runs exhausted the entire period's quota.
     *
     * Turn them on deliberately, for a one-off hydration run, once you know what
     * the plan's request allowance actually is.
     */
    'sync_floor_plans' => env('REDBRICKS_SYNC_FLOOR_PLANS', false),
    'sync_documents' => env('REDBRICKS_SYNC_DOCUMENTS', false),

    /*
     * Image sync: download project photos into Botble's media library.
     */
    'sync_images' => env('REDBRICKS_SYNC_IMAGES', true),
    'max_images_per_project' => (int) env('REDBRICKS_MAX_IMAGES_PER_PROJECT', 5),

    /*
     * Webhooks. Redbricks pushes project/floorplan/document lifecycle events
     * signed with HMAC-SHA256 in the "Signature" header, which is a better
     * incremental mechanism than polling. The secret is issued per endpoint in
     * their portal; without it every delivery is rejected.
     *
     * Their documentation states no retry or replay policy, so the scheduled
     * reconciliation sweep below stays on even when webhooks are working.
     */
    'webhook_secret' => env('REDBRICKS_WEBHOOK_SECRET'),
    'webhooks_enabled' => env('REDBRICKS_WEBHOOKS_ENABLED', false),

    /*
     * Nightly reconciliation: re-pull projects ordered by updated_at and stop
     * once we reach rows we already hold unchanged. Catches anything a missed
     * webhook would otherwise leave permanently stale.
     */
    'schedule_at' => env('REDBRICKS_SCHEDULE_AT', '04:00'),
];
