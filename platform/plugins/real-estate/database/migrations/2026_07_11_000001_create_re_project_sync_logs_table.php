<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('re_project_sync_logs')) {
            Schema::create('re_project_sync_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('source', 50)->index();          // buildify, ...
                $table->string('status', 20)->default('running'); // running | success | failed
                $table->string('triggered_by', 20)->default('manual'); // manual | cron
                $table->integer('created')->default(0);
                $table->integer('updated')->default(0);
                $table->integer('failed')->default(0);
                $table->integer('total')->default(0);
                $table->text('message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('re_project_sync_logs');
    }
};
