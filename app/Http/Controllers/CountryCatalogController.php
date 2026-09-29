<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\CountryCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CountryCatalogController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user() ?? auth('sanctum')->user();

        return response()->json(['data' => CountryCatalog::all(), 'can_manage' => (bool) $user?->can('system.manage')]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->can('system.manage'), 403);
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'regex:/^[A-Z]{2}$/', Rule::notIn(array_column(CountryCatalog::defaults(), 'code'))],
            'names' => ['required', 'array:de,en,fr,ar'],
            'names.de' => ['required', 'string', 'max:100'],
            'names.en' => ['required', 'string', 'max:100'],
            'names.fr' => ['nullable', 'string', 'max:100'],
            'names.ar' => ['nullable', 'string', 'max:100'],
        ]);
        Setting::setValue('country_catalog.'.$data['code'], json_encode($data['names'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $this->index($request);
    }
}
