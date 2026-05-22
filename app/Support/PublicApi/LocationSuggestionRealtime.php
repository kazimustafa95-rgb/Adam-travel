<?php

namespace App\Support\PublicApi;

final class LocationSuggestionRealtime
{
    public const EVENT_PROCESSING = 'location-suggestions.processing';

    public const EVENT_COMPLETED = 'location-suggestions.completed';

    public const EVENT_FAILED = 'location-suggestions.failed';

    public static function channelName(string $token): string
    {
        return 'location-suggestions.'.$token;
    }

    /**
     * @return array{processing:string,completed:string,failed:string}
     */
    public static function events(): array
    {
        return [
            'processing' => self::EVENT_PROCESSING,
            'completed' => self::EVENT_COMPLETED,
            'failed' => self::EVENT_FAILED,
        ];
    }

    public static function eventNameForStatus(string $status): string
    {
        return match ($status) {
            'processing' => self::EVENT_PROCESSING,
            'completed' => self::EVENT_COMPLETED,
            'failed' => self::EVENT_FAILED,
            default => 'location-suggestions.updated',
        };
    }

    public static function isEnabled(): bool
    {
        $connection = self::connection();

        if (! in_array($connection, ['pusher', 'reverb', 'ably'], true)) {
            return false;
        }

        $key = trim((string) config('broadcasting.connections.'.$connection.'.key', ''));

        return $key !== '';
    }

    public static function connection(): string
    {
        return (string) config('broadcasting.default', 'null');
    }

    /**
     * @return array<string, mixed>
     */
    public static function subscriptionPayload(string $token): array
    {
        return [
            'enabled' => self::isEnabled(),
            'provider' => self::connection(),
            'channel' => self::channelName($token),
            'auth_required' => false,
            'events' => self::events(),
        ];
    }
}
