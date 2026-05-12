<?php

namespace App\Models;

use App\Enums\AdminRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => AdminRole::class,
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $admin): void {
            if (blank($admin->uuid)) {
                $admin->uuid = (string) Str::uuid();
            }
        });
    }

    public function assignedSupportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_admin_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
