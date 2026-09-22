<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Floor plans as rows rather than a JSON blob.
 *
 * re_projects.floor_plans holds a repeater copy for the admin form and the
 * theme, but it cannot be queried — "show me every 2-bed under 700 sq ft across
 * all projects" is the sort of question this feed exists to answer, and that
 * needs columns. price_history stays JSON because it is an ordered list whose
 * shape belongs to the plan, not to the query.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('re_project_floor_plans')) {
            return;
        }

        Schema::create('re_project_floor_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('re_projects')->cascadeOnDelete();

            // Redbricks' own ids, so a re-sync updates rather than duplicates.
            $table->string('external_id', 60)->nullable();
            $table->string('floorplan_uuid', 60)->nullable();

            $table->string('name', 191)->nullable();
            $table->string('bedrooms', 30)->nullable();
            $table->string('bathrooms', 30)->nullable();
            $table->decimal('size', 12, 2)->nullable();
            $table->string('exposure', 60)->nullable();
            $table->string('availability', 60)->nullable();

            $table->decimal('current_price', 14, 2)->nullable();
            $table->decimal('current_psf', 12, 2)->nullable();
            $table->decimal('launch_price', 14, 2)->nullable();

            $table->unsignedSmallInteger('floor_min')->nullable();
            $table->unsignedSmallInteger('floor_max')->nullable();

            $table->text('image_full')->nullable();
            $table->text('image_medium')->nullable();
            $table->text('image_thumbnail')->nullable();
            $table->string('local_image', 255)->nullable();

            $table->longText('price_history')->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'external_id']);
            $table->index('bedrooms');
            $table->index('availability');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('re_project_floor_plans');
    }
};
