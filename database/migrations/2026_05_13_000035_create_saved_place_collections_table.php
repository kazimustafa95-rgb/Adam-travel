<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_place_collections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color_hex', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::table('saved_places', function (Blueprint $table): void {
            $table->foreignId('saved_place_collection_id')
                ->nullable()
                ->after('region_label')
                ->constrained('saved_place_collections')
                ->nullOnDelete();
        });

        $existingLabels = DB::table('saved_places')
            ->select('user_id', 'region_label')
            ->whereNotNull('region_label')
            ->where('region_label', '!=', '')
            ->distinct()
            ->get();

        foreach ($existingLabels as $label) {
            $now = now();

            $collectionId = DB::table('saved_place_collections')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'user_id' => $label->user_id,
                'name' => $label->region_label,
                'description' => null,
                'color_hex' => null,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('saved_places')
                ->where('user_id', $label->user_id)
                ->where('region_label', $label->region_label)
                ->update([
                    'saved_place_collection_id' => $collectionId,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('saved_places', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('saved_place_collection_id');
        });

        Schema::dropIfExists('saved_place_collections');
    }
};
