<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next, ?string $requiredPermission = null): Response
    {
        $rawKey = $request->bearerToken() ?: $request->header('x-api-key');

        if (!$rawKey) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Missing API Key in Authorization Bearer or x-api-key header.',
            ], 401);
        }

        $hash = hash('sha256', $rawKey);
        $apiKey = ApiKey::with('user')->where('key_hash', $hash)->where('is_active', true)->first();

        if (!$apiKey) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid or revoked API key.',
            ], 401);
        }

        if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'API key has expired.',
            ], 401);
        }

        if ($requiredPermission && !$apiKey->hasPermission($requiredPermission)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => "API key lacks required permission: {$requiredPermission}",
            ], 403);
        }

        // Touch last used at
        $apiKey->update(['last_used_at' => now()]);

        // Bind user to request
        $request->setUserResolver(fn() => $apiKey->user);
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
