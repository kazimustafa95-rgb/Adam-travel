<?php

use App\Enums\TripSuggestionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_ai_run_id')->nullable()->constrained('trip_ai_runs')->nullOnDelete();
            $table->foreignId('saved_place_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('category', 50)->nullable()->index();
            $table->text('summary')->nullable();
            $table->decimal('score', 5, 2)->nullable()->index();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->enum('status', TripSuggestionStatus::values())->default(TripSuggestionStatus::Suggested->value)->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_suggestions');
    }
};
