<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proximity_prompt_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_place_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('distance_meters')->nullable();
            $table->timestamp('shown_at')->index();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'shown_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proximity_prompt_logs');
    }
};
