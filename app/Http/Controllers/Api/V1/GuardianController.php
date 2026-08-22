<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TrainingLog;
use App\Models\User;
use App\Support\AppNotification;
use App\Support\GuardianConsentNotifier;
use App\Support\GuardianConsentState;
use App\Support\MinorSafety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuardianController extends Controller
{
    private const RESPONSE_CONSENT_RESENT = 'guardian_consent_resent';

    private const RESPONSE_CONSENT_APPROVED = 'guardian_consent_approved';

    private const RESPONSE_CONSENT_REVOKED = 'guardian_consent_revoked';

    public function index(Request $request): JsonResponse
    {
        $guardian = $request->user();
        $this->assertCanViewChildren($guardian);

        $children = $this->childrenQuery($guardian)
            ->orderByDesc('birth_date')
            ->orderBy('name')
            ->get()
            ->map(fn (User $child) => $this->childData($child))
            ->values();

        return response()->json([
            'data' => [
                'guardian' => [
                    'id' => $guardian->id,
                    'name' => $guardian->name,
                    'email' => $guardian->email,
                ],
                'can_manage' => $this->canManageChildren($guardian),
                'summary' => [
                    'children' => $children->count(),
                    'approved' => $children->where('status', 'approved')->count(),
                    'pending' => $children->where('status', 'pending')->count(),
                    'rejected' => $children->where('status', 'rejected')->count(),
                    'revoked' => $children->where('status', 'revoked')->count(),
                ],
                'children' => $children,
                'safety' => [
                    'consent_age' => MinorSafety::CONSENT_AGE,
                    'profile_visibility' => 'private',
                    'direct_messages' => 'friends',
                    'friend_requests' => 'friends',
                    'tokens_exposed' => false,
                ],
            ],
        ]);
    }

    public function consentStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        GuardianConsentState::sync($user);
        $user->refresh();

        return response()->json([
            'data' => $this->consentData($user),
        ]);
    }

    public function child(Request $request, int $child): JsonResponse
    {
        $guardian = $request->user();
        $this->assertCanViewChildren($guardian);

        $managedChild = $this->childrenQuery($guardian)
            ->whereKey($child)
            ->firstOrFail();
        $childData = $this->childData($managedChild);

        if (! MinorSafety::hasResolvedGuardianConsent($managedChild)) {
            return response()->json([
                'data' => [
                    'child' => $childData,
                    'access' => [
                        'approved' => false,
                        'scope' => 'consent_only',
                        'private_details_hidden' => true,
                    ],
                    'upcoming_events' => [],
                    'training' => $this->emptyTrainingSummary(),
                ],
            ]);
        }

        $teamIds = $managedChild->teams()->pluck('teams.id')->all();
        $events = Event::query()
            ->where('status', 'scheduled')
            ->where('start_time', '>=', now())
            ->where(function ($query) use ($managedChild, $teamIds) {
                $query->whereHas('participantRecords', fn ($participants) => $participants->where('user_id', $managedChild->id));
                if ($teamIds !== []) {
                    $query->orWhereIn('team_id', $teamIds);
                }
            })
            ->with([
                'team:id,name',
                'participantRecords' => fn ($query) => $query->where('user_id', $managedChild->id),
            ])
            ->orderBy('start_time')
            ->limit(20)
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'type' => $event->type,
                'start_time' => $event->start_time?->toJSON(),
                'end_time' => $event->end_time?->toJSON(),
                'location_name' => $event->location_name,
                'location_city' => $event->location_city,
                'team' => $event->team ? [
                    'id' => $event->team->id,
                    'name' => $event->team->name,
                ] : null,
                'participation_status' => $event->participantRecords->first()?->status,
            ])
            ->values();

        $since = now()->subDays(28);
        $logs = TrainingLog::query()
            ->where('user_id', $managedChild->id)
            ->where('performed_at', '>=', $since)
            ->whereIn('status', ['completed', 'in_progress'])
            ->get(['performed_at', 'duration_minutes', 'distance_meters', 'calories', 'status']);

        return response()->json([
            'data' => [
                'child' => $childData,
                'access' => [
                    'approved' => true,
                    'scope' => 'safe_aggregates',
                    'private_details_hidden' => true,
                    'notes_visible' => false,
                ],
                'upcoming_events' => $events,
                'training' => [
                    'period_days' => 28,
                    'sessions' => $logs->count(),
                    'duration_minutes' => (int) $logs->sum('duration_minutes'),
                    'distance_meters' => (int) $logs->sum('distance_meters'),
                    'calories' => (int) $logs->sum('calories'),
                    'last_performed_at' => $logs->sortByDesc('performed_at')->first()?->performed_at?->toJSON(),
                    'private_notes_hidden' => true,
                ],
            ],
        ]);
    }

    public function resendOwnConsent(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(MinorSafety::isUnderConsentAge($user), 422, 'guardian_consent_not_required');
        abort_if(MinorSafety::hasResolvedGuardianConsent($user), 422, 'guardian_consent_already_approved');
        abort_if(blank($user->guardian_email), 422, 'guardian_email_missing');

        $this->assertResendAvailable($user);

        $user->forceFill([
            'guardian_consent_requested_at' => now(),
            'guardian_consent_rejected_at' => null,
            'guardian_consent_token' => $user->guardian_consent_token ?: Str::random(64),
            'guardian_consent_version' => config('guardian.consent_version'),
            ...MinorSafety::privacyDefaults(),
        ])->save();

        if (! $user->hasRole('minor_pending_consent')) {
            $user->assignRole('minor_pending_consent');
        }

        GuardianConsentNotifier::send($user, (string) $user->guardian_email);

        return response()->json([
            'message' => self::RESPONSE_CONSENT_RESENT,
            'message_text' => __('guardian.responses.consent_resent'),
            'data' => $this->consentData($user->refresh()),
        ]);
    }

    public function approve(Request $request, int $child): JsonResponse
    {
        $guardian = $request->user();
        $this->assertCanManageChildren($guardian);

        $managedChild = DB::transaction(function () use ($guardian, $child) {
            $managedChild = $this->manageableChildQuery($guardian)
                ->whereKey($child)
                ->lockForUpdate()
                ->firstOrFail();

            $managedChild->forceFill([
                'guardian_user_id' => $guardian->id,
                'guardian_consent_at' => now(),
                'guardian_consent_rejected_at' => null,
                'guardian_consent_revoked_at' => null,
                'guardian_consent_revoked_by_email' => null,
                'guardian_consent_token' => null,
                'guardian_consent_version' => $managedChild->guardian_consent_version ?: config('guardian.consent_version'),
                ...MinorSafety::privacyDefaults(),
            ])->save();

            if ($managedChild->hasRole('minor_pending_consent')) {
                $managedChild->removeRole('minor_pending_consent');
            }

            if (! $managedChild->hasRole('minor_player')) {
                $managedChild->assignRole('minor_player');
            }

            return $managedChild->refresh();
        });

        AppNotification::sendLocalized(
            $managedChild,
            'guardian.consent_approved',
            'guardian.notifications.approved_title',
            'guardian.notifications.approved_body',
            data: [
                'minor_id' => $managedChild->id,
                'guardian_user_id' => $guardian->id,
                'url' => route('auth.dashboard'),
            ],
        );

        return response()->json([
            'message' => self::RESPONSE_CONSENT_APPROVED,
            'message_text' => __('guardian.responses.consent_approved'),
            'data' => $this->childData($managedChild),
        ]);
    }

    public function revoke(Request $request, int $child): JsonResponse
    {
        $guardian = $request->user();
        $this->assertCanManageChildren($guardian);
        $guardianEmail = $this->normalizedEmail($guardian->email);

        $managedChild = DB::transaction(function () use ($guardian, $guardianEmail, $child) {
            $managedChild = $this->manageableChildQuery($guardian)
                ->whereKey($child)
                ->lockForUpdate()
                ->firstOrFail();

            $managedChild->forceFill([
                'guardian_consent_at' => null,
                'guardian_consent_rejected_at' => null,
                'guardian_consent_revoked_at' => now(),
                'guardian_consent_revoked_by_email' => $guardianEmail,
                'guardian_consent_requested_at' => now(),
                ...MinorSafety::privacyDefaults(),
            ])->save();

            if ($managedChild->hasRole('minor_player')) {
                $managedChild->removeRole('minor_player');
            }

            if (! $managedChild->hasRole('minor_pending_consent')) {
                $managedChild->assignRole('minor_pending_consent');
            }

            return $managedChild->refresh();
        });

        AppNotification::sendLocalized(
            $managedChild,
            'guardian.consent_revoked',
            'guardian.notifications.revoked_title',
            'guardian.notifications.revoked_body',
            data: [
                'minor_id' => $managedChild->id,
                'guardian_email' => $guardianEmail,
                'url' => route('guardian-consent.pending'),
            ],
        );

        return response()->json([
            'message' => self::RESPONSE_CONSENT_REVOKED,
            'message_text' => __('guardian.responses.consent_revoked'),
            'data' => $this->childData($managedChild),
        ]);
    }

    public function resend(Request $request, int $child): JsonResponse
    {
        $guardian = $request->user();
        $this->assertCanManageChildren($guardian);

        $managedChild = DB::transaction(function () use ($guardian, $child) {
            $managedChild = $this->manageableChildQuery($guardian)
                ->whereKey($child)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(MinorSafety::hasResolvedGuardianConsent($managedChild), 422, 'guardian_consent_already_approved');
            abort_if(blank($managedChild->guardian_email), 422, 'guardian_email_missing');
            $this->assertResendAvailable($managedChild);

            $managedChild->forceFill([
                'guardian_consent_requested_at' => now(),
                'guardian_consent_rejected_at' => null,
                'guardian_consent_token' => $managedChild->guardian_consent_token ?: Str::random(64),
                'guardian_consent_version' => config('guardian.consent_version'),
                ...MinorSafety::privacyDefaults(),
            ])->save();

            if (! $managedChild->hasRole('minor_pending_consent')) {
                $managedChild->assignRole('minor_pending_consent');
            }

            return $managedChild->refresh();
        });

        GuardianConsentNotifier::send($managedChild, (string) $managedChild->guardian_email);

        return response()->json([
            'message' => self::RESPONSE_CONSENT_RESENT,
            'message_text' => __('guardian.responses.consent_resent'),
            'data' => $this->childData($managedChild),
        ]);
    }

    private function childrenQuery(User $guardian): Builder
    {
        $guardianEmail = $this->normalizedEmail($guardian->email);

        return User::query()
            ->where(function (Builder $query) use ($guardian, $guardianEmail) {
                $query->where('guardian_user_id', $guardian->id)
                    ->orWhereRaw('LOWER(TRIM(guardian_email)) = ?', [$guardianEmail]);
            })
            ->whereNotNull('birth_date')
            ->whereDate('birth_date', '>', now()->subYears(MinorSafety::CONSENT_AGE)->toDateString());
    }

    private function manageableChildQuery(User $guardian): Builder
    {
        return $this->childrenQuery($guardian);
    }

    private function childData(User $child): array
    {
        MinorSafety::enforcePrivacyDefaults($child);
        $child->refresh();

        return [
            'id' => $child->id,
            'name' => $child->name,
            'first_name' => $child->first_name,
            'last_name' => $child->last_name,
            'email' => $child->email,
            'birth_date' => $child->birth_date?->toDateString(),
            'age' => $child->birth_date?->age,
            'status' => $this->consentStatusFor($child),
            'requested_at' => $child->guardian_consent_requested_at?->toJSON(),
            'approved_at' => $child->guardian_consent_at?->toJSON(),
            'rejected_at' => $child->guardian_consent_rejected_at?->toJSON(),
            'revoked_at' => $child->guardian_consent_revoked_at?->toJSON(),
            'resend_available_in' => $this->resendAvailableIn($child),
            'consent_version' => $child->guardian_consent_version ?: config('guardian.consent_version'),
            'privacy' => [
                'profile_visibility' => $child->profile_visibility,
                'direct_message_privacy' => $child->direct_message_privacy,
                'friend_request_privacy' => $child->friend_request_privacy,
                'direct_messages_enabled' => MinorSafety::hasResolvedGuardianConsent($child),
            ],
        ];
    }

    private function consentData(User $user): array
    {
        $required = MinorSafety::isUnderConsentAge($user);

        return [
            'required' => $required,
            'status' => $required ? $this->consentStatusFor($user) : 'not_required',
            'guardian_email' => $required ? $user->guardian_email : null,
            'requested_at' => $user->guardian_consent_requested_at?->toJSON(),
            'approved_at' => $user->guardian_consent_at?->toJSON(),
            'rejected_at' => $user->guardian_consent_rejected_at?->toJSON(),
            'revoked_at' => $user->guardian_consent_revoked_at?->toJSON(),
            'resend_available_in' => $required ? $this->resendAvailableIn($user) : 0,
            'consent_version' => $required
                ? ($user->guardian_consent_version ?: config('guardian.consent_version'))
                : null,
            'privacy' => [
                'profile_visibility' => $user->profile_visibility,
                'direct_message_privacy' => $user->direct_message_privacy,
                'friend_request_privacy' => $user->friend_request_privacy,
                'direct_messages_enabled' => MinorSafety::hasResolvedGuardianConsent($user),
            ],
        ];
    }

    private function emptyTrainingSummary(): array
    {
        return [
            'period_days' => 28,
            'sessions' => 0,
            'duration_minutes' => 0,
            'distance_meters' => 0,
            'calories' => 0,
            'last_performed_at' => null,
            'private_notes_hidden' => true,
        ];
    }

    private function consentStatusFor(User $user): string
    {
        if (MinorSafety::hasResolvedGuardianConsent($user)) {
            return 'approved';
        }

        if ($user->guardian_consent_revoked_at) {
            return 'revoked';
        }

        if ($user->guardian_consent_rejected_at) {
            return 'rejected';
        }

        return 'pending';
    }

    private function resendAvailableIn(User $user): int
    {
        if (! $user->guardian_consent_requested_at) {
            return 0;
        }

        return (int) max(0, ceil(60 - $user->guardian_consent_requested_at->diffInSeconds(now())));
    }

    private function assertResendAvailable(User $user): void
    {
        abort_if($this->resendAvailableIn($user) > 0, 422, 'guardian_consent_resend_too_soon');
    }

    private function assertCanViewChildren(User $user): void
    {
        abort_unless(
            $user->hasAnyRole(['guardian', 'parent'])
                || $user->can('guardians.children.view'),
            403,
            'guardian_access_forbidden',
        );
    }

    private function assertCanManageChildren(User $user): void
    {
        abort_unless($this->canManageChildren($user), 403, 'guardian_manage_forbidden');
    }

    private function canManageChildren(User $user): bool
    {
        return $user->hasAnyRole(['guardian', 'parent'])
            || $user->can('guardians.children.manage');
    }

    private function normalizedEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }
}
