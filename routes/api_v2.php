<?php

/*
|--------------------------------------------------------------------------
| API V2 Routes — Location Intelligence Module
|--------------------------------------------------------------------------
|
| This file is loaded by LocationIntelligenceServiceProvider and is
| completely isolated from the existing V1 API routes. No existing
| routes, controllers, or services are affected.
|
| Base URL: POST /api/v2/location-intelligence/resolve
|
*/

use App\Http\Controllers\Api\V2\LocationIntelligence\LocationIntelligenceController;
use Illuminate\Support\Facades\Route;

Route::prefix('location-intelligence')->group(function (): void {

    Route::post('resolve', [LocationIntelligenceController::class, 'resolve'])
        ->middleware('throttle:location-intelligence')
        ->name('api.v2.location-intelligence.resolve');

});
