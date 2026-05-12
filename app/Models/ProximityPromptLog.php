<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProximityPromptLog extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'saved_place_id',
        'latitude',
        'longitude',
        'distance_meters',
        'shown_at',
        'dismissed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'distance_meters' => 'integer',
            'shown_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function savedPlace(): BelongsTo
    {
        return $this->belongsTo(SavedPlace::class);
    }
}
