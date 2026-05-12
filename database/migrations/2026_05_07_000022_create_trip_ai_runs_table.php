<?php

use App\Enums\TripAiRunStatus;
use App\Enums\TripAiRunType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_ai_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', TripAiRunType::values())->index();
            $table->enum('status', TripAiRunStatus::values())->default(TripAiRunStatus::Pending->value)->index();
            $table->string('provider', 50);
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('trip_version')->default(1);
            $table->string('input_hash', 64)->index();
            $table->json('result_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_ai_runs');
    }
};
