<?php

namespace Database\Factories;

use App\Enums\SavedPlaceCategory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SavedPlace>
 */
class SavedPlaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'location_id' => Location::factory(),
            'import_id' => null,
            'title_override' => null,
            'notes' => fake()->sentence(),
            'category' => fake()->randomElement(SavedPlaceCategory::values()),
            'region_label' => fake()->randomElement(['Wishlist', 'Weekend', 'Europe 2027']),
            'is_favorite' => fake()->boolean(),
            'visibility' => 'private',
            'version' => 1,
        ];
    }
}
