<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('re_projects', 'source')) {
            Schema::table('re_projects', function (Blueprint $table): void {
                $table->string('source', 50)->nullable()->index()->after('unique_id');
            });
        }

        // Migrate the legacy scheme where the origin was encoded as a unique_id
        // prefix (buildify-/excel-/manual-). Move it into the dedicated `source`
        // column and strip the prefix so unique_id becomes the clean raw id.
        DB::table('re_projects')->where('unique_id', 'like', 'buildify-%')->update([
            'source' => 'buildify',
            'unique_id' => DB::raw('SUBSTRING(unique_id, 10)'),
        ]);

        DB::table('re_projects')->where('unique_id', 'like', 'excel-%')->update([
            'source' => 'excel',
            'unique_id' => DB::raw('SUBSTRING(unique_id, 7)'),
        ]);

        DB::table('re_projects')->where('unique_id', 'like', 'manual-%')->update([
            'source' => 'manual',
            'unique_id' => DB::raw('SUBSTRING(unique_id, 8)'),
        ]);

        // Anything without a known prefix is treated as a manual admin creation.
        DB::table('re_projects')->whereNull('source')->update(['source' => 'manual']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('re_projects', 'source')) {
            Schema::table('re_projects', function (Blueprint $table): void {
                $table->dropColumn('source');
            });
        }
    }
};
