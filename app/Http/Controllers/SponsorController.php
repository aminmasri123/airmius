<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Sponsor;
use App\Services\PlanFeatureService;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SponsorController extends Controller
{
    public function __construct(private PlanFeatureService $planFeatures) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('org.manage'), 403);

        $scope = (string) $request->input('scope', 'all');
        $scope = in_array($scope, ['all', 'platform', 'outfit_subscription', 'club'], true) ? $scope : 'all';

        $sponsorQuery = Sponsor::query()
            ->with('club:id,name')
            ->when($scope === 'platform', fn ($query) => $query->where(function ($query) {
                $query->where('scope', 'platform')
                    ->orWhere(function ($query) {
                        $query->whereNull('scope')->whereNull('club_id');
                    });
            }))
            ->when($scope === 'outfit_subscription', fn ($query) => $query->where('scope', 'outfit_subscription'))
            ->when($scope === 'club', fn ($query) => $query->where(function ($query) {
                $query->where('scope', 'club')
                    ->orWhereNotNull('club_id');
            }));

        return Inertia::render('Auth/Dashboard/Sponsors/Index', [
            'sponsors' => $sponsorQuery
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Sponsor $sponsor) => [
                    'id' => $sponsor->id,
                    'club_id' => $sponsor->club_id,
                    'scope' => $sponsor->scope ?: ($sponsor->club_id ? 'club' : 'platform'),
                    'name' => $sponsor->name,
                    'contact_name' => $sponsor->contact_name,
                    'email' => $sponsor->email,
                    'website' => $sponsor->website,
                    'logo' => $sponsor->logo,
                    'logo_light' => $sponsor->logo_light,
                    'logo_dark' => $sponsor->logo_dark,
                    'logo_url' => UploadStorage::url($sponsor->logo),
                    'logo_light_url' => UploadStorage::url($sponsor->logo_light ?: $sponsor->logo),
                    'logo_dark_url' => UploadStorage::url($sponsor->logo_dark ?: $sponsor->logo_light ?: $sponsor->logo),
                    'amount' => $sponsor->amount,
                    'starts_at' => optional($sponsor->starts_at)->toDateString(),
                    'ends_at' => optional($sponsor->ends_at)->toDateString(),
                    'club' => $sponsor->club ? [
                        'id' => $sponsor->club->id,
                        'name' => $sponsor->club->name,
                    ] : null,
                ]),
            'stats' => [
                'total' => Sponsor::query()->count(),
                'platform' => Sponsor::query()
                    ->where('scope', 'platform')
                    ->orWhere(function ($query) {
                        $query->whereNull('scope')->whereNull('club_id');
                    })
                    ->count(),
                'outfit_subscription' => Sponsor::query()->where('scope', 'outfit_subscription')->count(),
                'club' => Sponsor::query()
                    ->where('scope', 'club')
                    ->orWhereNotNull('club_id')
                    ->count(),
            ],
            'filters' => [
                'scope' => $scope,
            ],
            'clubs' => Club::query()
                ->visibleTo($request->user())
                ->with('currentSubscription.plan')
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(fn (Club $club) => [
                    'id' => $club->id,
                    'name' => $club->name,
                    'capabilities' => $this->planFeatures->capabilities($club),
                ]),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('org.manage'), 403);

        $data = $this->validated($request);

        $data = $this->normalizeScope($data);

        if (($data['scope'] ?? 'platform') === 'club') {
            $club = Club::query()->visibleTo($request->user())->findOrFail($data['club_id']);
            $this->planFeatures->ensureAllows($club, 'sponsors');
        }

        $data = $this->normalizeLogos($data);

        Sponsor::create($data);

        return back()->with('success', 'Sponsor erstellt.');
    }

    public function update(Request $request, Sponsor $sponsor)
    {
        abort_unless($request->user()->can('org.manage'), 403);

        $data = $this->validated($request);

        $data = $this->normalizeScope($data);

        if (($data['scope'] ?? 'platform') === 'club') {
            $club = Club::query()->visibleTo($request->user())->findOrFail($data['club_id']);
            $this->planFeatures->ensureAllows($club, 'sponsors');
        }

        $data = $this->normalizeLogos($data);

        $sponsor->update($data);

        return back()->with('success', 'Sponsor aktualisiert.');
    }

    public function destroy(Request $request, Sponsor $sponsor)
    {
        abort_unless($request->user()->can('org.manage'), 403);

        $sponsor->delete();

        return back()->with('success', 'Sponsor gelöscht.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'scope' => ['required', Rule::in(['platform', 'outfit_subscription', 'club'])],
            'club_id' => ['nullable', 'required_if:scope,club', 'exists:clubs,id'],
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'logo_light' => ['nullable', 'string', 'max:2048'],
            'logo_dark' => ['nullable', 'string', 'max:2048'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }

    private function normalizeScope(array $data): array
    {
        $data['scope'] = $data['scope'] ?? 'platform';

        if ($data['scope'] !== 'club') {
            $data['club_id'] = null;
        }

        return $data;
    }

    private function normalizeLogos(array $data): array
    {
        $fallback = $data['logo'] ?: ($data['logo_light'] ?? null) ?: ($data['logo_dark'] ?? null);

        $data['logo'] = $fallback;
        $data['logo_light'] = $data['logo_light'] ?: $fallback;
        $data['logo_dark'] = $data['logo_dark'] ?: $data['logo_light'];

        return $data;
    }
}
