<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/', 'GET');
$response = $kernel->handle($request);
$html = $response->getContent();

$start = strpos($html, '<section class="flat-map hero-banner-4">');

if ($start !== false) {
    echo "Raw HTML of hero-banner-4 (first 8000 chars):\n";
    echo htmlspecialchars(substr($html, $start, 8000));
} else {
    echo "Could not find start string.\n";
}
$kernel->terminate($request, $response);
