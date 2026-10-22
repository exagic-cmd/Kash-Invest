<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Change re_properties.square from FLOAT to VARCHAR(100) to allow storing
 * exact banded ranges (e.g. "1100-1500", "1500-2000", "< 700") as provided
 * by TRREB without lossy midpoint approximations.
 */
return new class () extends Migration
{
    public function up(): void
    {
        Schema::table('re_properties', function (Blueprint $table): void {
            $table->string('square', 100)->nullable()->change();
        });

        // Backfill Living Area Range verbatim into square for all TRREB properties
        try {
            DB::statement("
                UPDATE re_properties p
                JOIN re_custom_field_values cf 
                  ON cf.reference_id = p.id 
                 AND cf.reference_type = 'Botble\\\\RealEstate\\\\Models\\\\Property' 
                 AND cf.name = 'Living Area Range'
                SET p.square = cf.value
                WHERE cf.value IS NOT NULL 
                  AND cf.value != '' 
                  AND cf.value != '0'
            ");

            // Backfill Building Area Total where Living Area Range is not set
            DB::statement("
                UPDATE re_properties p
                JOIN re_custom_field_values cf 
                  ON cf.reference_id = p.id 
                 AND cf.reference_type = 'Botble\\\\RealEstate\\\\Models\\\\Property' 
                 AND cf.name = 'Building Area Total'
                SET p.square = cf.value
                WHERE cf.value IS NOT NULL 
                  AND cf.value != '' 
                  AND cf.value != '0'
                  AND (p.square IS NULL OR p.square = '' OR p.square = '0')
            ");
        } catch (\Throwable $e) {
            // Non-fatal if custom fields table is not yet populated
        }
    }

    public function down(): void
    {
        Schema::table('re_properties', function (Blueprint $table): void {
            $table->float('square')->nullable()->change();
        });
    }
};
