<?php

namespace App\Models;

use App\Enums\TripStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Trip extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_user_id',
        'title',
        'slug',
        'description',
        'start_location_name',
        'start_latitude',
        'start_longitude',
        'end_location_name',
        'end_latitude',
        'end_longitude',
        'start_date',
        'end_date',
        'status',
        'cover_image_url',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_latitude' => 'decimal:7',
            'start_longitude' => 'decimal:7',
            'end_latitude' => 'decimal:7',
            'end_longitude' => 'decimal:7',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => TripStatus::class,
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $trip): void {
            if (blank($trip->uuid)) {
                $trip->uuid = (string) Str::uuid();
            }

            if (blank($trip->slug)) {
                $trip->slug = Str::slug($trip->title.'-'.Str::random(6));
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TripMember::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(TripInvite::class);
    }

    public function pool(): HasMany
    {
        return $this->hasMany(TripPlace::class)->whereNull('deleted_at');
    }

    public function itineraryDays(): HasMany
    {
        return $this->hasMany(ItineraryDay::class)->whereNull('deleted_at')->orderBy('day_number');
    }

    public function aiRuns(): HasMany
    {
        return $this->hasMany(TripAiRun::class);
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(TripSuggestion::class);
    }

    public function offlinePackages(): HasMany
    {
        return $this->hasMany(OfflinePackage::class);
    }
}
