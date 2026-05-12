<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRequest extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'context_type',
        'context_id',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'status',
        'request_hash',
        'response_excerpt',
        'error_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
