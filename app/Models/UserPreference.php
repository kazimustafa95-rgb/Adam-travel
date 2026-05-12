<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'distance_unit',
        'map_style',
        'default_radius_meters',
        'notifications_enabled',
        'offline_auto_sync',
        'theme',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_radius_meters' => 'integer',
            'notifications_enabled' => 'boolean',
            'offline_auto_sync' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
