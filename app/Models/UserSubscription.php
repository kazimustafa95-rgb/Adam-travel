<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class UserSubscription extends Model
{
    use HasFactory;

    protected $table = 'subscriptions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'provider',
        'provider_product_id',
        'provider_customer_id',
        'provider_subscription_id',
        'provider_original_transaction_id',
        'status',
        'starts_at',
        'trial_ends_at',
        'expires_at',
        'grace_ends_at',
        'canceled_at',
        'auto_renews',
        'last_synced_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'expires_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'auto_renews' => 'boolean',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            if (blank($subscription->uuid)) {
                $subscription->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class, 'user_subscription_id');
    }
}
