<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Post;
use App\Models\Sport;
use App\Models\Team;
use App\Models\UserSport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SportAdminController extends Controller
{
    public function index()
    {
        $teamCounts = Team::query()
            ->select('sport_type', DB::raw('count(*) as aggregate'))
            ->whereNotNull('sport_type')
            ->groupBy('sport_type')
            ->pluck('aggregate', 'sport_type');

        $clubCounts = Club::query()
            ->select('sport_type', DB::raw('count(*) as aggregate'))
            ->whereNotNull('sport_type')
            ->groupBy('sport_type')
            ->pluck('aggregate', 'sport_type');

        $profileCounts = UserSport::query()
            ->select('sport_id', DB::raw('count(*) as aggregate'))
            ->groupBy('sport_id')
            ->pluck('aggregate', 'sport_id');

        $postCounts = Post::query()
            ->select('sport_id', DB::raw('count(*) as aggregate'))
            ->whereNotNull('sport_id')
            ->groupBy('sport_id')
            ->pluck('aggregate', 'sport_id');

        $sports = Sport::query()
            ->withCount('skills')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Sport $sport) use ($teamCounts, $clubCounts, $profileCounts, $postCounts) {
                $teamsCount = (int) ($teamCounts[$sport->slug] ?? 0) + (int) ($teamCounts[$sport->name] ?? 0);
                $clubsCount = (int) ($clubCounts[$sport->slug] ?? 0) + (int) ($clubCounts[$sport->name] ?? 0);
                $profilesCount = (int) ($profileCounts[$sport->id] ?? 0);
                $postsCount = (int) ($postCounts[$sport->id] ?? 0);

                return [
                    'id' => $sport->id,
                    'name' => $sport->name,
                    'slug' => $sport->slug,
                    'category' => $sport->category,
                    'is_active' => $sport->is_active,
                    'sort_order' => $sport->sort_order,
                    'skills_count' => $sport->skills_count,
                    'teams_count' => $teamsCount,
                    'clubs_count' => $clubsCount,
                    'profiles_count' => $profilesCount,
                    'posts_count' => $postsCount,
                    'usage_count' => $teamsCount + $clubsCount + $profilesCount + $postsCount,
                ];
            })
            ->values();

        return Inertia::render('Auth/Dashboard/Sports/Index', [
            'sports' => $sports,
            'categories' => $sports->pluck('category')->filter()->unique()->sort()->values(),
            'summary' => [
                'sports_count' => $sports->count(),
                'active_count' => $sports->where('is_active', true)->count(),
                'teams_count' => $sports->sum('teams_count'),
                'clubs_count' => $sports->sum('clubs_count'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'slug' => $request->input('slug') ?: Str::slug((string) $request->input('name')),
        ]);

        $data = $this->validatedData($request);

        Sport::create($data);

        return back()->with('success', 'Sportart erstellt.');
    }

    public function update(Request $request, Sport $sport)
    {
        $request->merge([
            'slug' => $request->input('slug') ?: Str::slug((string) $request->input('name')),
        ]);

        $data = $this->validatedData($request, $sport);

        DB::transaction(function () use ($sport, $data) {
            $oldSlug = $sport->slug;
            $oldName = $sport->name;

            $sport->update($data);

            if ($oldSlug !== $sport->slug) {
                Team::query()
                    ->whereIn('sport_type', [$oldSlug, $oldName])
                    ->update(['sport_type' => $sport->slug]);

                Club::query()
                    ->whereIn('sport_type', [$oldSlug, $oldName])
                    ->update(['sport_type' => $sport->slug]);
            }
        });

        return back()->with('success', 'Sportart aktualisiert.');
    }

    public function destroy(Sport $sport)
    {
        $teamsCount = Team::query()->whereIn('sport_type', [$sport->slug, $sport->name])->count();
        $clubsCount = Club::query()->whereIn('sport_type', [$sport->slug, $sport->name])->count();
        $profilesCount = UserSport::query()->where('sport_id', $sport->id)->count();
        $postsCount = Post::query()->where('sport_id', $sport->id)->count();

        abort_if(
            ($teamsCount + $clubsCount + $profilesCount + $postsCount) > 0,
            422,
            'Diese Sportart wird bereits verwendet. Bitte deaktivieren statt löschen.'
        );

        $sport->delete();

        return back()->with('success', 'Sportart gelöscht.');
    }

    private function validatedData(Request $request, ?Sport $sport = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('sports', 'name')->ignore($sport)],
            'slug' => ['nullable', 'string', 'max:140', Rule::unique('sports', 'slug')->ignore($sport)],
            'category' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['boolean'],
        ]);
    }
}
