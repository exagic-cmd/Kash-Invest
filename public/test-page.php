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
    // Get homepage settings directly from options/settings DB table
    $homepageSetting = DB::table('settings')->where('key', 'homepage_id')->first();
    echo "homepage_id setting row: " . json_encode($homepageSetting) . "\n<br>";

    $themeOption = DB::table('options')->where('key', 'homepage_id')->first();
    echo "homepage_id theme option row: " . json_encode($themeOption) . "\n<br>";
    
    $themeName = DB::table('options')->where('key', 'like', '%theme%')->get();
    echo "Theme options count: " . count($themeName) . "\n<br>";
    foreach ($themeName as $option) {
        if (str_contains($option->key, 'homepage_id') || str_contains($option->key, 'primary_color')) {
            echo "Option: {$option->key} = {$option->value}\n<br>";
        }
    }
    
    // Dump all settings containing homepage
    $allHomeSettings = DB::table('settings')->where('key', 'like', '%homepage%')->get();
    foreach ($allHomeSettings as $s) {
        echo "Setting: {$s->key} = {$s->value}\n<br>";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
