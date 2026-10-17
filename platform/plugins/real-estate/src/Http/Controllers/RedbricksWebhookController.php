<?php

namespace Botble\RealEstate\Http\Controllers;

use Botble\RealEstate\Jobs\ProcessRedbricksWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Inbound endpoint for Redbricks webhook deliveries.
 *
 * Redbricks signs every delivery with HMAC-SHA256 (hex) in the "Signature"
 * header, keyed by the per-endpoint secret issued in their portal. The whole
 * raw body is signed, so parsing or re-encoding the JSON before verifying is
 * a bug — the recomputed hash will no longer match.
 *
 * The controller's job is to prove the delivery is genuine and acknowledge
 * it. The actual re-pull of the affected project is queued, both so the
 * portal sees a sub-second 2xx (and never retries) and so the work can
 * share the rate-limited details queue with the scheduled sync.
 */
class RedbricksWebhookController extends Controller
{
    /**
     * Hard-cap on body size (bytes) accepted before the signature is even
     * checked, as a cheap defense against someone POSTing a huge payload to
     * burn CPU hashing it. Redbricks' real deliveries are well under this.
     */
    protected const MAX_BODY_BYTES = 262144;

    public function handle(Request $request): JsonResponse
    {
        if (! config('plugins.real-estate.redbricks.webhooks_enabled')) {
            // 404 rather than 403: an attacker scanning should not learn
            // which routes exist but are gated off.
            return response()->json(['message' => 'Not found'], 404);
        }

        $secret = (string) config('plugins.real-estate.redbricks.webhook_secret');

        if ($secret === '') {
            Log::warning('[Redbricks Webhook] Rejected: REDBRICKS_WEBHOOK_SECRET not configured.');

            return response()->json(['message' => 'Webhook secret not configured'], 500);
        }

        $rawBody = $request->getContent();

        if (strlen($rawBody) > self::MAX_BODY_BYTES) {
            return response()->json(['message' => 'Payload too large'], 413);
        }

        $signature = (string) ($request->header('Signature') ?: $request->header('X-Redbricks-Signature'));

        if (! $this->signatureIsValid($rawBody, $signature, $secret)) {
            Log::warning('[Redbricks Webhook] Rejected: invalid signature', [
                'ip' => $request->ip(),
                'has_signature' => $signature !== '',
            ]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid JSON body'], 400);
        }

        $event = (string) (Arr::get($payload, 'event') ?: Arr::get($payload, 'type', ''));
        $externalId = $this->extractExternalId($payload);
        $deliveryId = $request->header('X-Redbricks-Delivery') ?: Arr::get($payload, 'id');

        if ($event === '' || $externalId === null || $externalId === '') {
            Log::warning('[Redbricks Webhook] Rejected: event or project id missing', [
                'event' => $event,
                'delivery_id' => $deliveryId,
            ]);

            return response()->json(['message' => 'Missing event or project id'], 422);
        }

        ProcessRedbricksWebhookJob::dispatch($event, (string) $externalId, $deliveryId ? (string) $deliveryId : null);

        Log::info('[Redbricks Webhook] Accepted', [
            'event' => $event,
            'external_id' => $externalId,
            'delivery_id' => $deliveryId,
        ]);

        return response()->json(['status' => 'queued'], 202);
    }

    /**
     * Timing-safe comparison, tolerant of the two shapes a signature can
     * take: bare hex ("3f8a...") or an algorithm-prefixed value
     * ("sha256=3f8a..."), which is what most webhook ecosystems settle on.
     */
    protected function signatureIsValid(string $body, string $signature, string $secret): bool
    {
        if ($signature === '') {
            return false;
        }

        if (str_contains($signature, '=')) {
            [, $signature] = explode('=', $signature, 2);
        }

        $expected = hash_hmac('sha256', $body, $secret);

        return hash_equals($expected, strtolower(trim($signature)));
    }

    /**
     * Redbricks publishes project / floorplan / document events. Floor plan
     * and document events carry the owning project id separately — we route
     * both to a project refresh, because sub-resources are hydrated through
     * the same code path as the project itself.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractExternalId(array $payload): int|string|null
    {
        foreach ([
            'data.project_id',
            'data.project.id',
            'project_id',
            'project.id',
            'data.id',
            'id',
        ] as $path) {
            $value = Arr::get($payload, $path);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
