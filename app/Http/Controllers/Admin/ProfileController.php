<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Profile\UpdateAdminPasswordRequest;
use App\Http\Requests\Admin\Profile\UpdateAdminProfileRequest;
use App\Services\Admin\AdminActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends BaseAdminController
{
    public function __construct(protected AdminActivityLogService $activityLogService)
    {
    }

    public function edit(): View
    {
        return view('admin.profile.edit', [
            'admin' => $this->admin(),
        ]);
    }

    public function update(UpdateAdminProfileRequest $request): RedirectResponse
    {
        $admin = $this->admin();
        $payload = $request->validated();
        $changes = [];

        foreach (['name', 'email'] as $field) {
            if ($admin->{$field} !== $payload[$field]) {
                $changes[$field] = [
                    'from' => $admin->{$field},
                    'to' => $payload[$field],
                ];
            }
        }

        if ($changes === []) {
            return redirect()
                ->route('admin.profile.edit')
                ->with('status', 'Profile settings are already up to date.');
        }

        $admin->forceFill($payload)->save();

        $this->activityLogService->log(
            admin: $admin,
            action: 'admin.profile_updated',
            description: 'Updated admin profile settings.',
            target: $admin,
            metadata: [
                'changes' => $changes,
            ],
        );

        return redirect()
            ->route('admin.profile.edit')
            ->with('status', 'Profile settings updated successfully.');
    }

    public function updatePassword(UpdateAdminPasswordRequest $request): RedirectResponse
    {
        $admin = $this->admin();

        $admin->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        $this->activityLogService->log(
            admin: $admin,
            action: 'admin.password_updated',
            description: 'Updated admin password.',
            target: $admin,
        );

        return redirect()
            ->route('admin.profile.edit')
            ->with('status', 'Password updated successfully.');
    }
}
