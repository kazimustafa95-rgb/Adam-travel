<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('target_type', 100)->nullable()->index();
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->string('target_label')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
