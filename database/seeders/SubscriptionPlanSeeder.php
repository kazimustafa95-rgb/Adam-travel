<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionPlan::query()->upsert([
            [
                'code' => 'free',
                'name' => 'Free',
                'provider_product_id' => null,
                'is_active' => true,
                'monthly_price' => 0,
                'yearly_price' => 0,
                'features_json' => json_encode([
                    'saved_places_limit' => 50,
                    'offline_packages_limit' => 1,
                    'enhanced_ai' => false,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'premium',
                'name' => 'Premium',
                'provider_product_id' => 'adam_travel_premium',
                'is_active' => true,
                'monthly_price' => 4.99,
                'yearly_price' => 49.99,
                'features_json' => json_encode([
                    'saved_places_limit' => 1000,
                    'offline_packages_limit' => 20,
                    'enhanced_ai' => true,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['code'], ['name', 'provider_product_id', 'is_active', 'monthly_price', 'yearly_price', 'features_json', 'updated_at']);
    }
}
