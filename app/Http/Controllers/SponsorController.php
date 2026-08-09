<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Sponsor;
use App\Services\PlanFeatureService;
use App\Services\RevenueTrustService;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SponsorController extends Controller
{
    public function __construct(
        private PlanFeatureService $planFeatures,
        private RevenueTrustService $revenueTrust,
    ) {}

    public function index(Request $request)
    {
        abort_unless($this->canManageGlobal($request), 403);

        $scope = (string) $request->input('scope', 'all');
        $scope = in_array($scope, ['all', 'platform', 'outfit_subscription', 'club'], true) ? $scope : 'all';
        $query = mb_substr(trim((string) $request->input('q', '')), 0, 120);
        $clubQuery = mb_substr(trim((string) $request->input('club_query', '')), 0, 120);

        $sponsorQuery = Sponsor::query()
            ->with('club:id,name')
            ->when($query, fn ($builder, $search) => $builder->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('club', fn ($club) => $club->where('name', 'like', "%{$search}%"));
            }))
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
                    'legal_name' => $sponsor->legal_name,
                    'country_code' => $sponsor->country_code,
                    'registration_number' => $sponsor->registration_number,
                    'vat_id' => $sponsor->vat_id,
                    'verification_status' => $sponsor->verification_status,
                    'verification_note' => $sponsor->verification_note,
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
            'stats' => fn () => [
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
                'q' => $query,
                'club_query' => $clubQuery,
            ],
            'clubs' => fn () => Club::query()
                ->with('currentSubscription.plan')
                ->select(['id', 'name'])
                ->when($clubQuery, fn ($builder, $search) => $builder->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit(100)
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
        abort_unless($this->canManageGlobal($request), 403);

        $data = $this->validated($request);

        $data = $this->normalizeScope($data);

        if (($data['scope'] ?? 'platform') === 'club') {
            $club = Club::query()->findOrFail($data['club_id']);
            $this->planFeatures->ensureAllows($club, 'sponsors');
        }

        $data = $this->normalizeLogos($data);

        if (($data['verification_status'] ?? 'verified') === 'verified') {
            $data['verified_by'] = $request->user()->id;
            $data['verified_at'] = now();
        }

        Sponsor::create($data);

        return back()->with('success', __('sponsor.flash.created'));
    }

    public function update(Request $request, Sponsor $sponsor)
    {
        abort_unless($this->canManageGlobal($request), 403);

        $data = $this->validated($request);

        $data = $this->normalizeScope($data);

        if (($data['scope'] ?? 'platform') === 'club') {
            $club = Club::query()->findOrFail($data['club_id']);
            $this->planFeatures->ensureAllows($club, 'sponsors');
        }

        $data = $this->normalizeLogos($data);

        if (($data['verification_status'] ?? null) === 'verified') {
            $candidate = clone $sponsor;
            $candidate->forceFill($data);
            $this->revenueTrust->ensureSponsorApprovable($candidate);
            $data['verified_by'] = $request->user()->id;
            $data['verified_at'] = now();
            $data['verification_note'] = null;
        } elseif (array_key_exists('verification_status', $data)) {
            $data['verified_by'] = null;
            $data['verified_at'] = null;
        }

        $sponsor->update($data);

        return back()->with('success', __('sponsor.flash.updated'));
    }

    public function destroy(Request $request, Sponsor $sponsor)
    {
        abort_unless($this->canManageGlobal($request), 403);

        $sponsor->delete();

        return back()->with('success', __('sponsor.flash.deleted'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['platform', 'outfit_subscription', 'club'])],
            'club_id' => ['nullable', 'required_if:scope,club', 'exists:clubs,id'],
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'vat_id' => ['nullable', 'string', 'max:80'],
            'verification_status' => ['nullable', Rule::in(['pending_review', 'verified', 'rejected'])],
            'verification_note' => ['nullable', 'string', 'max:2000'],
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

        if (filled($data['country_code'] ?? null)) {
            $data['country_code'] = strtoupper((string) $data['country_code']);
        }

        return $data;
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

    private function canManageGlobal(Request $request): bool
    {
        return $request->user()->can('system.manage')
            || $request->user()->hasAnyRole(['super_admin', 'admin', 'sponsor_manager']);
    }
}
