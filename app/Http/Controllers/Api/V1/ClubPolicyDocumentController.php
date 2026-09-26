<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubPolicyDocument;
use App\Models\WorkAutomationJob;
use App\Services\WorkAutomationJobService;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use App\Support\UploadStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubPolicyDocumentController extends Controller
{
    public function __construct(private readonly WorkAutomationJobService $workAutomationJobs) {}

    public function index(Request $request, Club $club)
    {
        [$canView, $canEdit, $canDownload, $canDelete] = $this->viewerAccess($request, $club);
        $documents = $club->policyDocuments()
            ->with('file:id,club_id,display_name,path,type,size')
            ->when(! $canView, fn (Builder $query) => $query->where('is_public', true))
            ->orderBy('type')
            ->orderBy('title')
            ->orderByDesc('valid_from')
            ->get();

        return response()->json(['data' => [
            'documents' => $documents->map(fn (ClubPolicyDocument $document) => $this->payload($document)),
            'types' => ClubPolicyDocument::TYPES,
            'can_manage' => $canEdit || $canDelete,
            'can_edit' => $canEdit,
            'can_download' => $canDownload,
            'can_delete' => $canDelete,
        ]]);
    }

    public function store(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club, ClubPermissions::POLICY_DOCUMENTS_EDIT);
        $data = $this->documentData($request, $club);
        $this->assertNoOverlap($club, $data);
        $document = $club->policyDocuments()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);
        $this->syncLifecycleState($club, $request, $document->refresh());
        $this->syncReviewJob($club, $request, $document);
        $this->audit($club, $request, 'club.policy_document.created', $document);

        return response()->json(['data' => $this->payload($document->load('file'))], 201);
    }

    public function update(
        Request $request,
        Club $club,
        ClubPolicyDocument $policyDocument
    ) {
        $this->authorizeDocument($request, $club, $policyDocument, ClubPermissions::POLICY_DOCUMENTS_EDIT);
        $data = $this->documentData($request, $club, $policyDocument);
        $this->assertPublishedVersionIsImmutable($policyDocument, $data);
        $this->assertNoOverlap($club, $data, $policyDocument);
        $this->assertContributionRuleCoverage($policyDocument, $data);
        $policyDocument->update($data);
        $this->syncLifecycleState($club, $request, $policyDocument->refresh());
        $this->syncReviewJob($club, $request, $policyDocument->refresh());
        $this->audit($club, $request, 'club.policy_document.updated', $policyDocument);

        return response()->json(['data' => $this->payload($policyDocument->refresh()->load('file'))]);
    }

    public function destroy(
        Request $request,
        Club $club,
        ClubPolicyDocument $policyDocument
    ) {
        $this->authorizeDocument($request, $club, $policyDocument, ClubPermissions::POLICY_DOCUMENTS_DELETE);
        if ($policyDocument->contributionRules()->exists()) {
            throw ValidationException::withMessages([
                'policy_document' => __('validation.policy_document_in_use'),
            ]);
        }
        $this->audit($club, $request, 'club.policy_document.deleted', $policyDocument);
        $policyDocument->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function download(
        Request $request,
        Club $club,
        ClubPolicyDocument $policyDocument
    ) {
        [$canView, , $canDownload] = $this->viewerAccess($request, $club);
        abort_unless((int) $policyDocument->club_id === (int) $club->id, 404);
        abort_unless(($canView && $canDownload) || $policyDocument->is_public, 404);

        $file = $policyDocument->file;
        abort_unless($file && (int) $file->club_id === (int) $club->id, 404);
        abort_unless(Storage::disk(UploadStorage::disk())->exists($file->path), 404);

        return Storage::disk(UploadStorage::disk())->download($file->path, $file->display_name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function viewerAccess(Request $request, Club $club): array
    {
        $user = $request->user();
        $canView = (bool) $user
            && ClubPermissions::allows($club, $user, ClubPermissions::POLICY_DOCUMENTS_VIEW);
        $canEdit = (bool) $user
            && ClubPermissions::allows($club, $user, ClubPermissions::POLICY_DOCUMENTS_EDIT);
        $canDownload = (bool) $user
            && ClubPermissions::allows($club, $user, ClubPermissions::POLICY_DOCUMENTS_DOWNLOAD);
        $canDelete = (bool) $user
            && ClubPermissions::allows($club, $user, ClubPermissions::POLICY_DOCUMENTS_DELETE);
        abort_unless($canView || $club->is_listed, 404);

        return [$canView, $canEdit, $canDownload, $canDelete];
    }

    private function authorizeManage(Request $request, Club $club, string $permission): void
    {
        abort_unless($request->user()
            && ClubPermissions::allows($club, $request->user(), $permission), 403);
    }

    private function authorizeDocument(
        Request $request,
        Club $club,
        ClubPolicyDocument $document,
        string $permission
    ): void {
        $this->authorizeManage($request, $club, $permission);
        abort_unless((int) $document->club_id === (int) $club->id, 404);
    }

    private function documentData(
        Request $request,
        Club $club,
        ?ClubPolicyDocument $document = null
    ): array {
        $data = $request->validate([
            'type' => ['required', Rule::in(ClubPolicyDocument::TYPES)],
            'title' => ['required', 'string', 'max:160'],
            'version_label' => ['required', 'string', 'max:80'],
            'valid_from' => ['required', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'contract_starts_on' => ['nullable', 'date_format:Y-m-d'],
            'contract_ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:contract_starts_on'],
            'cancellation_notice_days' => ['nullable', 'integer', 'min:0', 'max:730'],
            'review_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'workflow_status' => ['sometimes', Rule::in(ClubPolicyDocument::WORKFLOW_STATUSES)],
            'classification' => ['sometimes', Rule::in(ClubPolicyDocument::CLASSIFICATIONS)],
            'retention_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'is_public' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'file_id' => [
                'required',
                'integer',
                Rule::exists('files', 'id')->where(fn ($query) => $query
                    ->where('club_id', $club->id)
                    ->whereNull('team_id')
                    ->whereNull('event_id')),
            ],
        ]);
        foreach (['title', 'version_label', 'notes'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $data[$field] = $value === '' ? null : $value;
        }
        if ($data['title'] === null || $data['version_label'] === null) {
            $errors = [];
            if ($data['title'] === null) {
                $errors['title'] = __('validation.required', ['attribute' => 'title']);
            }
            if ($data['version_label'] === null) {
                $errors['version_label'] = __('validation.required', ['attribute' => 'version label']);
            }
            throw ValidationException::withMessages($errors);
        }
        $duplicate = $club->policyDocuments()
            ->where('type', $data['type'])
            ->where('title', $data['title'])
            ->where('version_label', $data['version_label'])
            ->when($document, fn (Builder $query) => $query->where('id', '!=', $document->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['version_label' => __('validation.unique', [
                'attribute' => 'version label',
            ])]);
        }
        if (in_array($data['type'], ClubPolicyDocument::PROTECTED_TYPES, true) && $data['is_public']) {
            throw ValidationException::withMessages([
                'is_public' => 'Contract, employment and compensation documents must remain internal.',
            ]);
        }
        $data['workflow_status'] ??= $document?->workflow_status ?: 'draft';
        $data['classification'] ??= $document?->classification ?: ($data['is_public'] ? 'public' : 'internal');

        return $data;
    }

    private function syncLifecycleState(Club $club, Request $request, ClubPolicyDocument $document): void
    {
        $changes = [];
        if ($document->workflow_status === 'approved' && ! $document->approved_at) {
            $changes['approved_by'] = $request->user()->id;
            $changes['approved_at'] = now();
        }

        if ($document->workflow_status === 'published' && ! $document->published_at) {
            $changes['approved_by'] = $document->approved_by ?: $request->user()->id;
            $changes['approved_at'] = $document->approved_at ?: now();
            $changes['published_by'] = $request->user()->id;
            $changes['published_at'] = now();
            $changes['is_public'] = true;
            $changes['publication_checksum'] = $this->publicationChecksum($document);
        }

        if ($document->workflow_status === 'archived' && ! $document->archived_at) {
            $changes['archived_at'] = now();
            $changes['is_public'] = false;
        }

        if ($changes !== []) {
            $document->forceFill($changes)->save();
            $this->audit($club, $request, 'club.policy_document.lifecycle.updated', $document->refresh());
        }
    }

    private function assertPublishedVersionIsImmutable(ClubPolicyDocument $document, array $data): void
    {
        if (! $document->published_at) {
            return;
        }

        foreach ([
            'type', 'title', 'version_label', 'valid_from', 'valid_until', 'file_id',
            'classification', 'retention_until', 'contract_starts_on', 'contract_ends_on',
            'cancellation_notice_days',
        ] as $field) {
            $current = $document->{$field};
            $incoming = $data[$field] ?? null;
            if ($current instanceof \DateTimeInterface) {
                $current = $current->format('Y-m-d');
            }
            if ((string) $current !== (string) $incoming) {
                throw ValidationException::withMessages([
                    $field => __('validation.policy_document_published_immutable'),
                ]);
            }
        }
    }

    private function publicationChecksum(ClubPolicyDocument $document): string
    {
        return hash('sha256', json_encode([
            'club_id' => $document->club_id,
            'file_id' => $document->file_id,
            'type' => $document->type,
            'title' => $document->title,
            'version_label' => $document->version_label,
            'valid_from' => $document->valid_from?->format('Y-m-d'),
            'valid_until' => $document->valid_until?->format('Y-m-d'),
            'classification' => $document->classification,
            'retention_until' => $document->retention_until?->format('Y-m-d'),
        ], JSON_THROW_ON_ERROR));
    }

    private function assertNoOverlap(
        Club $club,
        array $data,
        ?ClubPolicyDocument $document = null
    ): void {
        $overlaps = $club->policyDocuments()
            ->where('type', $data['type'])
            ->where('title', $data['title'])
            ->when($document, fn (Builder $query) => $query->where('id', '!=', $document->id))
            ->when(
                $data['valid_until'] ?? null,
                fn (Builder $query, string $end) => $query->whereDate('valid_from', '<=', $end)
            )
            ->where(fn (Builder $query) => $query
                ->whereNull('valid_until')
                ->orWhereDate('valid_until', '>=', $data['valid_from']))
            ->exists();
        if ($overlaps) {
            throw ValidationException::withMessages([
                'valid_from' => __('validation.policy_document_period_overlap'),
            ]);
        }
    }

    private function assertContributionRuleCoverage(
        ClubPolicyDocument $document,
        array $data
    ): void {
        if (! $document->contributionRules()->exists()) {
            return;
        }

        $outsidePeriod = $data['type'] !== 'contribution_model'
            || $document->contributionRules()
                ->where(function (Builder $query) use ($data) {
                    $query->whereDate('valid_from', '<', $data['valid_from']);
                    if ($data['valid_until'] ?? null) {
                        $query->orWhereNull('valid_until')
                            ->orWhereDate('valid_until', '>', $data['valid_until']);
                    }
                })
                ->exists();
        if ($outsidePeriod) {
            throw ValidationException::withMessages([
                'valid_from' => __('validation.policy_document_rule_period'),
            ]);
        }
    }

    private function payload(ClubPolicyDocument $document): array
    {
        $today = now()->startOfDay();
        $status = $document->valid_from->greaterThan($today)
            ? 'upcoming'
            : ($document->valid_until?->lessThan($today) ? 'expired' : 'current');

        return [
            'id' => $document->id,
            'type' => $document->type,
            'title' => $document->title,
            'version_label' => $document->version_label,
            'valid_from' => $document->valid_from->format('Y-m-d'),
            'valid_until' => $document->valid_until?->format('Y-m-d'),
            'contract_starts_on' => $document->contract_starts_on?->format('Y-m-d'),
            'contract_ends_on' => $document->contract_ends_on?->format('Y-m-d'),
            'cancellation_notice_days' => $document->cancellation_notice_days,
            'review_at' => $document->review_at?->format('Y-m-d'),
            'review_job_id' => $document->review_job_id,
            'workflow_status' => $document->workflow_status ?: 'draft',
            'classification' => $document->classification ?: 'internal',
            'retention_until' => $document->retention_until?->format('Y-m-d'),
            'approved_by' => $document->approved_by,
            'approved_at' => $document->approved_at?->toJSON(),
            'published_by' => $document->published_by,
            'published_at' => $document->published_at?->toJSON(),
            'publication_checksum' => $document->publication_checksum,
            'archived_at' => $document->archived_at?->toJSON(),
            'status' => $status,
            'is_public' => (bool) $document->is_public,
            'notes' => $document->notes,
            'file' => [
                'id' => $document->file_id,
                'display_name' => $document->file?->display_name,
                'type' => $document->file?->type,
                'size' => $document->file?->size,
                'download_url' => route('api.v1.clubs.policy-documents.download', [
                    $document->club_id,
                    $document->id,
                ]),
            ],
        ];
    }

    private function audit(
        Club $club,
        Request $request,
        string $type,
        ClubPolicyDocument $document
    ): void {
        ClubAuditLog::record($club, $request->user(), $type, $document, [
            'entity_type' => 'policy_document',
        ]);
    }

    private function syncReviewJob(Club $club, Request $request, ClubPolicyDocument $document): void
    {
        if (! $document->review_at) {
            return;
        }

        $job = $this->workAutomationJobs->enqueue(
            $club,
            WorkAutomationJob::KIND_REMINDER,
            "policy-document:{$document->id}:review:".$document->review_at->format('Y-m-d'),
            $request->user(),
            $document,
            [ClubPermissions::POLICY_DOCUMENTS_EDIT],
            [
                'template' => 'policy_document_contract_review',
                'title' => $document->title,
                'version_label' => $document->version_label,
                'review_at' => $document->review_at->format('Y-m-d'),
                'contract_ends_on' => $document->contract_ends_on?->format('Y-m-d'),
                'cancellation_notice_days' => $document->cancellation_notice_days,
                'url' => "/clubs/{$club->id}",
            ]
        );

        if ((int) $document->review_job_id !== (int) $job->id) {
            $document->forceFill(['review_job_id' => $job->id])->save();
        }
    }
}
