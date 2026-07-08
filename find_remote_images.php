    <?php
/**
 * Find projects that still have remote (non-local) image URLs.
 * Run: php find_remote_images.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Botble\RealEstate\Models\Project;

$projects = Project::query()
    ->where('unique_id', 'like', 'buildify-%')
    ->get(['id', 'name', 'unique_id', 'images']);

$remoteProjects = [];

foreach ($projects as $project) {
    $images = is_array($project->images) ? $project->images : json_decode($project->images, true) ?? [];
    $remoteUrls = [];
    foreach ($images as $img) {
        if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) {
            $remoteUrls[] = $img;
        }
    }
    if (count($remoteUrls) > 0) {
        $remoteProjects[] = [
            'id' => $project->id,
            'name' => $project->name,
            'unique_id' => $project->unique_id,
            'total_images' => count($images),
            'remote_count' => count($remoteUrls),
            'remote_urls' => $remoteUrls,
        ];
    }
}

echo "=== Projects with remote image URLs ===\n";
echo "Total Buildify projects: " . $projects->count() . "\n";
echo "Projects with remote URLs: " . count($remoteProjects) . "\n\n";

foreach ($remoteProjects as $p) {
    echo "Project #{$p['id']}: {$p['name']} ({$p['unique_id']})\n";
    echo "  Images: {$p['total_images']} total, {$p['remote_count']} remote\n";
    foreach ($p['remote_urls'] as $url) {
        echo "  → $url\n";
    }
    echo "\n";
}

if (empty($remoteProjects)) {
    echo "✅ All 161 projects have fully local images!\n";
}
