<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'provider_product_id',
        'is_active',
        'monthly_price',
        'yearly_price',
        'features_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'features_json' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'subscription_plan_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class, 'subscription_plan_id');
    }
}
