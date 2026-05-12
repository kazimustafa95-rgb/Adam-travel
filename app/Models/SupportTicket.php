<?php

namespace App\Models;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'assigned_admin_id',
        'subject',
        'message',
        'priority',
        'status',
        'admin_notes',
        'metadata',
        'last_user_reply_at',
        'last_admin_reply_at',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => SupportTicketPriority::class,
            'status' => SupportTicketStatus::class,
            'metadata' => 'array',
            'last_user_reply_at' => 'datetime',
            'last_admin_reply_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            if (blank($ticket->uuid)) {
                $ticket->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }
}
