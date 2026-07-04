<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/', 'GET');
$response = $kernel->handle($request);
$html = $response->getContent();

// Find height-related inline styles
preg_match_all('/style="[^"]*height:\s*\d{4,}[^"]*"/', $html, $matches);
echo "=== Inline styles with very large heights ===\n";
foreach ($matches[0] as $m) {
    echo htmlspecialchars($m) . "\n\n";
}

// Search for mortgage-calculator related content
$mortgagePos = strpos($html, 'mortgage');
if ($mortgagePos !== false) {
    echo "\n=== Mortgage section context (500 chars around first occurrence) ===\n";
    echo htmlspecialchars(substr($html, max(0, $mortgagePos - 200), 700)) . "\n";
}

// Check for elements with very large padding or margin
preg_match_all('/style="[^"]*(?:padding|margin)[^"]*\d{4,}px[^"]*"/', $html, $matches2);
echo "\n=== Inline styles with very large padding/margins ===\n";
foreach ($matches2[0] as $m) {
    echo htmlspecialchars($m) . "\n\n";
}

// Look for any hidden divs or overflow elements that might cause issues
echo "\n=== Checking for large invisible/overflow sections ===\n";
preg_match_all('/<div[^>]*class="[^"]*(?:modal|offcanvas|sidebar|hidden|overlay)[^"]*"/', $html, $matches3);
echo "Count of modal/offcanvas/sidebar/hidden/overlay divs: " . count($matches3[0]) . "\n";
foreach (array_slice($matches3[0], 0, 15) as $m) {
    echo htmlspecialchars($m) . "\n";
}

$kernel->terminate($request, $response);
