<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/', 'GET');
$response = $kernel->handle($request);
$html = $response->getContent();

$footerPos = strrpos($html, '</footer>');
if ($footerPos !== false) {
    $afterFooter = substr($html, $footerPos);
    echo "=== After footer length: " . strlen($afterFooter) . " bytes ===\n";
    echo htmlspecialchars(substr($afterFooter, 0, 5000)) . "\n";
} else {
    echo "No </footer> tag found!\n";
}

echo "\nTotal HTML: " . strlen($html) . " bytes\n";
echo "Open divs: " . substr_count($html, '<div') . " | Close divs: " . substr_count($html, '</div>') . " | Diff: " . (substr_count($html, '<div') - substr_count($html, '</div>')) . "\n";
echo "Open sections: " . substr_count($html, '<section') . " | Close sections: " . substr_count($html, '</section>') . " | Diff: " . (substr_count($html, '<section') - substr_count($html, '</section>')) . "\n";
echo "Open mains: " . substr_count($html, '<main') . " | Close mains: " . substr_count($html, '</main>') . " | Diff: " . (substr_count($html, '<main') - substr_count($html, '</main>')) . "\n";

$kernel->terminate($request, $response);
