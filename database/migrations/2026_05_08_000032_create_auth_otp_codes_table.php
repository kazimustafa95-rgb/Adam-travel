<?php

use App\Enums\AuthOtpPurpose;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_otp_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('purpose', AuthOtpPurpose::values());
            $table->uuid('challenge_id')->unique();
            $table->string('email')->index();
            $table->string('code_hash');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['purpose', 'email']);
            $table->index(['purpose', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_otp_codes');
    }
};
