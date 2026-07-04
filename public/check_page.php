<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Simulate a request to the homepage
$request = Illuminate\Http\Request::create('/', 'GET');
$response = $kernel->handle($request);

$html = $response->getContent();

// Find everything after the closing </footer> tag
$footerPos = strrpos($html, '</footer>');
if ($footerPos !== false) {
    $afterFooter = substr($html, $footerPos);
    echo "=== Content after last </footer> tag ===<br>";
    echo "<pre>" . htmlspecialchars($afterFooter) . "</pre>";
    echo "<br>Length after footer: " . strlen($afterFooter) . " bytes<br>";
} else {
    echo "No </footer> tag found!<br>";
}

// Also check total page size
echo "<br>Total HTML size: " . strlen($html) . " bytes<br>";

// Check for unclosed tags
$openDivs = substr_count($html, '<div');
$closeDivs = substr_count($html, '</div>');
echo "Open divs: $openDivs | Close divs: $closeDivs | Diff: " . ($openDivs - $closeDivs) . "<br>";

$openSections = substr_count($html, '<section');
$closeSections = substr_count($html, '</section>');
echo "Open sections: $openSections | Close sections: $closeSections | Diff: " . ($openSections - $closeSections) . "<br>";

$openMains = substr_count($html, '<main');
$closeMains = substr_count($html, '</main>');
echo "Open mains: $openMains | Close mains: $closeMains | Diff: " . ($openMains - $closeMains) . "<br>";

$kernel->terminate($request, $response);
