<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('re_projects', function (Blueprint $table): void {
            // Import data contains suite sizes above decimal(8,2)'s 999,999.99 ceiling
            $table->decimal('suite_size_from', 12, 2)->nullable()->change();
            $table->decimal('suite_size_to', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('re_projects', function (Blueprint $table): void {
            $table->decimal('suite_size_from', 8, 2)->nullable()->change();
            $table->decimal('suite_size_to', 8, 2)->nullable()->change();
        });
    }
};
