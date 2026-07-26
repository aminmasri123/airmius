<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\MobileVerifyEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MobileEmailVerificationController extends Controller
{
    public function verify(Request $request, int $id, string $hash)
    {
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw ValidationException::withMessages([
                'email' => ['Der Bestätigungslink ist ungültig oder abgelaufen.'],
            ]);
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'data' => [
                'verified' => true,
                'message' => 'Deine E-Mail-Adresse wurde erfolgreich bestätigt.',
            ],
        ]);
    }

    public function send(Request $request)
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->notify(new MobileVerifyEmail);
        }

        return response()->json([
            'data' => [
                'verified' => $user->hasVerifiedEmail(),
                'message' => $user->hasVerifiedEmail()
                    ? 'Deine E-Mail-Adresse ist bereits bestätigt.'
                    : 'Eine neue Bestätigungs-E-Mail wurde gesendet.',
            ],
        ]);
    }
}
