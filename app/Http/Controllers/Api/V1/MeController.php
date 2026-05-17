<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeController extends Controller
{
    public function show(Request $request)
    {
        return new UserResource(
            $request->user()->loadMissing(['roles', 'permissions', 'clubs', 'teams.club'])
        );
    }

    public function updateLanguage(Request $request)
    {
        $data = $request->validate([
            'language' => ['required', Rule::in(['de', 'en', 'fr', 'ar'])],
        ]);

        $request->user()->forceFill([
            'language' => $data['language'],
        ])->save();

        return new UserResource($request->user()->refresh());
    }
}
