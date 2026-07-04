<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);

$homepageId = theme_option('homepage_id');
echo "Homepage ID from theme_option: " . $homepageId . "<br>";

if ($homepageId) {
    $page = \Botble\Page\Models\Page::find($homepageId);
    if ($page) {
        echo "Homepage Name: " . $page->name . "<br>";
        echo "Content:<br><pre>" . htmlspecialchars($page->content) . "</pre>";
    }
}
