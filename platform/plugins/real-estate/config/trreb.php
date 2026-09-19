<?php

return [
    /*
     * Master on/off switch for the TRREB/PROPTX sync (command + schedule).
     */
    'enabled' => env('TRREB_SYNC_ENABLED', env('TREEB_SYNC_ENABLED', true)),

    /*
     * PROPTX AMPRE RESO Web API (OData v4).
     *
     *   GET {base_url}/Property?$filter=...&$top=...&$skip=...
     *   Header: Authorization: Bearer <token>
     *
     * The token is IDX-licensed material under the PROPTX/TRREB IDX Agreement:
     * keep it in .env only, never in version control or a support ticket.
     */
    'api_key' => env('TRREB_API_KEY', env('TREEB_API_KEY')),
    'base_url' => env('TRREB_API_BASE_URL', env('TREEB_API_BASE_URL', 'https://query.ampre.ca/odata')),

    /*
     * OData $top per request. AMPRE caps this server-side (commonly 100).
     */
    'per_page' => (int) env('TRREB_PER_PAGE', env('TREEB_PER_PAGE', 100)),

    /*
     * Testing cap: stop the run once this many listings have been saved.
     * 0 = uncapped (the real, full-feed sync).
     */
    'max_records' => (int) env('TRREB_MAX_RECORDS', env('TREEB_MAX_RECORDS', 0)),

    /*
     * Which TransactionType values to pull: "sale", "lease", or "sale,lease".
     */
    'transaction_types' => env('TRREB_TRANSACTION_TYPES', env('TREEB_TRANSACTION_TYPES', 'sale,lease')),

    /*
     * Which RESO StandardStatus values to seed from on the FIRST run (when there
     * is no previous successful sync to measure changes against).
     *
     * Incremental runs deliberately ignore this: they must receive listings whose
     * status just changed to Sold/Leased/Expired, otherwise a listing that leaves
     * the market would stay on the site advertised as available forever.
     */
    'standard_statuses' => env('TRREB_STANDARD_STATUSES', env('TREEB_STANDARD_STATUSES', 'Active')),

    /*
     * What to do when a listing we already hold leaves Active status.
     *
     *   hide   - keep the row, set its status to sold/rented/not_available and
     *            drop moderation back to pending so it disappears from the site.
     *   delete - remove the property and its imported photos outright.
     *
     * "hide" is the default: it satisfies the accuracy requirement without
     * discarding the audit trail. Choose "delete" if PROPTX requires off-market
     * listings to be purged rather than merely hidden.
     */
    'delisted_action' => env('TRREB_DELISTED_ACTION', env('TREEB_DELISTED_ACTION', 'hide')),

    /*
     * Mirror PropertyType / PropertySubType into re_categories so every kind of
     * listing (Residential Freehold, Residential Condo & Other, Commercial, ...)
     * is browsable on the front end.
     */
    'sync_categories' => env('TRREB_SYNC_CATEGORIES', env('TREEB_SYNC_CATEGORIES', true)),

    /*
     * TRREB quotes prices in Canadian dollars, but the feed carries no currency
     * field. This is matched against re_currencies.title; if it isn't found the
     * site default is used and the run warns, so CAD prices are never quietly
     * displayed under the wrong symbol.
     */
    'currency' => env('TRREB_CURRENCY', env('TREEB_CURRENCY', 'CAD')),

    /*
     * Extra raw OData $filter appended with "and". Escape hatch for board-specific
     * scoping (e.g. "City eq 'Toronto'") without a code change.
     */
    'extra_filter' => env('TRREB_EXTRA_FILTER', env('TREEB_EXTRA_FILTER')),

    /*
     * Image sync: download listing photos from the Media resource into Botble's
     * media library. This is the slowest part of a run by a wide margin.
     */
    'sync_images' => env('TRREB_SYNC_IMAGES', env('TREEB_SYNC_IMAGES', true)),
    'max_images_per_property' => (int) env('TRREB_MAX_IMAGES_PER_PROPERTY', env('TREEB_MAX_IMAGES_PER_PROPERTY', 5)),

    /*
     * The IDX agreement requires a refresh at least every 24 hours.
     * Changing this away from a sub-24h cadence is a compliance decision.
     */
    'schedule_at' => env('TRREB_SCHEDULE_AT', env('TREEB_SCHEDULE_AT', '03:00')),
];
