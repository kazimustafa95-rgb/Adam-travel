<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
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
