<?php

use Botble\RealEstate\Http\Controllers\RedbricksWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Inbound webhook endpoints. Deliberately NOT inside the "web" middleware
 * group: those routes carry CSRF, session, cookie-encryption and sanctum
 * middleware, none of which make sense for a server-to-server POST signed
 * with HMAC. Authentication is the Signature header, verified in the
 * controller.
 */

Route::post('api/webhooks/redbricks', [RedbricksWebhookController::class, 'handle'])
    ->name('real-estate.webhooks.redbricks');
