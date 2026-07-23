<?php

namespace Botble\RealEstate\Commands;

use Botble\RealEstate\Models\Project;
use Botble\Slug\Facades\SlugHelper;
use Botble\Slug\Models\Slug;
use Botble\Slug\Services\SlugService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('cms:real-estate:update-project-slugs', 'Update all project slugs to format: province-city-project-name')]
class UpdateProjectSlugsCommand extends Command
{
    public function handle(): int
    {
        $this->components->info('Updating project slugs to include Province/State and City...');

        $projects = Project::query()->with(['state', 'city', 'slugable'])->get();
        $total = $projects->count();
        $updated = 0;
        $created = 0;
        $unchanged = 0;

        $slugService = new SlugService();
        $prefix = SlugHelper::getPrefix(Project::class, 'projects', false);

        foreach ($projects as $project) {
            $newSlugKey = $project->generateSlugKey();

            if (empty($newSlugKey)) {
                $unchanged++;
                continue;
            }

            /** @var Slug|null $slugRecord */
            $slugRecord = Slug::query()
                ->where('reference_type', Project::class)
                ->where('reference_id', $project->id)
                ->first();

            if ($slugRecord) {
                if ($slugRecord->key !== $newSlugKey) {
                    $slugRecord->key = $newSlugKey;
                    $slugRecord->save();
                    $updated++;
                } else {
                    $unchanged++;
                }
            } else {
                Slug::query()->create([
                    'key' => $newSlugKey,
                    'reference_type' => Project::class,
                    'reference_id' => $project->id,
                    'prefix' => $prefix,
                ]);
                $created++;
            }
        }

        $this->components->info("Done! Processed {$total} projects: {$updated} updated, {$created} created, {$unchanged} unchanged.");

        return self::SUCCESS;
    }
}
