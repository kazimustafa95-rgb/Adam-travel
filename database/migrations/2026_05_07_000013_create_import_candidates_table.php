<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('candidate_rank')->default(1);
            $table->string('place_name');
            $table->string('category', 50)->nullable()->index();
            $table->string('city')->nullable()->index();
            $table->string('region')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('provider_place_id')->nullable()->index();
            $table->text('summary')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_candidates');
    }
};
