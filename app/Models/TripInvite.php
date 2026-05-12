<?php

namespace App\Models;

use App\Enums\TripInviteStatus;
use App\Enums\TripMemberRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TripInvite extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'invited_by_user_id',
        'email',
        'token',
        'role',
        'status',
        'expires_at',
        'accepted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TripMemberRole::class,
            'status' => TripInviteStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invite): void {
            if (blank($invite->token)) {
                $invite->token = Str::random(48);
            }
        });
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
