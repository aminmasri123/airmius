<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Sponsor;
use App\Services\PlanFeatureService;
use App\Services\RevenueTrustService;
use App\Support\ClubRoles;
use App\Support\UploadStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SponsorManagementController extends Controller
{
    public function __construct(
        private PlanFeatureService $planFeatures,
        private RevenueTrustService $revenueTrust,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);
        $global = $this->canManageGlobal($request);
        $managedClubIds = $this->managedClubIds($request);
        $clubs = Club::query()
            ->when(! $global, fn ($query) => $query->whereIn('id', $managedClubIds))
            ->select(['id', 'name'])
            ->orderBy('name')
            ->limit(100)
            ->get();
        $sponsorQuery = Sponsor::query()
            ->with('club:id,name')
            ->when(! $global, fn ($query) => $query->whereIn('club_id', $managedClubIds));
        $stats = [
            'total' => (clone $sponsorQuery)->count(),
            'platform' => (clone $sponsorQuery)->where('scope', 'platform')->count(),
            'outfit_subscription' => (clone $sponsorQuery)->where('scope', 'outfit_subscription')->count(),
            'club' => (clone $sponsorQuery)->where('scope', 'club')->count(),
        ];
        $sponsors = (clone $sponsorQuery)
            ->latest('id')
            ->limit(500)
            ->get()
            ->map(fn (Sponsor $sponsor) => $this->sponsorData($sponsor))
            ->values();

        return response()->json([
            'data' => $sponsors,
            'clubs' => $clubs,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);
        $data = $this->validated($request);
        $this->authorizeScope($request, $data);
        $data = $this->normalize($data);
        if (($data['verification_status'] ?? 'verified') === 'verified') {
            $data['verified_by'] = $request->user()->id;
            $data['verified_at'] = now();
        }
        $sponsor = Sponsor::query()->create($data);

        return response()->json([
            'message' => __('sponsor.flash.created'),
            'data' => $this->sponsorData($sponsor->load('club:id,name')),
        ], 201);
    }

    public function update(Request $request, Sponsor $sponsor): JsonResponse
    {
        abort_unless($this->canManageSponsor($request, $sponsor), 403);
        $data = $this->validated($request);
        $this->authorizeScope($request, $data);
        $data = $this->normalize($data);
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

        return response()->json([
            'message' => __('sponsor.flash.updated'),
            'data' => $this->sponsorData($sponsor->fresh('club:id,name')),
        ]);
    }

    public function destroy(Request $request, Sponsor $sponsor): JsonResponse
    {
        abort_unless($this->canManageSponsor($request, $sponsor), 403);
        $sponsor->delete();

        return response()->json(['message' => __('sponsor.flash.deleted')]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'scope' => ['required', Rule::in(['platform', 'outfit_subscription', 'club'])],
            'club_id' => ['nullable', 'required_if:scope,club', 'integer', Rule::exists('clubs', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'vat_id' => ['nullable', 'string', 'max:80'],
            'verification_status' => ['nullable', Rule::in(['pending_review', 'verified', 'rejected'])],
            'verification_note' => ['nullable', 'string', 'max:2000'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'logo_light' => ['nullable', 'url:http,https', 'max:2048'],
            'logo_dark' => ['nullable', 'url:http,https', 'max:2048'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }

    private function authorizeScope(Request $request, array $data): void
    {
        if ($data['scope'] === 'club') {
            $club = Club::query()->findOrFail($data['club_id']);
            abort_unless($this->canManageGlobal($request) || $this->managedClubIds($request)->contains($club->id), 403);
            $this->planFeatures->ensureAllows($club, 'sponsors');

            return;
        }

        abort_unless($this->canManageGlobal($request), 403);
    }

    private function normalize(array $data): array
    {
        if ($data['scope'] !== 'club') {
            $data['club_id'] = null;
        }
        if (filled($data['country_code'] ?? null)) {
            $data['country_code'] = strtoupper((string) $data['country_code']);
        }
        $fallback = $data['logo_light'] ?? $data['logo_dark'] ?? null;
        $data['logo'] = $fallback;
        $data['logo_light'] = $data['logo_light'] ?? $fallback;
        $data['logo_dark'] = $data['logo_dark'] ?? $data['logo_light'];

        return $data;
    }

    private function sponsorData(Sponsor $sponsor): array
    {
        return [
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
            'logo_light' => $sponsor->logo_light,
            'logo_dark' => $sponsor->logo_dark,
            'logo_url' => UploadStorage::url($sponsor->logo),
            'amount' => $sponsor->amount,
            'starts_at' => $sponsor->starts_at?->toDateString(),
            'ends_at' => $sponsor->ends_at?->toDateString(),
            'club' => $sponsor->club,
        ];
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless(
            $this->canManageGlobal($request)
                || $this->managedClubIds($request)->isNotEmpty(),
            403,
        );
    }

    private function canManageSponsor(Request $request, Sponsor $sponsor): bool
    {
        if ($this->canManageGlobal($request)) {
            return true;
        }

        if (! $sponsor->club_id) {
            return false;
        }

        return $this->managedClubIds($request)->contains($sponsor->club_id);
    }

    private function canManageGlobal(Request $request): bool
    {
        return $request->user()->can('finance.edit')
            || $request->user()->can('system.manage')
            || $request->user()->hasAnyRole(['super_admin', 'admin', 'sponsor_manager']);
    }

    private function managedClubIds(Request $request)
    {
        $user = $request->user();
        $owned = Club::query()->where('owner_id', $user->id)->pluck('id');
        $managed = ClubRoles::whereAny($user->clubs(), ['owner', 'admin', 'manager', 'financial_controller'])
            ->pluck('clubs.id');

        return $owned->concat($managed)->map(fn ($id) => (int) $id)->unique()->values();
    }
}
