<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user()?->loadMissing('role');

        if ($user === null || $user->role === null || ! in_array($user->role->name, $roles, true)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
