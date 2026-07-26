<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Notifications\MobileVerifyEmail;
use App\Support\MobileTwoFactorChallenge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Features;

class AuthController extends Controller
{
    public function registrationEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $exists = User::where('email', $validated['email'])->exists();

        return response()->json([
            'data' => [
                'exists' => $exists,
                'message' => $exists
                    ? 'Dieses Konto existiert bereits. Bitte melde dich an oder nutze Passwort vergessen.'
                    : null,
            ],
        ]);
    }

    public function register(Request $request, CreateNewUser $creator)
    {
        abort_unless(Features::enabled(Features::registration()), 404);

        $user = $creator->create($request->all());
        $tokenName = trim((string) $request->input('device_name', '')) ?: 'mobile';
        $user->notify(new MobileVerifyEmail);

        return response()->json([
            'data' => [
                'token' => $user->createToken($tokenName)->plainTextToken,
                'token_type' => 'Bearer',
                'user' => new UserResource($user->loadMissing(['roles', 'permissions'])),
            ],
        ], 201);
    }

    public function login(Request $request, MobileTwoFactorChallenge $challenges)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $user = $request->user() ?? Auth::user();
        $tokenName = $credentials['device_name'] ?? 'mobile';

        if ($user->hasEnabledTwoFactorAuthentication()) {
            Auth::logout();
            $challenge = $challenges->issue($user, $tokenName);

            return response()->json([
                'data' => [
                    'two_factor_required' => true,
                    'challenge_token' => $challenge['token'],
                    'expires_in' => $challenge['expires_in'],
                    'available_methods' => ['authenticator', 'recovery_code'],
                ],
            ], 202);
        }

        return response()->json([
            'data' => [
                'token' => $user->createToken($tokenName)->plainTextToken,
                'token_type' => 'Bearer',
                'user' => new UserResource($user->loadMissing(['roles', 'permissions'])),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'data' => [
                'message' => 'Logged out',
            ],
        ]);
    }
}
