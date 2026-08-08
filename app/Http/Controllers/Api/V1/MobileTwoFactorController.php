<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Fortify;

class MobileTwoFactorController extends Controller
{
    public function show(Request $request)
    {
        return response()->json(['data' => $this->status($request->user())]);
    }

    public function store(Request $request, EnableTwoFactorAuthentication $enable)
    {
        $this->confirmPassword($request);
        $user = $request->user();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages([
                'two_factor' => ['Die Zwei-Faktor-Authentifizierung ist bereits aktiviert.'],
            ]);
        }

        $enable($user, true);
        $user->refresh();

        return response()->json([
            'data' => [
                ...$this->status($user),
                'setup_key' => Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
                'otpauth_url' => $user->twoFactorQrCodeUrl(),
            ],
        ], 201);
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $user = $request->user();
        $confirm($user, preg_replace('/\s+/', '', $data['code']));
        $user->refresh();

        return response()->json([
            'data' => [
                ...$this->status($user),
                'recovery_codes' => $user->recoveryCodes(),
            ],
        ]);
    }

    public function destroy(Request $request, DisableTwoFactorAuthentication $disable)
    {
        $this->confirmPassword($request);
        $disable($request->user());

        return response()->json([
            'data' => [
                ...$this->status($request->user()->refresh()),
                'message' => __('account_security.responses.two_factor_disabled'),
            ],
        ]);
    }

    public function regenerateRecoveryCodes(
        Request $request,
        GenerateNewRecoveryCodes $generate
    ) {
        $this->confirmPassword($request);
        $user = $request->user();

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages([
                'two_factor' => ['Aktiviere zuerst die Zwei-Faktor-Authentifizierung.'],
            ]);
        }

        $generate($user);

        return response()->json([
            'data' => [
                ...$this->status($user->refresh()),
                'recovery_codes' => $user->recoveryCodes(),
            ],
        ]);
    }

    private function confirmPassword(Request $request): void
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:255'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('account_security.errors.current_password')],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function status($user): array
    {
        return [
            'enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'pending_confirmation' => ! empty($user->two_factor_secret)
                && empty($user->two_factor_confirmed_at),
            'confirmed_at' => $user->two_factor_confirmed_at?->toJSON(),
        ];
    }
}
