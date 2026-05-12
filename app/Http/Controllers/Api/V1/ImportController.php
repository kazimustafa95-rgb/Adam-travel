<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Imports\ConfirmImportRequest;
use App\Http\Requests\Api\V1\Imports\ManualOverrideImportRequest;
use App\Http\Requests\Api\V1\Imports\StoreImportRequest;
use App\Http\Resources\Api\V1\ImportResource;
use App\Http\Resources\Api\V1\SavedPlaceResource;
use App\Models\Import;
use App\Services\Imports\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends BaseApiController
{
    public function __construct(protected ImportService $importService)
    {
    }

    public function store(StoreImportRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $import = $this->importService->create($user, $request->validated());

        return $this->success(
            data: (new ImportResource($import))->resolve(),
            message: 'Import submitted successfully.',
            status: 201,
        );
    }

    public function show(Request $request, Import $import): JsonResponse
    {
        $this->authorize('view', $import);

        return $this->success(
            data: (new ImportResource($import->load(['candidates', 'savedPlaces'])))->resolve(),
            message: 'Import loaded successfully.',
        );
    }

    public function retry(Request $request, Import $import): JsonResponse
    {
        $this->authorize('update', $import);
        $updatedImport = $this->importService->retry($import);

        return $this->success(
            data: (new ImportResource($updatedImport))->resolve(),
            message: 'Import reprocessing started successfully.',
        );
    }

    public function manualOverride(ManualOverrideImportRequest $request, Import $import): JsonResponse
    {
        $this->authorize('update', $import);
        $updatedImport = $this->importService->manualOverride($import, $request->validated());

        return $this->success(
            data: (new ImportResource($updatedImport))->resolve(),
            message: 'Import candidate updated successfully.',
        );
    }

    public function confirm(ConfirmImportRequest $request, Import $import): JsonResponse
    {
        $this->authorize('update', $import);
        $result = $this->importService->confirm($import, $request->validated());

        return $this->success(
            data: [
                'import' => (new ImportResource($result['import']))->resolve(),
                'saved_place' => (new SavedPlaceResource($result['saved_place']))->resolve(),
            ],
            message: 'Import confirmed and saved successfully.',
        );
    }
}
