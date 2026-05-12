<?php

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('subject');
            $table->text('message');
            $table->enum('priority', SupportTicketPriority::values())->default(SupportTicketPriority::Medium->value)->index();
            $table->enum('status', SupportTicketStatus::values())->default(SupportTicketStatus::Open->value)->index();
            $table->text('admin_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_user_reply_at')->nullable();
            $table->timestamp('last_admin_reply_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
