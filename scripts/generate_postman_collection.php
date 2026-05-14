<?php

declare(strict_types=1);

function uuidv4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function stringify(mixed $value): string
{
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if ($value === null) {
        return '';
    }

    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    return (string) $value;
}

function body(array $payload): array
{
    return [
        'mode' => 'raw',
        'raw' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'options' => [
            'raw' => [
                'language' => 'json',
            ],
        ],
    ];
}

function queryList(array $query): array
{
    $items = [];

    foreach ($query as $key => $value) {
        $items[] = [
            'key' => $key,
            'value' => stringify($value),
            'disabled' => $value === null,
        ];
    }

    return $items;
}

function rawUrl(string $path, array $query = []): string
{
    $base = '{{base_url}}/'.ltrim($path, '/');

    if ($query === []) {
        return $base;
    }

    $parts = [];

    foreach ($query as $key => $value) {
        $parts[] = $key.'='.stringify($value);
    }

    return $base.'?'.implode('&', $parts);
}

function eventScript(string $listen, string $script): array
{
    return [
        'listen' => $listen,
        'script' => [
            'type' => 'text/javascript',
            'exec' => preg_split("/\r\n|\n|\r/", trim($script)) ?: [],
        ],
    ];
}

function jsAccessor(string $path): string
{
    $segments = explode('.', $path);
    $expression = 'json';

    foreach ($segments as $segment) {
        if (ctype_digit($segment)) {
            $expression .= '['.$segment.']';

            continue;
        }

        $expression .= '['.json_encode($segment, JSON_THROW_ON_ERROR).']';
    }

    return $expression;
}

function captureScript(array $captures, array $extraLines = []): array
{
    $lines = [
        'if (pm.response.code >= 200 && pm.response.code < 300) {',
        '    const json = pm.response.json();',
    ];

    foreach ($captures as $variable => $path) {
        $jsVar = preg_replace('/[^a-zA-Z0-9_]/', '_', $variable) ?: 'value';
        $accessor = jsAccessor($path);
        $lines[] = '    const '.$jsVar.' = '.$accessor.';';
        $lines[] = '    if ('.$jsVar.' !== undefined && '.$jsVar.' !== null) {';
        $lines[] = '        pm.collectionVariables.set('.json_encode($variable, JSON_THROW_ON_ERROR).', String('.$jsVar.'));';
        $lines[] = '    }';
    }

    foreach ($extraLines as $line) {
        $lines[] = '    '.$line;
    }

    $lines[] = '}';

    return eventScript('test', implode("\n", $lines));
}

function requestItem(
    string $name,
    string $method,
    string $path,
    array $options = [],
): array {
    $query = $options['query'] ?? [];
    $headers = [
        [
            'key' => 'Accept',
            'value' => 'application/json',
            'type' => 'text',
        ],
    ];

    if (isset($options['body'])) {
        $headers[] = [
            'key' => 'Content-Type',
            'value' => 'application/json',
            'type' => 'text',
        ];
    }

    foreach ($options['headers'] ?? [] as $key => $value) {
        $headers[] = [
            'key' => $key,
            'value' => $value,
            'type' => 'text',
        ];
    }

    $request = [
        'method' => strtoupper($method),
        'header' => $headers,
        'url' => [
            'raw' => rawUrl($path, $query),
            'host' => ['{{base_url}}'],
            'path' => array_values(array_filter(explode('/', trim($path, '/')), static fn (string $segment): bool => $segment !== '')),
        ],
    ];

    if ($query !== []) {
        $request['url']['query'] = queryList($query);
    }

    if (isset($options['body'])) {
        $request['body'] = body($options['body']);
    }

    if (isset($options['description'])) {
        $request['description'] = $options['description'];
    }

    if (($options['auth'] ?? 'bearer') === 'noauth') {
        $request['auth'] = [
            'type' => 'noauth',
        ];
    }

    $item = [
        'name' => $name,
        'request' => $request,
        'response' => [],
    ];

    $events = [];

    if (isset($options['testScript'])) {
        $events[] = eventScript('test', $options['testScript']);
    }

    if (isset($options['capture'])) {
        $events[] = captureScript($options['capture'], $options['captureExtra'] ?? []);
    }

    if (isset($options['prerequestScript'])) {
        $events[] = eventScript('prerequest', $options['prerequestScript']);
    }

    if ($events !== []) {
        $item['event'] = $events;
    }

    return $item;
}

function folder(string $name, array $items, ?string $description = null): array
{
    $folder = [
        'name' => $name,
        'item' => $items,
    ];

    if ($description !== null) {
        $folder['description'] = $description;
    }

    return $folder;
}

function countRequests(array $items): int
{
    $count = 0;

    foreach ($items as $item) {
        if (isset($item['request'])) {
            $count++;

            continue;
        }

        $count += countRequests($item['item'] ?? []);
    }

    return $count;
}

function indexRequests(array $items): array
{
    $index = [];

    foreach ($items as $item) {
        if (isset($item['request'])) {
            $index[$item['name']] = $item;

            continue;
        }

        $index = array_merge($index, indexRequests($item['item'] ?? []));
    }

    return $index;
}

function pickRequest(array $index, string $name): array
{
    if (! isset($index[$name])) {
        throw new RuntimeException('Unable to find request item: '.$name);
    }

    return $index[$name];
}

function authDefinition(): array
{
    return [
        'type' => 'bearer',
        'bearer' => [
            [
                'key' => 'token',
                'value' => '{{access_token}}',
                'type' => 'string',
            ],
        ],
    ];
}

function variableDefinitions(array $variables): array
{
    return array_map(
        static fn (string $key, string $value): array => [
            'key' => $key,
            'value' => $value,
            'type' => 'string',
        ],
        array_keys($variables),
        array_values($variables),
    );
}

function collectionDocument(string $name, string $description, array $items, array $variables): array
{
    return [
        'info' => [
            '_postman_id' => uuidv4(),
            'name' => $name,
            'description' => $description,
            'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
        ],
        'auth' => authDefinition(),
        'variable' => variableDefinitions($variables),
        'item' => $items,
    ];
}

function filesystemName(string $name): string
{
    $normalized = preg_replace('/[\\\\\\/:*?"<>|]+/', ' ', $name) ?? $name;
    $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? trim($name);

    return $normalized !== '' ? $normalized : 'Module';
}

function writeFolderCollections(string $baseDir, array $folders, array $variables): void
{
    foreach ($folders as $folderItem) {
        if (! isset($folderItem['item']) || isset($folderItem['request'])) {
            continue;
        }

        $directoryName = filesystemName($folderItem['name']);
        $folderDir = $baseDir.DIRECTORY_SEPARATOR.$directoryName;

        if (! is_dir($folderDir) && ! mkdir($folderDir, 0777, true) && ! is_dir($folderDir)) {
            throw new RuntimeException('Unable to create module directory: '.$folderDir);
        }

        $collectionPath = $folderDir.DIRECTORY_SEPARATOR.$directoryName.'.postman_collection.json';
        $collection = collectionDocument(
            $folderItem['name'].' - Adam Travel API',
            'Module export for '.$folderItem['name'].' based on the Figma-aligned Postman organization.',
            $folderItem['item'],
            $variables,
        );

        file_put_contents(
            $collectionPath,
            json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );

        writeFolderCollections($folderDir, $folderItem['item'], $variables);
    }
}

$variables = [
    'base_url' => 'http://127.0.0.1:8000',
    'access_token' => '',
    'device_name' => 'QA iPhone',
    'device_platform' => 'ios',
    'device_identifier' => 'device-001',
    'firebase_google_id_token' => '',
    'firebase_apple_id_token' => '',
    'otp_challenge_id' => '',
    'otp_code' => '',
    'reset_token' => '',
    'user_id' => '',
    'user_uuid' => '',
    'saved_place_id' => '1',
    'import_id' => '1',
    'import_candidate_id' => '1',
    'trip_id' => '1',
    'trip_version' => '1',
    'invite_id' => '1',
    'friend_request_id' => '1',
    'notification_id' => '1',
    'trip_invite_token' => '',
    'member_id' => '1',
    'trip_place_id' => '1',
    'itinerary_day_id' => '1',
    'itinerary_item_id' => '1',
    'trip_ai_run_id' => '1',
    'suggestion_id' => '1',
    'saved_place_collection_id' => '1',
    'support_ticket_id' => '1',
    'revenuecat_webhook_secret' => 'testing-revenuecat-secret',
    'rc_event_timestamp_ms' => '',
    'rc_purchased_at_ms' => '',
    'rc_expiration_at_ms' => '',
];

$items = [
    folder('Meta & Webhooks', [
        requestItem(
            'Meta',
            'GET',
            'api/v1/meta',
            [
                'auth' => 'noauth',
            ],
        ),
        requestItem(
            'RevenueCat Webhook',
            'POST',
            'api/v1/billing/webhooks/revenuecat',
            [
                'auth' => 'noauth',
                'description' => 'Pre-request script stamps current timestamps and signs the JSON body with {{revenuecat_webhook_secret}}.',
                'body' => [
                    'api_version' => '1.0',
                    'event' => [
                        'type' => 'INITIAL_PURCHASE',
                        'app_user_id' => '{{user_uuid}}',
                        'product_id' => 'adam_travel_premium',
                        'original_transaction_id' => 'orig-premium-001',
                        'transaction_id' => 'txn-premium-001',
                        'store' => 'app_store',
                        'period_type' => 'normal',
                        'event_timestamp_ms' => '{{rc_event_timestamp_ms}}',
                        'purchased_at_ms' => '{{rc_purchased_at_ms}}',
                        'expiration_at_ms' => '{{rc_expiration_at_ms}}',
                        'entitlement_ids' => ['premium'],
                    ],
                ],
                'prerequestScript' => <<<'JS'
const nowMs = Date.now();
pm.collectionVariables.set('rc_event_timestamp_ms', String(nowMs));
pm.collectionVariables.set('rc_purchased_at_ms', String(nowMs - 60000));
pm.collectionVariables.set('rc_expiration_at_ms', String(nowMs + (30 * 24 * 60 * 60 * 1000)));
const secret = pm.collectionVariables.get('revenuecat_webhook_secret') || '';
const resolvedBody = pm.variables.replaceIn(pm.request.body.raw);
const signature = CryptoJS.HmacSHA256(resolvedBody, secret).toString();
pm.request.headers.upsert({ key: 'X-RevenueCat-Signature', value: signature });
JS,
            ],
        ),
    ], 'Public platform metadata plus the signed billing webhook used to sync premium entitlements.'),

    folder('Auth', [
        requestItem(
            'Register',
            'POST',
            'api/v1/auth/register',
            [
                'auth' => 'noauth',
                'body' => [
                    'name' => 'Jamie Traveler',
                    'email' => 'jamie@example.com',
                    'password' => 'securePass123!',
                    'password_confirmation' => 'securePass123!',
                    'device_name' => '{{device_name}}',
                    'device_platform' => '{{device_platform}}',
                    'device_identifier' => '{{device_identifier}}',
                ],
                'capture' => [
                    'access_token' => 'data.token',
                    'user_id' => 'data.user.id',
                    'user_uuid' => 'data.user.uuid',
                ],
            ],
        ),
        requestItem(
            'Login',
            'POST',
            'api/v1/auth/login',
            [
                'auth' => 'noauth',
                'body' => [
                    'email' => 'jamie@example.com',
                    'password' => 'securePass123!',
                    'device_name' => '{{device_name}}',
                    'device_platform' => '{{device_platform}}',
                    'device_identifier' => '{{device_identifier}}',
                ],
                'capture' => [
                    'access_token' => 'data.token',
                    'user_id' => 'data.user.id',
                    'user_uuid' => 'data.user.uuid',
                ],
            ],
        ),
        requestItem(
            'Google Social Sign In',
            'POST',
            'api/v1/auth/social/google',
            [
                'auth' => 'noauth',
                'body' => [
                    'firebase_id_token' => '{{firebase_google_id_token}}',
                    'device_name' => '{{device_name}}',
                    'device_platform' => '{{device_platform}}',
                    'device_identifier' => '{{device_identifier}}',
                ],
                'capture' => [
                    'access_token' => 'data.token',
                    'user_id' => 'data.user.id',
                    'user_uuid' => 'data.user.uuid',
                ],
            ],
        ),
        requestItem(
            'Apple Social Sign In',
            'POST',
            'api/v1/auth/social/apple',
            [
                'auth' => 'noauth',
                'body' => [
                    'firebase_id_token' => '{{firebase_apple_id_token}}',
                    'name' => 'Apple Traveler',
                    'email' => 'appletraveler@example.com',
                    'device_name' => '{{device_name}}',
                    'device_platform' => '{{device_platform}}',
                    'device_identifier' => '{{device_identifier}}',
                ],
                'capture' => [
                    'access_token' => 'data.token',
                    'user_id' => 'data.user.id',
                    'user_uuid' => 'data.user.uuid',
                ],
            ],
        ),
        requestItem(
            'Forgot Password',
            'POST',
            'api/v1/auth/forgot-password',
            [
                'auth' => 'noauth',
                'body' => [
                    'email' => 'jamie@example.com',
                ],
            ],
        ),
        requestItem(
            'Request Password Reset OTP',
            'POST',
            'api/v1/auth/password-otp/request',
            [
                'auth' => 'noauth',
                'body' => [
                    'email' => 'jamie@example.com',
                ],
                'capture' => [
                    'otp_challenge_id' => 'data.challenge_id',
                ],
            ],
        ),
        requestItem(
            'Verify Password Reset OTP',
            'POST',
            'api/v1/auth/password-otp/verify',
            [
                'auth' => 'noauth',
                'body' => [
                    'email' => 'jamie@example.com',
                    'challenge_id' => '{{otp_challenge_id}}',
                    'code' => '{{otp_code}}',
                ],
                'capture' => [
                    'reset_token' => 'data.reset_token',
                ],
            ],
        ),
        requestItem(
            'Reset Password',
            'POST',
            'api/v1/auth/reset-password',
            [
                'auth' => 'noauth',
                'body' => [
                    'email' => 'jamie@example.com',
                    'token' => '{{reset_token}}',
                    'password' => 'newSecurePass123!',
                    'password_confirmation' => 'newSecurePass123!',
                ],
            ],
        ),
        requestItem(
            'Logout',
            'POST',
            'api/v1/auth/logout',
            [
                'testScript' => <<<'JS'
if (pm.response.code >= 200 && pm.response.code < 300) {
    pm.collectionVariables.unset('access_token');
}
JS,
            ],
        ),
    ], 'Authentication lifecycle for mobile clients, including Sanctum token bootstrap and password recovery.'),

    folder('Dashboard & Onboarding', [
        requestItem(
            'Dashboard',
            'GET',
            'api/v1/dashboard',
            [
                'query' => [
                    'latitude' => 35.6804,
                    'longitude' => 139.769,
                    'radius_meters' => 2500,
                ],
            ],
        ),
        requestItem('Onboarding', 'GET', 'api/v1/onboarding'),
        requestItem(
            'Update Onboarding',
            'PUT',
            'api/v1/onboarding',
            [
                'body' => [
                    'completed' => true,
                ],
            ],
        ),
    ]),

    folder('Home', [
        requestItem(
            'Home Search',
            'GET',
            'api/v1/home/search',
            [
                'query' => [
                    'q' => 'Tokyo',
                    'latitude' => 35.6804,
                    'longitude' => 139.769,
                    'radius_meters' => 2500,
                    'limit' => 10,
                ],
            ],
        ),
        requestItem(
            'Save Recent Search',
            'POST',
            'api/v1/home/searches',
            [
                'body' => [
                    'q' => 'Tokyo food',
                    'result_count' => 3,
                ],
            ],
        ),
        requestItem('Clear Recent Searches', 'DELETE', 'api/v1/home/searches'),
        requestItem(
            'Notifications',
            'GET',
            'api/v1/notifications',
            [
                'capture' => [
                    'notification_id' => 'data.groups.0.items.0.id',
                ],
            ],
        ),
        requestItem('Mark Notification Read', 'POST', 'api/v1/notifications/{{notification_id}}/read'),
        requestItem('Mark All Notifications Read', 'POST', 'api/v1/notifications/read-all'),
        requestItem(
            'List Saved Place Collections',
            'GET',
            'api/v1/saved-place-collections',
            [
                'query' => [
                    'saved_place_id' => '{{saved_place_id}}',
                ],
            ],
        ),
        requestItem(
            'Create Saved Place Collection',
            'POST',
            'api/v1/saved-place-collections',
            [
                'body' => [
                    'name' => 'Europe',
                    'description' => 'Summer route shortlist',
                    'color_hex' => '#0F9FB2',
                ],
                'capture' => [
                    'saved_place_collection_id' => 'data.id',
                ],
            ],
        ),
        requestItem(
            'Categorize Saved Place',
            'POST',
            'api/v1/saved-places/{{saved_place_id}}/categorize',
            [
                'body' => [
                    'saved_place_collection_id' => '{{saved_place_collection_id}}',
                ],
            ],
        ),
        requestItem('Saved Place Trip Options', 'GET', 'api/v1/saved-places/{{saved_place_id}}/trip-options'),
        requestItem(
            'Add Saved Place To Trip',
            'POST',
            'api/v1/saved-places/{{saved_place_id}}/trip-links',
            [
                'body' => [
                    'trip_id' => '{{trip_id}}',
                    'trip_category' => 'activity',
                    'notes' => 'Added from the home screen.',
                ],
                'capture' => [
                    'trip_place_id' => 'data.id',
                ],
            ],
        ),
    ], 'Home-screen hydration, discovery search, notifications, categorization containers, and add-to-trip actions.'),

    folder('Profile', [
        folder('Account', [
            requestItem('Profile Home', 'GET', 'api/v1/profile'),
            requestItem('Me', 'GET', 'api/v1/me'),
            requestItem(
                'Update Me',
                'PATCH',
                'api/v1/me',
                [
                    'body' => [
                        'name' => 'Jamie Traveler Updated',
                        'email' => 'jamie.updated@example.com',
                    ],
                ],
            ),
            requestItem(
                'Delete Account',
                'DELETE',
                'api/v1/me',
                [
                    'body' => [
                        'current_password' => 'newSecurePass123!',
                    ],
                ],
            ),
            requestItem('Settings', 'GET', 'api/v1/settings'),
            requestItem(
                'Update Settings',
                'PATCH',
                'api/v1/settings',
                [
                    'body' => [
                        'distance_unit' => 'mi',
                        'map_style' => 'standard',
                        'default_radius_meters' => 5000,
                        'notifications_enabled' => false,
                        'offline_auto_sync' => true,
                        'theme' => 'dark',
                    ],
                ],
            ),
        ]),

        folder('Friends & Invitations', [
            requestItem('Friends', 'GET', 'api/v1/friends'),
            requestItem(
                'Send Friend Request',
                'POST',
                'api/v1/friends/requests',
                [
                    'body' => [
                        'recipient_email' => 'friend@example.com',
                    ],
                    'capture' => [
                        'friend_request_id' => 'data.id',
                    ],
                ],
            ),
            requestItem('Cancel Friend Request', 'DELETE', 'api/v1/friends/requests/{{friend_request_id}}'),
            requestItem(
                'Invitations - All Tabs',
                'GET',
                'api/v1/profile/invitations',
                [
                    'query' => [
                        'tab' => 'all',
                    ],
                ],
            ),
            requestItem(
                'Accept All Friend Requests',
                'POST',
                'api/v1/profile/invitations/friends/accept-all',
            ),
            requestItem(
                'Accept Friend Request',
                'POST',
                'api/v1/profile/invitations/friends/{{friend_request_id}}/accept',
            ),
            requestItem(
                'Decline Friend Request',
                'POST',
                'api/v1/profile/invitations/friends/{{friend_request_id}}/decline',
            ),
            requestItem(
                'Accept Trip Invitation',
                'POST',
                'api/v1/profile/invitations/trips/{{invite_id}}/accept',
                [
                    'capture' => [
                        'trip_id' => 'data.id',
                    ],
                ],
            ),
            requestItem(
                'Decline Trip Invitation',
                'POST',
                'api/v1/profile/invitations/trips/{{invite_id}}/decline',
            ),
        ]),

        folder('Timeline', [
            requestItem(
                'Timeline',
                'GET',
                'api/v1/timeline',
                [
                    'query' => [
                        'per_page' => 10,
                    ],
                    'capture' => [
                        'trip_id' => 'data.0.id',
                    ],
                ],
            ),
            requestItem('Timeline Trip Detail', 'GET', 'api/v1/timeline/{{trip_id}}'),
        ]),

        folder('Support', [
            requestItem(
                'Help & Support',
                'GET',
                'api/v1/support',
                [
                    'query' => [
                        'q' => 'offline',
                    ],
                ],
            ),
            requestItem('List Support Tickets', 'GET', 'api/v1/support-tickets'),
            requestItem(
                'Create Support Ticket',
                'POST',
                'api/v1/support-tickets',
                [
                    'body' => [
                        'subject' => 'Need help with offline maps',
                        'message' => 'My offline package is missing after reinstalling the app.',
                        'priority' => 'high',
                    ],
                    'capture' => [
                        'support_ticket_id' => 'data.id',
                    ],
                ],
            ),
        ]),

        folder('Subscription', [
            requestItem('List Plans', 'GET', 'api/v1/plans'),
            requestItem('Show Subscription', 'GET', 'api/v1/subscription'),
            requestItem(
                'Subscription Checkout Preview',
                'POST',
                'api/v1/subscription/checkout-preview',
                [
                    'body' => [
                        'plan_code' => 'premium',
                        'billing_cycle' => 'monthly',
                        'payment_method_brand' => 'visa',
                        'payment_method_last4' => '4242',
                    ],
                ],
            ),
            requestItem('Subscription Activated Summary', 'GET', 'api/v1/subscription/activated'),
            requestItem(
                'Restore Subscription',
                'POST',
                'api/v1/subscription/restore',
                [
                    'body' => [
                        'provider' => 'revenuecat',
                        'provider_app_user_id' => '{{user_uuid}}',
                        'provider_product_id' => 'adam_travel_premium',
                        'receipt_reference' => 'receipt-demo-123',
                        'device_platform' => 'ios',
                        'metadata' => [
                            'source' => 'settings',
                        ],
                    ],
                ],
            ),
        ]),
    ]),

    folder('Map & Discovery', [
        requestItem(
            'Map Pins',
            'GET',
            'api/v1/map/pins',
            [
                'query' => [
                    'north' => 36,
                    'south' => 35,
                    'east' => 140,
                    'west' => 139,
                    'category' => 'activity',
                    'saved_place_collection_id' => '{{saved_place_collection_id}}',
                    'is_favorite' => 1,
                    'q' => 'Tokyo',
                    'latitude' => 35.6804,
                    'longitude' => 139.769,
                    'radius_meters' => 2500,
                    'limit' => 100,
                ],
            ],
        ),
        requestItem(
            'Proximity Check',
            'POST',
            'api/v1/proximity/check',
            [
                'body' => [
                    'latitude' => 35.6895,
                    'longitude' => 139.6917,
                    'radius_meters' => 500,
                ],
            ],
        ),
    ]),

    folder('Saved Places', [
        requestItem(
            'Search Saved Places',
            'GET',
            'api/v1/saved-places/search',
            [
                'query' => [
                    'q' => 'Tokyo',
                    'limit' => 10,
                ],
            ],
        ),
        requestItem(
            'List Saved Places',
            'GET',
            'api/v1/saved-places',
            [
                'query' => [
                    'q' => '',
                    'category' => 'activity',
                    'region_label' => 'Japan 2027',
                    'saved_place_collection_id' => '{{saved_place_collection_id}}',
                    'visibility' => 'private',
                    'is_favorite' => 1,
                    'sort' => 'favorites',
                    'per_page' => 15,
                ],
            ],
        ),
        requestItem(
            'Create Saved Place',
            'POST',
            'api/v1/saved-places',
            [
                'body' => [
                    'location' => [
                        'name' => 'Senso-ji Temple',
                        'category' => 'activity',
                        'city' => 'Tokyo',
                        'country_code' => 'JP',
                        'latitude' => 35.7148,
                        'longitude' => 139.7967,
                        'provider_source' => 'manual',
                    ],
                    'category' => 'activity',
                    'title_override' => 'Must Visit Temple',
                    'notes' => 'Historic temple stop for day one.',
                    'region_label' => 'Japan 2027',
                    'is_favorite' => true,
                    'visibility' => 'private',
                ],
                'capture' => [
                    'saved_place_id' => 'data.id',
                ],
            ],
        ),
        requestItem('Show Saved Place', 'GET', 'api/v1/saved-places/{{saved_place_id}}'),
        requestItem(
            'Update Saved Place',
            'PATCH',
            'api/v1/saved-places/{{saved_place_id}}',
            [
                'body' => [
                    'title_override' => null,
                    'notes' => 'Updated notes from Postman.',
                    'category' => 'restaurant',
                    'is_favorite' => true,
                    'visibility' => 'private',
                ],
            ],
        ),
        requestItem('Delete Saved Place', 'DELETE', 'api/v1/saved-places/{{saved_place_id}}'),
    ], 'Personal place catalog, search surface, and map-ready location persistence.'),

    folder('Imports', [
        requestItem(
            'Create Import',
            'POST',
            'api/v1/imports',
            [
                'body' => [
                    'raw_text' => 'Place: Senso-ji Temple. City: Tokyo. Country: JP. Category: activity. Coordinates: 35.7148, 139.7967. Historic temple district with a lively market street.',
                ],
                'capture' => [
                    'import_id' => 'data.id',
                    'import_candidate_id' => 'data.candidates.0.id',
                ],
            ],
        ),
        requestItem('Show Import', 'GET', 'api/v1/imports/{{import_id}}'),
        requestItem('Retry Import', 'POST', 'api/v1/imports/{{import_id}}/retry'),
        requestItem(
            'Manual Override Import',
            'PATCH',
            'api/v1/imports/{{import_id}}/manual-override',
            [
                'body' => [
                    'place_name' => 'Central Park',
                    'category' => 'activity',
                    'city' => 'New York',
                    'region' => 'NY',
                    'country' => 'US',
                    'latitude' => 40.7829,
                    'longitude' => -73.9654,
                    'summary' => 'Manual correction added exact park coordinates.',
                ],
                'capture' => [
                    'import_candidate_id' => 'data.candidates.0.id',
                ],
            ],
        ),
        requestItem(
            'Confirm Import',
            'POST',
            'api/v1/imports/{{import_id}}/confirm',
            [
                'body' => [
                    'candidate_id' => '{{import_candidate_id}}',
                    'category' => 'activity',
                    'title_override' => 'Imported Favorite',
                    'notes' => 'Confirmed from Postman.',
                    'region_label' => 'Japan 2027',
                    'is_favorite' => true,
                    'visibility' => 'private',
                ],
                'capture' => [
                    'saved_place_id' => 'data.saved_place.id',
                ],
            ],
        ),
    ], 'AI/NLP import pipeline: raw input, retry, manual correction, and saved place confirmation.'),

    folder('Trips', [
        requestItem(
            'List Trips',
            'GET',
            'api/v1/trips',
            [
                'query' => [
                    'q' => '',
                    'status' => 'draft',
                    'per_page' => 15,
                ],
            ],
        ),
        requestItem(
            'Create Trip',
            'POST',
            'api/v1/trips',
            [
                'body' => [
                    'title' => 'Japan Spring 2027',
                    'description' => 'A collaborative spring route through Tokyo and Kyoto.',
                    'start_location_name' => 'Tokyo',
                    'start_latitude' => 35.6762,
                    'start_longitude' => 139.6503,
                    'end_location_name' => 'Kyoto',
                    'end_latitude' => 35.0116,
                    'end_longitude' => 135.7681,
                    'start_date' => '2027-03-10',
                    'end_date' => '2027-03-18',
                    'status' => 'draft',
                ],
                'capture' => [
                    'trip_id' => 'data.id',
                    'trip_version' => 'data.version',
                ],
            ],
        ),
        requestItem('Show Trip', 'GET', 'api/v1/trips/{{trip_id}}'),
        requestItem(
            'Update Trip',
            'PATCH',
            'api/v1/trips/{{trip_id}}',
            [
                'body' => [
                    'description' => 'Updated collaborative route with Kyoto focus.',
                    'status' => 'active',
                    'cover_image_url' => 'https://images.example.com/trips/japan-spring-2027.jpg',
                ],
            ],
        ),
        requestItem('Delete Trip', 'DELETE', 'api/v1/trips/{{trip_id}}'),
        requestItem(
            'Create Trip Invite',
            'POST',
            'api/v1/trips/{{trip_id}}/invites',
            [
                'body' => [
                    'email' => 'friend@example.com',
                    'role' => 'editor',
                ],
                'capture' => [
                    'invite_id' => 'data.id',
                    'trip_invite_token' => 'data.token',
                ],
            ],
        ),
        requestItem('Delete Trip Invite', 'DELETE', 'api/v1/trips/{{trip_id}}/invites/{{invite_id}}'),
        requestItem(
            'Accept Trip Invite',
            'POST',
            'api/v1/trip-invites/{{trip_invite_token}}/accept',
            [
                'capture' => [
                    'trip_id' => 'data.id',
                ],
            ],
        ),
        requestItem(
            'Update Trip Member',
            'PATCH',
            'api/v1/trips/{{trip_id}}/members/{{member_id}}',
            [
                'body' => [
                    'role' => 'viewer',
                ],
            ],
        ),
        requestItem('Delete Trip Member', 'DELETE', 'api/v1/trips/{{trip_id}}/members/{{member_id}}'),
        requestItem('List Trip Pool', 'GET', 'api/v1/trips/{{trip_id}}/pool'),
        requestItem(
            'Add Trip Pool Place',
            'POST',
            'api/v1/trips/{{trip_id}}/pool',
            [
                'body' => [
                    'saved_place_id' => '{{saved_place_id}}',
                    'trip_category' => 'activity',
                    'notes' => 'Great sunset option',
                ],
                'capture' => [
                    'trip_place_id' => 'data.id',
                ],
            ],
        ),
        requestItem(
            'Update Trip Pool Place',
            'PATCH',
            'api/v1/trips/{{trip_id}}/pool/{{trip_place_id}}',
            [
                'body' => [
                    'trip_category' => 'restaurant',
                    'notes' => 'Updated pool note from Postman.',
                ],
            ],
        ),
        requestItem('Delete Trip Pool Place', 'DELETE', 'api/v1/trips/{{trip_id}}/pool/{{trip_place_id}}'),
        requestItem('Heart Trip Pool Place', 'POST', 'api/v1/trips/{{trip_id}}/pool/{{trip_place_id}}/heart'),
        requestItem('Unheart Trip Pool Place', 'DELETE', 'api/v1/trips/{{trip_id}}/pool/{{trip_place_id}}/heart'),
    ], 'Trip container, collaboration invites, members, and shared pool management.'),

    folder('Itinerary & AI Planning', [
        requestItem('Show AI Itinerary', 'GET', 'api/v1/trips/{{trip_id}}/ai-itinerary'),
        requestItem(
            'Generate AI Itinerary',
            'POST',
            'api/v1/trips/{{trip_id}}/ai-itinerary/generate',
            [
                'body' => [
                    'force_refresh' => false,
                ],
                'capture' => [
                    'trip_ai_run_id' => 'data.id',
                ],
            ],
        ),
        requestItem(
            'Apply AI Itinerary',
            'POST',
            'api/v1/trips/{{trip_id}}/ai-itinerary/apply',
            [
                'body' => [
                    'trip_ai_run_id' => '{{trip_ai_run_id}}',
                ],
                'capture' => [
                    'trip_version' => 'meta.trip_version',
                ],
            ],
        ),
        requestItem('List Itinerary', 'GET', 'api/v1/trips/{{trip_id}}/itinerary'),
        requestItem(
            'Create Itinerary Day',
            'POST',
            'api/v1/trips/{{trip_id}}/itinerary/days',
            [
                'body' => [
                    'day_number' => 1,
                    'title' => 'Arrival Day',
                    'notes' => 'Check-in, luggage, and a short evening walk.',
                ],
                'capture' => [
                    'itinerary_day_id' => 'data.id',
                    'trip_version' => 'meta.trip_version',
                ],
            ],
        ),
        requestItem(
            'Reorder Itinerary',
            'PUT',
            'api/v1/trips/{{trip_id}}/itinerary/reorder',
            [
                'body' => [
                    'version' => '{{trip_version}}',
                    'days' => [
                        [
                            'day_id' => '{{itinerary_day_id}}',
                            'items' => [
                                [
                                    'item_id' => '{{itinerary_item_id}}',
                                    'sort_order' => 1,
                                    'starts_at' => '2027-03-10T12:00:00Z',
                                ],
                            ],
                        ],
                    ],
                ],
                'capture' => [
                    'trip_version' => 'meta.trip_version',
                ],
            ],
        ),
        requestItem(
            'Create Itinerary Item',
            'POST',
            'api/v1/trips/{{trip_id}}/itinerary/items',
            [
                'body' => [
                    'itinerary_day_id' => '{{itinerary_day_id}}',
                    'trip_place_id' => '{{trip_place_id}}',
                    'starts_at' => '2027-03-10T10:00:00Z',
                    'notes' => 'Check in and explore nearby.',
                ],
                'capture' => [
                    'itinerary_item_id' => 'data.id',
                    'trip_version' => 'meta.trip_version',
                ],
            ],
        ),
        requestItem(
            'Update Itinerary Item',
            'PATCH',
            'api/v1/trips/{{trip_id}}/itinerary/items/{{itinerary_item_id}}',
            [
                'body' => [
                    'starts_at' => '2027-03-10T11:00:00Z',
                    'ends_at' => '2027-03-10T13:00:00Z',
                    'notes' => 'Adjusted timing from Postman.',
                ],
            ],
        ),
        requestItem('Delete Itinerary Item', 'DELETE', 'api/v1/trips/{{trip_id}}/itinerary/items/{{itinerary_item_id}}'),
        requestItem('List Suggestions', 'GET', 'api/v1/trips/{{trip_id}}/suggestions'),
        requestItem(
            'Generate Suggestions',
            'POST',
            'api/v1/trips/{{trip_id}}/suggestions/generate',
            [
                'body' => [
                    'limit' => 3,
                    'force_refresh' => false,
                ],
                'capture' => [
                    'suggestion_id' => 'data.0.id',
                ],
            ],
        ),
        requestItem('Add Suggestion To Pool', 'POST', 'api/v1/trips/{{trip_id}}/suggestions/{{suggestion_id}}/add'),
        requestItem('Dismiss Suggestion', 'POST', 'api/v1/trips/{{trip_id}}/suggestions/{{suggestion_id}}/dismiss'),
        requestItem('Trip Balance', 'GET', 'api/v1/trips/{{trip_id}}/balance'),
    ], 'Structured itinerary scheduling, AI-generated plans, smart suggestions, and trip balance diagnostics.'),

    folder('Offline & Sync', [
        requestItem(
            'List Offline Packages',
            'GET',
            'api/v1/offline/packages',
            [
                'query' => [
                    'status' => 'ready',
                ],
            ],
        ),
        requestItem(
            'Create Trip Offline Package',
            'POST',
            'api/v1/offline/packages/trips/{{trip_id}}',
        ),
        requestItem(
            'Sync Pull',
            'GET',
            'api/v1/sync',
            [
                'query' => [
                    'cursor' => '{{sync_cursor}}',
                    'device_identifier' => '{{device_identifier}}',
                    'device_name' => '{{device_name}}',
                    'device_platform' => '{{device_platform}}',
                ],
            ],
        ),
        requestItem(
            'Sync Push',
            'POST',
            'api/v1/sync/push',
            [
                'body' => [
                    'device_identifier' => '{{device_identifier}}',
                    'device_name' => '{{device_name}}',
                    'device_platform' => '{{device_platform}}',
                    'changes' => [
                        [
                            'entity' => 'user_preference',
                            'action' => 'update',
                            'payload' => [
                                'offline_auto_sync' => false,
                                'theme' => 'light',
                            ],
                        ],
                        [
                            'entity' => 'saved_place',
                            'action' => 'update',
                            'record_id' => '{{saved_place_id}}',
                            'version' => 1,
                            'payload' => [
                                'notes' => 'Updated offline note',
                                'is_favorite' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ),
    ], 'Offline packaging, device-aware sync pull, and client-originated change push flows.'),

];

$requestIndex = indexRequests($items);

$items = [
    folder('Authentication', [
        pickRequest($requestIndex, 'Register'),
        pickRequest($requestIndex, 'Login'),
        pickRequest($requestIndex, 'Google Social Sign In'),
        pickRequest($requestIndex, 'Apple Social Sign In'),
        pickRequest($requestIndex, 'Forgot Password'),
        pickRequest($requestIndex, 'Request Password Reset OTP'),
        pickRequest($requestIndex, 'Verify Password Reset OTP'),
        pickRequest($requestIndex, 'Reset Password'),
        pickRequest($requestIndex, 'Logout'),
        pickRequest($requestIndex, 'Onboarding'),
        pickRequest($requestIndex, 'Update Onboarding'),
    ], 'Sign-up, sign-in, social login, password recovery, and first-run onboarding flows.'),
    folder('Home', [
        pickRequest($requestIndex, 'Dashboard'),
        pickRequest($requestIndex, 'Home Search'),
        pickRequest($requestIndex, 'Save Recent Search'),
        pickRequest($requestIndex, 'Clear Recent Searches'),
        pickRequest($requestIndex, 'Notifications'),
        pickRequest($requestIndex, 'Mark Notification Read'),
        pickRequest($requestIndex, 'Mark All Notifications Read'),
    ], 'Home-screen hydration, search, search history, and notification flows.'),
    folder('Import Flow', [
        pickRequest($requestIndex, 'Create Import'),
        pickRequest($requestIndex, 'Show Import'),
        pickRequest($requestIndex, 'Retry Import'),
        pickRequest($requestIndex, 'Manual Override Import'),
        pickRequest($requestIndex, 'Confirm Import'),
    ], 'Link/text import pipeline with review, retry, and confirm actions.'),
    folder('Tap Pin', [
        pickRequest($requestIndex, 'Map Pins'),
        pickRequest($requestIndex, 'Proximity Check'),
    ], 'Map and nearby-pin APIs used from the home map interaction flows.'),
    folder('Location Details & Organization', [
        pickRequest($requestIndex, 'Search Saved Places'),
        pickRequest($requestIndex, 'List Saved Places'),
        pickRequest($requestIndex, 'Create Saved Place'),
        pickRequest($requestIndex, 'Show Saved Place'),
        pickRequest($requestIndex, 'Update Saved Place'),
        pickRequest($requestIndex, 'Delete Saved Place'),
        pickRequest($requestIndex, 'List Saved Place Collections'),
        pickRequest($requestIndex, 'Create Saved Place Collection'),
        pickRequest($requestIndex, 'Categorize Saved Place'),
        pickRequest($requestIndex, 'Saved Place Trip Options'),
        pickRequest($requestIndex, 'Add Saved Place To Trip'),
    ], 'Saved-place detail, categorization, collection management, and add-to-trip organization flows.'),
    folder('Trips', [
        pickRequest($requestIndex, 'List Trips'),
        pickRequest($requestIndex, 'Create Trip'),
        pickRequest($requestIndex, 'Accept Trip Invite'),
        folder('Single', [
            pickRequest($requestIndex, 'Show Trip'),
            pickRequest($requestIndex, 'List Trip Pool'),
            pickRequest($requestIndex, 'Show AI Itinerary'),
            pickRequest($requestIndex, 'List Itinerary'),
            pickRequest($requestIndex, 'List Suggestions'),
            pickRequest($requestIndex, 'Trip Balance'),
        ], 'Single-trip read flows shared across trip roles.'),
        folder('Owner', [
            pickRequest($requestIndex, 'Update Trip'),
            pickRequest($requestIndex, 'Delete Trip'),
            pickRequest($requestIndex, 'Create Trip Invite'),
            pickRequest($requestIndex, 'Delete Trip Invite'),
            pickRequest($requestIndex, 'Generate AI Itinerary'),
            pickRequest($requestIndex, 'Apply AI Itinerary'),
            pickRequest($requestIndex, 'Create Itinerary Day'),
            pickRequest($requestIndex, 'Reorder Itinerary'),
            pickRequest($requestIndex, 'Create Itinerary Item'),
            pickRequest($requestIndex, 'Update Itinerary Item'),
            pickRequest($requestIndex, 'Delete Itinerary Item'),
            pickRequest($requestIndex, 'Generate Suggestions'),
        ], 'Owner-only trip mutation, planning, and collaboration actions.'),
        folder('Editor', [
            pickRequest($requestIndex, 'Add Trip Pool Place'),
            pickRequest($requestIndex, 'Update Trip Pool Place'),
            pickRequest($requestIndex, 'Delete Trip Pool Place'),
            pickRequest($requestIndex, 'Heart Trip Pool Place'),
            pickRequest($requestIndex, 'Unheart Trip Pool Place'),
            pickRequest($requestIndex, 'Generate Suggestions'),
            pickRequest($requestIndex, 'Add Suggestion To Pool'),
            pickRequest($requestIndex, 'Dismiss Suggestion'),
        ], 'Editor-capable trip contribution and suggestion-management actions.'),
        folder('Viewer', [
            pickRequest($requestIndex, 'Show Trip'),
            pickRequest($requestIndex, 'List Trip Pool'),
            pickRequest($requestIndex, 'Show AI Itinerary'),
            pickRequest($requestIndex, 'List Itinerary'),
            pickRequest($requestIndex, 'List Suggestions'),
            pickRequest($requestIndex, 'Trip Balance'),
        ], 'Viewer-safe read-only trip APIs.'),
        folder('Manage Members (Owner)', [
            pickRequest($requestIndex, 'Update Trip Member'),
            pickRequest($requestIndex, 'Delete Trip Member'),
        ], 'Owner-only member management APIs.'),
    ], 'Trip lifecycle APIs, organized by role-specific Figma flows.'),
    folder('Profile', [
        folder('Account', [
            pickRequest($requestIndex, 'Profile Home'),
            pickRequest($requestIndex, 'Me'),
            pickRequest($requestIndex, 'Update Me'),
            pickRequest($requestIndex, 'Delete Account'),
            pickRequest($requestIndex, 'Settings'),
            pickRequest($requestIndex, 'Update Settings'),
        ]),
        folder('Invitations', [
            pickRequest($requestIndex, 'Invitations - All Tabs'),
            pickRequest($requestIndex, 'Accept Trip Invitation'),
            pickRequest($requestIndex, 'Decline Trip Invitation'),
        ]),
        folder('Timeline', [
            pickRequest($requestIndex, 'Timeline'),
            pickRequest($requestIndex, 'Timeline Trip Detail'),
        ]),
        folder('Support', [
            pickRequest($requestIndex, 'Help & Support'),
            pickRequest($requestIndex, 'List Support Tickets'),
            pickRequest($requestIndex, 'Create Support Ticket'),
        ]),
        folder('Subscription', [
            pickRequest($requestIndex, 'List Plans'),
            pickRequest($requestIndex, 'Show Subscription'),
            pickRequest($requestIndex, 'Subscription Checkout Preview'),
            pickRequest($requestIndex, 'Subscription Activated Summary'),
            pickRequest($requestIndex, 'Restore Subscription'),
        ]),
    ], 'Profile-area APIs including settings, invitations, memory timeline, support, and subscription.'),
    folder('Friends', [
        pickRequest($requestIndex, 'Friends'),
        pickRequest($requestIndex, 'Send Friend Request'),
        pickRequest($requestIndex, 'Cancel Friend Request'),
        pickRequest($requestIndex, 'Accept All Friend Requests'),
        pickRequest($requestIndex, 'Accept Friend Request'),
        pickRequest($requestIndex, 'Decline Friend Request'),
    ], 'Friend list and friend-request management flows.'),
];

$collection = collectionDocument(
    'Adam Travel API',
    'Figma-aligned Postman collection organized module-wise for mobile flows. Offline and sync requests are intentionally excluded.',
    $items,
    $variables,
);

$environment = [
    'id' => uuidv4(),
    'name' => 'Adam Travel Local',
    'values' => array_map(
        static fn (string $key, string $value): array => [
            'key' => $key,
            'value' => $value,
            'type' => 'text',
            'enabled' => true,
        ],
        array_keys($variables),
        array_values($variables),
    ),
    '_postman_variable_scope' => 'environment',
    '_postman_exported_at' => gmdate(DATE_ATOM),
    '_postman_exported_using' => 'OpenAI Codex',
];

$outputDir = dirname(__DIR__).DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'postman';

if (! is_dir($outputDir) && ! mkdir($outputDir, 0777, true) && ! is_dir($outputDir)) {
    throw new RuntimeException('Unable to create output directory: '.$outputDir);
}

$collectionPath = $outputDir.DIRECTORY_SEPARATOR.'adam-travel-api.postman_collection.json';
$environmentPath = $outputDir.DIRECTORY_SEPARATOR.'adam-travel-local.postman_environment.json';
$moduleOutputDir = $outputDir.DIRECTORY_SEPARATOR.'modules';

file_put_contents(
    $collectionPath,
    json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
);
file_put_contents(
    $environmentPath,
    json_encode($environment, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
);

if (! is_dir($moduleOutputDir) && ! mkdir($moduleOutputDir, 0777, true) && ! is_dir($moduleOutputDir)) {
    throw new RuntimeException('Unable to create module output directory: '.$moduleOutputDir);
}

writeFolderCollections($moduleOutputDir, $items, $variables);

echo 'Generated '.countRequests($items).' requests'.PHP_EOL;
echo $collectionPath.PHP_EOL;
echo $environmentPath.PHP_EOL;
echo $moduleOutputDir.PHP_EOL;
