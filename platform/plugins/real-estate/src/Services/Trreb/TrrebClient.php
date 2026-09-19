<?php

namespace Botble\RealEstate\Services\Trreb;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client for the PROPTX / AMPRE RESO Web API (OData v4).
 *
 *   GET {base}/Property?$filter=...&$top=100&$skip=0&$count=true
 *   GET {base}/Media?$filter=ResourceRecordKey eq '<key>'
 *   Header: Authorization: Bearer <token>
 *
 * Response envelope:
 *   { "@odata.count": 1234, "value": [ ... ], "@odata.nextLink": "https://..." }
 *
 * IDX COMPLIANCE: nothing in here may log a response body. Listing payloads are
 * licensed data — only counts, status codes and ListingKeys are safe to record.
 */
class TrrebClient
{
    protected string $apiKey;

    protected string $baseUrl;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null)
    {
        $this->apiKey = (string) ($apiKey ?? config('plugins.real-estate.trreb.api_key', config('plugins.real-estate.treeb.api_key')));
        $this->baseUrl = rtrim((string) ($baseUrl ?? config('plugins.real-estate.trreb.base_url', config('plugins.real-estate.treeb.base_url', 'https://query.ampre.ca/odata'))), '/');
    }

    public function hasCredentials(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * One page of listings. Pass $nextLink to follow the server's own paging
     * cursor instead of computing $skip (AMPRE returns it when more remain).
     *
     * @return array<string, mixed> decoded JSON body
     */
    public function properties(int $top = 100, int $skip = 0, ?string $filter = null, ?string $nextLink = null): array
    {
        if ($nextLink) {
            return $this->request($nextLink);
        }

        $query = [
            '$top' => $top,
            '$skip' => $skip,
            // Stable ordering; without it OData paging can repeat or drop rows.
            '$orderby' => 'ModificationTimestamp asc,ListingKey asc',
            '$count' => 'true',
        ];

        if ($filter) {
            $query['$filter'] = $filter;
        }

        return $this->request($this->baseUrl . '/Property', $query);
    }

    /**
     * Photos for one listing, ordered as the board supplies them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mediaFor(string $listingKey, int $limit = 5): array
    {
        $filter = sprintf(
            "ResourceRecordKey eq '%s' and MediaCategory eq 'Photo' and MediaStatus eq 'Active'",
            $this->escape($listingKey)
        );

        $body = $this->request($this->baseUrl . '/Media', [
            '$filter' => $filter,
            '$orderby' => 'Order asc',
            '$top' => max($limit * 5, 25),
        ]);

        $raw = Arr::get($body, 'value', []);

        $uniquePhotos = [];
        foreach ($raw as $item) {
            $objId = Arr::get($item, 'MediaObjectID') ?: Arr::get($item, 'MediaKey');
            if (! $objId) {
                continue;
            }

            $size = Arr::get($item, 'ImageSizeDescription');
            if (! isset($uniquePhotos[$objId])) {
                $uniquePhotos[$objId] = $item;
            } else {
                $currentSize = Arr::get($uniquePhotos[$objId], 'ImageSizeDescription');
                if (in_array($size, ['Largest', 'Large', 'HighRes'], true) && ! in_array($currentSize, ['Largest', 'HighRes'], true)) {
                    $uniquePhotos[$objId] = $item;
                }
            }

            if (count($uniquePhotos) >= $limit) {
                break;
            }
        }

        return array_values($uniquePhotos);
    }

    /**
     * Room dimensions for one listing, ordered by board sequence.
     *
     * @return array<int, array<string, mixed>>
     */
    public function roomsFor(string $listingKey): array
    {
        $body = $this->request($this->baseUrl . '/PropertyRooms', [
            '$filter' => "ListingKey eq '{$this->escape($listingKey)}'",
            '$orderby' => 'Order asc',
            '$top' => 50,
        ]);

        return Arr::get($body, 'value', []);
    }

    /**
     * Active open houses for one listing, ordered chronologically.
     *
     * @return array<int, array<string, mixed>>
     */
    public function openHousesFor(string $listingKey): array
    {
        $filter = sprintf(
            "ListingKey eq '%s' and OpenHouseStatus eq 'Active'",
            $this->escape($listingKey)
        );

        $body = $this->request($this->baseUrl . '/OpenHouse', [
            '$filter' => $filter,
            '$orderby' => 'OpenHouseDate asc,OpenHouseStartTime asc',
            '$top' => 20,
        ]);

        return Arr::get($body, 'value', []);
    }

    /**
     * Feed-wide open house page.
     *
     * @return array<string, mixed>
     */
    public function openHouses(int $top = 100, int $skip = 0, ?string $filter = null): array
    {
        $query = [
            '$top' => $top,
            '$skip' => $skip,
            '$orderby' => 'OpenHouseDate asc,OpenHouseStartTime asc',
        ];

        if ($filter) {
            $query['$filter'] = $filter;
        }

        return $this->request($this->baseUrl . '/OpenHouse', $query);
    }

    /**
     * Cheapest possible credential check — asks for zero rows, just the count.
     * Used by the sync job so a bad token fails in one call instead of mid-run.
     */
    public function ping(): int
    {
        $body = $this->request($this->baseUrl . '/Property', ['$top' => 0, '$count' => 'true']);

        return (int) Arr::get($body, '@odata.count', 0);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function request(string $url, array $query = []): array
    {
        $response = Http::withoutVerifying()
            ->withToken($this->apiKey)
            ->acceptJson()
            ->timeout(60)
            ->retry(2, 1000, throw: false)
            ->get($url, $query);

        if ($response->failed()) {
            // Deliberately does NOT include the body: an OData error payload can
            // echo listing fields back, and this message ends up in logs and in
            // the sync-log row. Status + resource is enough to diagnose.
            throw new TrrebApiException(sprintf(
                'PROPTX API returned HTTP %d for %s',
                $response->status(),
                parse_url($url, PHP_URL_PATH) ?: 'the requested resource'
            ), $response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * OData string literals escape a single quote by doubling it.
     */
    protected function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
