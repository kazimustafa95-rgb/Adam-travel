<?php

namespace App\Models;

use App\Enums\ItineraryItemSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ItineraryItem extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'itinerary_day_id',
        'trip_place_id',
        'scheduled_by_user_id',
        'source',
        'starts_at',
        'ends_at',
        'sort_order',
        'notes',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => ItineraryItemSource::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sort_order' => 'integer',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $itineraryItem): void {
            if (blank($itineraryItem->uuid)) {
                $itineraryItem->uuid = (string) Str::uuid();
            }
        });
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(ItineraryDay::class, 'itinerary_day_id');
    }

    public function tripPlace(): BelongsTo
    {
        return $this->belongsTo(TripPlace::class);
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by_user_id');
    }
}
