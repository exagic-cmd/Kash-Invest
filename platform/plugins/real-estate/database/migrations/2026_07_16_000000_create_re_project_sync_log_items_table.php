<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // One row per project a sync actually created / updated / failed on, so
        // the admin "API Sync" page can show a field-level breakdown per run.
        // Unchanged projects are only counted (on the parent log), never stored.
        if (! Schema::hasTable('re_project_sync_log_items')) {
            Schema::create('re_project_sync_log_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('sync_log_id')->index();
                $table->unsignedBigInteger('project_id')->nullable()->index();
                $table->string('external_id', 191)->nullable();
                $table->string('name')->nullable();
                $table->string('action', 20)->index(); // created | updated | failed
                // NB: not named "changes" — that collides with Eloquent's internal
                // protected $changes property and would shadow the cast attribute.
                $table->json('change_set')->nullable(); // {fields: [{field, from, to}]} | {error}
                $table->timestamps();
            });
        }

        // "Updated" now means genuinely-changed; the rest are counted here so the
        // history numbers stay truthful (e.g. "3 updated, 157 unchanged").
        if (Schema::hasTable('re_project_sync_logs') && ! Schema::hasColumn('re_project_sync_logs', 'unchanged')) {
            Schema::table('re_project_sync_logs', function (Blueprint $table): void {
                $table->integer('unchanged')->default(0)->after('updated');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('re_project_sync_log_items');

        if (Schema::hasTable('re_project_sync_logs') && Schema::hasColumn('re_project_sync_logs', 'unchanged')) {
            Schema::table('re_project_sync_logs', function (Blueprint $table): void {
                $table->dropColumn('unchanged');
            });
        }
    }
};
