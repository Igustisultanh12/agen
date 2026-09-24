<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\AI\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected QuotaService $quotaService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $throttleKey = 'login:' . Str::lower($request->input('email')) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            AuditLog::log('failed_login_throttled', 'User', null, ['email' => $request->input('email'), 'wait_seconds' => $seconds]);
            return response()->json([
                'error' => 'Too Many Attempts',
                'message' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            AuditLog::log('failed_login', 'User', null, ['email' => $request->input('email')]);
            return response()->json([
                'error' => 'Invalid Credentials',
                'message' => 'The provided email or password is incorrect.',
            ], 422);
        }

        if ($user->isSuspended()) {
            AuditLog::log('login_suspended_attempt', 'User', (string) $user->id, null, $user);
            return response()->json([
                'error' => 'Account Suspended',
                'message' => 'Your account has been suspended by an administrator.',
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        $token = $user->createToken('auth-token', [$user->role])->plainTextToken;

        AuditLog::log('login', 'User', (string) $user->id, null, $user);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'avatar_url' => $user->avatar_url,
                'user_group' => $user->userGroup?->name,
                'custom_instructions' => $user->custom_instructions,
            ],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $defaultGroup = UserGroup::where('is_default', true)->first()
            ?? UserGroup::where('slug', 'free')->first();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
            'status' => 'active',
            'user_group_id' => $defaultGroup?->id,
        ]);

        // Initialize user quota
        $this->quotaService->initializeUserQuota($user);

        $token = $user->createToken('auth-token', [$user->role])->plainTextToken;

        AuditLog::log('register', 'User', (string) $user->id, null, $user);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'avatar_url' => $user->avatar_url,
                'user_group' => $defaultGroup?->name,
                'custom_instructions' => $user->custom_instructions,
            ],
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('userGroup');
        $quota = $this->quotaService->getRemainingQuota($user);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'avatar_url' => $user->avatar_url,
                'user_group' => $user->userGroup ? [
                    'id' => $user->userGroup->id,
                    'name' => $user->userGroup->name,
                    'slug' => $user->userGroup->slug,
                ] : null,
                'custom_instructions' => $user->custom_instructions,
                'preferences' => $user->preferences,
            ],
            'quota' => $quota,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()?->delete();
            AuditLog::log('logout', 'User', (string) $user->id, null, $user);
        }

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'custom_instructions' => 'nullable|string|max:5000',
            'preferences' => 'nullable|array',
        ]);

        $user->update($validated);
        AuditLog::log('update_profile', 'User', (string) $user->id, null, $user);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($validated['password'])]);
        AuditLog::log('change_password', 'User', (string) $user->id, null, $user);

        return response()->json(['message' => 'Password changed successfully']);
    }
}
