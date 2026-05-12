<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImportStatus;
use App\Models\AiRequest;
use App\Models\Import;
use App\Services\Admin\AdminActivityLogService;
use App\Services\Imports\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImportController extends BaseAdminController
{
    public function __construct(
        protected ImportService $importService,
        protected AdminActivityLogService $activityLogService,
    ) {
    }

    public function index(Request $request): View
    {
        $imports = Import::query()
            ->with(['user', 'candidates'])
            ->withCount(['savedPlaces', 'candidates'])
            ->when($request->string('q')->toString() !== '', function ($query) use ($request): void {
                $search = $request->string('q')->toString();
                $query->where(function ($builder) use ($search): void {
                    $builder->where('source_url', 'like', '%'.$search.'%')
                        ->orWhere('raw_text', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('email', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->string('status')->toString() !== '', fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.imports.index', [
            'imports' => $imports,
            'statusOptions' => ImportStatus::values(),
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function show(Import $import): View
    {
        $import->load(['user', 'candidates', 'savedPlaces.location']);

        $aiRequests = AiRequest::query()
            ->where('context_type', 'import')
            ->where('context_id', $import->id)
            ->latest('id')
            ->get();

        return view('admin.imports.show', compact('import', 'aiRequests'));
    }

    public function retry(Import $import): RedirectResponse
    {
        $this->importService->retry($import);

        $this->activityLogService->log(
            admin: $this->admin(),
            action: 'import.retried',
            description: 'Retried import processing for import #'.$import->id.'.',
            target: $import,
            metadata: [
                'status' => $import->status?->value,
            ],
        );

        return redirect()
            ->back()
            ->with('status', 'Import retry queued successfully.');
    }
}
