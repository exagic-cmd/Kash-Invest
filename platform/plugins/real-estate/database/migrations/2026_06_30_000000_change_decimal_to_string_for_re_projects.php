<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('re_projects', function (Blueprint $table): void {
            $table->string('total_min_deposit', 255)->nullable()->change();
            $table->string('est_maint', 255)->nullable()->change();
            $table->string('locker_maint', 255)->nullable()->change();
            $table->string('parking_maint', 255)->nullable()->change();
            $table->string('est_property_tax', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('re_projects', function (Blueprint $table): void {
            $table->decimal('total_min_deposit', 10, 2)->nullable()->change();
            $table->decimal('est_maint', 10, 2)->nullable()->change();
            $table->decimal('locker_maint', 10, 2)->nullable()->change();
            $table->decimal('parking_maint', 10, 2)->nullable()->change();
            $table->decimal('est_property_tax', 10, 2)->nullable()->change();
        });
    }
};
