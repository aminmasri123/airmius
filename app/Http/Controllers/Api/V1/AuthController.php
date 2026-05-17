<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
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
