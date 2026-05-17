<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BadgeController extends Controller
{
    public function index()
    {
        return Inertia::render('Auth/Dashboard/Badges/Index', [
            'badges' => Badge::query()
                ->orderBy('actor_type')
                ->orderBy('trigger')
                ->orderBy('threshold')
                ->get(),
            'actorTypes' => ['sportler', 'trainer', 'verein', 'team'],
            'triggers' => ['xp', 'level', 'streak', 'reason'],
        ]);
    }

    public function store(Request $request)
    {
        Badge::create($this->validated($request));

        return back()->with('success', 'Badge wurde erstellt.');
    }

    public function update(Request $request, Badge $badge)
    {
        $badge->update($this->validated($request, $badge));

        return back()->with('success', 'Badge wurde aktualisiert.');
    }

    public function destroy(Badge $badge)
    {
        abort_if($badge->users()->exists(), 422, 'Badge wurde bereits vergeben und kann nicht geloescht werden.');

        $badge->delete();

        return back()->with('success', 'Badge wurde geloescht.');
    }

    private function validated(Request $request, ?Badge $badge = null): array
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::unique('badges', 'key')->ignore($badge)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9\\s_-]+$/i'],
            'actor_type' => ['required', Rule::in(['sportler', 'trainer', 'verein', 'team'])],
            'trigger' => ['required', Rule::in(['xp', 'level', 'streak', 'reason'])],
            'threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'meta' => ['nullable', 'array'],
            'meta.reason' => [Rule::requiredIf($request->input('trigger') === 'reason'), 'nullable', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
        ]);

        $data['meta'] = $data['trigger'] === 'reason'
            ? ['reason' => $data['meta']['reason']]
            : null;

        return $data;
    }
}
