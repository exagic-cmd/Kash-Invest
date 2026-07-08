<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Botble\RealEstate\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== RAW DB DATA FOR ID 155 ===\n";
$project = Project::find(155);

if ($project) {
    echo "Name: [" . $project->name . "]\n";
    echo "Status: " . $project->status . "\n";
    
    if (Schema::hasTable('re_projects_translations')) {
        $translations = DB::table('re_projects_translations')->where('re_projects_id', 155)->get();
        echo "Translations (" . count($translations) . "):\n";
        foreach ($translations as $t) {
            echo "  - Lang: [{$t->lang_code}] Name: [{$t->name}]\n";
        }
    } else {
        echo "No translation table found.\n";
    }
} else {
    echo "Project 155 not found!\n";
}
