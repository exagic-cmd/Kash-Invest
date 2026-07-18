<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('re_project_landing_pages')) {
            Schema::create('re_project_landing_pages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->unique();       // one landing page per project
                $table->string('template', 20)->default('light'); // light (dark discontinued)
                $table->boolean('is_published')->default(true);
                $table->longText('content')->nullable();          // JSON — every editable section
                $table->timestamps();
            });
        }

        // Dark template is discontinued. Move any project still on it to Light so it
        // keeps rendering a landing page instead of silently reverting to the normal page.
        if (Schema::hasColumn('re_projects', 'landing_template')) {
            DB::table('re_projects')->where('landing_template', 'dark')->update(['landing_template' => 'light']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('re_project_landing_pages');
    }
};
