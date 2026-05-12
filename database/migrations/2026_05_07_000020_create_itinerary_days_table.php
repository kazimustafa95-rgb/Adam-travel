<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itinerary_days', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->date('trip_date')->nullable();
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['trip_id', 'day_number'], 'itinerary_days_trip_day_number_unique');
            $table->index(['trip_id', 'trip_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itinerary_days');
    }
};
