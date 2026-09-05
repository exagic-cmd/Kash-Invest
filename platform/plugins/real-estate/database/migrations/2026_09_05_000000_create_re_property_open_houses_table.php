<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('re_property_open_houses')) {
            Schema::create('re_property_open_houses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('property_id')->constrained('re_properties')->cascadeOnDelete();
                $table->string('listing_key', 50)->index();
                $table->string('open_house_key', 100)->nullable()->unique();
                $table->date('open_house_date')->index();
                $table->timestamp('start_time')->nullable();
                $table->timestamp('end_time')->nullable();
                $table->string('time_range', 50)->nullable();
                $table->string('format', 50)->nullable();
                $table->string('type', 50)->nullable();
                $table->string('status', 50)->default('Active')->index();
                $table->text('url')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('re_property_open_houses');
    }
};
