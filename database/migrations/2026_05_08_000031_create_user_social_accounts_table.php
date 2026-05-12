<?php

use App\Enums\SocialAuthProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', SocialAuthProvider::values());
            $table->string('provider_user_id', 191);
            $table->string('provider_email')->nullable();
            $table->timestamp('provider_email_verified_at')->nullable();
            $table->string('avatar_url', 2048)->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_social_accounts');
    }
};
