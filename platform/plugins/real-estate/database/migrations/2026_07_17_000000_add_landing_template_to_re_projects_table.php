<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Which landing template (if any) this project is assigned.
        // null = use the standard project detail page.
        if (! Schema::hasColumn('re_projects', 'landing_template')) {
            Schema::table('re_projects', function (Blueprint $table): void {
                $table->string('landing_template', 20)->nullable()->after('source');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('re_projects', 'landing_template')) {
            Schema::table('re_projects', function (Blueprint $table): void {
                $table->dropColumn('landing_template');
            });
        }
    }
};
