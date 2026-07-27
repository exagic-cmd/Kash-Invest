<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow a project to have several landing pages (one per ad campaign) instead of
 * exactly one.
 *
 * Compatibility: every pre-existing row is backfilled as slug 'default' and
 * is_primary = true, so the bare /landing/{project} URL keeps serving the page it
 * already served. Live ad links must not break or silently change content.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('re_project_landing_pages')) {
            return;
        }

        Schema::table('re_project_landing_pages', function (Blueprint $table): void {
            if (! Schema::hasColumn('re_project_landing_pages', 'name')) {
                $table->string('name')->nullable()->after('project_id');
            }

            if (! Schema::hasColumn('re_project_landing_pages', 'slug')) {
                $table->string('slug', 191)->nullable()->after('name');
            }

            if (! Schema::hasColumn('re_project_landing_pages', 'is_primary')) {
                // Which page the bare /landing/{project} URL serves.
                $table->boolean('is_primary')->default(false)->after('is_published');
            }
        });

        // One landing page per project was enforced by a unique index on project_id.
        // Replace it with a plain index so several rows can share a project.
        $this->dropProjectIdUniqueIndex();

        // Backfill BEFORE adding the composite unique, or rows with a null slug collide.
        DB::table('re_project_landing_pages')
            ->whereNull('slug')
            ->orWhere('slug', '')
            ->update([
                'name' => DB::raw("COALESCE(NULLIF(name, ''), 'Default')"),
                'slug' => 'default',
                'is_primary' => true,
            ]);

        // A project should never end up with zero primary pages.
        foreach (DB::table('re_project_landing_pages')->select('project_id')->distinct()->pluck('project_id') as $projectId) {
            $hasPrimary = DB::table('re_project_landing_pages')
                ->where('project_id', $projectId)
                ->where('is_primary', true)
                ->exists();

            if (! $hasPrimary) {
                $firstId = DB::table('re_project_landing_pages')
                    ->where('project_id', $projectId)
                    ->orderBy('id')
                    ->value('id');

                if ($firstId) {
                    DB::table('re_project_landing_pages')->where('id', $firstId)->update(['is_primary' => true]);
                }
            }
        }

        Schema::table('re_project_landing_pages', function (Blueprint $table): void {
            // Slugs only need to be unique within a project — the URL carries the project id.
            $table->unique(['project_id', 'slug'], 're_landing_pages_project_slug_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('re_project_landing_pages')) {
            return;
        }

        Schema::table('re_project_landing_pages', function (Blueprint $table): void {
            $table->dropUnique('re_landing_pages_project_slug_unique');
        });

        // Collapse back to one page per project before restoring the unique index.
        $keepIds = DB::table('re_project_landing_pages')
            ->select(DB::raw('MIN(id) as id'))
            ->groupBy('project_id')
            ->pluck('id');

        DB::table('re_project_landing_pages')->whereNotIn('id', $keepIds)->delete();

        Schema::table('re_project_landing_pages', function (Blueprint $table): void {
            $table->dropColumn(['name', 'slug', 'is_primary']);
            $table->unique('project_id');
        });
    }

    protected function dropProjectIdUniqueIndex(): void
    {
        $indexes = collect(DB::select('SHOW INDEX FROM re_project_landing_pages'))
            ->filter(fn ($index) => $index->Column_name === 'project_id' && (int) $index->Non_unique === 0)
            ->pluck('Key_name')
            ->unique();

        foreach ($indexes as $indexName) {
            DB::statement("ALTER TABLE re_project_landing_pages DROP INDEX `{$indexName}`");
        }

        $hasPlainIndex = collect(DB::select('SHOW INDEX FROM re_project_landing_pages'))
            ->contains(fn ($index) => $index->Column_name === 'project_id');

        if (! $hasPlainIndex) {
            Schema::table('re_project_landing_pages', function (Blueprint $table): void {
                $table->index('project_id');
            });
        }
    }
};
