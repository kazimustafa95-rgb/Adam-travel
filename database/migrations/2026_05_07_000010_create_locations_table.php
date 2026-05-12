<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->index();
            $table->string('slug')->nullable()->index();
            $table->string('category', 50)->nullable()->index();
            $table->string('address_line')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('region')->nullable();
            $table->string('country_code', 10)->nullable()->index();
            $table->string('postal_code', 30)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('provider_place_id')->nullable()->index();
            $table->string('provider_source', 50)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->boolean('is_moderated_hidden')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['latitude', 'longitude'], 'locations_latitude_longitude_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
