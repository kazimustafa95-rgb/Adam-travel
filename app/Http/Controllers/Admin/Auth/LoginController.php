<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\StoreAdminLoginRequest;
use App\Services\Admin\AdminActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(protected AdminActivityLogService $activityLogService)
    {
    }

    public function create(): View
    {
        return view('admin.auth.login');
    }

    /**
     * @throws ValidationException
     */
    public function store(StoreAdminLoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided admin credentials do not match our records.'],
            ]);
        }

        $request->session()->regenerate();

        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();
        $admin->forceFill(['last_login_at' => now()])->save();

        $this->activityLogService->log(
            admin: $admin,
            action: 'admin.login',
            description: 'Signed in to the admin panel.',
            target: $admin,
        );

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(): RedirectResponse
    {
        Auth::guard('admin')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
