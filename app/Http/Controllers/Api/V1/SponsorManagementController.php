<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Sponsor;
use App\Services\PlanFeatureService;
use App\Support\UploadStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SponsorManagementController extends Controller
{
    public function __construct(private PlanFeatureService $planFeatures) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);
        $clubs = Club::query()
            ->visibleTo($request->user())
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
        $sponsors = Sponsor::query()
            ->with('club:id,name')
            ->get()
            ->filter(fn (Sponsor $sponsor) => $this->canManageSponsor($request, $sponsor))
            ->sortByDesc('id')
            ->map(fn (Sponsor $sponsor) => $this->sponsorData($sponsor))
            ->values();

        return response()->json([
            'data' => $sponsors,
            'clubs' => $clubs,
            'stats' => [
                'total' => $sponsors->count(),
                'platform' => $sponsors->where('scope', 'platform')->count(),
                'outfit_subscription' => $sponsors->where('scope', 'outfit_subscription')->count(),
                'club' => $sponsors->where('scope', 'club')->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);
        $data = $this->validated($request);
        $this->authorizeScope($request, $data);
        $sponsor = Sponsor::query()->create($this->normalize($data));

        return response()->json([
            'message' => 'sponsor_created',
            'data' => $this->sponsorData($sponsor->load('club:id,name')),
        ], 201);
    }

    public function update(Request $request, Sponsor $sponsor): JsonResponse
    {
        abort_unless($this->canManageSponsor($request, $sponsor), 403);
        $data = $this->validated($request);
        $this->authorizeScope($request, $data);
        $sponsor->update($this->normalize($data));

        return response()->json([
            'message' => 'sponsor_updated',
            'data' => $this->sponsorData($sponsor->fresh('club:id,name')),
        ]);
    }

    public function destroy(Request $request, Sponsor $sponsor): JsonResponse
    {
        abort_unless($this->canManageSponsor($request, $sponsor), 403);
        $sponsor->delete();

        return response()->json(['message' => 'sponsor_deleted']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'scope' => ['required', Rule::in(['platform', 'outfit_subscription', 'club'])],
            'club_id' => ['nullable', 'required_if:scope,club', 'integer', Rule::exists('clubs', 'id')],
            'name' => ['required', 'string', 'max:255'],
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
            abort_unless($request->user()->can('update', $club), 403);
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
                || Club::query()->visibleTo($request->user())->get()->contains(fn (Club $club) => $request->user()->can('update', $club)),
            403,
        );
    }

    private function canManageSponsor(Request $request, Sponsor $sponsor): bool
    {
        if (! $sponsor->club_id) {
            return $this->canManageGlobal($request);
        }

        return $sponsor->club && $request->user()->can('update', $sponsor->club);
    }

    private function canManageGlobal(Request $request): bool
    {
        return $request->user()->can('finance.edit')
            || $request->user()->can('system.manage')
            || $request->user()->hasAnyRole(['super_admin', 'admin']);
    }
}
