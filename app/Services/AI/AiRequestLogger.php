<?php

namespace App\Services\AI;

use App\Models\AiRequest;
use App\Models\User;

class AiRequestLogger
{
    public function start(
        User|null $user,
        string $contextType,
        int|null $contextId,
        string $provider,
        string|null $model,
        string $requestHash,
    ): AiRequest {
        return AiRequest::query()->create([
            'user_id' => $user?->id,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'provider' => $provider,
            'model' => $model,
            'status' => 'pending',
            'request_hash' => $requestHash,
        ]);
    }

    public function complete(
        AiRequest $aiRequest,
        string $status,
        string|null $responseExcerpt = null,
        string|null $errorMessage = null,
        int|null $promptTokens = null,
        int|null $completionTokens = null,
    ): AiRequest {
        $aiRequest->update([
            'status' => $status,
            'response_excerpt' => $responseExcerpt,
            'error_message' => $errorMessage,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
        ]);

        return $aiRequest->fresh();
    }
}
