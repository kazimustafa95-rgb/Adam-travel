<?php

namespace App\Http\Controllers\Api\V2\LocationIntelligence;

use App\Exceptions\LocationIntelligence\LocationIntelligenceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LocationIntelligence\ResolveLocationRequest;
use App\Services\LocationIntelligence\LocationIntelligenceOrchestrator;
use Illuminate\Http\JsonResponse;

class LocationIntelligenceController extends Controller
{
    public function __construct(
        private readonly LocationIntelligenceOrchestrator $orchestrator,
    ) {}

    public function resolve(ResolveLocationRequest $request): JsonResponse
    {
        try {
            $result = $this->orchestrator->resolve((string) $request->validated('input'));

            return response()->json([
                'success'          => true,
                'type'             => $result['type'],
                'resolved_place'   => $result['resolved_place'],
                'resolved_places'  => $result['resolved_places'] ?? null,
                'signals'          => $result['signals'],
            ]);
        } catch (LocationIntelligenceException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors'  => $exception->getErrors(),
            ], $exception->getStatusCode());
        }
    }
}
