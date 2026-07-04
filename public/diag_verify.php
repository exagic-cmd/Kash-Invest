<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/', 'GET');
$response = $kernel->handle($request);
$html = $response->getContent();
echo "Page size: " . number_format(strlen($html)) . " bytes\n";

$dom = new DOMDocument();
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

// Check if wd-search-form has display:none
$searchForms = $xpath->query("//div[contains(@class, 'wd-search-form')]");
echo "Found " . $searchForms->length . " wd-search-form elements\n";

// Check if style tag with display:none exists
$styles = $xpath->query("//style");
foreach ($styles as $style) {
    $content = $style->textContent;
    if (strpos($content, 'wd-search-form') !== false) {
        echo "Found CSS rule for wd-search-form: " . trim($content) . "\n";
    }
}

$hero = $xpath->query("//section[contains(@class, 'hero-banner-4')]")->item(0);
if ($hero) {
    echo "Hero banner size: " . number_format(strlen($dom->saveHTML($hero))) . " bytes\n";
}

$kernel->terminate($request, $response);
