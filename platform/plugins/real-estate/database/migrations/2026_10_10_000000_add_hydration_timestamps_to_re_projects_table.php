<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staged ingest for Redbricks: a listing-only pass creates rows in minutes,
 * then two queued passes hydrate sub-resources and media.
 *
 *   details_synced_at  → the floor plan + document API calls have been made,
 *                        and the admin repeater + child-table rows are current.
 *   media_synced_at    → project images, floor plan images and PDFs have been
 *                        pulled into the media library.
 *
 * Both nullable so the scheduler can enqueue work by "where ... is null".
 * Indexed because the enqueue sweep scans them against 8k+ rows every run.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('re_projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('re_projects', 'details_synced_at')) {
                $table->timestamp('details_synced_at')->nullable()->index();
            }

            if (! Schema::hasColumn('re_projects', 'media_synced_at')) {
                $table->timestamp('media_synced_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('re_projects', function (Blueprint $table): void {
            foreach (['details_synced_at', 'media_synced_at'] as $column) {
                if (Schema::hasColumn('re_projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
