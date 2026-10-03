<?php

namespace Botble\RealEstate\Commands;

use Botble\RealEstate\Models\Project;
use Botble\RealEstate\Models\ProjectDocument;
use Botble\RealEstate\Models\ProjectFloorPlan;
use Botble\RealEstate\Models\ProjectSyncLog;
use Botble\RealEstate\Services\Redbricks\RedbricksProjectSyncer;
use Botble\Slug\Models\Slug;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Removes everything the Redbricks syncer owns:
 *  - re_projects rows (source = 'redbricks'), with their custom fields, features,
 *    facilities, categories, translations and slugs
 *  - re_project_floor_plans and re_project_documents belonging to those projects
 *  - imported floor plan images and project gallery images from S3
 *  - sync log rows and their per-project items
 *
 * Manually created and Excel-imported projects are matched on source and are
 * never touched.
 */
#[AsCommand('cms:redbricks:purge', 'Permanently delete all Redbricks projects and their derived data')]
class PurgeRedbricksDataCommand extends Command
{
    public function handle(): int
    {
        @set_time_limit(0);

        $source = RedbricksProjectSyncer::SOURCE;

        $projectIds = Project::query()->where('source', $source)->pluck('id');
        $logIds = ProjectSyncLog::query()->where('source', $source)->pluck('id');

        $floorPlanCount = ProjectFloorPlan::query()->whereIn('project_id', $projectIds)->count();
        $documentCount = ProjectDocument::query()->whereIn('project_id', $projectIds)->count();

        $this->components->info(sprintf(
            'About to permanently delete: %d projects, %d floor plans, %d documents, %d sync log runs.',
            $projectIds->count(),
            $floorPlanCount,
            $documentCount,
            $logIds->count()
        ));

        if ($projectIds->isEmpty() && $logIds->isEmpty()) {
            $this->components->warn('Nothing to purge.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('This cannot be undone. Continue?', false)) {
            $this->components->warn('Aborted. Nothing was deleted.');

            return self::SUCCESS;
        }

        $deletedS3Files = 0;

        if ($projectIds->isNotEmpty()) {
            foreach ($projectIds->chunk(200) as $chunk) {
                $ids = $chunk->all();

                $deletedS3Files += $this->deleteS3Assets($ids);

                DB::table('re_custom_field_values')
                    ->where('reference_type', Project::class)
                    ->whereIn('reference_id', $ids)
                    ->delete();

                DB::table('re_project_features')->whereIn('project_id', $ids)->delete();
                DB::table('re_project_facilities')->whereIn('project_id', $ids)->delete();
                DB::table('re_project_categories')->whereIn('project_id', $ids)->delete();

                // Slugs MUST go too. A left-behind slug keeps its key, and
                // SlugHelper::getSlug() resolves the lowest-id match — so after a
                // re-import the public URL resolves to the purged row and 404s.
                Slug::query()
                    ->where('reference_type', Project::class)
                    ->whereIn('reference_id', $ids)
                    ->delete();

                if (Schema::hasTable('re_projects_translations')) {
                    DB::table('re_projects_translations')->whereIn('re_projects_id', $ids)->delete();
                }

                ProjectFloorPlan::query()->whereIn('project_id', $ids)->delete();
                ProjectDocument::query()->whereIn('project_id', $ids)->delete();

                // Detach properties rather than deleting them — a user may have
                // attached their own property to a Redbricks project.
                DB::table('re_properties')->whereIn('project_id', $ids)->update(['project_id' => 0]);

                Project::query()->whereIn('id', $ids)->delete();
            }
        }

        if ($logIds->isNotEmpty()) {
            DB::table('re_project_sync_log_items')->whereIn('sync_log_id', $logIds->all())->delete();
            ProjectSyncLog::query()->whereIn('id', $logIds->all())->delete();
        }

        // Sweep up project slugs whose project is already gone — including any
        // left behind by an earlier version of this command.
        $orphanSlugs = Slug::query()
            ->where('reference_type', Project::class)
            ->whereNotIn('reference_id', Project::query()->select('id'))
            ->delete();

        $this->components->success(sprintf(
            'Purged %d projects, %d floor plans, %d documents, %d sync log runs, %d S3 files and %d orphaned slug(s).',
            $projectIds->count(),
            $floorPlanCount,
            $documentCount,
            $logIds->count(),
            $deletedS3Files,
            $orphanSlugs
        ));

        return self::SUCCESS;
    }

    /**
     * Collect every S3 path the given projects own and delete it in one call per
     * chunk. Running the model's deleting hook per-row works, but 400+ hooks
     * cascade into 1000+ S3 round-trips; batching is dramatically faster and
     * lands at the same end state because the DB rows are deleted right after.
     *
     * @param  array<int, int>  $projectIds
     */
    protected function deleteS3Assets(array $projectIds): int
    {
        $paths = [];

        foreach (Project::query()->whereIn('id', $projectIds)->cursor() as $project) {
            foreach ((array) $project->images as $path) {
                if (is_string($path) && $path !== '') {
                    $paths[] = $path;
                }
            }
        }

        $floorPlanPaths = ProjectFloorPlan::query()
            ->whereIn('project_id', $projectIds)
            ->whereNotNull('local_image')
            ->pluck('local_image')
            ->filter()
            ->all();

        $paths = array_values(array_unique(array_merge($paths, $floorPlanPaths)));

        if (empty($paths)) {
            return 0;
        }

        foreach (array_chunk($paths, 100) as $batch) {
            Storage::delete($batch);
        }

        return count($paths);
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Do not ask for confirmation before purging.'
        );
    }
}
