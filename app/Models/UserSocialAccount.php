<?php

namespace App\Models;

use App\Enums\SocialAuthProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSocialAccount extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'provider_email',
        'provider_email_verified_at',
        'avatar_url',
        'provider_payload',
        'last_used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => SocialAuthProvider::class,
            'provider_email_verified_at' => 'datetime',
            'provider_payload' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
