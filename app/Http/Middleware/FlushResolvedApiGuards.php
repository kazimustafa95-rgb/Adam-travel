<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class FlushResolvedApiGuards
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Reset cached guard instances so each API request resolves the bearer token fresh.
        Auth::forgetGuards();

        return $next($request);
    }
}
