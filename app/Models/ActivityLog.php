<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ActivityLog extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'admin_id',
        'action',
        'target_type',
        'target_id',
        'target_label',
        'ip_address',
        'description',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            if (blank($log->uuid)) {
                $log->uuid = (string) Str::uuid();
            }
        });
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
