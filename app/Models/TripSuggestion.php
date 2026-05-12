<?php

namespace App\Models;

use App\Enums\TripSuggestionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripSuggestion extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'trip_ai_run_id',
        'saved_place_id',
        'location_id',
        'title',
        'category',
        'summary',
        'score',
        'distance_meters',
        'status',
        'raw_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'distance_meters' => 'integer',
            'status' => TripSuggestionStatus::class,
            'raw_payload' => 'array',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(TripAiRun::class, 'trip_ai_run_id');
    }

    public function savedPlace(): BelongsTo
    {
        return $this->belongsTo(SavedPlace::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
