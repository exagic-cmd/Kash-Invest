<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/', 'GET');
$response = $kernel->handle($request);
$html = $response->getContent();

$start = strpos($html, '<section class="flat-map hero-banner-4">');
$end = strpos($html, '<section class="flat-section flat-recommended">');

if ($start !== false && $end !== false) {
    echo "Raw HTML between hero-banner and next section:\n";
    echo substr($html, $start, $end - $start);
} else {
    echo "Could not find start or end string.\n";
    if ($start === false) echo "Start not found.\n";
    if ($end === false) echo "End not found.\n";
}
$kernel->terminate($request, $response);
