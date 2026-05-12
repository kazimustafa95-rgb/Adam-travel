<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_name');
            $table->string('device_platform', 30);
            $table->string('device_identifier_hash', 191)->index();
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_identifier_hash'], 'user_devices_user_id_identifier_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
