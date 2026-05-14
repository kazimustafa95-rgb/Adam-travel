<?php

namespace App\Http\Resources\Api\V1;

use App\Models\RecentSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecentSearch
 */
class RecentSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'query' => $this->query,
            'result_count' => $this->result_count,
            'used_at' => optional($this->used_at)?->toIso8601String(),
        ];
    }
}
