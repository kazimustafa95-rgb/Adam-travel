<?php

namespace App\Models;

use App\Enums\AccountStatus;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'status',
        'onboarding_completed_at',
        'last_seen_at',
        'last_proximity_prompt_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_proximity_prompt_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'password' => 'hashed',
            'status' => AccountStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (blank($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    protected function initials(): Attribute
    {
        return Attribute::get(function (): string {
            return (string) str($this->name)
                ->explode(' ')
                ->filter()
                ->take(2)
                ->map(fn (string $part) => str($part)->substr(0, 1)->upper()->toString())
                ->implode('');
        });
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $socialAccounts = $this->relationLoaded('socialAccounts')
                ? $this->socialAccounts
                : $this->socialAccounts()->get();

            return $socialAccounts->pluck('avatar_url')->filter()->first();
        });
    }

    public function preference(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(UserSocialAccount::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    public function savedPlaceCollections(): HasMany
    {
        return $this->hasMany(SavedPlaceCollection::class);
    }

    public function savedPlaces(): HasMany
    {
        return $this->hasMany(SavedPlace::class);
    }

    public function ownedTrips(): HasMany
    {
        return $this->hasMany(Trip::class, 'owner_user_id');
    }

    public function tripMemberships(): HasMany
    {
        return $this->hasMany(TripMember::class);
    }

    public function tripInvitesSent(): HasMany
    {
        return $this->hasMany(TripInvite::class, 'invited_by_user_id');
    }

    public function sentFriendRequests(): HasMany
    {
        return $this->hasMany(FriendRequest::class, 'sender_user_id');
    }

    public function receivedFriendRequests(): HasMany
    {
        return $this->hasMany(FriendRequest::class, 'recipient_user_id');
    }

    public function friendships(): HasMany
    {
        return $this->hasMany(Friendship::class);
    }

    public function tripPlaces(): HasMany
    {
        return $this->hasMany(TripPlace::class, 'added_by_user_id');
    }

    public function tripAiRuns(): HasMany
    {
        return $this->hasMany(TripAiRun::class, 'requested_by_user_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function subscriptionEvents(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    public function offlinePackages(): HasMany
    {
        return $this->hasMany(OfflinePackage::class);
    }

    public function proximityPromptLogs(): HasMany
    {
        return $this->hasMany(ProximityPromptLog::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function recentSearches(): HasMany
    {
        return $this->hasMany(RecentSearch::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }
}
