<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Friendship extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'friend_user_id',
        'friend_request_id',
        'connected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'connected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $friendship): void {
            if (blank($friendship->uuid)) {
                $friendship->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function friend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_user_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(FriendRequest::class, 'friend_request_id');
    }
}
