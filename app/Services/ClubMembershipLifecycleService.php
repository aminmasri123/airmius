<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubMembershipRequest;
use App\Models\Invoice;
use App\Models\User;
use App\Support\AppNotification;
use App\Support\ClubAuditLog;
use App\Support\ClubMembershipApplication;
use App\Support\ClubMembershipInput;
use App\Support\ClubRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClubMembershipLifecycleService
{
    public function __construct(
        private readonly ClubContributionCalculator $contributions,
    ) {}

    public function submitMembership(
        Club $club,
        User $applicant,
        array $data,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): ClubMembershipRequest {
        abort_unless($club->membership_requests_enabled, 403, __('organization.club.applications_closed'));
        abort_if($club->users()->where('users.id', $applicant->id)->exists(), 422, __('organization.club.already_member'));

        $membershipTypeId = filled($data['club_membership_type_id'] ?? null)
            ? (int) $data['club_membership_type_id']
            : null;
        $this->assertMembershipTypeBelongsToClub($club, $membershipTypeId);

        $consentAt = now();
        $applicationData = $this->validatedApplicationData(
            $club,
            $applicant,
            $membershipTypeId,
            $data['application_data'] ?? [],
        );
        $acceptedDocuments = $this->validatedDocuments(
            $club,
            $membershipTypeId,
            $data['accepted_documents'] ?? [],
            $consentAt->toIso8601String(),
        );
        $paymentMethod = $this->validatedPaymentMethod($club, $data['preferred_payment_method'] ?? null);
        $billingInterval = $this->validatedBillingInterval($data['requested_billing_interval'] ?? null);
        $preview = $this->contributions->resolve($club, $applicant, $membershipTypeId);

        $membershipRequest = DB::transaction(function () use (
            $club,
            $applicant,
            $data,
            $membershipTypeId,
            $applicationData,
            $acceptedDocuments,
            $consentAt,
            $paymentMethod,
            $billingInterval,
            $preview,
            $ipAddress,
            $userAgent,
        ): ClubMembershipRequest {
            $membershipRequest = ClubMembershipRequest::query()->updateOrCreate(
                [
                    'club_id' => $club->id,
                    'user_id' => $applicant->id,
                    'type' => 'membership',
                    'status' => 'pending',
                ],
                [
                    'club_membership_type_id' => $membershipTypeId,
                    'message' => filled($data['message'] ?? null) ? trim((string) $data['message']) : null,
                    'application_data' => $applicationData,
                    'accepted_documents' => $acceptedDocuments,
                    'consent_version' => trim((string) ($data['consent_version'] ?? 'membership-v1')) ?: 'membership-v1',
                    'consent_signature' => filled($data['consent_signature'] ?? null) ? trim((string) $data['consent_signature']) : null,
                    'consent_ip' => $ipAddress,
                    'consent_user_agent' => mb_substr((string) $userAgent, 0, 1000),
                    'consent_at' => $consentAt,
                    'preferred_payment_method' => $paymentMethod,
                    'requested_billing_interval' => $billingInterval,
                    'applicant_confirmed_at' => $consentAt,
                    'preview_amount' => $preview['amount'] ?? null,
                    'preview_base_amount' => $preview['base_amount'] ?? null,
                    'preview_discount_amount' => $preview['discount_amount'] ?? null,
                    'preview_rule_type' => $preview['rule_type'] ?? null,
                    'preview_interval' => $billingInterval ?? ($preview['interval'] ?? null),
                ],
            );

            ClubAuditLog::record($club, $applicant, 'club.membership_request.submitted', $membershipRequest, [
                'request_type' => 'membership',
                'target_user_id' => $applicant->id,
                'membership_type_id' => $membershipTypeId,
            ]);

            return $membershipRequest;
        });

        $this->notifyManagers(
            $club,
            'club.membership_request_created',
            'organization.notifications.request_created_title',
            'organization.notifications.request_created_body',
            ['user' => $applicant->name, 'club' => $club->name],
            $this->managerRequestLinks($club, $membershipRequest),
            $applicant->id,
        );

        return $membershipRequest;
    }

    public function requestPause(Club $club, User $member, array $data): ClubMembershipRequest
    {
        abort_unless($club->member_pause_requests_enabled, 403, __('organization.club.pause_requests_disabled'));
        abort_unless($this->hasActiveMembership($club, $member), 422, __('organization.club.request_closed'));

        $membershipRequest = DB::transaction(function () use ($club, $member, $data): ClubMembershipRequest {
            $membershipRequest = ClubMembershipRequest::query()->updateOrCreate(
                [
                    'club_id' => $club->id,
                    'user_id' => $member->id,
                    'type' => 'pause',
                    'status' => 'pending',
                ],
                [
                    'requested_pause_from' => $data['requested_pause_from'],
                    'requested_pause_until' => $data['requested_pause_until'] ?? null,
                    'message' => filled($data['message'] ?? null) ? trim((string) $data['message']) : null,
                ],
            );

            $club->users()->updateExistingPivot($member->id, ['pause_requested_at' => now()]);
            ClubAuditLog::record($club, $member, 'club.membership_pause.requested', $membershipRequest, [
                'request_type' => 'pause',
                'target_user_id' => $member->id,
                'requested_pause_from' => $membershipRequest->requested_pause_from?->toDateString(),
                'requested_pause_until' => $membershipRequest->requested_pause_until?->toDateString(),
            ]);

            return $membershipRequest;
        });

        $this->notifyManagers(
            $club,
            'club.membership_pause_requested',
            'organization.notifications.pause_requested_title',
            'organization.notifications.pause_requested_body',
            ['user' => $member->name, 'club' => $club->name],
            $this->managerRequestLinks($club, $membershipRequest),
            $member->id,
        );

        return $membershipRequest;
    }

    public function requestTermination(Club $club, User $member, array $data): ClubMembershipRequest
    {
        abort_if($club->owner_id === $member->id, 422, __('organization.club.termination_owner_forbidden'));
        abort_unless($this->hasActiveMembership($club, $member), 404);
        abort_if(
            Invoice::query()
                ->where('club_id', $club->id)
                ->where('user_id', $member->id)
                ->whereIn('status', ['open', 'overdue'])
                ->exists(),
            422,
            __('organization.club.open_invoices_before_leaving'),
        );

        $membershipRequest = DB::transaction(function () use ($club, $member, $data): ClubMembershipRequest {
            $membershipRequest = ClubMembershipRequest::query()->updateOrCreate(
                [
                    'club_id' => $club->id,
                    'user_id' => $member->id,
                    'type' => 'termination',
                    'status' => 'pending',
                ],
                [
                    'requested_termination_on' => $data['requested_termination_on'],
                    'termination_reason' => filled($data['termination_reason'] ?? null) ? trim((string) $data['termination_reason']) : null,
                    'message' => filled($data['termination_reason'] ?? null) ? trim((string) $data['termination_reason']) : null,
                ],
            );

            ClubAuditLog::record($club, $member, 'club.membership_termination.requested', $membershipRequest, [
                'request_type' => 'termination',
                'target_user_id' => $member->id,
                'requested_termination_on' => $membershipRequest->requested_termination_on?->toDateString(),
            ]);

            return $membershipRequest;
        });

        $this->notifyManagers(
            $club,
            'club.membership_termination_requested',
            'organization.notifications.termination_requested_title',
            'organization.notifications.termination_requested_body',
            ['user' => $member->name, 'club' => $club->name],
            $this->managerRequestLinks($club, $membershipRequest),
            $member->id,
        );

        return $membershipRequest;
    }

    public function withdrawMembership(Club $club, User $applicant): ClubMembershipRequest
    {
        $membershipRequest = DB::transaction(function () use ($club, $applicant): ClubMembershipRequest {
            $membershipRequest = ClubMembershipRequest::query()
                ->where('club_id', $club->id)
                ->where('user_id', $applicant->id)
                ->where('type', 'membership')
                ->where('status', 'pending')
                ->latest('id')
                ->lockForUpdate()
                ->firstOrFail();

            $membershipRequest->update([
                'status' => 'withdrawn',
                'reviewed_by' => $applicant->id,
                'reviewed_at' => now(),
                'review_note' => null,
            ]);
            ClubAuditLog::record($club, $applicant, 'club.membership_request.withdrawn', $membershipRequest, [
                'request_type' => 'membership',
                'target_user_id' => $applicant->id,
            ]);

            return $membershipRequest;
        });

        $this->notifyManagers(
            $club,
            'club.membership_request_withdrawn',
            'organization.notifications.request_withdrawn_title',
            'organization.notifications.request_withdrawn_body',
            ['user' => $applicant->name, 'club' => $club->name],
            $this->managerRequestLinks($club, $membershipRequest),
            $applicant->id,
        );

        return $membershipRequest;
    }

    public function approve(Club $club, ClubMembershipRequest $request, User $reviewer, ?string $reviewNote = null): ClubMembershipRequest
    {
        $membershipRequest = DB::transaction(function () use ($club, $request, $reviewer, $reviewNote): ClubMembershipRequest {
            $membershipRequest = ClubMembershipRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless((int) $membershipRequest->club_id === (int) $club->id, 404);
            abort_unless($membershipRequest->status === 'pending', 422, __('organization.club.request_closed'));

            $this->applyApprovedTransition($club, $membershipRequest);
            $membershipRequest->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => filled($reviewNote) ? trim((string) $reviewNote) : null,
            ]);
            ClubAuditLog::record($club, $reviewer, 'club.membership_request.approved', $membershipRequest, [
                'request_type' => $membershipRequest->type,
                'target_user_id' => $membershipRequest->user_id,
                'from_status' => 'pending',
                'to_status' => 'approved',
            ]);

            return $membershipRequest;
        });

        AppNotification::sendLocalized(
            $membershipRequest->user_id,
            'club.membership_request_approved',
            'organization.notifications.request_approved_title',
            'organization.notifications.request_approved_body',
            ['club' => $club->name],
            [
                'url' => '/clubs/'.$club->id,
                'mobile_url' => 'airmius://membership-applications/'.$membershipRequest->id,
                'club_id' => $club->id,
                'request_id' => $membershipRequest->id,
            ],
        );

        return $membershipRequest;
    }

    public function decline(Club $club, ClubMembershipRequest $request, User $reviewer, ?string $reviewNote = null): ClubMembershipRequest
    {
        $membershipRequest = DB::transaction(function () use ($club, $request, $reviewer, $reviewNote): ClubMembershipRequest {
            $membershipRequest = ClubMembershipRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless((int) $membershipRequest->club_id === (int) $club->id, 404);
            abort_unless($membershipRequest->status === 'pending', 422, __('organization.club.request_closed'));

            $membershipRequest->update([
                'status' => 'declined',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => filled($reviewNote) ? trim((string) $reviewNote) : null,
            ]);
            ClubAuditLog::record($club, $reviewer, 'club.membership_request.declined', $membershipRequest, [
                'request_type' => $membershipRequest->type,
                'target_user_id' => $membershipRequest->user_id,
                'from_status' => 'pending',
                'to_status' => 'declined',
            ]);

            return $membershipRequest;
        });

        AppNotification::sendLocalized(
            $membershipRequest->user_id,
            'club.membership_request_declined',
            'organization.notifications.request_declined_title',
            'organization.notifications.request_declined_body',
            ['club' => $club->name],
            [
                'url' => '/notifications',
                'mobile_url' => 'airmius://membership-applications/'.$membershipRequest->id,
                'club_id' => $club->id,
                'request_id' => $membershipRequest->id,
            ],
        );

        return $membershipRequest;
    }

    private function applyApprovedTransition(Club $club, ClubMembershipRequest $membershipRequest): void
    {
        if ($membershipRequest->type === 'pause') {
            $club->users()->updateExistingPivot($membershipRequest->user_id, [
                'membership_status' => 'paused',
                'paused_from' => $membershipRequest->requested_pause_from,
                'paused_until' => $membershipRequest->requested_pause_until,
                'pause_requested_at' => null,
            ]);

            return;
        }

        if ($membershipRequest->type === 'termination') {
            $club->users()->updateExistingPivot($membershipRequest->user_id, [
                'membership_ends_on' => ($membershipRequest->requested_termination_on ?: now())->toDateString(),
                'membership_end_notified_at' => null,
                'membership_ended_at' => null,
            ]);

            return;
        }

        if ($membershipRequest->type === 'removal_objection') {
            $club->users()->syncWithoutDetaching([
                $membershipRequest->user_id => [
                    'role' => 'member',
                    'roles' => ['member'],
                    'membership_status' => 'active',
                    'joined_on' => now()->toDateString(),
                ],
            ]);

            return;
        }

        abort_unless($membershipRequest->type === 'membership', 422, __('organization.club.request_closed'));
        $applicationData = $membershipRequest->application_data ?? [];
        $club->users()->syncWithoutDetaching([
            $membershipRequest->user_id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
                'club_membership_type_id' => $membershipRequest->club_membership_type_id,
                'contribution_amount' => $membershipRequest->preview_amount,
                'contribution_interval' => $membershipRequest->preview_interval ?: 'none',
                'payment_method' => $membershipRequest->preferred_payment_method,
                'sepa_iban' => ClubMembershipInput::normalizeIban($applicationData['sepa_iban'] ?? null),
                'sepa_bic' => ClubMembershipInput::normalizeBic($applicationData['sepa_bic'] ?? null),
                'sepa_mandate_active' => (bool) ($applicationData['sepa_mandate_consent'] ?? false),
                'joined_on' => now()->toDateString(),
            ],
        ]);
    }

    private function validatedApplicationData(Club $club, User $applicant, ?int $membershipTypeId, array $input): array
    {
        $selectedTypeFields = $membershipTypeId
            ? $club->membershipTypes()->whereKey($membershipTypeId)->first(['id', 'application_fields'])?->application_fields
            : null;
        $fields = collect(ClubMembershipApplication::fieldsForClub($club->membership_application_fields, $selectedTypeFields))
            ->where('mode', '!=', 'off')
            ->values();
        $values = array_merge(ClubMembershipApplication::prefillFor($applicant), $input);
        $validated = [];
        $errors = [];

        foreach ($fields as $field) {
            $key = $field['key'];
            $value = $values[$key] ?? null;
            $isCheckbox = ($field['type'] ?? null) === 'checkbox';
            $isEmpty = $isCheckbox ? ! (bool) $value : blank($value);

            if (($field['mode'] ?? 'off') === 'required' && $isEmpty) {
                $errors['application_data.'.$key] = $field['label'].' ist erforderlich.';
            }

            if (! $isEmpty && ($field['type'] ?? null) === 'select') {
                $allowed = collect($field['options'] ?? [])->pluck('value')->map(fn ($option) => (string) $option)->all();
                if ($allowed && ! in_array((string) $value, $allowed, true)) {
                    $errors['application_data.'.$key] = $field['label'].' ist ungültig.';

                    continue;
                }
            }

            if (! $isEmpty) {
                $validated[$key] = $isCheckbox ? (bool) $value : trim((string) $value);
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $validated;
    }

    private function validatedDocuments(
        Club $club,
        ?int $membershipTypeId,
        array $acceptedInput,
        string $acceptedAt,
    ): array {
        $documents = collect(ClubMembershipApplication::normalizeDocuments(
            $club->membership_application_documents,
            $club->membership_application_document_types,
        ))
            ->filter(fn (array $document) => empty($document['membership_type_id']) || (int) $document['membership_type_id'] === $membershipTypeId)
            ->where('is_visible', true)
            ->values();
        $acceptedIds = collect($acceptedInput)
            ->filter(fn ($accepted) => (bool) $accepted)
            ->keys()
            ->map(fn ($id) => (string) $id)
            ->all();
        $accepted = [];
        $errors = [];

        foreach ($documents as $document) {
            $isAccepted = in_array((string) $document['id'], $acceptedIds, true);
            if (($document['is_required'] ?? false) && ! $isAccepted) {
                $errors['accepted_documents.'.$document['id']] = $document['title'].' muss bestätigt werden.';
            }
            if ($isAccepted) {
                $accepted[] = [
                    'id' => $document['id'],
                    'type' => $document['type'],
                    'title' => $document['title'],
                    'url' => $document['url'],
                    'file_id' => $document['file_id'] ?? null,
                    'file_name' => $document['file_name'] ?? '',
                    'version' => ClubMembershipApplication::documentVersion($document),
                    'accepted_at' => $acceptedAt,
                ];
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $accepted;
    }

    private function validatedPaymentMethod(Club $club, mixed $paymentMethod): ?string
    {
        if (blank($paymentMethod)) {
            return null;
        }

        $paymentMethod = (string) $paymentMethod;
        if (! in_array($paymentMethod, ClubMembershipApplication::normalizePaymentMethods($club->membership_payment_methods), true)) {
            throw ValidationException::withMessages([
                'preferred_payment_method' => __('organization.club.payment_method_unavailable'),
            ]);
        }

        return $paymentMethod;
    }

    private function validatedBillingInterval(mixed $billingInterval): ?string
    {
        if (blank($billingInterval)) {
            return null;
        }

        $billingInterval = (string) $billingInterval;
        if (! in_array($billingInterval, ClubMembershipInput::CONTRIBUTION_INTERVALS, true)) {
            throw ValidationException::withMessages([
                'requested_billing_interval' => 'Dieses Beitragsintervall ist ungültig.',
            ]);
        }

        return $billingInterval;
    }

    private function assertMembershipTypeBelongsToClub(Club $club, ?int $membershipTypeId): void
    {
        if ($membershipTypeId && ! $club->membershipTypes()->whereKey($membershipTypeId)->exists()) {
            throw ValidationException::withMessages([
                'club_membership_type_id' => __('validation.exists', ['attribute' => 'club_membership_type_id']),
            ]);
        }
    }

    private function hasActiveMembership(Club $club, User $member): bool
    {
        return $club->users()
            ->where('users.id', $member->id)
            ->wherePivot('membership_status', 'active')
            ->exists();
    }

    private function managerRequestLinks(Club $club, ClubMembershipRequest $membershipRequest): array
    {
        return [
            'url' => '/club-memberships?tab=requests&club_id='.$club->id,
            'mobile_url' => 'airmius://clubs/'.$club->id.'/membership-requests',
            'club_id' => $club->id,
            'membership_request_id' => $membershipRequest->id,
        ];
    }

    private function notifyManagers(
        Club $club,
        string $type,
        string $titleKey,
        ?string $bodyKey,
        array $replace,
        array $data,
        ?int $exceptUserId = null,
    ): void {
        $club->users()
            ->tap(fn ($query) => ClubRoles::whereAny($query, ClubRoles::ELEVATED))
            ->when($exceptUserId, fn ($query) => $query->where('users.id', '!=', $exceptUserId))
            ->get(['users.id', 'users.language'])
            ->each(fn (User $manager) => AppNotification::sendLocalized(
                $manager,
                $type,
                $titleKey,
                $bodyKey,
                $replace,
                $data,
            ));
    }
}
