<?php

namespace App\Events\PublicApi;

use App\Support\PublicApi\LocationSuggestionRealtime;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LocationSuggestionStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>|null  $result
     */
    public function __construct(
        public string $token,
        public string $status,
        public ?array $result = null,
        public ?string $error = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel(LocationSuggestionRealtime::channelName($this->token));
    }

    public function broadcastAs(): string
    {
        return LocationSuggestionRealtime::eventNameForStatus($this->status);
    }

    /**
     * Keep realtime payloads small so mobile receives a signal immediately,
     * then can fetch the full async result from the status endpoint.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_filter([
            'token' => $this->token,
            'status' => $this->status,
            'result_ready' => $this->status === 'completed',
            'error' => $this->error,
        ], fn (mixed $value): bool => $value !== null);
    }
}
