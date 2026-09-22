<?php

namespace Botble\RealEstate\Commands;

use Botble\RealEstate\Models\Project;
use Botble\RealEstate\Models\ProjectDocument;
use Botble\RealEstate\Models\ProjectFloorPlan;
use Botble\RealEstate\Services\Redbricks\RedbricksProjectSyncer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * Rebuilds every Redbricks project from the payload already stored on it,
 * without making a single API request.
 *
 * Redbricks caps total requests per subscription period, so a mapping change
 * must not mean re-crawling the catalogue — and when the quota is spent, this is
 * the only way to apply one at all. raw_payload is a verbatim copy of what the
 * API returned, so the result is identical to a fresh sync for everything except
 * data that has genuinely changed upstream since it was captured.
 */
#[AsCommand('cms:redbricks:rebuild', 'Rebuild Redbricks projects from stored payloads (no API requests)')]
class RebuildRedbricksProjectsCommand extends Command
{
    public function handle(RedbricksProjectSyncer $syncer): int
    {
        @set_time_limit(0);

        $projects = Project::query()
            ->where('source', RedbricksProjectSyncer::SOURCE)
            ->whereNotNull('raw_payload')
            ->get();

        if ($projects->isEmpty()) {
            $this->components->error('No Redbricks projects with a stored payload to rebuild from.');

            return self::FAILURE;
        }

        $this->components->info(sprintf('Rebuilding %d project(s) from stored payloads.', $projects->count()));

        if ($this->option('fresh')) {
            $this->clearDerivedData($projects->pluck('id')->all());
        }

        $rebuilt = 0;
        $failed = 0;

        foreach ($projects as $project) {
            try {
                $syncer->rebuildFromPayload($project)
                    ? $rebuilt++
                    : $this->components->warn(sprintf('Skipped "%s" — payload has no project data.', $project->name));
            } catch (Throwable $e) {
                $failed++;
                $this->components->error(sprintf('%s: %s', $project->name, $e->getMessage()));
                report($e);
            }
        }

        $this->newLine();
        $this->components->twoColumnDetail('Projects rebuilt', (string) $rebuilt);
        $this->components->twoColumnDetail('Custom field values', (string) DB::table('re_custom_field_values')
            ->where('reference_type', Project::class)
            ->count());
        $this->components->twoColumnDetail('Floor plan rows', (string) ProjectFloorPlan::query()->count());
        $this->components->twoColumnDetail('Document rows', (string) ProjectDocument::query()->count());

        if ($failed > 0) {
            $this->components->warn($failed . ' project(s) failed — see the log.');
        }

        $this->components->success('Rebuild complete. No API requests were made.');

        return self::SUCCESS;
    }

    /**
     * Drop everything derived from the payload, so the rebuild is a clean slate
     * rather than an overlay. raw_payload itself is deliberately untouched — it
     * is the source being rebuilt from, and with the quota spent it cannot be
     * re-fetched.
     *
     * @param  array<int, int>  $projectIds
     */
    protected function clearDerivedData(array $projectIds): void
    {
        $customFields = DB::table('re_custom_field_values')
            ->where('reference_type', Project::class)
            ->whereIn('reference_id', $projectIds)
            ->delete();

        $floorPlans = ProjectFloorPlan::query()->whereIn('project_id', $projectIds)->delete();
        $documents = ProjectDocument::query()->whereIn('project_id', $projectIds)->delete();

        Project::query()->whereIn('id', $projectIds)->update(['floor_plans' => null]);

        $this->components->twoColumnDetail('Cleared custom fields', (string) $customFields);
        $this->components->twoColumnDetail('Cleared floor plan rows', (string) $floorPlans);
        $this->components->twoColumnDetail('Cleared document rows', (string) $documents);
    }

    protected function configure(): void
    {
        $this->addOption(
            'fresh',
            null,
            InputOption::VALUE_NONE,
            'Delete existing custom fields, floor plans and documents before rebuilding.'
        );
    }
}
