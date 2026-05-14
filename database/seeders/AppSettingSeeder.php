<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        AppSetting::query()->upsert([
            [
                'group_name' => 'proximity',
                'key' => 'proximity.default_radius_meters',
                'value' => json_encode(['value' => 3000], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group_name' => 'proximity',
                'key' => 'proximity.cooldown_minutes',
                'value' => json_encode(['value' => 180], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group_name' => 'offline',
                'key' => 'offline.package_ttl_days',
                'value' => json_encode(['value' => 30], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group_name' => 'support',
                'key' => 'support.contact',
                'value' => json_encode([
                    'email' => 'support@adamtravel.app',
                    'response_time' => 'Usually replies within 24 hours.',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group_name' => 'support',
                'key' => 'support.faqs',
                'value' => json_encode([
                    'items' => [
                        [
                            'question' => 'How do I import a location?',
                            'answer' => 'Tap the plus button, paste a Google Maps or Apple Maps link, and the app will auto-extract location details for you.',
                        ],
                        [
                            'question' => 'Can I use the app offline?',
                            'answer' => 'Yes. Download a trip package first and your itinerary, saved places, and offline guidance will stay available without signal.',
                        ],
                        [
                            'question' => 'How do I create a trip?',
                            'answer' => 'Open Trips, create a new trip, then add saved places into the shared pool before building your itinerary.',
                        ],
                        [
                            'question' => 'What does AI suggestions do?',
                            'answer' => 'AI suggestions help organize routes, highlight gaps in your plan, and recommend stronger trip combinations from your saved places.',
                        ],
                        [
                            'question' => 'How do I delete my account?',
                            'answer' => 'Open Settings, choose Delete Account, and confirm with your current password to permanently remove your profile.',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group_name' => 'home',
                'key' => 'home.trending_searches',
                'value' => json_encode([
                    'items' => [
                        [
                            'title' => 'Hidden Gems',
                            'subtitle' => 'Quiet spots locals love',
                            'theme' => 'teal',
                        ],
                        [
                            'title' => 'Sunset Spots',
                            'subtitle' => 'Golden hour viewpoints',
                            'theme' => 'orange',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['key'], ['group_name', 'value', 'updated_at']);
    }
}
