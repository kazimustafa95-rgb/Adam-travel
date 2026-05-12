<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('provider', 50)->index();
            $table->string('provider_product_id')->nullable()->index();
            $table->string('provider_customer_id')->nullable()->index();
            $table->string('provider_subscription_id')->nullable()->index();
            $table->string('provider_original_transaction_id')->nullable()->index();
            $table->string('status', 30)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->boolean('auto_renews')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->unique(['provider', 'provider_original_transaction_id'], 'subscriptions_provider_original_transaction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
