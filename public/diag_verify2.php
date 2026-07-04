<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$urls = ['/projects', '/properties'];

foreach ($urls as $url) {
    echo "=== URL: $url ===\n";
    $request = Illuminate\Http\Request::create($url, 'GET');
    try {
        $response = $kernel->handle($request);
        $html = $response->getContent();
        echo "Page size: " . number_format(strlen($html)) . " bytes\n";
        
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // Find if wd-search-form elements exist and count them
        $searchForms = $xpath->query("//div[contains(@class, 'wd-search-form')]");
        echo "Found " . $searchForms->length . " wd-search-form elements\n";
        
        // Check if inline style tag hiding wd-search-form exists
        $styles = $xpath->query("//style");
        $foundHideRule = false;
        foreach ($styles as $style) {
            if (strpos($style->textContent, 'wd-search-form') !== false) {
                $foundHideRule = true;
            }
        }
        echo "Found inline CSS hiding rule: " . ($foundHideRule ? 'YES' : 'NO') . "\n";
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

$kernel->terminate($request, $response);
