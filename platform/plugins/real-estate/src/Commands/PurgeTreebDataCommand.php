<?php

namespace Botble\RealEstate\Commands;

use Botble\Media\Facades\RvMedia;
use Botble\Media\Models\MediaFile;
use Botble\RealEstate\Models\ProjectSyncLog;
use Botble\RealEstate\Models\Property;
use Botble\RealEstate\Services\Treeb\TreebPropertySyncer;
use Botble\Slug\Models\Slug;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Honours the PROPTX/TRREB IDX Agreement's termination clause: on request, every
 * copy, extract and cache of IDX data must be permanently deleted immediately.
 *
 * Removes, for source = "treeb" only:
 *  - re_properties rows, with their custom fields, features and translations
 *  - imported listing photos from the media library
 *  - sync log rows and their per-listing items
 *
 * Manual and Excel-sourced properties are matched on source and are never touched.
 */
#[AsCommand('cms:treeb:purge', 'Permanently delete all TRREB/PROPTX IDX data (termination clause)')]
class PurgeTreebDataCommand extends Command
{
    public function handle(): int
    {
        $source = TreebPropertySyncer::SOURCE;

        $propertyIds = Property::query()->where('source', $source)->pluck('id');
        $logIds = ProjectSyncLog::query()->where('source', $source)->pluck('id');
        $mediaQuery = MediaFile::query()->where('name', 'like', 'treeb-%');

        $this->components->info(sprintf(
            'About to permanently delete: %d properties, %d media files, %d sync log runs.',
            $propertyIds->count(),
            (clone $mediaQuery)->count(),
            $logIds->count()
        ));

        if (! $this->option('force') && ! $this->confirm('This cannot be undone. Continue?', false)) {
            $this->components->warn('Aborted. Nothing was deleted.');

            return self::SUCCESS;
        }

        if ($propertyIds->isNotEmpty()) {
            foreach ($propertyIds->chunk(200) as $chunk) {
                $ids = $chunk->all();

                DB::table('re_custom_field_values')
                    ->where('reference_type', Property::class)
                    ->whereIn('reference_id', $ids)
                    ->delete();

                DB::table('re_property_features')->whereIn('property_id', $ids)->delete();
                DB::table('re_property_categories')->whereIn('property_id', $ids)->delete();

                // Slugs MUST go too. A left-behind slug keeps its key, and
                // SlugHelper::getSlug() resolves the lowest-id match — so after a
                // re-import the public URL resolves to the purged row and 404s.
                Slug::query()
                    ->where('reference_type', Property::class)
                    ->whereIn('reference_id', $ids)
                    ->delete();

                if (Schema::hasTable('re_properties_translations')) {
                    DB::table('re_properties_translations')->whereIn('re_properties_id', $ids)->delete();
                }

                Property::query()->whereIn('id', $ids)->delete();
            }
        }

        $deletedMedia = 0;
        foreach ($mediaQuery->cursor() as $file) {
            RvMedia::deleteFile($file);
            $file->forceDelete();
            $deletedMedia++;
        }

        if ($logIds->isNotEmpty()) {
            DB::table('re_project_sync_log_items')->whereIn('sync_log_id', $logIds->all())->delete();
            ProjectSyncLog::query()->whereIn('id', $logIds->all())->delete();
        }

        // Sweep up property slugs whose property is already gone — including any
        // left behind by an earlier version of this command.
        $orphanSlugs = Slug::query()
            ->where('reference_type', Property::class)
            ->whereNotIn('reference_id', Property::query()->select('id'))
            ->delete();

        $this->components->success(sprintf(
            'Purged %d properties, %d media files, %d sync log runs and %d orphaned slug(s). No non-IDX rows were touched.',
            $propertyIds->count(),
            $deletedMedia,
            $logIds->count(),
            $orphanSlugs
        ));

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Skip the confirmation prompt.');
    }
}
