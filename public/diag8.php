<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/', 'GET');
$response = $kernel->handle($request);
$html = $response->getContent();

if (strpos($html, '[projects') !== false) {
    echo "Found raw shortcode string in HTML!\n";
    $pos = strpos($html, '[projects');
    echo substr($html, $pos, 200);
} else {
    echo "No raw shortcode string found. Shortcode was processed.\n";
}

$kernel->terminate($request, $response);
