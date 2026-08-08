<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountSecurityController extends Controller
{
    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('account_security.errors.current_password')],
            ]);
        }

        $user->forceFill(['password' => $data['password']])->save();

        // A password change is a security event: keep the device used for the
        // change signed in and invalidate all other mobile access tokens.
        $currentTokenId = $user->currentAccessToken()?->getKey();
        $user->tokens()
            ->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))
            ->delete();

        return response()->json([
            'data' => [
                'message' => __('account_security.responses.password_updated'),
            ],
        ]);
    }

    public function updateProfilePhoto(Request $request)
    {
        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $request->user()->updateProfilePhoto($data['photo']);

        return response()->json([
            'data' => new UserResource($request->user()->refresh()),
        ]);
    }

    public function destroyProfilePhoto(Request $request)
    {
        $request->user()->deleteProfilePhoto();

        return response()->json([
            'data' => new UserResource($request->user()->refresh()),
        ]);
    }

    public function sessions(Request $request)
    {
        $currentTokenId = $request->user()->currentAccessToken()?->getKey();

        return response()->json([
            'data' => $request->user()->tokens()
                ->latest('last_used_at')
                ->latest('created_at')
                ->get()
                ->map(fn ($token) => [
                    'id' => (int) $token->getKey(),
                    'device_name' => $token->name,
                    'current' => (int) $token->getKey() === (int) $currentTokenId,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'created_at' => $token->created_at?->toIso8601String(),
                ])
                ->values(),
        ]);
    }

    public function destroySession(Request $request, int $token)
    {
        $accessToken = $request->user()->tokens()->findOrFail($token);
        $isCurrent = $accessToken->is($request->user()->currentAccessToken());
        $accessToken->delete();

        return response()->json([
            'data' => [
                'message' => __('account_security.responses.session_ended'),
                'current_session_ended' => $isCurrent,
            ],
        ]);
    }

    public function destroyOtherSessions(Request $request)
    {
        $currentTokenId = $request->user()->currentAccessToken()?->getKey();

        $deleted = $request->user()->tokens()
            ->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))
            ->delete();

        return response()->json([
            'data' => [
                'message' => __('account_security.responses.other_sessions_ended'),
                'deleted_count' => $deleted,
            ],
        ]);
    }
}
