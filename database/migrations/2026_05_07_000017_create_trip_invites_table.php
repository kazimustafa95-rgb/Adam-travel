<?php

use App\Enums\TripInviteStatus;
use App\Enums\TripMemberRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_invites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email')->nullable()->index();
            $table->string('token', 64)->unique();
            $table->enum('role', [
                TripMemberRole::Editor->value,
                TripMemberRole::Viewer->value,
            ])->index();
            $table->enum('status', TripInviteStatus::values())->default(TripInviteStatus::Pending->value)->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_invites');
    }
};
