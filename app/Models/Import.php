<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Enums\ImportSourceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Import extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'source_type',
        'source_url',
        'source_host',
        'raw_text',
        'normalized_text',
        'status',
        'error_code',
        'error_message',
        'confidence_score',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => ImportSourceType::class,
            'status' => ImportStatus::class,
            'confidence_score' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $import): void {
            if (blank($import->uuid)) {
                $import->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function savedPlaces(): HasMany
    {
        return $this->hasMany(SavedPlace::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(ImportCandidate::class)->orderBy('candidate_rank');
    }
}
