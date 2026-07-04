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

// Check sizes of children inside both search forms
foreach (['project-search-form', 'property-search-form'] as $formClass) {
    $form = $xpath->query("//div[contains(@class, '$formClass')]")->item(0);
    if ($form) {
        echo "=== $formClass ===\n";
        foreach ($form->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $cls = $child->getAttribute('class');
                $sz = strlen($dom->saveHTML($child));
                echo "  {$child->nodeName} ($cls) -> $sz bytes\n";
                if ($sz > 5000) {
                    foreach ($child->childNodes as $sub) {
                        if ($sub instanceof DOMElement) {
                            $scls = $sub->getAttribute('class');
                            $ssz = strlen($dom->saveHTML($sub));
                            echo "    {$sub->nodeName} ($scls) -> $ssz bytes\n";
                        }
                    }
                }
            }
        }
    }
}
$kernel->terminate($request, $response);
