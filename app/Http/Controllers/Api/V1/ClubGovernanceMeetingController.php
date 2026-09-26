<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubGovernanceMeeting;
use App\Models\ClubGovernanceMeetingDecision;
use App\Models\ClubGovernanceMeetingDecisionVote;
use App\Models\ClubGovernanceMeetingRecipient;
use App\Models\ClubPolicyDocument;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubGovernanceMeetingController extends Controller
{
    public function index(Request $request, Club $club)
    {
        $this->authorizeGovernance($request, $club, ClubPermissions::GOVERNANCE_VIEW);

        $meetings = $club->governanceMeetings()
            ->with(['governanceBody:id,name,type', 'yearPeriod:id,name,type,starts_on,ends_on'])
            ->withCount(['recipients as attendance_eligible_count' => fn ($query) => $query->where('attendance_eligible', true)])
            ->withCount(['recipients as voting_eligible_count' => fn ($query) => $query->where('voting_eligible', true)])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $meetings->map(fn (ClubGovernanceMeeting $meeting) => $this->meetingPayload($meeting, false))->values()]);
    }

    public function store(Request $request, Club $club)
    {
        $this->authorizeGovernance($request, $club, ClubPermissions::GOVERNANCE_EDIT);
        $meeting = DB::transaction(function () use ($request, $club) {
            $data = $this->meetingData($request, $club);
            $recipientData = $data['recipients'];
            unset($data['recipients']);

            $meeting = $club->governanceMeetings()->create([
                ...$data,
                'created_by' => $request->user()?->id,
            ]);

            foreach ($recipientData as $recipient) {
                $meeting->recipients()->create([
                    ...$this->recipientEligibilityData($recipient, $club, $meeting),
                    'club_id' => $club->id,
                ]);
            }

            $meeting->createVersion($request->user()?->id);

            return $meeting;
        });

        ClubAuditLog::record($club, $request->user(), 'club.governance.meeting.created', $meeting, [
            'entity_type' => 'meeting',
            'recipient_count' => $meeting->recipients()->count(),
        ]);

        return response()->json(['data' => $this->meetingPayload($meeting->load($this->showRelations()), true)], 201);
    }

    public function show(Request $request, Club $club, ClubGovernanceMeeting $meeting)
    {
        $this->authorizeMeeting($request, $club, $meeting, ClubPermissions::GOVERNANCE_VIEW);

        return response()->json(['data' => $this->meetingPayload($meeting->load($this->showRelations()), true)]);
    }

    public function update(Request $request, Club $club, ClubGovernanceMeeting $meeting)
    {
        $this->authorizeMeeting($request, $club, $meeting, ClubPermissions::GOVERNANCE_EDIT);

        DB::transaction(function () use ($request, $club, $meeting) {
            $data = $this->meetingData($request, $club, false);
            unset($data['recipients']);
            $meeting->update($data);
            $meeting->createVersion($request->user()?->id);
        });

        ClubAuditLog::record($club, $request->user(), 'club.governance.meeting.versioned', $meeting, [
            'entity_type' => 'meeting',
            'version' => (int) $meeting->versions()->max('version'),
        ]);

        return response()->json(['data' => $this->meetingPayload($meeting->fresh()->load($this->showRelations()), true)]);
    }

    public function updateRecipientDelivery(Request $request, Club $club, ClubGovernanceMeeting $meeting, ClubGovernanceMeetingRecipient $recipient)
    {
        $this->authorizeMeeting($request, $club, $meeting, ClubPermissions::GOVERNANCE_EDIT);
        abort_unless((int) $recipient->club_id === (int) $club->id && (int) $recipient->club_governance_meeting_id === (int) $meeting->id, 404);

        $data = $request->validate([
            'delivery_status' => ['required', Rule::in(ClubGovernanceMeetingRecipient::DELIVERY_STATUSES)],
            'delivered_at' => ['nullable', 'date'],
            'responded_at' => ['nullable', 'date'],
            'response_status' => ['nullable', 'string', 'max:40'],
        ]);

        if ($data['delivery_status'] === 'delivered' && empty($data['delivered_at'])) {
            $data['delivered_at'] = now();
        }

        $recipient->update($data);

        ClubAuditLog::record($club, $request->user(), 'club.governance.meeting.delivery.updated', $meeting, [
            'entity_type' => 'meeting_recipient',
            'recipient_id' => $recipient->id,
            'delivery_status' => $recipient->delivery_status,
        ]);

        return response()->json(['data' => $this->recipientPayload($recipient->fresh()->load(['user:id,name', 'externalMember:id,name']))]);
    }

    public function storeDecision(Request $request, Club $club, ClubGovernanceMeeting $meeting)
    {
        $this->authorizeMeeting($request, $club, $meeting, ClubPermissions::GOVERNANCE_EDIT);

        $data = $request->validate([
            'type' => ['required', Rule::in(ClubGovernanceMeetingDecision::TYPES)],
            'voting_mode' => ['required', Rule::in(ClubGovernanceMeetingDecision::VOTING_MODES)],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'majority_rule' => ['required', Rule::in(ClubGovernanceMeetingDecision::MAJORITY_RULES)],
            'quorum' => ['nullable', 'integer', 'min:1', 'max:100'],
            'club_policy_document_id' => [
                'nullable',
                'integer',
                Rule::exists('club_policy_documents', 'id')->where('club_id', $club->id),
            ],
            'external_review' => ['nullable', 'array'],
            'external_review.status' => ['nullable', 'string', 'max:40'],
            'external_review.provider' => ['nullable', 'string', 'max:160'],
            'external_review.reviewed_at' => ['nullable', 'date'],
            'external_review.reference' => ['nullable', 'string', 'max:240'],
            'options' => ['nullable', 'array', 'max:30'],
            'options.*' => ['required_with:options', 'string', 'max:80'],
        ]);
        $policyDocument = $this->validatedDecisionPolicyDocument($club, $data);
        $externalReview = $this->normalizedExternalReview($data['external_review'] ?? null);

        $eligibleRecipients = $meeting->recipients()
            ->with(['user:id,name', 'externalMember:id,name'])
            ->where('voting_eligible', true)
            ->orderBy('id')
            ->get();

        $decision = $meeting->decisions()->create([
            ...$data,
            'club_id' => $club->id,
            'club_policy_document_id' => $policyDocument?->id,
            'contract_review_status' => $policyDocument?->workflow_status,
            'external_review' => $externalReview,
            'ballot_salt_hash' => $data['voting_mode'] === 'secret' ? hash('sha256', $this->secretBallotSalt($club, $meeting, $request->user()?->id)) : null,
            'meeting_version' => (int) ($meeting->versions()->max('version') ?: 1),
            'status' => 'open',
            'options' => array_values($data['options'] ?? ($data['type'] === 'motion' ? ['yes', 'no', 'abstain'] : ['abstain'])),
            'eligible_voters' => $eligibleRecipients->count(),
            'recipient_snapshot' => $eligibleRecipients->map(fn (ClubGovernanceMeetingRecipient $recipient) => $this->decisionRecipientSnapshot($recipient))->values()->all(),
            'created_by' => $request->user()?->id,
        ]);

        ClubAuditLog::record($club, $request->user(), 'club.governance.meeting.decision.created', $meeting, [
            'entity_type' => 'meeting_decision',
            'decision_id' => $decision->id,
            'meeting_version' => $decision->meeting_version,
            'voting_mode' => $decision->voting_mode,
            'policy_document_id' => $decision->club_policy_document_id,
        ]);

        return response()->json(['data' => $this->decisionPayload($decision->load('votes'), true)], 201);
    }

    public function castDecisionVote(Request $request, Club $club, ClubGovernanceMeeting $meeting, ClubGovernanceMeetingDecision $decision)
    {
        $this->authorizeMeeting($request, $club, $meeting, ClubPermissions::GOVERNANCE_VIEW);
        $this->assertDecisionBelongsToMeeting($club, $meeting, $decision);
        abort_if($decision->status !== 'open' || $decision->correction_locked_at, 422, 'decision_locked');

        $options = array_values($decision->options ?? []);
        $data = $request->validate([
            'recipient_id' => ['required', 'integer', Rule::exists('club_governance_meeting_recipients', 'id')->where('club_id', $club->id)],
            'choice' => ['required', 'string', 'max:80', Rule::in($options)],
        ]);

        $recipient = $meeting->recipients()
            ->with(['user:id,name', 'externalMember:id,name'])
            ->where('club_id', $club->id)
            ->where('id', $data['recipient_id'])
            ->firstOrFail();

        abort_unless($recipient->voting_eligible, 403);
        if ((int) $recipient->user_id !== (int) $request->user()?->id) {
            $this->authorizeGovernance($request, $club, ClubPermissions::GOVERNANCE_EDIT);
        }

        $secret = $decision->voting_mode === 'secret';
        $identity = $secret
            ? [
                'club_governance_meeting_decision_id' => $decision->id,
                'ballot_hash' => $this->ballotHash($decision, $recipient),
            ]
            : [
                'club_governance_meeting_decision_id' => $decision->id,
                'club_governance_meeting_recipient_id' => $recipient->id,
            ];

        $vote = ClubGovernanceMeetingDecisionVote::query()->updateOrCreate(
            $identity,
            [
                'club_id' => $club->id,
                'club_governance_meeting_recipient_id' => $secret ? null : $recipient->id,
                'user_id' => $secret ? null : $recipient->user_id,
                'club_external_member_id' => $secret ? null : $recipient->club_external_member_id,
                'choice' => $data['choice'],
                'person_name' => $secret ? null : ($recipient->user?->name ?? $recipient->externalMember?->name),
                'recipient_snapshot' => $secret ? null : $this->decisionRecipientSnapshot($recipient),
            ]
        );

        return response()->json(['data' => $this->votePayload($vote->fresh())]);
    }

    public function closeDecision(Request $request, Club $club, ClubGovernanceMeeting $meeting, ClubGovernanceMeetingDecision $decision)
    {
        $this->authorizeMeeting($request, $club, $meeting, ClubPermissions::GOVERNANCE_EDIT);
        $this->assertDecisionBelongsToMeeting($club, $meeting, $decision);

        $result = $this->calculateDecisionResult($decision->load('votes'));
        $decision->update([
            'status' => 'closed',
            'result_snapshot' => $result,
            'outcome' => $result['outcome'],
            'correction_locked_at' => now(),
        ]);

        ClubAuditLog::record($club, $request->user(), 'club.governance.meeting.decision.closed', $meeting, [
            'entity_type' => 'meeting_decision',
            'decision_id' => $decision->id,
            'outcome' => $result['outcome'],
        ]);

        return response()->json(['data' => $this->decisionPayload($decision->fresh()->load('votes'), true)]);
    }

    private function meetingData(Request $request, Club $club, bool $requireRecipients = true): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(ClubGovernanceMeeting::TYPES)],
            'title' => ['required', 'string', 'max:180'],
            'status' => ['required', Rule::in(ClubGovernanceMeeting::STATUSES)],
            'club_governance_body_id' => [
                'nullable',
                Rule::exists('club_governance_bodies', 'id')->where('club_id', $club->id),
            ],
            'club_year_period_id' => [
                'nullable',
                Rule::exists('club_year_periods', 'id')->where('club_id', $club->id),
            ],
            'scheduled_at' => ['nullable', 'date'],
            'location_name' => ['nullable', 'string', 'max:180'],
            'participant_scope' => ['required', Rule::in(ClubGovernanceMeeting::PARTICIPANT_SCOPES)],
            'eligibility_as_of' => ['nullable', 'date_format:Y-m-d'],
            'motions_due_on' => ['nullable', 'date_format:Y-m-d'],
            'invitation_sent_at' => ['nullable', 'date'],
            'agenda_items' => ['nullable', 'array', 'max:50'],
            'agenda_items.*.title' => ['required_with:agenda_items', 'string', 'max:180'],
            'agenda_items.*.description' => ['nullable', 'string', 'max:2000'],
            'materials' => ['nullable', 'array', 'max:50'],
            'materials.*.title' => ['required_with:materials', 'string', 'max:180'],
            'materials.*.url' => ['nullable', 'url', 'max:500'],
            'decision_templates' => ['nullable', 'array', 'max:50'],
            'decision_templates.*.title' => ['required_with:decision_templates', 'string', 'max:180'],
            'decision_templates.*.majority_rule' => ['nullable', 'string', 'max:80'],
            'decision_templates.*.quorum' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'recipients' => [$requireRecipients ? 'required' : 'sometimes', 'array', 'min:1', 'max:500'],
            'recipients.*.user_id' => [
                'nullable', 'required_without:recipients.*.club_external_member_id',
                Rule::exists('club_user', 'user_id')->where('club_id', $club->id),
            ],
            'recipients.*.club_external_member_id' => [
                'nullable', 'required_without:recipients.*.user_id',
                Rule::exists('club_external_members', 'id')->where('club_id', $club->id),
            ],
            'recipients.*.attendance_eligible' => ['sometimes', 'boolean'],
            'recipients.*.voting_eligible' => ['sometimes', 'boolean'],
            'recipients.*.eligibility_source' => ['nullable', 'string', 'max:40'],
            'recipients.*.eligibility_role' => ['nullable', 'string', 'max:80'],
            'recipients.*.delivery_status' => ['required', Rule::in(ClubGovernanceMeetingRecipient::DELIVERY_STATUSES)],
            'recipients.*.delivered_at' => ['nullable', 'date'],
        ]);

        foreach (['title', 'location_name', 'notes'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $data[$field] = $value === '' ? null : $value;
        }

        $data['agenda_items'] = array_values($data['agenda_items'] ?? []);
        $data['materials'] = array_values($data['materials'] ?? []);
        $data['decision_templates'] = array_values($data['decision_templates'] ?? []);
        $data['recipients'] = array_values($data['recipients'] ?? []);
        $data['eligibility_as_of'] = $data['eligibility_as_of']
            ?? CarbonImmutable::parse($data['scheduled_at'] ?? now())->toDateString();

        return $data;
    }

    private function recipientEligibilityData(array $recipient, Club $club, ClubGovernanceMeeting $meeting): array
    {
        $derived = $this->deriveRecipientEligibility($recipient, $club, $meeting);
        $attendance = array_key_exists('attendance_eligible', $recipient)
            ? (bool) $recipient['attendance_eligible']
            : $derived['attendance_eligible'];
        $voting = array_key_exists('voting_eligible', $recipient)
            ? (bool) $recipient['voting_eligible']
            : $derived['voting_eligible'];

        if ($voting) {
            $attendance = true;
        }

        return [
            ...$recipient,
            'attendance_eligible' => $attendance,
            'voting_eligible' => $voting,
            'eligibility_source' => $recipient['eligibility_source'] ?? (
                array_key_exists('attendance_eligible', $recipient) || array_key_exists('voting_eligible', $recipient)
                    ? 'manual'
                    : $derived['eligibility_source']
            ),
            'eligibility_role' => $recipient['eligibility_role'] ?? $derived['eligibility_role'],
        ];
    }

    private function deriveRecipientEligibility(array $recipient, Club $club, ClubGovernanceMeeting $meeting): array
    {
        $asOf = CarbonImmutable::parse($meeting->eligibility_as_of ?? $meeting->scheduled_at ?? now())->toDateString();

        if (! empty($recipient['user_id']) && $meeting->club_governance_body_id) {
            $assignment = DB::table('club_governance_assignments')
                ->where('club_id', $club->id)
                ->where('club_governance_body_id', $meeting->club_governance_body_id)
                ->where('user_id', $recipient['user_id'])
                ->where(fn ($query) => $query->whereNull('starts_on')->orWhere('starts_on', '<=', $asOf))
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $asOf))
                ->orderByDesc('starts_on')
                ->first();

            if ($assignment) {
                return [
                    'attendance_eligible' => true,
                    'voting_eligible' => true,
                    'eligibility_source' => 'governance_assignment',
                    'eligibility_role' => $assignment->position_title,
                ];
            }
        }

        if (! empty($recipient['club_external_member_id']) && $meeting->club_governance_body_id) {
            $assignment = DB::table('club_governance_assignments')
                ->where('club_id', $club->id)
                ->where('club_governance_body_id', $meeting->club_governance_body_id)
                ->where('club_external_member_id', $recipient['club_external_member_id'])
                ->where(fn ($query) => $query->whereNull('starts_on')->orWhere('starts_on', '<=', $asOf))
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $asOf))
                ->orderByDesc('starts_on')
                ->first();

            if ($assignment) {
                return [
                    'attendance_eligible' => true,
                    'voting_eligible' => true,
                    'eligibility_source' => 'governance_assignment',
                    'eligibility_role' => $assignment->position_title,
                ];
            }
        }

        if (! empty($recipient['user_id'])) {
            $membership = DB::table('club_user')
                ->where('club_id', $club->id)
                ->where('user_id', $recipient['user_id'])
                ->first();

            $role = $membership?->role ?? 'member';
            $eligibleByMembership = (bool) $membership && $meeting->participant_scope !== 'body';

            return [
                'attendance_eligible' => $eligibleByMembership,
                'voting_eligible' => $eligibleByMembership && ($membership->membership_status ?? 'active') === 'active',
                'eligibility_source' => 'club_membership',
                'eligibility_role' => $role,
            ];
        }

        $external = DB::table('club_external_members')
            ->where('club_id', $club->id)
            ->where('id', $recipient['club_external_member_id'] ?? 0)
            ->first();

        return [
            'attendance_eligible' => (bool) $external && $meeting->participant_scope !== 'body',
            'voting_eligible' => (bool) $external && $meeting->participant_scope !== 'body' && ($external->membership_status ?? null) === 'active' && ($external->role ?? null) === 'member',
            'eligibility_source' => 'external_member_record',
            'eligibility_role' => $external?->role,
        ];
    }

    private function meetingPayload(ClubGovernanceMeeting $meeting, bool $includeRecipients): array
    {
        return [
            'id' => $meeting->id,
            'club_id' => $meeting->club_id,
            'type' => $meeting->type,
            'title' => $meeting->title,
            'status' => $meeting->status,
            'scheduled_at' => $meeting->scheduled_at?->toISOString(),
            'location_name' => $meeting->location_name,
            'participant_scope' => $meeting->participant_scope,
            'eligibility_as_of' => $meeting->eligibility_as_of?->format('Y-m-d'),
            'motions_due_on' => $meeting->motions_due_on?->format('Y-m-d'),
            'invitation_sent_at' => $meeting->invitation_sent_at?->toISOString(),
            'agenda_items' => $meeting->agenda_items ?? [],
            'materials' => $meeting->materials ?? [],
            'decision_templates' => $meeting->decision_templates ?? [],
            'governance_body' => $meeting->governanceBody ? [
                'id' => $meeting->governanceBody->id,
                'name' => $meeting->governanceBody->name,
                'type' => $meeting->governanceBody->type,
            ] : null,
            'year_period' => $meeting->yearPeriod ? [
                'id' => $meeting->yearPeriod->id,
                'name' => $meeting->yearPeriod->name,
                'type' => $meeting->yearPeriod->type,
                'starts_on' => $meeting->yearPeriod->starts_on?->format('Y-m-d'),
                'ends_on' => $meeting->yearPeriod->ends_on?->format('Y-m-d'),
            ] : null,
            'eligibility' => [
                'attendance_eligible' => (int) ($meeting->attendance_eligible_count ?? $meeting->recipients->where('attendance_eligible', true)->count()),
                'voting_eligible' => (int) ($meeting->voting_eligible_count ?? $meeting->recipients->where('voting_eligible', true)->count()),
            ],
            'versions' => $meeting->relationLoaded('versions') ? $meeting->versions->map(fn ($version) => [
                'id' => $version->id,
                'version' => $version->version,
                'status' => $version->status,
                'scheduled_at' => $version->scheduled_at?->toISOString(),
                'eligibility_as_of' => $version->eligibility_as_of?->format('Y-m-d'),
                'motions_due_on' => $version->motions_due_on?->format('Y-m-d'),
                'invitation_sent_at' => $version->invitation_sent_at?->toISOString(),
                'recipient_snapshot' => $version->recipient_snapshot ?? [],
                'created_at' => $version->created_at?->toISOString(),
            ])->values() : [],
            ...($includeRecipients ? [
                'recipients' => $meeting->recipients->map(fn (ClubGovernanceMeetingRecipient $recipient) => $this->recipientPayload($recipient))->values(),
                'decisions' => $meeting->relationLoaded('decisions')
                    ? $meeting->decisions->map(fn (ClubGovernanceMeetingDecision $decision) => $this->decisionPayload($decision, true))->values()
                    : [],
            ] : []),
        ];
    }

    private function decisionPayload(ClubGovernanceMeetingDecision $decision, bool $includeVotes): array
    {
        return [
            'id' => $decision->id,
            'club_id' => $decision->club_id,
            'meeting_id' => $decision->club_governance_meeting_id,
            'policy_document' => $decision->policyDocument ? [
                'id' => $decision->policyDocument->id,
                'type' => $decision->policyDocument->type,
                'title' => $decision->policyDocument->title,
                'version_label' => $decision->policyDocument->version_label,
                'workflow_status' => $decision->policyDocument->workflow_status,
            ] : null,
            'contract_review_status' => $decision->contract_review_status,
            'external_review' => $decision->external_review ?? [],
            'meeting_version' => $decision->meeting_version,
            'type' => $decision->type,
            'voting_mode' => $decision->voting_mode,
            'title' => $decision->title,
            'status' => $decision->status,
            'majority_rule' => $decision->majority_rule,
            'quorum' => $decision->quorum,
            'options' => $decision->options ?? [],
            'eligible_voters' => (int) $decision->eligible_voters,
            'result' => $decision->result_snapshot ?? $this->calculateDecisionResult($decision),
            'correction_locked_at' => $decision->correction_locked_at?->toISOString(),
            ...($includeVotes ? [
                'votes' => $decision->voting_mode === 'secret'
                    ? []
                    : $decision->votes->map(fn (ClubGovernanceMeetingDecisionVote $vote) => $this->votePayload($vote))->values(),
            ] : []),
        ];
    }

    private function validatedDecisionPolicyDocument(Club $club, array $data): ?ClubPolicyDocument
    {
        if (empty($data['club_policy_document_id'])) {
            return null;
        }

        $document = ClubPolicyDocument::query()
            ->where('club_id', $club->id)
            ->whereKey($data['club_policy_document_id'])
            ->firstOrFail();

        if (! in_array($document->workflow_status, ['approved', 'published'], true)) {
            throw ValidationException::withMessages([
                'club_policy_document_id' => 'Only professionally reviewed contract documents can be voted on.',
            ]);
        }

        return $document;
    }

    private function normalizedExternalReview(?array $review): ?array
    {
        if (! $review) {
            return null;
        }

        return array_filter([
            'status' => $review['status'] ?? null,
            'provider' => $review['provider'] ?? null,
            'reviewed_at' => isset($review['reviewed_at'])
                ? CarbonImmutable::parse($review['reviewed_at'])->toDateString()
                : null,
            'reference' => $review['reference'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function secretBallotSalt(Club $club, ClubGovernanceMeeting $meeting, ?int $actorId): string
    {
        return implode('|', [
            config('app.key'),
            $club->id,
            $meeting->id,
            now()->getTimestamp(),
            $actorId ?: 'system',
            random_int(100000, 999999),
        ]);
    }

    private function ballotHash(ClubGovernanceMeetingDecision $decision, ClubGovernanceMeetingRecipient $recipient): string
    {
        return hash_hmac('sha256', implode('|', [
            $decision->club_id,
            $decision->club_governance_meeting_id,
            $decision->id,
            $recipient->id,
            $recipient->user_id ?: 'external:'.$recipient->club_external_member_id,
        ]), (string) config('app.key'));
    }

    private function votePayload(ClubGovernanceMeetingDecisionVote $vote): array
    {
        return [
            'id' => $vote->id,
            'recipient_id' => $vote->club_governance_meeting_recipient_id,
            'choice' => $vote->choice,
            'person' => ['name' => $vote->person_name],
            'user_id' => $vote->user_id,
            'club_external_member_id' => $vote->club_external_member_id,
            'created_at' => $vote->created_at?->toISOString(),
            'updated_at' => $vote->updated_at?->toISOString(),
        ];
    }

    private function calculateDecisionResult(ClubGovernanceMeetingDecision $decision): array
    {
        $votes = $decision->relationLoaded('votes') ? $decision->votes : $decision->votes()->get();
        $counts = $votes->countBy('choice')->all();
        $cast = $votes->count();
        $eligible = max(0, (int) $decision->eligible_voters);
        $required = $decision->quorum === null ? 0 : (int) ceil($eligible * ((int) $decision->quorum) / 100);
        $quorumReached = $decision->quorum === null || ($eligible > 0 && $cast >= $required);
        $yes = (int) ($counts['yes'] ?? 0);
        $no = (int) ($counts['no'] ?? 0);
        $abstain = (int) ($counts['abstain'] ?? 0);
        $valid = max(0, $cast - $abstain);
        $passed = $decision->type === 'motion'
            ? ($decision->majority_rule === 'absolute' ? $yes > ($eligible / 2) : $yes > $no)
            : false;

        $winner = null;
        if ($decision->type === 'election') {
            $winner = collect($counts)->except('abstain')->sortDesc()->keys()->first();
            $winnerVotes = $winner ? (int) $counts[$winner] : 0;
            $passed = $decision->majority_rule === 'absolute' ? $winnerVotes > ($eligible / 2) : $winnerVotes > 0;
        }

        return [
            'eligible_voters' => $eligible,
            'votes_cast' => $cast,
            'quorum_required' => $required,
            'quorum_reached' => $quorumReached,
            'counts' => $counts,
            'abstentions' => $abstain,
            'valid_votes' => $valid,
            'winner' => $winner,
            'outcome' => $quorumReached && $passed ? 'accepted' : ($quorumReached ? 'rejected' : 'no_quorum'),
        ];
    }

    private function decisionRecipientSnapshot(ClubGovernanceMeetingRecipient $recipient): array
    {
        return [
            'recipient_id' => $recipient->id,
            'user_id' => $recipient->user_id,
            'club_external_member_id' => $recipient->club_external_member_id,
            'person_name' => $recipient->user?->name ?? $recipient->externalMember?->name,
            'voting_eligible' => (bool) $recipient->voting_eligible,
            'eligibility_source' => $recipient->eligibility_source,
            'eligibility_role' => $recipient->eligibility_role,
        ];
    }

    private function assertDecisionBelongsToMeeting(Club $club, ClubGovernanceMeeting $meeting, ClubGovernanceMeetingDecision $decision): void
    {
        abort_unless((int) $decision->club_id === (int) $club->id && (int) $decision->club_governance_meeting_id === (int) $meeting->id, 404);
    }

    private function recipientPayload(ClubGovernanceMeetingRecipient $recipient): array
    {
        return [
            'id' => $recipient->id,
            'attendance_eligible' => (bool) $recipient->attendance_eligible,
            'voting_eligible' => (bool) $recipient->voting_eligible,
            'eligibility_source' => $recipient->eligibility_source,
            'eligibility_role' => $recipient->eligibility_role,
            'delivery_status' => $recipient->delivery_status,
            'delivered_at' => $recipient->delivered_at?->toISOString(),
            'responded_at' => $recipient->responded_at?->toISOString(),
            'response_status' => $recipient->response_status,
            'person' => ['name' => $recipient->user?->name ?? $recipient->externalMember?->name],
            'user_id' => $recipient->user_id,
            'club_external_member_id' => $recipient->club_external_member_id,
        ];
    }

    private function authorizeGovernance(Request $request, Club $club, string $permission): void
    {
        abort_unless($request->user() && ClubPermissions::allows($club, $request->user(), $permission), 403);
    }

    private function authorizeMeeting(Request $request, Club $club, ClubGovernanceMeeting $meeting, string $permission): void
    {
        $this->authorizeGovernance($request, $club, $permission);
        abort_unless((int) $meeting->club_id === (int) $club->id, 404);
    }

    private function showRelations(): array
    {
        return [
            'governanceBody:id,name,type',
            'yearPeriod:id,name,type,starts_on,ends_on',
            'recipients.user:id,name',
            'recipients.externalMember:id,name',
            'decisions.policyDocument:id,type,title,version_label,workflow_status',
            'decisions.votes',
            'versions:id,club_id,club_governance_meeting_id,version,status,scheduled_at,eligibility_as_of,motions_due_on,invitation_sent_at,recipient_snapshot,created_at',
        ];
    }
}
