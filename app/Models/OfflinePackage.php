<?php

namespace App\Models;

use App\Enums\OfflinePackageScope;
use App\Enums\OfflinePackageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflinePackage extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'trip_id',
        'package_scope',
        'scope_reference',
        'manifest_version',
        'status',
        'manifest_payload',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'package_scope' => OfflinePackageScope::class,
            'manifest_version' => 'integer',
            'status' => OfflinePackageStatus::class,
            'manifest_payload' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
