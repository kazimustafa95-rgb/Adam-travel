<?php

namespace Tests\Feature\Api\V1;

use App\Events\PublicApi\LocationSuggestionStatusUpdated;
use App\Jobs\PublicApi\AnalyzeLocationSuggestionsJob;
use App\Services\PublicApi\LocationSuggestionAsyncService;
use App\Services\PublicApi\LocationSuggestionsService;
use Illuminate\Support\Facades\Event;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublicTravelToolsApiTest extends TestCase
{
    public function test_public_google_place_details_endpoint_returns_normalized_place_details(): void
    {
        Config::set('services.google_places.api_key', 'google-test-key');

        Http::fake(function (Request $request) {
            return match (true) {
                str_ends_with($request->url(), '/places:searchText') => Http::response([
                    'places' => [
                        [
                            'id' => 'place_123',
                            'displayName' => ['text' => 'LuckyOne Mall'],
                            'formattedAddress' => 'Rashid Minhas Rd, Karachi',
                            'location' => [
                                'latitude' => 24.9321,
                                'longitude' => 67.0862,
                            ],
                        ],
                    ],
                ]),
                str_ends_with($request->url(), '/places/place_123') => Http::response([
                    'id' => 'place_123',
                    'displayName' => ['text' => 'LuckyOne Mall'],
                    'formattedAddress' => 'Rashid Minhas Rd, Karachi',
                    'shortFormattedAddress' => 'Karachi',
                    'location' => [
                        'latitude' => 24.9321,
                        'longitude' => 67.0862,
                    ],
                    'types' => ['shopping_mall', 'point_of_interest'],
                    'primaryType' => 'shopping_mall',
                    'primaryTypeDisplayName' => ['text' => 'Shopping mall'],
                    'businessStatus' => 'OPERATIONAL',
                    'googleMapsUri' => 'https://maps.google.com/?cid=123',
                    'websiteUri' => 'https://luckyone.com.pk',
                    'nationalPhoneNumber' => '021-111-111',
                    'internationalPhoneNumber' => '+92 21 1111111',
                    'rating' => 4.5,
                    'userRatingCount' => 3210,
                    'priceLevel' => 'PRICE_LEVEL_MODERATE',
                    'priceRange' => ['startPrice' => ['units' => '1'], 'endPrice' => ['units' => '3']],
                    'regularOpeningHours' => ['openNow' => true],
                    'currentOpeningHours' => ['openNow' => true],
                    'editorialSummary' => ['text' => 'A large family shopping mall.'],
                    'plusCode' => ['globalCode' => '7JQH+12'],
                    'utcOffsetMinutes' => 300,
                    'timeZone' => ['id' => 'Asia/Karachi'],
                    'parkingOptions' => ['freeParkingLot' => true],
                    'paymentOptions' => ['acceptsCreditCards' => true],
                    'accessibilityOptions' => ['wheelchairAccessibleEntrance' => true],
                    'reviews' => [['name' => 'reviews/1']],
                    'photos' => [
                        [
                            'name' => 'places/place_123/photos/photo_1',
                            'widthPx' => 1200,
                            'heightPx' => 800,
                            'authorAttributions' => [],
                        ],
                    ],
                ]),
                str_contains($request->url(), '/photos/photo_1/media') => Http::response([
                    'photoUri' => 'https://cdn.example.com/photo-1.jpg',
                ]),
                default => Http::response([], 404),
            };
        });

        $this->postJson('/api/v1/public/google-place-details', [
            'place_query' => 'lucky one mall',
            'region_code' => 'PK',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', 'place_123')
            ->assertJsonPath('data.name', 'LuckyOne Mall')
            ->assertJsonPath('data.address', 'Rashid Minhas Rd, Karachi')
            ->assertJsonPath('data.lat', 24.9321)
            ->assertJsonPath('data.lng', 67.0862)
            ->assertJsonPath('data.photos.0.url', 'https://cdn.example.com/photo-1.jpg');
    }

    public function test_public_location_suggestions_endpoint_returns_places_and_metadata(): void
    {
        Config::set('services.openai.api_key', 'openai-test-key');
        Config::set('services.openai.model', 'gpt-4o');
        Config::set('services.google_places.api_key', 'google-test-key');

        Http::fake(function (Request $request) {
            $payload = $request->data();
            $textQuery = is_array($payload) ? ($payload['textQuery'] ?? null) : null;

            return match (true) {
                $request->url() === 'https://example.com/travel-post' => Http::response('
                    <html>
                        <head>
                            <meta property="og:title" content="LuckyOne Mall Karachi" />
                            <meta property="og:description" content="A popular shopping destination in Karachi." />
                            <meta property="og:image" content="https://example.com/mall.jpg" />
                            <title>LuckyOne Mall Karachi</title>
                        </head>
                        <body>
                            LuckyOne Mall is one of the biggest malls in Karachi.
                        </body>
                    </html>
                '),
                str_ends_with($request->url(), '/chat/completions') => Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'query' => 'LuckyOne Mall Karachi',
                                    'places' => [
                                        [
                                            'place' => 'LuckyOne Mall',
                                            'category' => 'shopping_mall',
                                            'city' => 'Karachi',
                                            'country' => 'Pakistan',
                                            'confidence' => '95%',
                                            'lat' => 0,
                                            'lng' => 0,
                                            'reason' => 'Exact mall name match from the page title.',
                                        ],
                                        [
                                            'place' => 'Dolmen Mall Clifton',
                                            'category' => 'shopping_mall',
                                            'city' => 'Karachi',
                                            'country' => 'Pakistan',
                                            'confidence' => '82%',
                                            'lat' => 0,
                                            'lng' => 0,
                                            'reason' => 'Similar major mall in the same city.',
                                        ],
                                    ],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ]),
                str_ends_with($request->url(), '/places:searchText') && $textQuery === 'LuckyOne Mall, Karachi, Pakistan' => Http::response([
                    'places' => [
                        [
                            'id' => 'google_place_1',
                            'displayName' => ['text' => 'LuckyOne Mall'],
                            'formattedAddress' => 'Rashid Minhas Rd, Karachi',
                            'location' => [
                                'latitude' => 24.9321,
                                'longitude' => 67.0862,
                            ],
                            'photos' => [
                                [
                                    'name' => 'places/google_place_1/photos/photo_1',
                                ],
                            ],
                        ],
                    ],
                ]),
                str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Dolmen Mall Clifton, Karachi, Pakistan' => Http::response([
                    'places' => [
                        [
                            'id' => 'google_place_2',
                            'displayName' => ['text' => 'Dolmen Mall Clifton'],
                            'formattedAddress' => 'Clifton, Karachi',
                            'location' => [
                                'latitude' => 24.8135,
                                'longitude' => 67.0304,
                            ],
                            'photos' => [
                                [
                                    'name' => 'places/google_place_2/photos/photo_2',
                                ],
                            ],
                        ],
                    ],
                ]),
                str_contains($request->url(), '/google_place_1/photos/photo_1/media') => Http::response([
                    'photoUri' => 'https://cdn.example.com/google-place-1.jpg',
                ]),
                str_contains($request->url(), '/google_place_2/photos/photo_2/media') => Http::response([
                    'photoUri' => 'https://cdn.example.com/google-place-2.jpg',
                ]),
                default => Http::response([], 404),
            };
        });

        $this->postJson('/api/v1/public/location-suggestions', [
            'input' => 'https://example.com/travel-post',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.query', 'LuckyOne Mall Karachi')
            ->assertJsonPath('data.metadata.platform', 'website')
            ->assertJsonPath('data.metadata.title', 'LuckyOne Mall Karachi')
            ->assertJsonPath('data.metadata.image', 'https://example.com/mall.jpg')
            ->assertJsonPath('data.places.0.place', 'LuckyOne Mall')
            ->assertJsonPath('data.places.0.lat', 24.9321)
            ->assertJsonPath('data.places.0.lng', 67.0862)
            ->assertJsonPath('data.places.0.google_place_details.id', 'google_place_1')
            ->assertJsonPath('data.places.0.google_place_details.place', 'LuckyOne Mall')
            ->assertJsonPath('data.places.0.google_place_details.shortAddress', 'Karachi')
            ->assertJsonPath('data.places.0.google_place_details.image', 'https://cdn.example.com/google-place-1.jpg')
            ->assertJsonPath('data.places.1.place', 'Dolmen Mall Clifton')
            ->assertJsonPath('data.places.1.lat', 24.8135)
            ->assertJsonPath('data.places.1.google_place_details.id', 'google_place_2')
            ->assertJsonPath('data.places.1.google_place_details.place', 'Dolmen Mall Clifton')
            ->assertJsonPath('data.places.1.google_place_details.shortAddress', 'Karachi')
            ->assertJsonPath('data.places.1.google_place_details.image', 'https://cdn.example.com/google-place-2.jpg');
    }

    public function test_public_location_suggestions_endpoint_uses_video_frames_and_transcript_for_video_urls(): void
    {
        Config::set('services.openai.api_key', 'openai-test-key');
        Config::set('services.openai.model', 'gpt-4o');
        Config::set('services.openai.transcribe_model', 'gpt-4o-transcribe');
        Config::set('services.google_places.api_key', 'google-test-key');
        Config::set('location_suggestions.video_processing.enabled', true);
        Config::set('location_suggestions.video_processing.yt_dlp_path', 'yt-dlp');
        Config::set('location_suggestions.video_processing.ffmpeg_path', 'ffmpeg');
        Config::set('location_suggestions.video_processing.max_frames', 4);
        Config::set('location_suggestions.video_processing.frame_divisor_seconds', 2);
        Config::set('location_suggestions.video_processing.frame_interval_seconds', 2);

        Http::fake(function (Request $request) {
            $payload = $request->data();
            $textQuery = is_array($payload) ? ($payload['textQuery'] ?? null) : null;

            return match (true) {
                $request->url() === 'https://www.tiktok.com/oembed?url='.urlencode('https://www.tiktok.com/@worldsecrets360/video/123') => Http::response([
                    'title' => '3 Beautiful Places in Turkey #cappadocia #pamukkale #ephesus',
                    'author_name' => 'worldsecrets360',
                    'thumbnail_url' => 'https://cdn.example.com/tiktok-thumb.jpg',
                ]),
                $request->url() === 'https://cdn.example.com/tiktok-thumb.jpg' => Http::response('fake-image', 200, [
                    'Content-Type' => 'image/jpeg',
                ]),
                $request->url() === 'https://www.tiktok.com/@worldsecrets360/video/123' => Http::response('
                    <html>
                        <head>
                            <meta property="og:title" content="3 Beautiful Places in Turkey" />
                            <meta property="og:description" content="Travel inspiration video." />
                            <meta property="og:image" content="https://cdn.example.com/tiktok-thumb.jpg" />
                        </head>
                        <body>
                            Video showcasing travel landmarks in Turkey.
                        </body>
                    </html>
                '),
                str_ends_with($request->url(), '/audio/transcriptions') => Http::response([
                    'text' => 'Today we explore Cappadocia, Pamukkale, and Ephesus in Turkey.',
                ]),
                str_ends_with($request->url(), '/chat/completions') => Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'query' => '3 Beautiful Places in Turkey',
                                    'places' => [
                                        [
                                            'place' => 'Cappadocia',
                                            'category' => 'Region',
                                            'city' => 'Nevsehir',
                                            'country' => 'Turkey',
                                            'confidence' => '90%',
                                            'lat' => 0,
                                            'lng' => 0,
                                            'reason' => 'The transcript explicitly names Cappadocia.',
                                        ],
                                        [
                                            'place' => 'Pamukkale',
                                            'category' => 'Natural Landmark',
                                            'city' => 'Denizli',
                                            'country' => 'Turkey',
                                            'confidence' => '88%',
                                            'lat' => 0,
                                            'lng' => 0,
                                            'reason' => 'The transcript explicitly names Pamukkale.',
                                        ],
                                        [
                                            'place' => 'Ephesus',
                                            'category' => 'Archaeological Site',
                                            'city' => 'Selcuk',
                                            'country' => 'Turkey',
                                            'confidence' => '87%',
                                            'lat' => 0,
                                            'lng' => 0,
                                            'reason' => 'The transcript explicitly names Ephesus.',
                                        ],
                                    ],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ]),
                str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Cappadocia, Nevsehir, Turkey' => Http::response([
                    'places' => [
                        [
                            'id' => 'google_place_1',
                            'displayName' => ['text' => 'Cappadocia'],
                            'formattedAddress' => 'Nevsehir, Turkey',
                            'location' => ['latitude' => 38.6431, 'longitude' => 34.8270],
                            'photos' => [['name' => 'places/google_place_1/photos/photo_1']],
                            'types' => ['tourist_attraction'],
                            'primaryType' => 'tourist_attraction',
                            'primaryTypeDisplayName' => ['text' => 'Tourist attraction'],
                        ],
                    ],
                ]),
                str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Pamukkale, Denizli, Turkey' => Http::response([
                    'places' => [
                        [
                            'id' => 'google_place_2',
                            'displayName' => ['text' => 'Pamukkale'],
                            'formattedAddress' => 'Denizli, Turkey',
                            'location' => ['latitude' => 37.9137, 'longitude' => 29.1187],
                            'photos' => [['name' => 'places/google_place_2/photos/photo_2']],
                            'types' => ['tourist_attraction'],
                            'primaryType' => 'tourist_attraction',
                            'primaryTypeDisplayName' => ['text' => 'Tourist attraction'],
                        ],
                    ],
                ]),
                str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Ephesus, Selcuk, Turkey' => Http::response([
                    'places' => [
                        [
                            'id' => 'google_place_3',
                            'displayName' => ['text' => 'Ephesus'],
                            'formattedAddress' => 'Selcuk, Turkey',
                            'location' => ['latitude' => 37.9390, 'longitude' => 27.3410],
                            'photos' => [['name' => 'places/google_place_3/photos/photo_3']],
                            'types' => ['tourist_attraction'],
                            'primaryType' => 'tourist_attraction',
                            'primaryTypeDisplayName' => ['text' => 'Tourist attraction'],
                        ],
                    ],
                ]),
                str_contains($request->url(), '/google_place_1/photos/photo_1/media') => Http::response([
                    'photoUri' => 'https://cdn.example.com/google-place-1.jpg',
                ]),
                str_contains($request->url(), '/google_place_2/photos/photo_2/media') => Http::response([
                    'photoUri' => 'https://cdn.example.com/google-place-2.jpg',
                ]),
                str_contains($request->url(), '/google_place_3/photos/photo_3/media') => Http::response([
                    'photoUri' => 'https://cdn.example.com/google-place-3.jpg',
                ]),
                default => Http::response([], 404),
            };
        });

        Process::fake(function ($process) {
            $command = is_array($process->command) ? $process->command : [$process->command];
            $binary = $command[0] ?? null;
            $lastArgument = (string) ($command[array_key_last($command)] ?? '');

            if ($binary === 'yt-dlp') {
                if (in_array('--dump-single-json', $command, true)) {
                    return Process::result(json_encode([
                        'duration' => 4,
                    ], JSON_THROW_ON_ERROR));
                }

                $outputIndex = array_search('--output', $command, true);
                $template = is_int($outputIndex) ? (string) ($command[$outputIndex + 1] ?? '') : '';
                $videoPath = str_replace('.%(ext)s', '.mp4', $template);

                if ($videoPath !== '') {
                    file_put_contents($videoPath, 'fake-video');
                }

                return Process::result();
            }

            if ($binary === 'ffmpeg' && str_contains($lastArgument, 'frame-%03d.jpg')) {
                $framesDir = dirname($lastArgument);
                file_put_contents($framesDir.DIRECTORY_SEPARATOR.'frame-001.jpg', 'frame-1');
                file_put_contents($framesDir.DIRECTORY_SEPARATOR.'frame-002.jpg', 'frame-2');

                return Process::result();
            }

            if ($binary === 'ffmpeg' && str_ends_with($lastArgument, 'audio.mp3')) {
                file_put_contents($lastArgument, 'fake-audio');

                return Process::result();
            }

            return Process::result('', '', 1);
        });

        $this->postJson('/api/v1/public/location-suggestions', [
            'input' => 'https://www.tiktok.com/@worldsecrets360/video/123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.query', '3 Beautiful Places in Turkey')
            ->assertJsonPath('data.metadata.platform', 'tiktok')
            ->assertJsonPath('data.metadata.image', 'https://cdn.example.com/tiktok-thumb.jpg')
            ->assertJsonPath('data.analysis_debug.mode', 'sync')
            ->assertJsonPath('data.analysis_debug.used_async', false)
            ->assertJsonPath('data.analysis_debug.video_duration_seconds', 4)
            ->assertJsonPath('data.analysis_debug.frame_target_count', 2)
            ->assertJsonPath('data.analysis_debug.frames_extracted', 2)
            ->assertJsonPath('data.analysis_debug.openai_images_sent', 4)
            ->assertJsonPath('data.analysis_debug.openai_image_detail', 'high')
            ->assertJsonCount(3, 'data.places')
            ->assertJsonPath('data.places.0.place', 'Cappadocia')
            ->assertJsonPath('data.places.1.place', 'Pamukkale')
            ->assertJsonPath('data.places.2.place', 'Ephesus');

        Process::assertRan(fn ($process) => is_array($process->command) && ($process->command[0] ?? null) === 'yt-dlp');
        Process::assertRanTimes(fn ($process) => is_array($process->command) && ($process->command[0] ?? null) === 'ffmpeg', 2);
    }

    public function test_public_location_suggestions_endpoint_chunks_ranked_video_frames_across_multiple_openai_requests(): void
    {
        Config::set('services.openai.api_key', 'openai-test-key');
        Config::set('services.openai.model', 'gpt-4o');
        Config::set('services.google_places.api_key', 'google-test-key');
        Config::set('location_suggestions.video_processing.enabled', true);
        Config::set('location_suggestions.video_processing.yt_dlp_path', 'yt-dlp');
        Config::set('location_suggestions.video_processing.ffmpeg_path', 'ffmpeg');
        Config::set('location_suggestions.video_processing.max_video_seconds', 45);
        Config::set('location_suggestions.video_processing.max_frames', 8);
        Config::set('location_suggestions.video_processing.frame_divisor_seconds', 2);
        Config::set('location_suggestions.openai.video_chunk_size', 8);
        Config::set('location_suggestions.openai.chunked_video_image_detail', 'low');

        $openAiImageCounts = [];
        $transcriptPromptPresence = [];

        Http::fake(function (Request $request) use (&$openAiImageCounts, &$transcriptPromptPresence) {
            $payload = $request->data();
            $textQuery = is_array($payload) ? ($payload['textQuery'] ?? null) : null;

            if ($request->url() === 'https://www.tiktok.com/oembed?url='.urlencode('https://www.tiktok.com/@adzhoc/video/7436136693327039777?lang=en')) {
                return Http::response([
                    'title' => 'Top 10 Things To See in Berlin',
                    'author_name' => 'adzhoc',
                    'thumbnail_url' => 'https://cdn.example.com/tiktok-thumb.jpg',
                ]);
            }

            if ($request->url() === 'https://cdn.example.com/tiktok-thumb.jpg') {
                return Http::response('fake-image', 200, [
                    'Content-Type' => 'image/jpeg',
                ]);
            }

            if ($request->url() === 'https://www.tiktok.com/@adzhoc/video/7436136693327039777?lang=en') {
                return Http::response('
                    <html>
                        <head>
                            <meta property="og:title" content="Top 10 Things To See in Berlin" />
                        </head>
                        <body>
                            Top 10 Things To See in Berlin #berlin #top10 #travel
                        </body>
                    </html>
                ');
            }

            if (str_ends_with($request->url(), '/audio/transcriptions')) {
                return Http::response([
                    'text' => '',
                ]);
            }

            if (str_ends_with($request->url(), '/chat/completions')) {
                $messages = $payload['messages'] ?? [];
                $userMessage = $messages[1]['content'] ?? [];
                $imageEntries = array_values(array_filter(
                    is_array($userMessage) ? $userMessage : [],
                    fn ($item): bool => is_array($item) && (($item['type'] ?? null) === 'image_url')
                ));
                $summaryText = (string) data_get($userMessage, '0.text', '');

                $openAiImageCounts[] = count($imageEntries);
                $transcriptPromptPresence[] = str_contains($summaryText, 'Transcript:');
                $this->assertTrue(collect($imageEntries)->every(
                    fn (array $entry): bool => (($entry['image_url']['detail'] ?? null) === 'low')
                ));

                $callNumber = count($openAiImageCounts);
                $places = match ($callNumber) {
                    1 => [[
                        'place' => 'Berlin TV Tower',
                        'category' => 'Landmark',
                        'city' => 'Berlin',
                        'country' => 'Germany',
                        'confidence' => '89%',
                        'lat' => 0,
                        'lng' => 0,
                        'reason' => 'The video frame overlay text reads TV TOWER.',
                    ]],
                    2 => [[
                        'place' => 'Gendarmenmarkt',
                        'category' => 'Square',
                        'city' => 'Berlin',
                        'country' => 'Germany',
                        'confidence' => '87%',
                        'lat' => 0,
                        'lng' => 0,
                        'reason' => 'The video frame overlay text reads GENDARMENMARKT.',
                    ]],
                    default => [[
                        'place' => 'Reichstag Building',
                        'category' => 'Landmark',
                        'city' => 'Berlin',
                        'country' => 'Germany',
                        'confidence' => '88%',
                        'lat' => 0,
                        'lng' => 0,
                        'reason' => 'The video frame overlay text reads REICHSTAG BUILDING.',
                    ]],
                };

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'query' => 'Top 10 Things To See in Berlin',
                                    'places' => $places,
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ]);
            }

            if (str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Berlin TV Tower, Berlin, Germany') {
                return Http::response([
                    'places' => [
                        [
                            'id' => 'google_place_tv_tower',
                            'displayName' => ['text' => 'Berlin TV Tower'],
                            'formattedAddress' => 'Berlin, Germany',
                            'location' => ['latitude' => 52.5208, 'longitude' => 13.4094],
                            'photos' => [['name' => 'places/google_place_tv_tower/photos/photo_1']],
                            'types' => ['tourist_attraction'],
                            'primaryType' => 'tourist_attraction',
                            'primaryTypeDisplayName' => ['text' => 'Tourist attraction'],
                        ],
                    ],
                ]);
            }

            if (str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Gendarmenmarkt, Berlin, Germany') {
                return Http::response([
                    'places' => [
                        [
                            'id' => 'google_place_gendarmenmarkt',
                            'displayName' => ['text' => 'Gendarmenmarkt'],
                            'formattedAddress' => 'Berlin, Germany',
                            'location' => ['latitude' => 52.5138, 'longitude' => 13.3928],
                            'types' => ['tourist_attraction'],
                            'primaryType' => 'tourist_attraction',
                            'primaryTypeDisplayName' => ['text' => 'Tourist attraction'],
                        ],
                    ],
                ]);
            }

            if (str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Reichstag Building, Berlin, Germany') {
                return Http::response([
                    'places' => [
                        [
                            'id' => 'google_place_reichstag',
                            'displayName' => ['text' => 'Reichstag Building'],
                            'formattedAddress' => 'Berlin, Germany',
                            'location' => ['latitude' => 52.5186, 'longitude' => 13.3762],
                            'types' => ['tourist_attraction'],
                            'primaryType' => 'tourist_attraction',
                            'primaryTypeDisplayName' => ['text' => 'Tourist attraction'],
                        ],
                    ],
                ]);
            }

            return Http::response([], 404);
        });

        Process::fake(function ($process) {
            $command = is_array($process->command) ? $process->command : [$process->command];
            $binary = $command[0] ?? null;
            $lastArgument = (string) ($command[array_key_last($command)] ?? '');

            if ($binary === 'yt-dlp') {
                if (in_array('--dump-single-json', $command, true)) {
                    return Process::result(json_encode([
                        'duration' => 40,
                    ], JSON_THROW_ON_ERROR));
                }

                $outputIndex = array_search('--output', $command, true);
                $template = is_int($outputIndex) ? (string) ($command[$outputIndex + 1] ?? '') : '';
                $videoPath = str_replace('.%(ext)s', '.mp4', $template);

                if ($videoPath !== '') {
                    file_put_contents($videoPath, 'fake-video');
                }

                return Process::result();
            }

            if ($binary === 'ffmpeg' && str_contains($lastArgument, 'frame-%03d.jpg')) {
                $framesDir = dirname($lastArgument);

                for ($index = 1; $index <= 20; $index++) {
                    file_put_contents($framesDir.DIRECTORY_SEPARATOR.'frame-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT).'.jpg', 'frame-'.$index);
                }

                return Process::result();
            }

            if ($binary === 'ffmpeg' && str_ends_with($lastArgument, 'audio.mp3')) {
                file_put_contents($lastArgument, 'fake-audio');

                return Process::result();
            }

            return Process::result('', '', 1);
        });

        $response = $this->postJson('/api/v1/public/location-suggestions', [
            'input' => 'https://www.tiktok.com/@adzhoc/video/7436136693327039777?lang=en',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.query', 'Top 10 Things To See in Berlin')
            ->assertJsonPath('data.analysis_debug.video_duration_seconds', 40)
            ->assertJsonPath('data.analysis_debug.frame_target_count', 20)
            ->assertJsonPath('data.analysis_debug.frames_extracted', 20)
            ->assertJsonPath('data.analysis_debug.openai_images_sent', 21)
            ->assertJsonPath('data.analysis_debug.openai_request_count', 3)
            ->assertJsonPath('data.analysis_debug.openai_image_detail', 'low')
            ->assertJsonPath('data.analysis_debug.openai_chunked', true)
            ->assertJsonCount(3, 'data.places')
            ->assertJsonPath('data.places.0.place', 'Berlin TV Tower')
            ->assertJsonPath('data.places.1.place', 'Gendarmenmarkt')
            ->assertJsonPath('data.places.2.place', 'Reichstag Building')
            ->json();

        $this->assertSame([7, 7, 7], data_get($response, 'data.analysis_debug.openai_chunk_image_counts'));
        $this->assertSame([false, false, false], $transcriptPromptPresence);
        $this->assertSame([7, 7, 7], $openAiImageCounts);
    }

    public function test_public_location_suggestions_endpoint_retries_video_download_with_fallback_strategy(): void
    {
        Config::set('services.openai.api_key', 'openai-test-key');
        Config::set('services.openai.model', 'gpt-4o');
        Config::set('services.google_places.api_key', 'google-test-key');
        Config::set('location_suggestions.video_processing.enabled', true);
        Config::set('location_suggestions.video_processing.yt_dlp_path', 'yt-dlp');
        Config::set('location_suggestions.video_processing.ffmpeg_path', 'ffmpeg');
        Config::set('location_suggestions.video_processing.yt_dlp_js_runtimes', 'node');

        $cookiesPath = storage_path('framework/testing/youtube-cookies.txt');
        File::ensureDirectoryExists(dirname($cookiesPath));
        file_put_contents($cookiesPath, "# Netscape HTTP Cookie File\n");
        Config::set('location_suggestions.video_processing.yt_dlp_cookies_path', $cookiesPath);

        Http::fake(function (Request $request) {
            $payload = $request->data();
            $textQuery = is_array($payload) ? ($payload['textQuery'] ?? null) : null;

            return match (true) {
                $request->url() === 'https://www.youtube.com/oembed?url='.urlencode('https://youtube.com/shorts/fallback-download-test?si=abc').'&format=json' => Http::response([
                    'title' => 'Top 10 places in Kuala Lumpur in 2 days',
                    'author_name' => 'Travel and Tales by Kiran',
                    'thumbnail_url' => 'https://i.ytimg.com/vi/test-short/hq2.jpg',
                ]),
                $request->url() === 'https://i.ytimg.com/vi/test-short/hq2.jpg' => Http::response('fake-image', 200, [
                    'Content-Type' => 'image/jpeg',
                ]),
                $request->url() === 'https://youtube.com/shorts/fallback-download-test?si=abc' => Http::response('
                    <html>
                        <head>
                            <meta property="og:title" content="Top 10 places in Kuala Lumpur in 2 days" />
                            <meta property="og:description" content="A short travel reel." />
                        </head>
                        <body>
                            Top 10 places in Kuala Lumpur in 2 days
                        </body>
                    </html>
                '),
                str_ends_with($request->url(), '/audio/transcriptions') => Http::response([
                    'text' => '',
                ]),
                str_ends_with($request->url(), '/chat/completions') => Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'query' => 'Top 10 places in Kuala Lumpur in 2 days',
                                    'places' => [
                                        [
                                            'place' => 'Jalan Alor',
                                            'category' => 'Street',
                                            'city' => 'Kuala Lumpur',
                                            'country' => 'Malaysia',
                                            'confidence' => '80%',
                                            'lat' => 0,
                                            'lng' => 0,
                                            'reason' => 'Video frames show Jalan Alor signage.',
                                        ],
                                        [
                                            'place' => 'Petronas Towers',
                                            'category' => 'Landmark',
                                            'city' => 'Kuala Lumpur',
                                            'country' => 'Malaysia',
                                            'confidence' => '82%',
                                            'lat' => 0,
                                            'lng' => 0,
                                            'reason' => 'Video frames show the twin towers.',
                                        ],
                                    ],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ]),
                str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Jalan Alor, Kuala Lumpur, Malaysia' => Http::response([
                    'places' => [
                        [
                            'id' => 'jalan_alor_place_1',
                            'displayName' => ['text' => 'Jalan Alor'],
                            'formattedAddress' => 'Kuala Lumpur, Malaysia',
                            'location' => ['latitude' => 3.1459204, 'longitude' => 101.7089731],
                            'types' => ['tourist_attraction'],
                            'primaryType' => 'tourist_attraction',
                            'primaryTypeDisplayName' => ['text' => 'Tourist attraction'],
                        ],
                    ],
                ]),
                str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Petronas Towers, Kuala Lumpur, Malaysia' => Http::response([
                    'places' => [
                        [
                            'id' => 'petronas_place_1',
                            'displayName' => ['text' => 'Petronas Twin Towers'],
                            'formattedAddress' => 'Kuala Lumpur, Malaysia',
                            'location' => ['latitude' => 3.1579, 'longitude' => 101.7116],
                            'types' => ['tourist_attraction'],
                            'primaryType' => 'tourist_attraction',
                            'primaryTypeDisplayName' => ['text' => 'Tourist attraction'],
                        ],
                    ],
                ]),
                default => Http::response([], 404),
            };
        });

        $ytDlpDownloadAttempts = 0;

        Process::fake(function ($process) use (&$ytDlpDownloadAttempts) {
            $command = is_array($process->command) ? $process->command : [$process->command];
            $binary = $command[0] ?? null;
            $lastArgument = (string) ($command[array_key_last($command)] ?? '');

            if ($binary === 'yt-dlp') {
                if (in_array('--dump-single-json', $command, true)) {
                    return Process::result(json_encode([
                        'duration' => 18,
                    ], JSON_THROW_ON_ERROR));
                }

                $ytDlpDownloadAttempts++;
                $outputIndex = array_search('--output', $command, true);
                $template = is_int($outputIndex) ? (string) ($command[$outputIndex + 1] ?? '') : '';
                $videoPath = str_replace('.%(ext)s', '.mp4', $template);

                if ($ytDlpDownloadAttempts === 1) {
                    return Process::result('', 'Requested format is not available', 1);
                }

                if ($videoPath !== '') {
                    file_put_contents($videoPath, 'fake-video');
                }

                return Process::result();
            }

            if ($binary === 'ffmpeg' && str_contains($lastArgument, 'frame-%03d.jpg')) {
                $framesDir = dirname($lastArgument);

                for ($index = 1; $index <= 4; $index++) {
                    file_put_contents($framesDir.DIRECTORY_SEPARATOR.'frame-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT).'.jpg', 'frame-'.$index);
                }

                return Process::result();
            }

            if ($binary === 'ffmpeg' && str_ends_with($lastArgument, 'audio.mp3')) {
                file_put_contents($lastArgument, 'fake-audio');

                return Process::result();
            }

            return Process::result('', '', 1);
        });

        $response = $this->postJson('/api/v1/public/location-suggestions', [
            'input' => 'https://youtube.com/shorts/fallback-download-test?si=abc',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.analysis_debug.video_download_succeeded', true)
            ->assertJsonPath('data.analysis_debug.frames_extracted', 4)
            ->assertJsonCount(2, 'data.places')
            ->json();

        $this->assertSame(2, $ytDlpDownloadAttempts);
        $this->assertSame('Jalan Alor', data_get($response, 'data.places.0.place'));
        $this->assertSame('Petronas Towers', data_get($response, 'data.places.1.place'));
        Process::assertRan(function ($process) use ($cookiesPath): bool {
            $command = is_array($process->command) ? $process->command : [$process->command];

            return ($command[0] ?? null) === 'yt-dlp'
                && in_array('--js-runtimes', $command, true)
                && in_array('node', $command, true)
                && in_array('--cookies', $command, true)
                && in_array($cookiesPath, $command, true);
        });

        @unlink($cookiesPath);
    }

    public function test_public_location_suggestions_endpoint_balances_ranked_video_chunks_to_avoid_tiny_tail_batches(): void
    {
        Config::set('services.openai.api_key', 'openai-test-key');
        Config::set('services.openai.model', 'gpt-4o');
        Config::set('services.google_places.api_key', '');
        Config::set('location_suggestions.video_processing.enabled', true);
        Config::set('location_suggestions.video_processing.yt_dlp_path', 'yt-dlp');
        Config::set('location_suggestions.video_processing.ffmpeg_path', 'ffmpeg');
        Config::set('location_suggestions.openai.video_chunk_size', 8);

        $openAiImageCounts = [];

        Http::fake(function (Request $request) use (&$openAiImageCounts) {
            if ($request->url() === 'https://www.instagram.com/oembed/?url='.urlencode('https://www.instagram.com/reel/test-ranked-nine-frames/')) {
                return Http::response([
                    'title' => 'Top 5 Places to Visit in North in Spring',
                    'author_name' => 'abrar_khawja',
                    'thumbnail_url' => 'https://cdn.example.com/instagram-thumb.jpg',
                ]);
            }

            if ($request->url() === 'https://cdn.example.com/instagram-thumb.jpg') {
                return Http::response('fake-image', 200, [
                    'Content-Type' => 'image/jpeg',
                ]);
            }

            if ($request->url() === 'https://www.instagram.com/reel/test-ranked-nine-frames/') {
                return Http::response('
                    <html>
                        <head>
                            <meta property="og:title" content="Top 5 Places to Visit in North in Spring" />
                            <meta property="og:description" content="A spring travel reel." />
                        </head>
                        <body>
                            Top 5 Places to Visit in North in Spring
                        </body>
                    </html>
                ');
            }

            if (str_ends_with($request->url(), '/audio/transcriptions')) {
                return Http::response([
                    'text' => '',
                ]);
            }

            if (str_ends_with($request->url(), '/chat/completions')) {
                $payload = $request->data();
                $messages = $payload['messages'] ?? [];
                $userMessage = $messages[1]['content'] ?? [];
                $imageEntries = array_values(array_filter(
                    is_array($userMessage) ? $userMessage : [],
                    fn ($item): bool => is_array($item) && (($item['type'] ?? null) === 'image_url')
                ));

                $openAiImageCounts[] = count($imageEntries);

                $callNumber = count($openAiImageCounts);
                $places = match ($callNumber) {
                    1 => [
                        [
                            'place' => 'Khaplu Valley',
                            'category' => 'Valley',
                            'city' => 'Skardu',
                            'country' => 'Pakistan',
                            'confidence' => '90%',
                            'lat' => 0,
                            'lng' => 0,
                            'reason' => 'Overlay text identifies Khaplu Valley.',
                        ],
                        [
                            'place' => 'Baghardo, Kachura',
                            'category' => 'Area',
                            'city' => 'Skardu',
                            'country' => 'Pakistan',
                            'confidence' => '88%',
                            'lat' => 0,
                            'lng' => 0,
                            'reason' => 'Overlay text identifies Baghardo, Kachura.',
                        ],
                        [
                            'place' => 'Chunda Valley',
                            'category' => 'Valley',
                            'city' => 'Skardu',
                            'country' => 'Pakistan',
                            'confidence' => '87%',
                            'lat' => 0,
                            'lng' => 0,
                            'reason' => 'Overlay text identifies Chunda Valley.',
                        ],
                    ],
                    default => [
                        [
                            'place' => 'Shigar Valley',
                            'category' => 'Valley',
                            'city' => 'Skardu',
                            'country' => 'Pakistan',
                            'confidence' => '89%',
                            'lat' => 0,
                            'lng' => 0,
                            'reason' => 'Overlay text identifies Shigar Valley.',
                        ],
                        [
                            'place' => 'Sadpara Lake',
                            'category' => 'Lake',
                            'city' => 'Skardu',
                            'country' => 'Pakistan',
                            'confidence' => '86%',
                            'lat' => 0,
                            'lng' => 0,
                            'reason' => 'Overlay text identifies Sadpara Lake.',
                        ],
                    ],
                };

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'query' => 'Top 5 Places to Visit in North in Spring',
                                    'places' => $places,
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ]);
            }

            return Http::response([], 404);
        });

        Process::fake(function ($process) {
            $command = is_array($process->command) ? $process->command : [$process->command];
            $binary = $command[0] ?? null;
            $lastArgument = (string) ($command[array_key_last($command)] ?? '');

            if ($binary === 'yt-dlp') {
                if (in_array('--dump-single-json', $command, true)) {
                    return Process::result(json_encode([
                        'duration' => 18,
                    ], JSON_THROW_ON_ERROR));
                }

                $outputIndex = array_search('--output', $command, true);
                $template = is_int($outputIndex) ? (string) ($command[$outputIndex + 1] ?? '') : '';
                $videoPath = str_replace('.%(ext)s', '.mp4', $template);

                if ($videoPath !== '') {
                    file_put_contents($videoPath, 'fake-video');
                }

                return Process::result();
            }

            if ($binary === 'ffmpeg' && str_contains($lastArgument, 'frame-%03d.jpg')) {
                $framesDir = dirname($lastArgument);

                for ($index = 1; $index <= 9; $index++) {
                    file_put_contents($framesDir.DIRECTORY_SEPARATOR.'frame-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT).'.jpg', 'frame-'.$index);
                }

                return Process::result();
            }

            if ($binary === 'ffmpeg' && str_ends_with($lastArgument, 'audio.mp3')) {
                file_put_contents($lastArgument, 'fake-audio');

                return Process::result();
            }

            return Process::result('', '', 1);
        });

        $response = $this->postJson('/api/v1/public/location-suggestions', [
            'input' => 'https://www.instagram.com/reel/test-ranked-nine-frames/',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.analysis_debug.video_duration_seconds', 18)
            ->assertJsonPath('data.analysis_debug.openai_request_count', 2)
            ->assertJsonCount(5, 'data.places')
            ->json();

        $this->assertSame([5, 4], data_get($response, 'data.analysis_debug.openai_chunk_image_counts'));
        $this->assertSame([5, 4], $openAiImageCounts);
    }

    public function test_public_location_suggestions_endpoint_ignores_tiktok_tracking_image_urls(): void
    {
        Config::set('services.openai.api_key', 'openai-test-key');
        Config::set('services.openai.model', 'gpt-4o');
        Config::set('services.google_places.api_key', 'google-test-key');
        Config::set('location_suggestions.video_processing.enabled', false);

        Http::fake(function (Request $request) {
            if ($request->url() === 'https://www.tiktok.com/oembed?url='.urlencode('https://www.tiktok.com/@adzhoc/video/7436136693327039777?lang=en')) {
                return Http::response([
                    'title' => 'Beautiful Places',
                    'author_name' => 'adzhoc',
                    'thumbnail_url' => 'https://cdn.example.com/tiktok-thumb.jpg',
                ]);
            }

            if ($request->url() === 'https://cdn.example.com/tiktok-thumb.jpg') {
                return Http::response('fake-image', 200, [
                    'Content-Type' => 'image/jpeg',
                ]);
            }

            if ($request->url() === 'https://www.tiktok.com/@adzhoc/video/7436136693327039777?lang=en') {
                return Http::response('
                    <html>
                        <head>
                            <meta property="og:title" content="Beautiful Places" />
                        </head>
                        <body>
                            <img src="https://www.tiktok.com/node/extra/api/monitor/collect?event=open_app_popup_header" />
                        </body>
                    </html>
                ');
            }

            if (str_ends_with($request->url(), '/chat/completions')) {
                $payload = $request->data();
                $messages = $payload['messages'] ?? [];
                $userMessage = $messages[1]['content'] ?? [];
                $imageEntries = array_values(array_filter(
                    is_array($userMessage) ? $userMessage : [],
                    fn ($item): bool => is_array($item) && (($item['type'] ?? null) === 'image_url')
                ));

                $this->assertCount(1, $imageEntries);
                $this->assertStringStartsWith('data:image/', (string) data_get($imageEntries, '0.image_url.url', ''));

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'query' => 'Beautiful Places',
                                    'places' => [],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ]);
            }

            return Http::response([], 404);
        });

        $this->postJson('/api/v1/public/location-suggestions', [
            'input' => 'https://www.tiktok.com/@adzhoc/video/7436136693327039777?lang=en',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.query', 'Beautiful Places')
            ->assertJsonPath('data.metadata.platform', 'tiktok');
    }

    public function test_public_location_suggestions_endpoint_rejects_google_place_mismatches(): void
    {
        Config::set('services.openai.api_key', 'openai-test-key');
        Config::set('services.openai.model', 'gpt-4o');
        Config::set('services.google_places.api_key', 'google-test-key');

        Http::fake(function (Request $request) {
            $payload = $request->data();
            $textQuery = is_array($payload) ? ($payload['textQuery'] ?? null) : null;

            return match (true) {
                $request->url() === 'https://example.com/heavens-gate' => Http::response('
                    <html>
                        <head>
                            <meta property="og:title" content="Heaven\'s Gate China" />
                            <meta property="og:description" content="A scenic landmark in China." />
                        </head>
                        <body>
                            Heaven\'s Gate in China.
                        </body>
                    </html>
                '),
                str_ends_with($request->url(), '/chat/completions') => Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'query' => 'Heaven\'s Gate China',
                                    'places' => [
                                        [
                                            'place' => 'Heaven\'s Gate',
                                            'category' => 'Natural Landmark',
                                            'city' => 'Zhangjiajie',
                                            'country' => 'China',
                                            'confidence' => '86%',
                                            'lat' => 0,
                                            'lng' => 0,
                                            'reason' => 'The title identifies Heaven\'s Gate in China.',
                                        ],
                                    ],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ]),
                str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Heaven\'s Gate, Zhangjiajie, China' => Http::response([
                    'places' => [
                        [
                            'id' => 'hotel_place_1',
                            'displayName' => ['text' => 'Heaven Gate Hotel Zhangjiajie'],
                            'formattedAddress' => 'Zhangjiajie, Hunan, China',
                            'location' => [
                                'latitude' => 29.3594,
                                'longitude' => 110.4630,
                            ],
                            'photos' => [
                                ['name' => 'places/hotel_place_1/photos/photo_1'],
                            ],
                            'types' => ['lodging', 'hotel'],
                            'primaryType' => 'lodging',
                            'primaryTypeDisplayName' => ['text' => 'Lodging'],
                        ],
                    ],
                ]),
                str_contains($request->url(), '/hotel_place_1/photos/photo_1/media') => Http::response([
                    'photoUri' => 'https://cdn.example.com/hotel.jpg',
                ]),
                default => Http::response([], 404),
            };
        });

        $this->postJson('/api/v1/public/location-suggestions', [
            'input' => 'https://example.com/heavens-gate',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.places.0.place', 'Heaven\'s Gate')
            ->assertJsonPath('data.places.0.lat', 0)
            ->assertJsonPath('data.places.0.lng', 0)
            ->assertJsonPath('data.places.0.google_place_details', null);
    }

    public function test_public_location_suggestions_endpoint_deduplicates_similar_places_and_uses_later_google_match(): void
    {
        Config::set('services.openai.api_key', 'openai-test-key');
        Config::set('services.openai.model', 'gpt-4o');
        Config::set('services.google_places.api_key', 'google-test-key');

        Http::fake(function (Request $request) {
            $payload = $request->data();
            $textQuery = is_array($payload) ? ($payload['textQuery'] ?? null) : null;

            return match (true) {
                $request->url() === 'https://example.com/heavens-gate-duplicate' => Http::response('
                    <html>
                        <head>
                            <meta property="og:title" content="Heaven\'s Gate China" />
                            <meta property="og:description" content="A scenic landmark in China." />
                        </head>
                        <body>
                            Heaven\'s Gate in China.
                        </body>
                    </html>
                '),
                str_ends_with($request->url(), '/chat/completions') => Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'query' => 'Heaven\'s Gate China',
                                    'places' => [
                                        [
                                            'place' => 'Heaven Gate',
                                            'category' => 'Natural Landmark',
                                            'city' => 'Zhangjiajie',
                                            'country' => 'China',
                                            'confidence' => '90%',
                                            'lat' => 0,
                                            'lng' => 0,
                                            'reason' => 'The frames mention Heaven Gate China.',
                                        ],
                                        [
                                            'place' => 'Heaven\'s Gate',
                                            'category' => 'Natural Landmark',
                                            'city' => 'Zhangjiajie',
                                            'country' => 'China',
                                            'confidence' => '80%',
                                            'lat' => 0,
                                            'lng' => 0,
                                            'reason' => 'The title identifies Heaven\'s Gate in China.',
                                        ],
                                    ],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ]),
                str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Heaven\'s Gate, Zhangjiajie, China' => Http::response([
                    'places' => [
                        [
                            'id' => 'hotel_place_1',
                            'displayName' => ['text' => 'Heaven Gate Hotel Zhangjiajie'],
                            'formattedAddress' => 'Zhangjiajie, Hunan, China',
                            'location' => [
                                'latitude' => 29.3594,
                                'longitude' => 110.4630,
                            ],
                            'photos' => [
                                ['name' => 'places/hotel_place_1/photos/photo_1'],
                            ],
                            'types' => ['lodging', 'hotel'],
                            'primaryType' => 'lodging',
                            'primaryTypeDisplayName' => ['text' => 'Lodging'],
                        ],
                        [
                            'id' => 'landmark_place_1',
                            'displayName' => ['text' => 'Heaven\'s Gate'],
                            'formattedAddress' => 'Tianmen Mountain, Zhangjiajie, Hunan, China',
                            'location' => [
                                'latitude' => 29.0522,
                                'longitude' => 110.4786,
                            ],
                            'photos' => [
                                ['name' => 'places/landmark_place_1/photos/photo_2'],
                            ],
                            'types' => ['tourist_attraction', 'point_of_interest'],
                            'primaryType' => 'tourist_attraction',
                            'primaryTypeDisplayName' => ['text' => 'Tourist attraction'],
                        ],
                    ],
                ]),
                str_contains($request->url(), '/hotel_place_1/photos/photo_1/media') => Http::response([
                    'photoUri' => 'https://cdn.example.com/hotel.jpg',
                ]),
                str_contains($request->url(), '/landmark_place_1/photos/photo_2/media') => Http::response([
                    'photoUri' => 'https://cdn.example.com/heavens-gate.jpg',
                ]),
                default => Http::response([], 404),
            };
        });

        $this->postJson('/api/v1/public/location-suggestions', [
            'input' => 'https://example.com/heavens-gate-duplicate',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.places')
            ->assertJsonPath('data.places.0.place', 'Heaven\'s Gate')
            ->assertJsonPath('data.places.0.confidence', '90%')
            ->assertJsonPath('data.places.0.lat', 29.0522)
            ->assertJsonPath('data.places.0.lng', 110.4786)
            ->assertJsonPath('data.places.0.google_place_details.id', 'landmark_place_1')
            ->assertJsonPath('data.places.0.google_place_details.place', 'Heaven\'s Gate')
            ->assertJsonPath('data.places.0.google_place_details.image', 'https://cdn.example.com/heavens-gate.jpg');
    }

    public function test_public_location_suggestions_endpoint_uses_alias_lookup_queries_for_canonical_landmarks(): void
    {
        Config::set('services.openai.api_key', 'openai-test-key');
        Config::set('services.openai.model', 'gpt-4o');
        Config::set('services.google_places.api_key', 'google-test-key');

        Http::fake(function (Request $request) {
            $payload = $request->data();
            $textQuery = is_array($payload) ? ($payload['textQuery'] ?? null) : null;

            return match (true) {
                $request->url() === 'https://example.com/heavens-gate-alias' => Http::response('
                    <html>
                        <head>
                            <meta property="og:title" content="Heaven\'s Gate China" />
                            <meta property="og:description" content="A scenic landmark in China." />
                        </head>
                        <body>
                            Heaven\'s Gate in China.
                        </body>
                    </html>
                '),
                str_ends_with($request->url(), '/chat/completions') => Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'query' => 'Heaven\'s Gate China',
                                    'places' => [
                                        [
                                            'place' => 'Heaven\'s Gate',
                                            'category' => 'Natural Landmark',
                                            'city' => 'Zhangjiajie',
                                            'country' => 'China',
                                            'confidence' => '91%',
                                            'lat' => 0,
                                            'lng' => 0,
                                            'reason' => 'The title identifies Heaven\'s Gate in China.',
                                        ],
                                    ],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ]),
                str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Heaven\'s Gate, Zhangjiajie, China' => Http::response([
                    'places' => [],
                ]),
                str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Tianmen Cave, Zhangjiajie, China' => Http::response([
                    'places' => [
                        [
                            'id' => 'tianmen_cave_place_1',
                            'displayName' => ['text' => 'Tianmen Cave'],
                            'formattedAddress' => 'Tianmen Mountain, Zhangjiajie, Hunan, China',
                            'location' => [
                                'latitude' => 29.0522,
                                'longitude' => 110.4786,
                            ],
                            'photos' => [
                                ['name' => 'places/tianmen_cave_place_1/photos/photo_1'],
                            ],
                            'types' => ['tourist_attraction', 'point_of_interest'],
                            'primaryType' => 'tourist_attraction',
                            'primaryTypeDisplayName' => ['text' => 'Tourist attraction'],
                        ],
                    ],
                ]),
                str_contains($request->url(), '/tianmen_cave_place_1/photos/photo_1/media') => Http::response([
                    'photoUri' => 'https://cdn.example.com/tianmen-cave.jpg',
                ]),
                default => Http::response([], 404),
            };
        });

        $this->postJson('/api/v1/public/location-suggestions', [
            'input' => 'https://example.com/heavens-gate-alias',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.places')
            ->assertJsonPath('data.places.0.place', 'Heaven\'s Gate')
            ->assertJsonPath('data.places.0.lat', 29.0522)
            ->assertJsonPath('data.places.0.lng', 110.4786)
            ->assertJsonPath('data.places.0.google_place_details.id', 'tianmen_cave_place_1')
            ->assertJsonPath('data.places.0.google_place_details.place', 'Tianmen Cave')
            ->assertJsonPath('data.places.0.google_place_details.image', 'https://cdn.example.com/tianmen-cave.jpg');
    }

    public function test_public_location_suggestions_endpoint_uses_alias_lookup_queries_for_descriptive_places(): void
    {
        Config::set('services.openai.api_key', 'openai-test-key');
        Config::set('services.openai.model', 'gpt-4o');
        Config::set('services.google_places.api_key', 'google-test-key');

        Http::fake(function (Request $request) {
            $payload = $request->data();
            $textQuery = is_array($payload) ? ($payload['textQuery'] ?? null) : null;

            return match (true) {
                $request->url() === 'https://example.com/cloud-waterfall' => Http::response('
                    <html>
                        <head>
                            <meta property="og:title" content="Cloud Waterfall Chongqing" />
                            <meta property="og:description" content="A cloud sea in the mountains of Chongqing." />
                        </head>
                        <body>
                            Cloud Waterfall in Chongqing.
                        </body>
                    </html>
                '),
                str_ends_with($request->url(), '/chat/completions') => Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'query' => 'Cloud Waterfall Chongqing',
                                    'places' => [
                                        [
                                            'place' => 'Cloud Waterfall',
                                            'category' => 'Natural Phenomenon',
                                            'city' => 'Chongqing',
                                            'country' => 'China',
                                            'confidence' => '85%',
                                            'lat' => 0,
                                            'lng' => 0,
                                            'reason' => 'The transcript mentions Cloud Waterfall in Chongqing.',
                                        ],
                                    ],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ]),
                str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Cloud Waterfall, Chongqing, China' => Http::response([
                    'places' => [],
                ]),
                str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Jinfoshan Scenic Area, Chongqing, China' => Http::response([
                    'places' => [
                        [
                            'id' => 'jinfoshan_place_1',
                            'displayName' => ['text' => 'Jinfoshan Scenic Area'],
                            'formattedAddress' => 'Nanchuan District, Chongqing, China',
                            'location' => [
                                'latitude' => 29.0128,
                                'longitude' => 107.2440,
                            ],
                            'photos' => [
                                ['name' => 'places/jinfoshan_place_1/photos/photo_1'],
                            ],
                            'types' => ['tourist_attraction', 'park'],
                            'primaryType' => 'tourist_attraction',
                            'primaryTypeDisplayName' => ['text' => 'Tourist attraction'],
                        ],
                    ],
                ]),
                str_contains($request->url(), '/jinfoshan_place_1/photos/photo_1/media') => Http::response([
                    'photoUri' => 'https://cdn.example.com/jinfoshan.jpg',
                ]),
                default => Http::response([], 404),
            };
        });

        $this->postJson('/api/v1/public/location-suggestions', [
            'input' => 'https://example.com/cloud-waterfall',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.places')
            ->assertJsonPath('data.places.0.place', 'Cloud Waterfall')
            ->assertJsonPath('data.places.0.lat', 29.0128)
            ->assertJsonPath('data.places.0.lng', 107.2440)
            ->assertJsonPath('data.places.0.google_place_details.id', 'jinfoshan_place_1')
            ->assertJsonPath('data.places.0.google_place_details.place', 'Jinfoshan Scenic Area')
            ->assertJsonPath('data.places.0.google_place_details.image', 'https://cdn.example.com/jinfoshan.jpg');
    }

    public function test_public_location_suggestions_endpoint_deduplicates_places_that_enrich_to_the_same_google_location(): void
    {
        Config::set('services.openai.api_key', 'openai-test-key');
        Config::set('services.openai.model', 'gpt-4o');
        Config::set('services.google_places.api_key', 'google-test-key');

        Http::fake(function (Request $request) {
            $payload = $request->data();
            $textQuery = is_array($payload) ? ($payload['textQuery'] ?? null) : null;

            return match (true) {
                $request->url() === 'https://example.com/heavens-gate-same-google-place' => Http::response('
                    <html>
                        <head>
                            <meta property="og:title" content="Heaven\'s Gate China" />
                            <meta property="og:description" content="A scenic landmark in China." />
                        </head>
                        <body>
                            Heaven\'s Gate in China.
                        </body>
                    </html>
                '),
                str_ends_with($request->url(), '/chat/completions') => Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'query' => 'Heaven\'s Gate China',
                                    'places' => [
                                        [
                                            'place' => 'Heaven\'s Gate',
                                            'category' => 'Natural Landmark',
                                            'city' => 'Zhangjiajie',
                                            'country' => 'China',
                                            'confidence' => '90%',
                                            'lat' => 0,
                                            'lng' => 0,
                                            'reason' => 'The mountain arch and stairs identify Heaven\'s Gate.',
                                        ],
                                        [
                                            'place' => 'Tianmen Mountain',
                                            'category' => 'Natural Landmark',
                                            'city' => 'Zhangjiajie',
                                            'country' => 'China',
                                            'confidence' => '80%',
                                            'lat' => 0,
                                            'lng' => 0,
                                            'reason' => 'The frames show Tianmen Mountain in Zhangjiajie.',
                                        ],
                                    ],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ]),
                str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Heaven\'s Gate, Zhangjiajie, China' => Http::response([
                    'places' => [
                        [
                            'id' => 'tianmen_mountain_place_1',
                            'displayName' => ['text' => 'Tianmen Mountain'],
                            'formattedAddress' => 'Zhangjiajie, Hunan, China',
                            'location' => [
                                'latitude' => 29.046809,
                                'longitude' => 110.482084,
                            ],
                            'photos' => [
                                ['name' => 'places/tianmen_mountain_place_1/photos/photo_1'],
                            ],
                            'types' => ['tourist_attraction', 'point_of_interest'],
                            'primaryType' => 'tourist_attraction',
                            'primaryTypeDisplayName' => ['text' => 'Tourist attraction'],
                        ],
                    ],
                ]),
                str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Tianmen Mountain, Zhangjiajie, China' => Http::response([
                    'places' => [
                        [
                            'id' => 'tianmen_mountain_place_1',
                            'displayName' => ['text' => 'Tianmen Mountain'],
                            'formattedAddress' => 'Zhangjiajie, Hunan, China',
                            'location' => [
                                'latitude' => 29.046809,
                                'longitude' => 110.482084,
                            ],
                            'photos' => [
                                ['name' => 'places/tianmen_mountain_place_1/photos/photo_1'],
                            ],
                            'types' => ['tourist_attraction', 'point_of_interest'],
                            'primaryType' => 'tourist_attraction',
                            'primaryTypeDisplayName' => ['text' => 'Tourist attraction'],
                        ],
                    ],
                ]),
                str_contains($request->url(), '/tianmen_mountain_place_1/photos/photo_1/media') => Http::response([
                    'photoUri' => 'https://cdn.example.com/tianmen-mountain.jpg',
                ]),
                default => Http::response([], 404),
            };
        });

        $this->postJson('/api/v1/public/location-suggestions', [
            'input' => 'https://example.com/heavens-gate-same-google-place',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.places')
            ->assertJsonPath('data.places.0.place', 'Heaven\'s Gate')
            ->assertJsonPath('data.places.0.lat', 29.046809)
            ->assertJsonPath('data.places.0.lng', 110.482084)
            ->assertJsonPath('data.places.0.google_place_details.id', 'tianmen_mountain_place_1')
            ->assertJsonPath('data.places.0.google_place_details.place', 'Tianmen Mountain')
            ->assertJsonPath('data.places.0.google_place_details.image', 'https://cdn.example.com/tianmen-mountain.jpg');
    }

    public function test_public_location_suggestions_endpoint_sends_multiple_page_images_to_openai(): void
    {
        Config::set('services.openai.api_key', 'openai-test-key');
        Config::set('services.openai.model', 'gpt-4o');
        Config::set('services.google_places.api_key', 'google-test-key');

        Http::fake(function (Request $request) {
            $payload = $request->data();
            $textQuery = is_array($payload) ? ($payload['textQuery'] ?? null) : null;

            if ($request->url() === 'https://example.com/travel-gallery') {
                return Http::response('
                    <html>
                        <head>
                            <meta property="og:title" content="Amazing Turkey Stops" />
                            <meta property="og:image" content="https://example.com/cover.jpg" />
                        </head>
                        <body>
                            <img src="/images/cappadocia.jpg" />
                            <img data-src="https://example.com/images/pamukkale.jpg" />
                            <img srcset="https://example.com/images/ephesus.jpg 1200w, https://example.com/images/ephesus-small.jpg 600w" />
                        </body>
                    </html>
                ');
            }

            if (str_ends_with($request->url(), '/chat/completions')) {
                $messages = $payload['messages'] ?? [];
                $userMessage = $messages[1]['content'] ?? [];
                $imageEntries = array_values(array_filter(
                    is_array($userMessage) ? $userMessage : [],
                    fn ($item): bool => is_array($item) && (($item['type'] ?? null) === 'image_url')
                ));

                $this->assertCount(4, $imageEntries);

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'query' => 'Amazing Turkey Stops',
                                    'places' => [
                                        [
                                            'place' => 'Cappadocia',
                                            'category' => 'Region',
                                            'city' => 'Nevsehir',
                                            'country' => 'Turkey',
                                            'confidence' => '91%',
                                            'lat' => 0,
                                            'lng' => 0,
                                            'reason' => 'Supported by the page gallery and title.',
                                        ],
                                    ],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ]);
            }

            return match (true) {
                str_ends_with($request->url(), '/places:searchText') && $textQuery === 'Cappadocia, Nevsehir, Turkey' => Http::response([
                    'places' => [
                        [
                            'id' => 'google_place_1',
                            'displayName' => ['text' => 'Cappadocia'],
                            'formattedAddress' => 'Nevsehir, Turkey',
                            'location' => ['latitude' => 38.6431, 'longitude' => 34.8270],
                            'photos' => [['name' => 'places/google_place_1/photos/photo_1']],
                            'types' => ['tourist_attraction'],
                            'primaryType' => 'tourist_attraction',
                            'primaryTypeDisplayName' => ['text' => 'Tourist attraction'],
                        ],
                    ],
                ]),
                str_contains($request->url(), '/google_place_1/photos/photo_1/media') => Http::response([
                    'photoUri' => 'https://cdn.example.com/google-place-1.jpg',
                ]),
                default => Http::response([], 404),
            };
        });

        $this->postJson('/api/v1/public/location-suggestions', [
            'input' => 'https://example.com/travel-gallery',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.query', 'Amazing Turkey Stops')
            ->assertJsonPath('data.places.0.place', 'Cappadocia');
    }

    public function test_public_location_suggestions_endpoint_auto_routes_long_videos_to_async(): void
    {
        Config::set('location_suggestions.async.enabled', true);
        Config::set('location_suggestions.async.auto_route_long_videos', true);
        Config::set('location_suggestions.async.auto_route_video_seconds', 45);
        Config::set('location_suggestions.video_processing.yt_dlp_path', 'yt-dlp');
        Config::set('broadcasting.default', 'pusher');
        Config::set('broadcasting.connections.pusher.key', 'pusher-test-key');

        Queue::fake();

        Process::fake(function ($process) {
            $command = is_array($process->command) ? $process->command : [$process->command];

            if (($command[0] ?? null) === 'yt-dlp' && in_array('--dump-single-json', $command, true)) {
                return Process::result(json_encode([
                    'duration' => 120,
                ], JSON_THROW_ON_ERROR));
            }

            return Process::result('', '', 1);
        });

        $response = $this->postJson('/api/v1/public/location-suggestions', [
            'input' => 'https://www.tiktok.com/@worldsecrets360/video/long-video',
        ])
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.query', 'https://www.tiktok.com/@worldsecrets360/video/long-video')
            ->assertJsonPath('data.metadata.platform', 'tiktok')
            ->assertJsonPath('data.async_job.status', 'pending')
            ->assertJsonPath('data.async_job.estimated_duration_seconds', 120)
            ->assertJsonPath('data.async_job.realtime.enabled', true)
            ->assertJsonPath('data.analysis_debug.mode', 'queued')
            ->assertJsonPath('data.analysis_debug.routing_reason', 'auto_long_video')
            ->json();

        $token = data_get($response, 'data.async_job.token');

        $this->assertIsString($token);
        $this->assertNotSame('', $token);
        $this->assertIsString(data_get($response, 'data.async_job.poll_url'));

        Queue::assertPushed(AnalyzeLocationSuggestionsJob::class, function (AnalyzeLocationSuggestionsJob $job) use ($token): bool {
            return $job->token === $token
                && $job->input === 'https://www.tiktok.com/@worldsecrets360/video/long-video';
        });
    }

    public function test_public_async_location_suggestions_endpoint_queues_a_job(): void
    {
        Config::set('location_suggestions.async.enabled', true);
        Config::set('broadcasting.default', 'pusher');
        Config::set('broadcasting.connections.pusher.key', 'pusher-test-key');
        Queue::fake();

        $response = $this->postJson('/api/v1/public/location-suggestions/async', [
            'input' => 'https://example.com/queued-video',
        ])
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.analysis_debug.routing_reason', 'direct_async')
            ->assertJsonPath('data.realtime.enabled', true)
            ->assertJsonPath('data.realtime.provider', 'pusher')
            ->json();

        $token = data_get($response, 'data.token');

        $this->assertIsString($token);
        $this->assertSame('location-suggestions.'.$token, data_get($response, 'data.realtime.channel'));
        $this->assertSame('location-suggestions.processing', data_get($response, 'data.realtime.events.processing'));
        $this->assertSame('location-suggestions.completed', data_get($response, 'data.realtime.events.completed'));
        $this->assertSame('location-suggestions.failed', data_get($response, 'data.realtime.events.failed'));

        Queue::assertPushed(AnalyzeLocationSuggestionsJob::class, function (AnalyzeLocationSuggestionsJob $job) use ($token): bool {
            return $job->token === $token && $job->input === 'https://example.com/queued-video';
        });
    }

    public function test_public_async_location_suggestion_status_endpoint_returns_cached_result(): void
    {
        Config::set('location_suggestions.async.enabled', true);
        Config::set('broadcasting.default', 'pusher');
        Config::set('broadcasting.connections.pusher.key', 'pusher-test-key');

        Cache::put('location-suggestions:async:test-token', [
            'token' => 'test-token',
            'status' => 'completed',
            'input' => 'https://example.com/queued-video',
            'result' => [
                'query' => 'Queued Video',
                'places' => [
                    ['place' => 'Cappadocia'],
                ],
                'metadata' => ['platform' => 'youtube'],
                'analysis_debug' => ['mode' => 'async'],
            ],
            'error' => null,
            'realtime' => [
                'enabled' => true,
                'provider' => 'pusher',
                'channel' => 'location-suggestions.test-token',
                'auth_required' => false,
                'events' => [
                    'processing' => 'location-suggestions.processing',
                    'completed' => 'location-suggestions.completed',
                    'failed' => 'location-suggestions.failed',
                ],
            ],
            'created_at' => now()->subMinute()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ], now()->addHour());

        $this->getJson('/api/v1/public/location-suggestions/async/test-token')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token', 'test-token')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.result.query', 'Queued Video')
            ->assertJsonPath('data.realtime.channel', 'location-suggestions.test-token')
            ->assertJsonPath('data.result.analysis_debug.mode', 'async')
            ->assertJsonPath('data.result.places.0.place', 'Cappadocia');
    }

    public function test_public_async_location_suggestion_status_endpoint_allows_repeated_polling(): void
    {
        Config::set('location_suggestions.async.enabled', true);

        Cache::put('location-suggestions:async:poll-token', [
            'token' => 'poll-token',
            'status' => 'processing',
            'input' => 'https://example.com/poll-video',
            'result' => null,
            'error' => null,
            'created_at' => now()->subMinute()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ], now()->addHour());

        for ($attempt = 1; $attempt <= 25; $attempt++) {
            $this->getJson('/api/v1/public/location-suggestions/async/poll-token')
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.token', 'poll-token')
                ->assertJsonPath('data.status', 'processing');
        }
    }

    public function test_public_location_suggestions_endpoint_allows_repeated_async_submissions_without_using_ai_generation_limit(): void
    {
        Config::set('location_suggestions.async.enabled', true);
        Config::set('location_suggestions.rate_limits.submit_per_minute', 30);
        Config::set('location_suggestions.rate_limits.submit_per_hour', 120);
        Queue::fake();

        for ($attempt = 1; $attempt <= 25; $attempt++) {
            $this->postJson('/api/v1/public/location-suggestions', [
                'input' => 'https://www.tiktok.com/@adzhoc/video/7495788078519373078?lang=en',
                'prefer_async' => true,
            ])
                ->assertStatus(202)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.analysis_debug.routing_reason', 'forced_async');
        }

        Queue::assertPushed(AnalyzeLocationSuggestionsJob::class, 25);
    }

    public function test_analyze_location_suggestions_job_dispatches_realtime_processing_and_completed_events(): void
    {
        Event::fake([LocationSuggestionStatusUpdated::class]);
        Queue::fake();

        $asyncService = app(LocationSuggestionAsyncService::class);
        $payload = $asyncService->create('https://example.com/realtime-video');
        $token = (string) $payload['token'];

        $locationSuggestionsService = \Mockery::mock(LocationSuggestionsService::class);
        $locationSuggestionsService
            ->shouldReceive('getLocations')
            ->once()
            ->with('https://example.com/realtime-video', ['mode' => 'async'])
            ->andReturn([
                'query' => 'Realtime Video',
                'places' => [
                    ['place' => 'Lake Bled'],
                ],
                'metadata' => ['platform' => 'tiktok'],
            ]);

        $job = new AnalyzeLocationSuggestionsJob($token, 'https://example.com/realtime-video');
        $job->handle($locationSuggestionsService, $asyncService);

        Event::assertDispatched(LocationSuggestionStatusUpdated::class, function (LocationSuggestionStatusUpdated $event) use ($token): bool {
            return $event->token === $token
                && $event->status === LocationSuggestionAsyncService::STATUS_PROCESSING;
        });

        Event::assertDispatched(LocationSuggestionStatusUpdated::class, function (LocationSuggestionStatusUpdated $event) use ($token): bool {
            return $event->token === $token
                && $event->status === LocationSuggestionAsyncService::STATUS_COMPLETED
                && $event->error === null;
        });
    }

    public function test_public_location_suggestions_endpoint_validates_required_input(): void
    {
        $this->postJson('/api/v1/public/location-suggestions', [
            'input' => '',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.input.0', 'The input field is required.');
    }
}
