<?php

namespace App\Services\Users;

use App\Models\User;
use App\Models\UserPreference;

class UserPreferenceService
{
    public function ensureDefaults(User $user): UserPreference
    {
        return UserPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'distance_unit' => 'km',
                'map_style' => null,
                'default_radius_meters' => 3000,
                'notifications_enabled' => true,
                'offline_auto_sync' => true,
                'theme' => 'system',
                'version' => 1,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(User $user, array $payload): UserPreference
    {
        $preference = $this->ensureDefaults($user);
        $preference->fill($payload);
        $preference->version = $preference->version + 1;
        $preference->save();

        return $preference->fresh();
    }
}
