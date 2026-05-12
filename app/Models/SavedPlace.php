<?php

namespace App\Models;

use App\Enums\SavedPlaceCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SavedPlace extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'location_id',
        'import_id',
        'title_override',
        'notes',
        'category',
        'region_label',
        'is_favorite',
        'visibility',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => SavedPlaceCategory::class,
            'is_favorite' => 'boolean',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $savedPlace): void {
            if (blank($savedPlace->uuid)) {
                $savedPlace->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function tripPlaces(): HasMany
    {
        return $this->hasMany(TripPlace::class);
    }
}
