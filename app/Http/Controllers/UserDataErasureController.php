<?php

namespace App\Http\Controllers;

use App\Notifications\UserDataErasureCompleted;
use App\Services\UserDataErasureConfirmationService;
use App\Services\UserDataErasureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserDataErasureController extends Controller
{
    public function edit(Request $request, UserDataErasureService $erasure, UserDataErasureConfirmationService $confirmation): Response
    {
        return Inertia::render('Auth/Dashboard/Settings/DataErasure', [
            'categories' => $erasure->categories(),
            'usesSocialLogin' => $confirmation->usesSocialLogin($request->user()),
            'accountEmail' => $request->user()->email,
        ]);
    }

    public function sendCode(Request $request, UserDataErasureConfirmationService $confirmation, UserDataErasureService $erasure): RedirectResponse
    {
        $data = $request->validate($this->confirmationRules($erasure));
        $confirmation->issue($request->user(), $data['identity'], $data['categories']);

        return back()->with('success', 'Wir haben dir einen Bestätigungscode per E-Mail gesendet. Er ist 15 Minuten gültig.');
    }

    public function destroy(Request $request, UserDataErasureConfirmationService $confirmation, UserDataErasureService $erasure): RedirectResponse
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
        $erasure->erase($user, $categories);

        try {
            Notification::route('mail', $user->email)
                ->notify(new UserDataErasureCompleted($name));
        } catch (\Throwable $exception) {
            Log::warning('Data-erasure confirmation email could not be sent.', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
        }

        return to_route('auth.settings.privacy.erasure')
            ->with('success', 'Die ausgewählten Daten wurden gelöscht oder – soweit erforderlich – anonymisiert. Dein Konto bleibt bestehen.');
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
