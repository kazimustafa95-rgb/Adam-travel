<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripPlaceHeart extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_place_id',
        'user_id',
    ];

    public function tripPlace(): BelongsTo
    {
        return $this->belongsTo(TripPlace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
