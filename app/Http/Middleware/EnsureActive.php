<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if ($user->isSuspended()) {
                return response()->json([
                    'error' => 'Account Suspended',
                    'message' => 'Your account has been suspended by an administrator.',
                ], 403);
            }

            if (!$user->isActive()) {
                return response()->json([
                    'error' => 'Account Inactive',
                    'message' => 'Your account is inactive.',
                ], 403);
            }
        }

        return $next($request);
    }
}
