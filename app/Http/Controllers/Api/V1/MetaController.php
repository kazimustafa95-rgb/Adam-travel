<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

class MetaController extends BaseApiController
{
    public function __invoke(): JsonResponse
    {
        return $this->success(
            data: [
                'product' => [
                    'name' => config('app.name'),
                    'api_version' => 'v1',
                    'environment' => app()->environment(),
                ],
                'surfaces' => [
                    'mobile_api',
                    'admin_panel',
                ],
                'architecture' => [
                    'auth_guards' => ['web', 'api', 'admin'],
                    'async_ready' => true,
                    'offline_ready' => true,
                    'provider_ready' => [
                        'maps',
                        'geocoding',
                        'ai',
                        'billing',
                    ],
                ],
            ],
            message: 'API metadata loaded successfully.',
        );
    }
}
