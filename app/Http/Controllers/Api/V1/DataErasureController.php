<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Notifications\UserDataErasureCompleted;
use App\Services\UserDataErasureConfirmationService;
use App\Services\UserDataErasureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class DataErasureController extends Controller
{
    public function sendCode(Request $request, UserDataErasureConfirmationService $confirmation, UserDataErasureService $erasure)
    {
        $data = $request->validate($this->confirmationRules($erasure));
        $confirmation->issue($request->user(), $data['identity'], $data['categories']);

        return response()->json([
            'data' => [
                'message' => 'Wir haben dir einen Bestätigungscode per E-Mail gesendet.',
                'expires_in_minutes' => 15,
            ],
        ]);
    }

    public function destroy(Request $request, UserDataErasureConfirmationService $confirmation, UserDataErasureService $erasure)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            ...$this->categoryRules($erasure),
        ], [
            'code.required' => 'Bitte gib den Code aus deiner E-Mail ein.',
        ]);

        $user = $request->user();
        $name = $user->name;
        $categories = $confirmation->consume($user, $data['code'], $data['categories']);
        $result = $erasure->erase($user, $categories);

        try {
            Notification::route('mail', $user->email)
                ->notify(new UserDataErasureCompleted($name));
        } catch (\Throwable $exception) {
            Log::warning('Mobile data-erasure confirmation email could not be sent.', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'data' => [
                'message' => 'Die ausgewählten Daten wurden gelöscht oder – soweit erforderlich – anonymisiert. Dein Konto bleibt bestehen.',
                'result' => $result,
            ],
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function confirmationRules(UserDataErasureService $erasure): array
    {
        return [
            'identity' => ['required', 'string', 'max:255'],
            ...$this->categoryRules($erasure),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function categoryRules(UserDataErasureService $erasure): array
    {
        return [
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'string', Rule::in($erasure->categoryKeys())],
        ];
    }
}
