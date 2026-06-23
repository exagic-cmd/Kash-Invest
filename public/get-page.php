<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;

try {
    $pages = DB::table('pages')->get();
    foreach ($pages as $page) {
        if (str_contains($page->content, 'style="4"')) {
            echo "<h3>Found Page ID: {$page->id}, Title: {$page->name} has style=\"4\"</h3>\n";
            echo "<pre>" . htmlspecialchars(substr($page->content, 0, 500)) . "</pre><hr>\n";
        }
        if (str_contains($page->content, 'hero-banner')) {
            echo "Page ID: {$page->id}, Title: {$page->name} has [hero-banner]. Style attribute: ";
            preg_match('/style="([^"]+)"/', $page->content, $matches);
            echo ($matches[1] ?? 'none') . "<br>\n";
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
