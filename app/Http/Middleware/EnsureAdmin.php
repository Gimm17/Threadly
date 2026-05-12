<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Ensure the authenticated user has admin or owner role.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }

            abort(403, 'Unauthorized. Admin access required.');
        }

        return $next($request);
    }
}
