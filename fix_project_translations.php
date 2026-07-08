<?php
/**
 * Fix missing translations for imported projects so they appear in admin search.
 * Run: php fix_project_translations.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Botble\RealEstate\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (!Schema::hasTable('re_projects_translations')) {
    echo "Translation table doesn't exist. Nothing to fix.\n";
    exit;
}

$projects = Project::all();
$fixed = 0;

// Determine default language code (Botble usually uses en_US)
$langCode = 'en_US';
if (is_plugin_active('language')) {
    $langCode = \Botble\Language\Facades\Language::getDefaultLocaleCode();
}

foreach ($projects as $project) {
    $exists = DB::table('re_projects_translations')
        ->where('re_projects_id', $project->id)
        ->where('lang_code', $langCode)
        ->exists();

    if (!$exists) {
        DB::table('re_projects_translations')->insert([
            're_projects_id' => $project->id,
            'lang_code'      => $langCode,
            'name'           => $project->name ?? '',
            'description'    => $project->description ?? '',
            'content'        => $project->content ?? '',
            'location'       => $project->location ?? '',
        ]);
        $fixed++;
    }
}

echo "✅ Created missing translations for $fixed projects.\n";
echo "They should now appear properly in your admin panel search!\n";
