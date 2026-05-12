<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->string('provider', 50)->index();
            $table->string('event_type', 50)->index();
            $table->string('event_hash', 64)->unique();
            $table->string('external_event_id')->nullable()->index();
            $table->json('event_payload')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'provider', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};
