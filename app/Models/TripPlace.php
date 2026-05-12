<?php

namespace App\Models;

use App\Enums\TripPlaceSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TripPlace extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'saved_place_id',
        'added_by_user_id',
        'source',
        'trip_category',
        'notes',
        'is_removed',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => TripPlaceSource::class,
            'is_removed' => 'boolean',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $tripPlace): void {
            if (blank($tripPlace->uuid)) {
                $tripPlace->uuid = (string) Str::uuid();
            }
        });
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function savedPlace(): BelongsTo
    {
        return $this->belongsTo(SavedPlace::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function hearts(): HasMany
    {
        return $this->hasMany(TripPlaceHeart::class);
    }

    public function itineraryItem(): HasOne
    {
        return $this->hasOne(ItineraryItem::class)->whereNull('deleted_at');
    }
}
