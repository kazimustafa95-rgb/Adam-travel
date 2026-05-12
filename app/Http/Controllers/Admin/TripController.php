<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TripStatus;
use App\Http\Requests\Admin\Trips\UpdateTripStatusRequest;
use App\Models\Trip;
use App\Services\Admin\AdminActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TripController extends BaseAdminController
{
    public function __construct(protected AdminActivityLogService $activityLogService)
    {
    }

    public function index(Request $request): View
    {
        $trips = Trip::query()
            ->with('owner')
            ->withCount(['members', 'pool', 'itineraryDays', 'suggestions'])
            ->when($request->string('q')->toString() !== '', function ($query) use ($request): void {
                $search = $request->string('q')->toString();
                $query->where(function ($builder) use ($search): void {
                    $builder->where('title', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery->where('email', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->string('status')->toString() !== '', fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.trips.index', [
            'trips' => $trips,
            'statusOptions' => TripStatus::values(),
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function show(Trip $trip): View
    {
        $trip->load([
            'owner',
            'members.user',
            'pool.savedPlace.location',
            'itineraryDays.items.tripPlace.savedPlace.location',
            'aiRuns.requestedBy',
            'offlinePackages',
        ]);

        return view('admin.trips.show', compact('trip'));
    }

    public function update(UpdateTripStatusRequest $request, Trip $trip): RedirectResponse
    {
        $previousStatus = $trip->status?->value;
        $nextStatus = $request->validated('status');

        $trip->forceFill([
            'status' => $nextStatus,
        ])->save();

        $this->activityLogService->log(
            admin: $this->admin(),
            action: 'trip.status_updated',
            description: 'Updated trip status from '.$previousStatus.' to '.$nextStatus.' for "'.$trip->title.'".',
            target: $trip,
            metadata: [
                'previous_status' => $previousStatus,
                'next_status' => $nextStatus,
            ],
        );

        return redirect()
            ->back()
            ->with('status', 'Trip status updated successfully.');
    }
}
