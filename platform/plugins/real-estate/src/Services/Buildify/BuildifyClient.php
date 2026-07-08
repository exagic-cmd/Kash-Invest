<?php

namespace Botble\RealEstate\Services\Buildify;

use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client for the Buildify "search listings" endpoint.
 *
 *   GET https://api.getbuildify.com/{version}/{province}/search_listings
 *   Header: X-BLOBR-KEY: <api key>
 *
 * The response envelope looks like:
 *   { "results": [ ... ], "total": 161, "page": 0, "pages": 33, "perPage": 5 }
 */
class BuildifyClient
{
    protected string $apiKey;

    protected string $baseUrl;

    protected string $version;

    protected string $province;

    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $version = null,
        ?string $province = null,
    ) {
        $this->apiKey = (string) ($apiKey ?? config('plugins.real-estate.buildify.api_key'));
        $this->baseUrl = rtrim((string) ($baseUrl ?? config('plugins.real-estate.buildify.base_url')), '/');
        $this->version = (string) ($version ?? config('plugins.real-estate.buildify.version'));
        $this->province = (string) ($province ?? config('plugins.real-estate.buildify.province'));
    }

    /**
     * Fetch a single page of listings. Page numbers start at 0.
     *
     * @return array<string, mixed> decoded JSON body
     */
    public function searchListings(int $page = 0, int $perPage = 50): array
    {
        $url = sprintf('%s/%s/%s/search_listings', $this->baseUrl, $this->version, $this->province);

        // Buildify uses the BLOBR gateway, but it sits behind Google API Gateway
        // which strictly requires the x-api-key header for caller identification.
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'X-BLOBR-KEY' => $this->apiKey,
        ])
            ->acceptJson()
            ->timeout(60)
            ->retry(2, 500, throw: false)
            ->get($url, [
                'page' => $page,
                'perPage' => $perPage,
            ]);

        $response->throw();

        return $response->json() ?? [];
    }
}
