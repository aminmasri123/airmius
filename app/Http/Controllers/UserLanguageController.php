<?php

namespace App\Http\Controllers;

use App\Actions\Identity\UpdateUserLanguage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserLanguageController extends Controller
{
    public function __invoke(Request $request, UpdateUserLanguage $updateLanguage): RedirectResponse
    {
        $data = $request->validate([
            'language' => ['required', Rule::in(['de', 'en', 'fr', 'ar'])],
        ]);

        if ($request->user()) {
            $updateLanguage->execute($request->user(), $data['language']);
        }

        $request->session()->put('locale', $data['language']);
        app()->setLocale($data['language']);

        return back();
    }
}
