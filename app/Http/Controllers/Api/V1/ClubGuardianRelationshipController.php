<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\GuardianChildRelationship;
use App\Models\User;
use App\Services\GuardianChildRelationshipService;
use App\Support\ClubPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubGuardianRelationshipController extends Controller
{
    public function __construct(private readonly GuardianChildRelationshipService $relationships) {}

    public function index(Request $request, Club $club, User $child): JsonResponse
    {
        $this->authorizeClubChild($request, $club, $child, ClubPermissions::MEMBERS_EDIT);

        $relationships = GuardianChildRelationship::query()
            ->where('club_id', $club->id)
            ->where('child_user_id', $child->id)
            ->with(['guardian:id,name,email'])
            ->orderByDesc('is_primary')
            ->orderByRaw("CASE status WHEN 'accepted' THEN 1 WHEN 'invited' THEN 2 WHEN 'ambiguous' THEN 3 WHEN 'declined' THEN 4 WHEN 'revoked' THEN 5 ELSE 6 END")
            ->orderBy('guardian_email')
            ->get()
            ->map(fn (GuardianChildRelationship $relationship) => $this->relationshipData($relationship))
            ->values();

        return response()->json([
            'data' => [
                'club' => ['id' => $club->id, 'name' => $club->name],
                'child' => ['id' => $child->id, 'name' => $child->name, 'email' => $child->email],
                'summary' => [
                    'total' => $relationships->count(),
                    'accepted' => $relationships->where('status', GuardianChildRelationship::STATUS_ACCEPTED)->count(),
                    'invited' => $relationships->where('status', GuardianChildRelationship::STATUS_INVITED)->count(),
                    'needs_review' => $relationships->where('status', GuardianChildRelationship::STATUS_AMBIGUOUS)->count(),
                ],
                'relationships' => $relationships,
            ],
        ]);
    }

    public function store(Request $request, Club $club, User $child): JsonResponse
    {
        $this->authorizeClubChild($request, $club, $child, ClubPermissions::MEMBERS_EDIT);

        $data = $request->validate([
            'guardian_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'relationship_type' => ['nullable', 'string', 'max:50'],
            'primary' => ['sometimes', 'boolean'],
        ]);

        $guardian = isset($data['guardian_user_id'])
            ? User::query()->findOrFail($data['guardian_user_id'])
            : null;

        $relationship = $this->relationships->invite(
            $club,
            $child,
            $guardian,
            $data['guardian_email'] ?? null,
            $request->user(),
            $data['relationship_type'] ?? 'guardian',
            (bool) ($data['primary'] ?? false),
        );

        return response()->json([
            'message' => 'guardian_relationship_saved',
            'message_text' => $relationship->status === GuardianChildRelationship::STATUS_INVITED
                ? 'Einladung offen: Das Konto der sorgeberechtigten Person ist noch nicht verknüpft.'
                : 'Sorgeberechtigten-Beziehung aktiv.',
            'data' => $this->relationshipData($relationship->load('guardian:id,name,email')),
        ], 201);
    }

    public function accept(Request $request, Club $club, User $child, GuardianChildRelationship $relationship): JsonResponse
    {
        $this->authorizeRelationshipAction($request, $club, $child, $relationship);

        return response()->json([
            'message' => 'guardian_relationship_accepted',
            'data' => $this->relationshipData($this->relationships->accept($relationship, $relationship->guardian, $request->user())->load('guardian:id,name,email')),
        ]);
    }

    public function decline(Request $request, Club $club, User $child, GuardianChildRelationship $relationship): JsonResponse
    {
        $this->authorizeRelationshipAction($request, $club, $child, $relationship);

        return response()->json([
            'message' => 'guardian_relationship_declined',
            'data' => $this->relationshipData($this->relationships->decline($relationship, $request->user())->load('guardian:id,name,email')),
        ]);
    }

    public function revoke(Request $request, Club $club, User $child, GuardianChildRelationship $relationship): JsonResponse
    {
        $this->authorizeRelationshipAction($request, $club, $child, $relationship);

        return response()->json([
            'message' => 'guardian_relationship_revoked',
            'data' => $this->relationshipData($this->relationships->revoke($relationship, $request->user())->load('guardian:id,name,email')),
        ]);
    }

    public function primary(Request $request, Club $club, User $child, GuardianChildRelationship $relationship): JsonResponse
    {
        $this->authorizeRelationshipAction($request, $club, $child, $relationship);

        return response()->json([
            'message' => 'guardian_relationship_primary_changed',
            'data' => $this->relationshipData($this->relationships->setPrimary($relationship, $request->user())->load('guardian:id,name,email')),
        ]);
    }

    private function authorizeRelationshipAction(Request $request, Club $club, User $child, GuardianChildRelationship $relationship): void
    {
        $this->authorizeClubChild($request, $club, $child, ClubPermissions::MEMBERS_EDIT);

        abort_unless(
            $relationship->club_id === $club->id && $relationship->child_user_id === $child->id,
            404,
        );
    }

    private function authorizeClubChild(Request $request, Club $club, User $child, string $permission): void
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), $permission), 403);

        $belongsToClub = $child->clubs()->whereKey($club->id)->exists()
            || $child->teams()->where('club_id', $club->id)->exists();

        abort_unless($belongsToClub, 404);
    }

    private function relationshipData(GuardianChildRelationship $relationship): array
    {
        return [
            'id' => $relationship->id,
            'club_id' => $relationship->club_id,
            'child_user_id' => $relationship->child_user_id,
            'guardian' => $relationship->guardian ? [
                'id' => $relationship->guardian->id,
                'name' => $relationship->guardian->name,
                'email' => $relationship->guardian->email,
            ] : null,
            'guardian_email' => $relationship->guardian_email,
            'relationship_type' => $relationship->relationship_type,
            'is_primary' => $relationship->is_primary,
            'status' => $relationship->status,
            'status_label' => $this->statusLabel($relationship->status),
            'status_description' => $this->statusDescription($relationship),
            'invited_at' => $relationship->invited_at?->toJSON(),
            'accepted_at' => $relationship->accepted_at?->toJSON(),
            'declined_at' => $relationship->declined_at?->toJSON(),
            'revoked_at' => $relationship->revoked_at?->toJSON(),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            GuardianChildRelationship::STATUS_ACCEPTED => 'Aktiv',
            GuardianChildRelationship::STATUS_INVITED => 'Einladung offen',
            GuardianChildRelationship::STATUS_DECLINED => 'Abgelehnt',
            GuardianChildRelationship::STATUS_REVOKED => 'Widerrufen',
            GuardianChildRelationship::STATUS_AMBIGUOUS => 'Klärung nötig',
            default => 'Unbekannt',
        };
    }

    private function statusDescription(GuardianChildRelationship $relationship): string
    {
        return match ($relationship->status) {
            GuardianChildRelationship::STATUS_ACCEPTED => $relationship->is_primary
                ? 'Dieses Konto ist aktiv verknüpft und als Hauptkontakt hinterlegt.'
                : 'Dieses Konto ist aktiv verknüpft.',
            GuardianChildRelationship::STATUS_INVITED => 'Die Einladung wurde erfasst, aber noch nicht durch ein eigenes Konto angenommen.',
            GuardianChildRelationship::STATUS_DECLINED => 'Die sorgeberechtigte Person hat die Verknüpfung abgelehnt.',
            GuardianChildRelationship::STATUS_REVOKED => 'Die Verknüpfung wurde beendet und gewährt keine aktiven Rechte mehr.',
            GuardianChildRelationship::STATUS_AMBIGUOUS => 'Legacy-Daten widersprechen sich und müssen manuell geprüft werden.',
            default => 'Der Status ist nicht bekannt.',
        };
    }
}
