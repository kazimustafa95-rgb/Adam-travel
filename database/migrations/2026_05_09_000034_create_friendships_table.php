<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('friend_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('friend_request_id')->nullable()->constrained('friend_requests')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'friend_user_id']);
            $table->index(['user_id', 'connected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
