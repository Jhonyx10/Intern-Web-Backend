<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSupervisor
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->loadMissing('role');

        if ($user === null || ! $user->hasRole('supervisor')) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
