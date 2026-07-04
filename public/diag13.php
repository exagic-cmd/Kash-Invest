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
$tabContent = $xpath->query("//div[contains(@class, 'tab-content')]")->item(0);

if ($tabContent) {
    echo "Checking largest children of tab-content:\n";
    foreach ($tabContent->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $cClass = $child->getAttribute('class');
            $cSize = strlen($dom->saveHTML($child));
            echo "Child: {$child->nodeName} (class: $cClass) -> Size: $cSize\n";
            if ($cSize > 10000) {
                foreach ($child->childNodes as $sub) {
                    if ($sub instanceof DOMElement) {
                        $sClass = $sub->getAttribute('class');
                        $sSize = strlen($dom->saveHTML($sub));
                        echo "  Sub: {$sub->nodeName} (class: $sClass) -> Size: $sSize\n";
                        
                        if ($sSize > 10000) {
                            foreach ($sub->childNodes as $sub2) {
                                if ($sub2 instanceof DOMElement) {
                                    $s2Class = $sub2->getAttribute('class');
                                    $s2Size = strlen($dom->saveHTML($sub2));
                                    echo "    Sub2: {$sub2->nodeName} (class: $s2Class) -> Size: $s2Size\n";
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
$kernel->terminate($request, $response);
