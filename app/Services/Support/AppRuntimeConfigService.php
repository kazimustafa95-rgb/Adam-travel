<?php

namespace App\Services\Support;

use App\Models\AppSetting;

class AppRuntimeConfigService
{
    public function integer(string $key, int $default): int
    {
        $value = AppSetting::query()->where('key', $key)->first()?->value;

        if (! is_array($value)) {
            return $default;
        }

        return (int) ($value['value'] ?? $default);
    }
}
