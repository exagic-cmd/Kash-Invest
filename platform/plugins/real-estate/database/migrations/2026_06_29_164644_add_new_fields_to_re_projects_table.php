<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('re_projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('re_projects', 'suites_starting_floor')) {
                $table->integer('suites_starting_floor')->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'number_of_suites_per_floor')) {
                $table->integer('number_of_suites_per_floor')->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'suite_size_from')) {
                $table->decimal('suite_size_from', 8, 2)->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'suite_size_to')) {
                $table->decimal('suite_size_to', 8, 2)->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'price_per_sqft_from')) {
                $table->decimal('price_per_sqft_from', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'parking_price')) {
                $table->decimal('parking_price', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'locker_price')) {
                $table->decimal('locker_price', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'total_min_deposit')) {
                $table->decimal('total_min_deposit', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'deposit_notes')) {
                $table->text('deposit_notes')->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'development_levies')) {
                $table->string('development_levies', 255)->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'assignment_policy')) {
                $table->string('assignment_policy', 255)->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'est_maint')) {
                $table->decimal('est_maint', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'locker_maint')) {
                $table->decimal('locker_maint', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'parking_maint')) {
                $table->decimal('parking_maint', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'est_property_tax')) {
                $table->decimal('est_property_tax', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'maintenance_notes')) {
                $table->text('maintenance_notes')->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'neighbour')) {
                $table->string('neighbour', 255)->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'intersection')) {
                $table->string('intersection', 255)->nullable();
            }
            if (! Schema::hasColumn('re_projects', 'architects')) {
                $table->string('architects', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('re_projects', function (Blueprint $table): void {
            $table->dropColumn([
                'suites_starting_floor',
                'number_of_suites_per_floor',
                'suite_size_from',
                'suite_size_to',
                'price_per_sqft_from',
                'parking_price',
                'locker_price',
                'total_min_deposit',
                'deposit_notes',
                'development_levies',
                'assignment_policy',
                'est_maint',
                'locker_maint',
                'parking_maint',
                'est_property_tax',
                'maintenance_notes',
                'neighbour',
                'intersection',
                'architects',
            ]);
        });
    }
};
