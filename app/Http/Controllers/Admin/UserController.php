<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Http\Requests\Admin\Users\UpdateUserStatusRequest;
use App\Models\User;
use App\Services\Admin\AdminActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends BaseAdminController
{
    public function __construct(protected AdminActivityLogService $activityLogService)
    {
    }

    public function index(Request $request): View
    {
        $users = User::query()
            ->with(['preference', 'subscriptions.plan'])
            ->withCount(['savedPlaces', 'imports', 'ownedTrips'])
            ->when($request->string('q')->toString() !== '', function ($query) use ($request): void {
                $search = $request->string('q')->toString();
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($request->string('status')->toString() !== '', fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'statusOptions' => AccountStatus::values(),
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function show(User $user): View
    {
        $user->load([
            'preference',
            'devices' => fn ($query) => $query->latest('id')->limit(5),
            'subscriptions.plan',
            'supportTickets' => fn ($query) => $query->latest('id')->limit(5),
        ])->loadCount(['savedPlaces', 'imports', 'ownedTrips', 'supportTickets']);

        $recentImports = $user->imports()->latest('id')->limit(5)->get();
        $recentTrips = $user->ownedTrips()->withCount(['members', 'pool'])->latest('id')->limit(5)->get();

        return view('admin.users.show', compact('user', 'recentImports', 'recentTrips'));
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        $previousStatus = $user->status?->value;
        $nextStatus = $request->validated('status');

        $user->forceFill([
            'status' => $nextStatus,
        ])->save();

        if ($nextStatus !== AccountStatus::Active->value) {
            $user->tokens()->delete();
        }

        $this->activityLogService->log(
            admin: $this->admin(),
            action: 'user.status_updated',
            description: 'Updated user status from '.$previousStatus.' to '.$nextStatus.'.',
            target: $user,
            metadata: [
                'previous_status' => $previousStatus,
                'next_status' => $nextStatus,
            ],
        );

        return redirect()
            ->back()
            ->with('status', 'User status updated successfully.');
    }
}
