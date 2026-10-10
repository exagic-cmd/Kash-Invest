<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen re_custom_field_values.value from VARCHAR(255) to TEXT.
 *
 * Redbricks pushes long plain-text fields (e.g. "Finishes", "Inclusions",
 * "Incentives") that easily exceed 255 characters. The syncer already
 * preserved the full string in raw_payload, but the custom-field write was
 * failing with SQLSTATE[22001] "String data, right truncated".
 *
 * TEXT holds up to 65,535 bytes — plenty for any property description.
 * The translations table mirrors the same column, so it is widened too.
 */
return new class () extends Migration
{
    public function up(): void
    {
        Schema::table('re_custom_field_values', function (Blueprint $table): void {
            $table->text('value')->nullable()->change();
        });

        if (Schema::hasTable('re_custom_field_values_translations')) {
            Schema::table('re_custom_field_values_translations', function (Blueprint $table): void {
                $table->text('value')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('re_custom_field_values', function (Blueprint $table): void {
            $table->string('value')->nullable()->change();
        });

        if (Schema::hasTable('re_custom_field_values_translations')) {
            Schema::table('re_custom_field_values_translations', function (Blueprint $table): void {
                $table->string('value')->nullable()->change();
            });
        }
    }
};
