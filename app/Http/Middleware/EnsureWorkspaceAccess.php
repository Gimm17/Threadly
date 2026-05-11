<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->workspace_id) {
            abort(403, 'Anda tidak memiliki akses ke workspace manapun.');
        }

        return $next($request);
    }
}
