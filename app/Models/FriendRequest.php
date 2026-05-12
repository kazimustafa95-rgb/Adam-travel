<?php

namespace App\Models;

use App\Enums\FriendRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FriendRequest extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sender_user_id',
        'recipient_user_id',
        'status',
        'responded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FriendRequestStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $friendRequest): void {
            if (blank($friendRequest->uuid)) {
                $friendRequest->uuid = (string) Str::uuid();
            }
        });
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
