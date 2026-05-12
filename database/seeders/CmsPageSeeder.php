<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;

class CmsPageSeeder extends Seeder
{
    public function run(): void
    {
        CmsPage::query()->upsert([
            [
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'content' => 'Privacy policy content for Adam Travel.',
                'is_published' => true,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'terms-of-service',
                'title' => 'Terms of Service',
                'content' => 'Terms of service content for Adam Travel.',
                'is_published' => true,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'help-center',
                'title' => 'Help Center',
                'content' => 'Help content for onboarding, imports, trips, and offline usage.',
                'is_published' => true,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['slug'], ['title', 'content', 'is_published', 'published_at', 'updated_at']);
    }
}
