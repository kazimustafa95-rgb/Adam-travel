<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => data_get($this->resource, 'token'),
            'token_type' => 'Bearer',
            'user' => (new UserResource(data_get($this->resource, 'user')))->resolve(),
        ];
    }
}
