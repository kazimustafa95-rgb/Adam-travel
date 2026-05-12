<?php

namespace Database\Factories;

use App\Enums\TripStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trip>
 */
class TripFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'slug' => Str::slug(fake()->unique()->sentence(3)).'-'.Str::lower(Str::random(6)),
            'description' => fake()->paragraph(),
            'start_location_name' => fake()->city(),
            'start_latitude' => fake()->latitude(),
            'start_longitude' => fake()->longitude(),
            'end_location_name' => fake()->city(),
            'end_latitude' => fake()->latitude(),
            'end_longitude' => fake()->longitude(),
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(16)->toDateString(),
            'status' => TripStatus::Draft,
            'cover_image_url' => null,
            'version' => 1,
        ];
    }
}
