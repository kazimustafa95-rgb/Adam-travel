<?php

namespace Database\Factories;

use App\Enums\ImportSourceType;
use App\Enums\ImportStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Import>
 */
class ImportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source_type' => ImportSourceType::Text,
            'source_url' => null,
            'source_host' => null,
            'raw_text' => 'Place: Test Place. City: Tokyo. Country: JP. Coordinates: 35.0, 139.0.',
            'normalized_text' => null,
            'status' => ImportStatus::Pending,
            'error_code' => null,
            'error_message' => null,
            'confidence_score' => null,
            'processed_at' => null,
        ];
    }
}
