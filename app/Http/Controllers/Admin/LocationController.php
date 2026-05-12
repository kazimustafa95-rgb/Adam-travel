<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Locations\UpdateLocationModerationRequest;
use App\Models\Location;
use App\Services\Admin\AdminActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends BaseAdminController
{
    public function __construct(protected AdminActivityLogService $activityLogService)
    {
    }

    public function index(Request $request): View
    {
        $locations = Location::query()
            ->withCount('savedPlaces')
            ->when($request->string('q')->toString() !== '', function ($query) use ($request): void {
                $search = $request->string('q')->toString();
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', '%'.$search.'%')
                        ->orWhere('city', 'like', '%'.$search.'%')
                        ->orWhere('country_code', 'like', '%'.$search.'%');
                });
            })
            ->when($request->filled('hidden'), fn ($query) => $query->where('is_moderated_hidden', $request->boolean('hidden')))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.locations.index', [
            'locations' => $locations,
            'filters' => $request->only(['q', 'hidden']),
        ]);
    }

    public function update(UpdateLocationModerationRequest $request, Location $location): RedirectResponse
    {
        $location->forceFill([
            'is_moderated_hidden' => $request->boolean('is_moderated_hidden'),
        ])->save();

        $this->activityLogService->log(
            admin: $this->admin(),
            action: 'location.moderation_updated',
            description: ($location->is_moderated_hidden ? 'Hidden' : 'Unhid').' location "'.$location->name.'".',
            target: $location,
            metadata: [
                'is_moderated_hidden' => $location->is_moderated_hidden,
            ],
        );

        return redirect()
            ->back()
            ->with('status', 'Location moderation updated successfully.');
    }
}
