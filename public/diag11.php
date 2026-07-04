<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/', 'GET');
$response = $kernel->handle($request);
$html = $response->getContent();

$dom = new DOMDocument();
@$dom->loadHTML($html);

$xpath = new DOMXPath($dom);
$hero = $xpath->query("//section[contains(@class, 'hero-banner-4')]")->item(0);

if ($hero) {
    $htmlOut = $dom->saveHTML($hero);
    echo "Length: " . strlen($htmlOut) . "\n";
    echo "Last 2000 chars of hero-banner-4:\n";
    echo htmlspecialchars(substr($htmlOut, -2000));
}
$kernel->terminate($request, $response);
