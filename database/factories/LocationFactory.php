<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->city().' Highlight',
            'slug' => fake()->slug(),
            'category' => fake()->randomElement(['hotel', 'restaurant', 'activity']),
            'address_line' => fake()->streetAddress(),
            'city' => fake()->city(),
            'region' => fake()->state(),
            'country_code' => fake()->countryCode(),
            'postal_code' => fake()->postcode(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'provider_place_id' => null,
            'provider_source' => null,
            'metadata' => [],
            'is_moderated_hidden' => false,
        ];
    }
}
