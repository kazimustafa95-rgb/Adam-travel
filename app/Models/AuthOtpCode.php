<?php

namespace App\Models;

use App\Enums\AuthOtpPurpose;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthOtpCode extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'purpose',
        'challenge_id',
        'email',
        'code_hash',
        'attempt_count',
        'max_attempts',
        'expires_at',
        'verified_at',
        'consumed_at',
        'last_sent_at',
        'ip_address',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => AuthOtpPurpose::class,
            'attempt_count' => 'integer',
            'max_attempts' => 'integer',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
