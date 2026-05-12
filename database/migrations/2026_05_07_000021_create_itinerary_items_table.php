<?php

use App\Enums\ItineraryItemSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itinerary_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('itinerary_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_place_id')->constrained()->restrictOnDelete();
            $table->foreignId('scheduled_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('source', ItineraryItemSource::values())->default(ItineraryItemSource::Manual->value)->index();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['itinerary_day_id', 'sort_order']);
            $table->index(['trip_place_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itinerary_items');
    }
};
