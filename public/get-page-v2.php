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
    echo "<h1>All Pages list and their hero-banner style:</h1><br>\n";
    foreach ($pages as $page) {
        if (str_contains($page->content, 'hero-banner')) {
            preg_match('/style="([^"]+)"/', $page->content, $matches);
            $style = $matches[1] ?? 'none';
            echo "Page ID: {$page->id}, Title: {$page->name}, Status: {$page->status}, Style: {$style}<br>\n";
        } else {
            echo "Page ID: {$page->id}, Title: {$page->name}, Status: {$page->status} (No hero-banner)<br>\n";
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
