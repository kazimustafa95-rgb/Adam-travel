<?php

use App\Enums\SavedPlaceCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_places', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('import_id')->nullable()->constrained('imports')->nullOnDelete();
            $table->string('title_override')->nullable();
            $table->text('notes')->nullable();
            $table->enum('category', SavedPlaceCategory::values())->default(SavedPlaceCategory::Other->value)->index();
            $table->string('region_label')->nullable()->index();
            $table->boolean('is_favorite')->default(false)->index();
            $table->string('visibility', 20)->default('private')->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at'], 'saved_places_user_id_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_places');
    }
};
