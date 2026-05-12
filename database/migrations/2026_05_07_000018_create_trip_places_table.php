<?php

use App\Enums\TripPlaceSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_places', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_place_id')->constrained()->restrictOnDelete();
            $table->foreignId('added_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('source', TripPlaceSource::values())->default(TripPlaceSource::SavedPlace->value)->index();
            $table->string('trip_category', 50)->nullable()->index();
            $table->text('notes')->nullable();
            $table->boolean('is_removed')->default(false)->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['trip_id', 'deleted_at']);
            $table->unique(['trip_id', 'saved_place_id'], 'trip_places_trip_saved_place_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_places');
    }
};
