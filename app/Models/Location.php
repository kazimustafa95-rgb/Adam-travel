<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Location extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'category',
        'address_line',
        'city',
        'region',
        'country_code',
        'postal_code',
        'latitude',
        'longitude',
        'provider_place_id',
        'provider_source',
        'metadata',
        'is_moderated_hidden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'metadata' => 'array',
            'is_moderated_hidden' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $location): void {
            if (blank($location->uuid)) {
                $location->uuid = (string) Str::uuid();
            }
        });
    }

    public function savedPlaces(): HasMany
    {
        return $this->hasMany(SavedPlace::class);
    }
}
