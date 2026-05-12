<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use App\Support\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUserAccount
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->status !== AccountStatus::Active) {
            return ApiResponse::error(
                message: 'This account is not currently active.',
                errors: [
                    'account' => ['Your account is suspended or disabled. Please contact support.'],
                ],
                status: 403,
            );
        }

        return $next($request);
    }
}
