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
    echo "hero-banner-4 children lengths:\n";
    foreach ($hero->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $id = $child->getAttribute('id') ?: 'no-id';
            $class = $child->getAttribute('class') ?: 'no-class';
            $htmlChild = $dom->saveHTML($child);
            $len = strlen($htmlChild);
            echo "Element: <{$child->nodeName} id=\"$id\" class=\"$class\"> - Size: " . number_format($len) . " bytes\n";
        }
    }
}
$kernel->terminate($request, $response);
