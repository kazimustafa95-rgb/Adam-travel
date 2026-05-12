<?php

use App\Enums\ImportStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 20)->index();
            $table->string('source_url')->nullable();
            $table->string('source_host')->nullable()->index();
            $table->longText('raw_text')->nullable();
            $table->longText('normalized_text')->nullable();
            $table->enum('status', ImportStatus::values())->default(ImportStatus::Pending->value)->index();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
