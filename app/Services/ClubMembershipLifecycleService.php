<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\ClubInventoryLoan;
use App\Models\ClubMembershipProspect;
use App\Models\ClubMembershipRequest;
use App\Models\Invoice;
use App\Models\User;
use App\Support\AppNotification;
use App\Support\ClubAuditLog;
use App\Support\ClubMembershipApplication;
use App\Support\ClubMembershipInput;
use App\Support\ClubPermissions;
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
        abort_if(
            ClubMembershipRequest::query()
                ->where('club_id', $club->id)
                ->where('user_id', $applicant->id)
                ->where('type', 'membership')
                ->whereIn('status', ['information_requested', 'waitlisted'])
                ->exists(),
            422,
            __('organization.club.request_closed'),
        );

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
            $this->linkProspectApplication($club, $applicant, $membershipRequest);

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

    public function requestMembershipChange(Club $club, User $member, array $data): ClubMembershipRequest
    {
        abort_unless($this->hasActiveMembership($club, $member), 422, __('organization.club.request_closed'));

        $membershipTypeId = filled($data['club_membership_type_id'] ?? null)
            ? (int) $data['club_membership_type_id']
            : null;
        $departmentId = filled($data['club_department_id'] ?? null)
            ? (int) $data['club_department_id']
            : null;
        abort_if($membershipTypeId === null && $departmentId === null, 422, __('organization.club.membership_change_target_required'));

        $membershipType = $membershipTypeId
            ? $club->membershipTypes()
                ->whereKey($membershipTypeId)
                ->where('is_active', true)
                ->where('is_public', true)
                ->firstOrFail()
            : null;
        $department = $departmentId
            ? $club->departments()
                ->whereKey($departmentId)
                ->where('is_public', true)
                ->firstOrFail()
            : null;
        $currentMembership = $club->users()->where('users.id', $member->id)->firstOrFail();
        if ($membershipTypeId !== null) {
            abort_if(
                (int) ($currentMembership->pivot?->club_membership_type_id ?? 0) === $membershipTypeId,
                422,
                __('organization.club.membership_change_same_type'),
            );
        }
        if ($departmentId !== null) {
            abort_if(
                $this->memberDepartmentIds($club, $member)->contains($departmentId),
                422,
                __('organization.club.membership_change_same_department'),
            );
        }

        $preview = $membershipTypeId !== null
            ? $this->contributions->resolve(
                $club,
                $member,
                $membershipTypeId,
                null,
                $currentMembership->pivot?->family_group_key,
            )
            : [];

        $membershipRequest = DB::transaction(function () use ($club, $member, $data, $membershipTypeId, $departmentId, $preview): ClubMembershipRequest {
            $membershipRequest = ClubMembershipRequest::query()->updateOrCreate(
                [
                    'club_id' => $club->id,
                    'user_id' => $member->id,
                    'type' => 'membership_change',
                    'status' => 'pending',
                ],
                [
                    'club_membership_type_id' => $membershipTypeId,
                    'club_department_id' => $departmentId,
                    'message' => filled($data['message'] ?? null) ? trim((string) $data['message']) : null,
                    'preview_amount' => $preview['amount'] ?? null,
                    'preview_base_amount' => $preview['base_amount'] ?? null,
                    'preview_discount_amount' => $preview['discount_amount'] ?? null,
                    'preview_rule_type' => $preview['rule_type'] ?? null,
                    'preview_interval' => $preview['interval'] ?? null,
                ],
            );

            ClubAuditLog::record($club, $member, 'club.membership_change.requested', $membershipRequest, [
                'request_type' => 'membership_change',
                'target_user_id' => $member->id,
                'membership_type_id' => $membershipTypeId,
                'department_id' => $departmentId,
            ]);

            return $membershipRequest;
        });

        $this->notifyManagers(
            $club,
            'club.membership_change_requested',
            'organization.notifications.membership_change_requested_title',
            'organization.notifications.membership_change_requested_body',
            [
                'user' => $member->name,
                'club' => $club->name,
                'membership_type' => $this->membershipChangeTargetLabel($membershipType, $department),
            ],
            $this->managerRequestLinks($club, $membershipRequest),
            $member->id,
        );

        return $membershipRequest;
    }

    private function memberDepartmentIds(Club $club, User $member)
    {
        $primaryDepartmentId = $club->users()
            ->whereKey($member->id)
            ->first()?->pivot?->club_department_id;

        return $club->teams()
            ->whereNotNull('club_department_id')
            ->whereHas('users', fn ($query) => $query->where('users.id', $member->id))
            ->pluck('club_department_id')
            ->when($primaryDepartmentId, fn ($ids) => $ids->push($primaryDepartmentId))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function membershipChangeTargetLabel(?object $membershipType, ?ClubDepartment $department): ?string
    {
        return $membershipType?->name ?: $department?->name;
    }

    public function requestTermination(Club $club, User $member, array $data): ClubMembershipRequest
    {
        abort_if($club->owner_id === $member->id, 422, __('organization.club.termination_owner_forbidden'));
        abort_unless($this->hasActiveMembership($club, $member), 404);
        $this->assertNoExitBlockers($club, $member);

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

    public function withdrawMembership(Club $club, User $applicant, string $type = 'membership'): ClubMembershipRequest
    {
        abort_unless(in_array($type, ['membership', 'termination'], true), 422);

        $membershipRequest = DB::transaction(function () use ($club, $applicant, $type): ClubMembershipRequest {
            $membershipRequest = ClubMembershipRequest::query()
                ->where('club_id', $club->id)
                ->where('user_id', $applicant->id)
                ->where('type', $type)
                ->whereIn('status', ['pending', 'information_requested', 'waitlisted'])
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
                'request_type' => $type,
                'target_user_id' => $applicant->id,
            ]);

            return $membershipRequest;
        });

        $this->notifyManagers(
            $club,
            $type === 'termination' ? 'club.membership_termination_withdrawn' : 'club.membership_request_withdrawn',
            'organization.notifications.request_withdrawn_title',
            'organization.notifications.request_withdrawn_body',
            ['user' => $applicant->name, 'club' => $club->name],
            $this->managerRequestLinks($club, $membershipRequest),
            $applicant->id,
        );

        return $membershipRequest;
    }

    public function requestInformation(
        Club $club,
        ClubMembershipRequest $request,
        User $reviewer,
        string $message,
    ): ClubMembershipRequest {
        $membershipRequest = DB::transaction(function () use ($club, $request, $reviewer, $message): ClubMembershipRequest {
            $membershipRequest = ClubMembershipRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless((int) $membershipRequest->club_id === (int) $club->id, 404);
            abort_unless($membershipRequest->type === 'membership', 422, __('organization.club.request_closed'));
            abort_unless(in_array($membershipRequest->status, ['pending', 'information_requested', 'waitlisted'], true), 422, __('organization.club.request_closed'));

            $previousStatus = $membershipRequest->status;
            $membershipRequest->update([
                'status' => 'information_requested',
                'information_request_message' => trim($message),
                'information_requested_by' => $reviewer->id,
                'information_requested_at' => now(),
                'applicant_response_message' => null,
                'applicant_responded_at' => null,
                'waitlisted_by' => null,
                'waitlisted_at' => null,
            ]);
            ClubAuditLog::record($club, $reviewer, 'club.membership_request.information_requested', $membershipRequest, [
                'request_type' => $membershipRequest->type,
                'target_user_id' => $membershipRequest->user_id,
                'from_status' => $previousStatus,
                'to_status' => 'information_requested',
            ]);

            return $membershipRequest;
        });

        AppNotification::sendLocalized(
            $membershipRequest->user_id,
            'club.membership_request_information_requested',
            'organization.notifications.request_information_title',
            'organization.notifications.request_information_body',
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

    public function respondToInformationRequest(
        Club $club,
        ClubMembershipRequest $request,
        User $applicant,
        array $data,
    ): ClubMembershipRequest {
        if (blank($data['message'] ?? null)
            && empty($data['application_data'] ?? [])
            && empty($data['accepted_documents'] ?? [])) {
            throw ValidationException::withMessages([
                'message' => __('validation.required', ['attribute' => __('organization.club.response')]),
            ]);
        }

        $membershipRequest = DB::transaction(function () use ($club, $request, $applicant, $data): ClubMembershipRequest {
            $membershipRequest = ClubMembershipRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless((int) $membershipRequest->club_id === (int) $club->id, 404);
            abort_unless((int) $membershipRequest->user_id === (int) $applicant->id, 403);
            abort_unless($membershipRequest->type === 'membership' && $membershipRequest->status === 'information_requested', 422, __('organization.club.request_closed'));

            $respondedAt = now();
            $changes = [
                'status' => 'pending',
                'applicant_response_message' => filled($data['message'] ?? null) ? trim((string) $data['message']) : null,
                'applicant_responded_at' => $respondedAt,
            ];

            if (array_key_exists('application_data', $data)) {
                $applicationData = array_merge(
                    $membershipRequest->application_data ?? [],
                    is_array($data['application_data']) ? $data['application_data'] : [],
                );
                $changes['application_data'] = $this->validatedApplicationData(
                    $club,
                    $applicant,
                    $membershipRequest->club_membership_type_id,
                    $applicationData,
                );
            }

            if (array_key_exists('accepted_documents', $data)) {
                $acceptedDocuments = collect($membershipRequest->accepted_documents ?? [])
                    ->filter(fn ($document) => is_array($document) && filled($document['id'] ?? null))
                    ->mapWithKeys(fn (array $document) => [(string) $document['id'] => true])
                    ->merge(is_array($data['accepted_documents']) ? $data['accepted_documents'] : [])
                    ->all();
                $changes['accepted_documents'] = $this->validatedDocuments(
                    $club,
                    $membershipRequest->club_membership_type_id,
                    $acceptedDocuments,
                    $respondedAt->toIso8601String(),
                );
            }

            $membershipRequest->update($changes);
            ClubAuditLog::record($club, $applicant, 'club.membership_request.information_provided', $membershipRequest, [
                'request_type' => $membershipRequest->type,
                'target_user_id' => $applicant->id,
                'from_status' => 'information_requested',
                'to_status' => 'pending',
            ]);

            return $membershipRequest;
        });

        $this->notifyManagers(
            $club,
            'club.membership_request_information_provided',
            'organization.notifications.request_information_provided_title',
            'organization.notifications.request_information_provided_body',
            ['user' => $applicant->name, 'club' => $club->name],
            $this->managerRequestLinks($club, $membershipRequest),
            $applicant->id,
        );

        return $membershipRequest;
    }

    public function waitlist(Club $club, ClubMembershipRequest $request, User $reviewer, ?string $note = null): ClubMembershipRequest
    {
        $membershipRequest = DB::transaction(function () use ($club, $request, $reviewer, $note): ClubMembershipRequest {
            $membershipRequest = ClubMembershipRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless((int) $membershipRequest->club_id === (int) $club->id, 404);
            abort_unless($membershipRequest->type === 'membership' && $membershipRequest->status === 'pending', 422, __('organization.club.request_closed'));

            $membershipRequest->update([
                'status' => 'waitlisted',
                'waitlisted_by' => $reviewer->id,
                'waitlisted_at' => now(),
                'review_note' => filled($note) ? trim((string) $note) : null,
            ]);
            ClubAuditLog::record($club, $reviewer, 'club.membership_request.waitlisted', $membershipRequest, [
                'request_type' => $membershipRequest->type,
                'target_user_id' => $membershipRequest->user_id,
                'from_status' => 'pending',
                'to_status' => 'waitlisted',
            ]);

            return $membershipRequest;
        });

        AppNotification::sendLocalized(
            $membershipRequest->user_id,
            'club.membership_request_waitlisted',
            'organization.notifications.request_waitlisted_title',
            'organization.notifications.request_waitlisted_body',
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

    public function approve(Club $club, ClubMembershipRequest $request, User $reviewer, ?string $reviewNote = null): ClubMembershipRequest
    {
        $membershipRequest = DB::transaction(function () use ($club, $request, $reviewer, $reviewNote): ClubMembershipRequest {
            $membershipRequest = ClubMembershipRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless((int) $membershipRequest->club_id === (int) $club->id, 404);
            abort_unless(in_array($membershipRequest->status, ['pending', 'waitlisted'], true), 422, __('organization.club.request_closed'));
            abort_if(
                (int) $membershipRequest->user_id === (int) $reviewer->id,
                422,
                __('organization.club.review_second_person'),
            );

            $previousStatus = $membershipRequest->status;
            if ($membershipRequest->type === 'termination') {
                $departing = User::query()->findOrFail($membershipRequest->user_id);
                $this->assertNoExitBlockers($club, $departing);
            }
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
                'from_status' => $previousStatus,
                'to_status' => 'approved',
            ]);
            if ($membershipRequest->type === 'membership') {
                $this->convertProspect($club, $membershipRequest, $reviewer);
            }

            return $membershipRequest;
        });

        if ($membershipRequest->type === 'termination' && $membershipRequest->requested_termination_on) {
            $departing = User::query()->find($membershipRequest->user_id);
            if ($departing) {
                app(ClubAccessHandoverService::class)->ensure(
                    $club,
                    $departing,
                    $membershipRequest->requested_termination_on,
                );
            }
        }

        $approvalNotification = $this->approvalNotification($club, $membershipRequest);

        AppNotification::sendLocalized(
            $membershipRequest->user_id,
            'club.membership_request_approved',
            $approvalNotification['title_key'],
            $approvalNotification['body_key'],
            $approvalNotification['replace'],
            [
                'url' => '/clubs/'.$club->id,
                'mobile_url' => 'airmius://membership-applications/'.$membershipRequest->id,
                'club_id' => $club->id,
                'request_id' => $membershipRequest->id,
                'request_type' => $membershipRequest->type,
                ...$approvalNotification['data'],
            ],
        );

        return $membershipRequest;
    }

    public function decline(Club $club, ClubMembershipRequest $request, User $reviewer, ?string $reviewNote = null): ClubMembershipRequest
    {
        $membershipRequest = DB::transaction(function () use ($club, $request, $reviewer, $reviewNote): ClubMembershipRequest {
            $membershipRequest = ClubMembershipRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless((int) $membershipRequest->club_id === (int) $club->id, 404);
            abort_unless(in_array($membershipRequest->status, ['pending', 'information_requested', 'waitlisted'], true), 422, __('organization.club.request_closed'));

            $previousStatus = $membershipRequest->status;
            $membershipRequest->update([
                'status' => 'declined',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => filled($reviewNote) ? trim((string) $reviewNote) : null,
            ]);
            ClubAuditLog::record($club, $reviewer, 'club.membership_request.declined', $membershipRequest, [
                'request_type' => $membershipRequest->type,
                'target_user_id' => $membershipRequest->user_id,
                'from_status' => $previousStatus,
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
        if ($membershipRequest->type === 'membership_change') {
            $changes = [];
            if ($membershipRequest->club_membership_type_id) {
                $membershipType = $club->membershipTypes()
                    ->whereKey($membershipRequest->club_membership_type_id)
                    ->where('is_active', true)
                    ->firstOrFail();
                $changes['club_membership_type_id'] = $membershipType->id;
                if ($membershipRequest->preview_amount !== null) {
                    $changes['contribution_amount'] = $membershipRequest->preview_amount;
                }
                if (filled($membershipRequest->preview_interval)) {
                    $changes['contribution_interval'] = $membershipRequest->preview_interval;
                }
            }
            if ($membershipRequest->club_department_id) {
                $department = $club->departments()
                    ->whereKey($membershipRequest->club_department_id)
                    ->where('is_public', true)
                    ->firstOrFail();
                $changes['club_department_id'] = $department->id;
            }
            abort_if($changes === [], 422, __('organization.club.membership_change_target_required'));
            $club->users()->updateExistingPivot($membershipRequest->user_id, $changes);

            return;
        }

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

    /**
     * @return array{
     *     title_key: string,
     *     body_key: string,
     *     replace: array<string, mixed>,
     *     data: array<string, mixed>
     * }
     */
    private function approvalNotification(Club $club, ClubMembershipRequest $membershipRequest): array
    {
        $replace = ['club' => $club->name];
        $data = [];

        if ($membershipRequest->type === 'membership') {
            $membershipTypeName = $membershipRequest->membershipType?->name;
            if (filled($membershipTypeName)) {
                $replace['membership_type'] = $membershipTypeName;
                $data['club_membership_type_id'] = $membershipRequest->club_membership_type_id;
                $data['membership_type_name'] = $membershipTypeName;
            }

            return [
                'title_key' => 'organization.notifications.membership_welcome_title',
                'body_key' => filled($membershipTypeName)
                    ? 'organization.notifications.membership_welcome_with_type_body'
                    : 'organization.notifications.membership_welcome_body',
                'replace' => $replace,
                'data' => $data + ['lifecycle_event' => 'membership_admission_confirmed'],
            ];
        }

        if ($membershipRequest->type === 'membership_change') {
            $targetName = $this->membershipChangeTargetLabel($membershipRequest->membershipType, $membershipRequest->department)
                ?? AppNotification::translatedReplacement(
                    'organization.notifications.membership_change_type_fallback',
                    'the selected membership type',
                );
            $replace['membership_type'] = $targetName;

            return [
                'title_key' => 'organization.notifications.membership_change_confirmed_title',
                'body_key' => 'organization.notifications.membership_change_confirmed_body',
                'replace' => $replace,
                'data' => [
                    'lifecycle_event' => 'membership_change_confirmed',
                    'club_membership_type_id' => $membershipRequest->club_membership_type_id,
                    'club_department_id' => $membershipRequest->club_department_id,
                    'membership_type_name' => $membershipRequest->membershipType?->name,
                    'department_name' => $membershipRequest->department?->name,
                ],
            ];
        }

        if ($membershipRequest->type === 'pause') {
            $replace['from'] = $membershipRequest->requested_pause_from?->toDateString();
            $replace['until'] = $membershipRequest->requested_pause_until?->toDateString()
                ?? AppNotification::translatedReplacement(
                    'organization.notifications.pause_confirmation_open_ended',
                    'open-ended',
                );

            return [
                'title_key' => 'organization.notifications.pause_confirmed_title',
                'body_key' => 'organization.notifications.pause_confirmed_body',
                'replace' => $replace,
                'data' => [
                    'lifecycle_event' => 'membership_pause_confirmed',
                    'requested_pause_from' => $membershipRequest->requested_pause_from?->toDateString(),
                    'requested_pause_until' => $membershipRequest->requested_pause_until?->toDateString(),
                ],
            ];
        }

        if ($membershipRequest->type === 'termination') {
            $terminationDate = $membershipRequest->requested_termination_on?->toDateString();
            $replace['date'] = $terminationDate
                ?? AppNotification::translatedReplacement(
                    'organization.notifications.termination_confirmation_pending_date',
                    'the confirmed leaving date',
                );

            return [
                'title_key' => 'organization.notifications.termination_confirmed_title',
                'body_key' => 'organization.notifications.termination_confirmed_body',
                'replace' => $replace,
                'data' => [
                    'lifecycle_event' => 'membership_termination_confirmed',
                    'requested_termination_on' => $terminationDate,
                    'membership_ends_on' => $terminationDate,
                ],
            ];
        }

        return [
            'title_key' => 'organization.notifications.request_approved_title',
            'body_key' => 'organization.notifications.request_approved_body',
            'replace' => $replace,
            'data' => ['lifecycle_event' => 'request_approved'],
        ];
    }

    private function assertNoExitBlockers(Club $club, User $member): void
    {
        abort_if(
            Invoice::query()
                ->where('club_id', $club->id)
                ->where('user_id', $member->id)
                ->whereIn('status', ['open', 'overdue'])
                ->exists(),
            422,
            __('organization.club.open_invoices_before_leaving'),
        );
        abort_if(
            ClubInventoryLoan::query()
                ->where('club_id', $club->id)
                ->where('borrower_id', $member->id)
                ->where('status', 'active')
                ->exists(),
            422,
            __('organization.club.active_loans_before_leaving'),
        );
    }

    private function linkProspectApplication(Club $club, User $applicant, ClubMembershipRequest $request): void
    {
        $prospect = $this->matchingProspect($club, $applicant);
        if (! $prospect) {
            return;
        }

        $prospect->update([
            'user_id' => $applicant->id,
            'club_membership_request_id' => $request->id,
            'club_membership_type_id' => $request->club_membership_type_id ?: $prospect->club_membership_type_id,
            'status' => 'application',
            'trial_outcome' => $prospect->trial_at ? 'application' : $prospect->trial_outcome,
        ]);
    }

    private function convertProspect(Club $club, ClubMembershipRequest $request, User $reviewer): void
    {
        $applicant = User::query()->find($request->user_id);
        if (! $applicant) {
            return;
        }

        $prospect = ClubMembershipProspect::query()
            ->where('club_id', $club->id)
            ->where(fn ($query) => $query
                ->where('club_membership_request_id', $request->id)
                ->orWhere('user_id', $applicant->id)
                ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($applicant->email)]))
            ->whereNotIn('status', ['converted', 'archived'])
            ->lockForUpdate()
            ->latest('id')
            ->first();
        if (! $prospect) {
            return;
        }

        $previousStatus = $prospect->status;
        $prospect->update([
            'user_id' => $applicant->id,
            'club_membership_request_id' => $request->id,
            'club_membership_type_id' => $request->club_membership_type_id ?: $prospect->club_membership_type_id,
            'status' => 'converted',
            'trial_outcome' => $prospect->trial_at ? 'converted' : $prospect->trial_outcome,
            'converted_at' => now(),
        ]);
        ClubAuditLog::record($club, $reviewer, 'club.membership_prospect.converted', $prospect, [
            'from_status' => $previousStatus,
            'to_status' => 'converted',
            'membership_request_id' => $request->id,
            'target_user_id' => $applicant->id,
        ]);
    }

    private function matchingProspect(Club $club, User $applicant): ?ClubMembershipProspect
    {
        return ClubMembershipProspect::query()
            ->where('club_id', $club->id)
            ->where(fn ($query) => $query
                ->where('user_id', $applicant->id)
                ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($applicant->email)]))
            ->whereNotIn('status', ['converted', 'archived', 'declined'])
            ->lockForUpdate()
            ->latest('id')
            ->first();
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
            ->when($exceptUserId, fn ($query) => $query->where('users.id', '!=', $exceptUserId))
            ->get(['users.id', 'users.language'])
            ->filter(fn (User $manager) => ClubPermissions::allows(
                $club,
                $manager,
                ClubPermissions::MEMBERS_APPROVE,
            ))
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
