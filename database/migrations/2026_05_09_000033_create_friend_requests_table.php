<?php

use App\Enums\FriendRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friend_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', FriendRequestStatus::values())->default(FriendRequestStatus::Pending->value)->index();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['sender_user_id', 'recipient_user_id']);
            $table->index(['recipient_user_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friend_requests');
    }
};
