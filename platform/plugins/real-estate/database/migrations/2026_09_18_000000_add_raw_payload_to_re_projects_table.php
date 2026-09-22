<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the complete API response for a synced project.
 *
 * The Redbricks project object carries 92 fields; re_projects has a home for
 * roughly a quarter of them. Rather than discard the rest — unit mix, GFA
 * breakdown, walk/transit scores, parking detail, bedroom economics, incentives,
 * document lists — the whole payload is retained here, so a later feature can
 * surface a field without needing a re-sync of the entire catalogue.
 *
 * Deliberately source-agnostic: Buildify and Trreb can use the same column.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('re_projects', 'raw_payload')) {
            return;
        }

        Schema::table('re_projects', function (Blueprint $table): void {
            $table->longText('raw_payload')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('re_projects', 'raw_payload')) {
            return;
        }

        Schema::table('re_projects', function (Blueprint $table): void {
            $table->dropColumn('raw_payload');
        });
    }
};
