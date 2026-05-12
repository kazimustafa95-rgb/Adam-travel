<?php

use App\Enums\OfflinePackageScope;
use App\Enums\OfflinePackageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('package_scope', OfflinePackageScope::values())->index();
            $table->string('scope_reference')->nullable();
            $table->unsignedInteger('manifest_version')->default(1);
            $table->enum('status', OfflinePackageStatus::values())->default(OfflinePackageStatus::Queued->value)->index();
            $table->json('manifest_payload')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'package_scope', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_packages');
    }
};
