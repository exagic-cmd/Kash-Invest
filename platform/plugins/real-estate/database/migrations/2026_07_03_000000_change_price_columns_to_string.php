<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('re_projects', function (Blueprint $table): void {
            $table->string('price_from', 255)->nullable()->change();
            $table->string('price_to', 255)->nullable()->change();
        });

        Schema::table('re_properties', function (Blueprint $table): void {
            $table->string('price', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('re_projects', function (Blueprint $table): void {
            $table->decimal('price_from', 15, 2)->nullable()->change();
            $table->decimal('price_to', 15, 2)->nullable()->change();
        });

        Schema::table('re_properties', function (Blueprint $table): void {
            $table->decimal('price', 15, 2)->nullable()->change();
        });
    }
};
