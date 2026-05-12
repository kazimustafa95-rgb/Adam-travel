<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ItineraryDay extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'day_number',
        'trip_date',
        'title',
        'notes',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_number' => 'integer',
            'trip_date' => 'date',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $itineraryDay): void {
            if (blank($itineraryDay->uuid)) {
                $itineraryDay->uuid = (string) Str::uuid();
            }
        });
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItineraryItem::class)->whereNull('deleted_at')->orderBy('sort_order');
    }
}
