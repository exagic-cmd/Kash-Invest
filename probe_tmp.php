<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Botble\RealEstate\Models\Project;
use Botble\RealEstate\Models\ProjectLandingPage;

echo "=== what is STORED on each landing page row ===\n";
foreach (ProjectLandingPage::query()->with('project')->orderBy('project_id')->get() as $p) {
    $c = is_array($p->content) ? $p->content : [];
    $stored = [];
    array_walk_recursive($c, function ($v, $k) use (&$stored) {
        if (filled($v) && ! is_bool($v)) { $stored[] = $k; }
    });
    printf("  proj=%-4s %-22s stored keys with values: %d  (%s)\n",
        $p->project_id, $p->project?->name ?? '?', count($stored),
        $stored ? implode(', ', array_slice(array_unique($stored), 0, 6)) : 'EMPTY — fully live');
}

echo "\n=== live vs stored, for a saved page (project 24) ===\n";
$project = Project::query()->find(24);
$page = ProjectLandingPage::query()->where('project_id', 24)->where('is_primary', true)->first();
$c = is_array($page->content) ? $page->content : [];

$rows = [
    'name'        => ['live' => $project->name,                       'stored' => data_get($c, 'hero.heading')],
    'description' => ['live' => \Illuminate\Support\Str::limit(strip_tags((string) $project->description), 45),
                      'stored' => \Illuminate\Support\Str::limit(strip_tags((string) data_get($c, 'overview.body')), 45)],
    'price_from'  => ['live' => $project->price_from,                 'stored' => data_get($c, 'hero.price')],
    'neighbour'   => ['live' => $project->neighbour,                  'stored' => data_get($c, 'location.neighbourhood')],
    'images[0]'   => ['live' => \Illuminate\Support\Arr::first($project->images ?: []),
                      'stored' => \Illuminate\Support\Arr::first((array) data_get($c, 'gallery.images', []))],
];
foreach ($rows as $field => $v) {
    printf("  %-12s live: %-48s stored: %s\n", $field,
        \Illuminate\Support\Str::limit((string) ($v['live'] ?? '—'), 46),
        $v['stored'] === null || $v['stored'] === '' ? '(none → uses live)' : \Illuminate\Support\Str::limit((string) $v['stored'], 40));
}

echo "\n=== proof: change the project, does the landing page follow? ===\n";
$original = $project->name;
$project->name = $original . ' [LIVE-TEST]';
$project->saveQuietly();

$landing = \Theme\Homzen\Support\LandingData::fromProject(Project::find(24), $page);
echo "  project renamed to: {$project->name}\n";
echo "  landing hero heading now: " . var_export($landing['hero']['heading'] ?: $landing['name'], true) . "\n";
echo "  landing page <title> name: " . var_export($landing['name'], true) . "\n";

$project->name = $original;
$project->saveQuietly();
echo "  (project name restored to '{$original}')\n";
