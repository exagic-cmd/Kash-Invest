<?php

namespace Botble\RealEstate\Services\Redbricks;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for the Redbricks Data API.
 *
 *   GET {base}/projects?page=1&per_page=200&sort_by=updated_at
 *   GET {base}/projects/{id}
 *   GET {base}/projects/{id}/floorplans
 *   GET {base}/projects/{id}/documents
 *   Header: Authorization: Bearer rb_live_...
 *
 * Response envelope is the Laravel default:
 *   { "data": [ ... ], "links": {...}, "meta": { "current_page": 1, "last_page": 9, ... } }
 *
 * Two things differ from the Treeb client and are easy to get wrong:
 *  - pages are 1-indexed here, not 0-indexed;
 *  - the documented ceiling is 60 requests/minute PER TEAM, so this class paces
 *    itself rather than leaving it to callers. The pacing is per process, so two
 *    concurrent syncs would still breach it.
 */
class RedbricksClient
{
    /** How many times to wait out a 429 before giving up on a request. */
    protected const MAX_THROTTLE_RETRIES = 3;

    /**
     * Longest Retry-After we will sit through. Anything beyond this is a spent
     * subscription quota rather than a burst throttle, and must fail immediately.
     */
    protected const MAX_WAIT_SECONDS = 300;

    /** @var (callable(int, int): void)|null fn(int $seconds, int $attempt) */
    protected $onThrottle = null;

    protected string $apiKey;

    protected string $baseUrl;

    protected int $requestsPerMinute;

    /**
     * Unix timestamp (float) of the last request, used to space calls out.
     */
    protected ?float $lastRequestAt = null;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null, ?int $requestsPerMinute = null)
    {
        $this->apiKey = (string) ($apiKey ?? config('plugins.real-estate.redbricks.api_key'));
        $this->baseUrl = rtrim((string) ($baseUrl ?? config('plugins.real-estate.redbricks.base_url')), '/');
        $this->requestsPerMinute = max(
            (int) ($requestsPerMinute ?? config('plugins.real-estate.redbricks.requests_per_minute')),
            1
        );
    }

    public function hasCredentials(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Called when a 429 forces a wait, so a long sync can say why it has stalled
     * instead of looking hung.
     *
     * @param  callable(int, int): void  $callback  fn(int $seconds, int $attempt)
     */
    public function onThrottle(callable $callback): self
    {
        $this->onThrottle = $callback;

        return $this;
    }

    /**
     * One page of projects. Ordered by updated_at descending by default: Redbricks
     * publishes no updated_since filter, so an incremental pull means walking this
     * ordering and stopping once rows stop being newer than the last sync.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed> decoded JSON body, including meta/links
     */
    public function projects(int $page = 1, int $perPage = 200, array $filters = []): array
    {
        $query = array_merge([
            'page' => max($page, 1),
            'per_page' => min(max($perPage, 1), 200),
            'sort_by' => 'updated_at',
            'sort_direction' => 'desc',
        ], $filters);

        return $this->request('/projects', $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function project(int|string $id): array
    {
        return Arr::get($this->request('/projects/' . $id), 'data', []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function floorPlansFor(int|string $projectId, int $perPage = 200): array
    {
        return Arr::get(
            $this->request(sprintf('/projects/%s/floorplans', $projectId), ['per_page' => $perPage]),
            'data',
            []
        );
    }

    /**
     * per_page is explicit on both sub-resources: the API defaults to 20, which
     * silently truncated projects holding 80+ documents or more than 20 plans.
     *
     * @return array<int, array<string, mixed>>
     */
    public function documentsFor(int|string $projectId, int $perPage = 200): array
    {
        return Arr::get(
            $this->request(sprintf('/projects/%s/documents', $projectId), ['per_page' => $perPage]),
            'data',
            []
        );
    }

    /**
     * Cheapest credential check — one project, just to see the token work.
     * Returns the catalogue size the token can see.
     */
    public function ping(): int
    {
        $body = $this->request('/projects', ['page' => 1, 'per_page' => 1]);

        return (int) Arr::get($body, 'meta.total', 0);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function request(string $path, array $query = []): array
    {
        $attempt = 0;

        do {
            $this->pace();

            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout(60)
                // Transient network faults only; 429 is handled below, because it
                // needs a wait measured in tens of seconds, not milliseconds.
                ->retry(2, 1000, throw: false)
                ->get($this->baseUrl . $path, $query);

            if ($response->status() !== 429) {
                break;
            }

            // Two different things return 429. A short Retry-After is the
            // per-minute burst ceiling and is worth waiting out. A long one means
            // the subscription-period request quota is spent — no amount of
            // waiting inside this run will clear that, and retrying only makes
            // the operator watch six minutes of pointless sleep.
            $wait = (int) ($response->header('Retry-After') ?: 0);

            if ($wait > self::MAX_WAIT_SECONDS) {
                throw new RedbricksApiException($this->quotaMessage($response, $wait), 429);
            }

            $wait = $wait > 0 ? $wait : 60;

            if ($this->onThrottle) {
                ($this->onThrottle)($wait, $attempt + 1);
            }

            sleep($wait);

            $this->lastRequestAt = null;
        } while (++$attempt < self::MAX_THROTTLE_RETRIES);

        if ($response->failed()) {
            throw new RedbricksApiException(sprintf(
                'Redbricks API returned HTTP %d for %s',
                $response->status(),
                $path
            ), $response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * Turn a quota-exhausted 429 into a message that says what ran out and when
     * it returns, because "HTTP 429" alone sends people hunting for a bug that
     * is really a billing limit.
     */
    protected function quotaMessage(Response $response, int $retryAfter): string
    {
        $limits = Arr::get($response->json() ?? [], 'limits.api_requests', []);

        $used = Arr::get($limits, 'used');
        $limit = Arr::get($limits, 'limit');

        return sprintf(
            'Redbricks request quota exhausted%s. The subscription period resets in about %s. '
            . 'Sub-resource hydration (floor plans, documents) costs one request per project — '
            . 'keep it off unless the plan allows it.',
            $used !== null && $limit !== null ? sprintf(' (%s of %s used)', $used, $limit) : '',
            $retryAfter >= 86400
                ? round($retryAfter / 86400) . ' day(s)'
                : round($retryAfter / 3600) . ' hour(s)'
        );
    }

    /**
     * Sleep just long enough that we never exceed the configured per-minute rate.
     * Cheaper than reacting to 429s, and keeps a long backfill predictable.
     */
    protected function pace(): void
    {
        $minInterval = 60 / $this->requestsPerMinute;

        if ($this->lastRequestAt !== null) {
            $elapsed = microtime(true) - $this->lastRequestAt;

            if ($elapsed < $minInterval) {
                usleep((int) (($minInterval - $elapsed) * 1_000_000));
            }
        }

        $this->lastRequestAt = microtime(true);
    }
}
