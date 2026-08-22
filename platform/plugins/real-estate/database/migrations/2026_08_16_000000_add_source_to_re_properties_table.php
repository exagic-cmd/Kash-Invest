<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives re_properties the same ownership tagging re_projects already has, so an
 * API syncer can find its own rows and never touch the ones an admin typed in
 * by hand. Mirrors 2026_07_11_000000_add_source_to_re_projects_table.php.
 *
 * Without this there is no safe way to run the TRREB/PROPTX (treeb) sync against
 * the properties table — and the IDX agreement's deletion clause needs a way to
 * identify every feed-sourced row on request.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('re_properties', 'source')) {
            Schema::table('re_properties', function (Blueprint $table): void {
                $table->string('source', 50)->nullable()->index()->after('unique_id');
            });
        }

        // Everything that exists today predates any API sync, so it is manual.
        DB::table('re_properties')->whereNull('source')->update(['source' => 'manual']);

        // The syncer's hot path: "find the property this feed record owns".
        if (! $this->hasIndex('re_properties', 're_properties_source_unique_id_index')) {
            Schema::table('re_properties', function (Blueprint $table): void {
                $table->index(['source', 'unique_id'], 're_properties_source_unique_id_index');
            });
        }

        // Sync-log items can now point at a property instead of a project.
        if (! Schema::hasColumn('re_project_sync_log_items', 'property_id')) {
            Schema::table('re_project_sync_log_items', function (Blueprint $table): void {
                $table->unsignedBigInteger('property_id')->nullable()->index()->after('project_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('re_project_sync_log_items', 'property_id')) {
            Schema::table('re_project_sync_log_items', function (Blueprint $table): void {
                $table->dropColumn('property_id');
            });
        }

        if ($this->hasIndex('re_properties', 're_properties_source_unique_id_index')) {
            Schema::table('re_properties', function (Blueprint $table): void {
                $table->dropIndex('re_properties_source_unique_id_index');
            });
        }

        if (Schema::hasColumn('re_properties', 'source')) {
            Schema::table('re_properties', function (Blueprint $table): void {
                $table->dropColumn('source');
            });
        }
    }

    protected function hasIndex(string $table, string $index): bool
    {
        return count(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index])) > 0;
    }
};
