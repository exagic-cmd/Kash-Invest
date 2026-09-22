<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project documents — brochures, price lists, features & finishes.
 *
 * file_url is Redbricks' own CDN link and can rotate or expire; local_path is
 * our downloaded copy in the media library. Both are kept: the local copy is
 * what the site serves, the remote one is how we re-fetch if it is replaced.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('re_project_documents')) {
            return;
        }

        Schema::create('re_project_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('re_projects')->cascadeOnDelete();

            $table->string('external_id', 60)->nullable();
            $table->string('name', 255)->nullable();
            $table->string('document_type', 100)->nullable();

            $table->text('file_url')->nullable();
            $table->string('local_path', 255)->nullable();

            // Redbricks distinguishes the current document set from superseded
            // revisions; both are pulled so a price list history survives.
            $table->boolean('is_historical')->default(false);

            $table->timestamps();

            $table->unique(['project_id', 'external_id']);
            $table->index('document_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('re_project_documents');
    }
};
