<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('distance_unit', 10)->default('km');
            $table->string('map_style')->nullable();
            $table->unsignedInteger('default_radius_meters')->default(3000);
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('offline_auto_sync')->default(true);
            $table->string('theme', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
