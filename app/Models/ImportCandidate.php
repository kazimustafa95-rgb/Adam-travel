<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportCandidate extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'import_id',
        'candidate_rank',
        'place_name',
        'category',
        'city',
        'region',
        'country',
        'latitude',
        'longitude',
        'provider_place_id',
        'summary',
        'confidence_score',
        'metadata',
        'selected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'confidence_score' => 'decimal:2',
            'metadata' => 'array',
            'selected_at' => 'datetime',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
