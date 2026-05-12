<?php

namespace App\Models;

use App\Enums\TripAiRunStatus;
use App\Enums\TripAiRunType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripAiRun extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'requested_by_user_id',
        'type',
        'status',
        'provider',
        'model',
        'trip_version',
        'input_hash',
        'result_payload',
        'error_message',
        'applied_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TripAiRunType::class,
            'status' => TripAiRunStatus::class,
            'trip_version' => 'integer',
            'result_payload' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(TripSuggestion::class);
    }
}
