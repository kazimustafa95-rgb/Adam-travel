<?php

use App\Enums\TripStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('start_location_name');
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();
            $table->string('end_location_name');
            $table->decimal('end_latitude', 10, 7)->nullable();
            $table->decimal('end_longitude', 10, 7)->nullable();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->enum('status', TripStatus::values())->default(TripStatus::Draft->value)->index();
            $table->string('cover_image_url')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_user_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
