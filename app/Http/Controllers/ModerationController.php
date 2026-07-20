<?php

namespace App\Http\Controllers;

use App\Models\ContentReport;
use App\Models\ModerationFlag;
use App\Models\AccountWarning;
use App\Support\ModerationAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ModerationController extends Controller
{
    public function index()
    {
        return Inertia::render('Auth/Dashboard/Admin/Moderation/Index', [
            'flags' => ModerationFlag::query()
                ->with(['user:id,name', 'flaggable'])
                ->latest()
                ->limit(80)
                ->get()
                ->map(fn (ModerationFlag $flag) => $this->flagPayload($flag)),
            'reports' => ContentReport::query()
                ->with(['reporter:id,name', 'reportable'])
                ->latest()
                ->limit(80)
                ->get()
                ->map(fn (ContentReport $report) => $this->reportPayload($report)),
            'warnings' => AccountWarning::query()
                ->with(['user:id,name,email,account_status,suspended_until', 'flag'])
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (AccountWarning $warning) => $this->warningPayload($warning)),
            'warningSummary' => [
                'warnings_90_days' => AccountWarning::query()->where('created_at', '>=', now()->subDays(90))->count(),
                'users_with_warnings_90_days' => AccountWarning::query()
                    ->where('created_at', '>=', now()->subDays(90))
                    ->distinct('user_id')
                    ->count('user_id'),
                'suspended_users' => \App\Models\User::query()->where('account_status', 'suspended')->count(),
            ],
        ]);
    }

    public function updateFlag(Request $request, ModerationFlag $flag)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'dismissed', 'actioned'])],
            'remove_content' => ['nullable', 'boolean'],
            'decision_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $previousStatus = $flag->status;
        $actionTaken = $data['status'];

        if ($data['remove_content'] ?? false) {
            $this->removeContent($flag->flaggable);
            $data['status'] = 'actioned';
            $actionTaken = 'content_removed';
        }

        $flag->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'decision_reason' => $data['decision_reason'] ?? null,
            'action_taken' => $actionTaken,
        ]);

        ModerationAuditLog::record(
            $flag,
            'decision',
            $request->user(),
            $previousStatus,
            $flag->status,
            $data['decision_reason'] ?? null,
            [
                ...ModerationAuditLog::contentMetadata($flag->flaggable),
                'action_taken' => $actionTaken,
            ]
        );

        return back()->with('success', 'Moderationsfall wurde aktualisiert.');
    }

    public function updateReport(Request $request, ContentReport $report)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'dismissed', 'actioned'])],
            'remove_content' => ['nullable', 'boolean'],
            'decision_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $previousStatus = $report->status;
        $actionTaken = $data['status'];

        if ($data['remove_content'] ?? false) {
            $this->removeContent($report->reportable);
            $data['status'] = 'actioned';
            $actionTaken = 'content_removed';
        }

        $report->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'decision_reason' => $data['decision_reason'] ?? null,
            'action_taken' => $actionTaken,
        ]);

        ModerationAuditLog::record(
            $report,
            'decision',
            $request->user(),
            $previousStatus,
            $report->status,
            $data['decision_reason'] ?? null,
            [
                ...ModerationAuditLog::contentMetadata($report->reportable),
                'action_taken' => $actionTaken,
            ]
        );

        return back()->with('success', 'Meldung wurde aktualisiert.');
    }

    public function decideReportAppeal(Request $request, ContentReport $report)
    {
        abort_unless($report->appeal_status === 'pending', 422, 'Zu dieser Meldung ist keine offene Beschwerde vorhanden.');

        $data = $request->validate([
            'appeal_status' => ['required', Rule::in(['accepted', 'rejected'])],
            'appeal_decision' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $previousAppealStatus = $report->appeal_status;
        $updates = [
            'appeal_status' => $data['appeal_status'],
            'appeal_decision' => $data['appeal_decision'],
            'appeal_decided_by' => $request->user()->id,
            'appeal_decided_at' => now(),
        ];

        if ($data['appeal_status'] === 'accepted' && $report->status === 'dismissed') {
            $updates['status'] = 'open';
        }

        $report->forceFill($updates)->save();

        ModerationAuditLog::record(
            $report,
            'appeal_decided',
            $request->user(),
            $previousAppealStatus,
            $report->appeal_status,
            $data['appeal_decision'],
            [
                ...ModerationAuditLog::contentMetadata($report->reportable),
                'report_status' => $report->status,
            ]
        );

        return back()->with('success', 'Beschwerde wurde entschieden.');
    }

    private function removeContent(?Model $model): void
    {
        if (! $model) {
            return;
        }

        if ($model->isFillable('moderation_status')) {
            $model->forceFill(['moderation_status' => 'removed'])->save();
        }

        if (method_exists($model, 'delete') && $model::class === \App\Models\Message::class) {
            $model->delete();
        }
    }

    private function flagPayload(ModerationFlag $flag): array
    {
        return [
            'id' => $flag->id,
            'source' => $flag->source,
            'severity' => $flag->severity,
            'status' => $flag->status,
            'automated_action' => $flag->automated_action,
            'decision_reason' => $flag->decision_reason,
            'action_taken' => $flag->action_taken,
            'categories' => $flag->categories ?: [],
            'matched_terms' => $flag->matched_terms ?: [],
            'created_at' => $flag->created_at,
            'user' => $flag->user,
            'content' => $this->contentPayload($flag->flaggable),
            'logs' => ModerationAuditLog::forCase($flag)
                ->limit(20)
                ->get()
                ->map(fn ($log) => $this->logPayload($log)),
        ];
    }

    private function reportPayload(ContentReport $report): array
    {
        return [
            'id' => $report->id,
            'reason' => $report->reason,
            'details' => $report->details,
            'status' => $report->status,
            'decision_reason' => $report->decision_reason,
            'action_taken' => $report->action_taken,
            'appeal_reason' => $report->appeal_reason,
            'appeal_status' => $report->appeal_status,
            'appealed_at' => $report->appealed_at,
            'appeal_decision' => $report->appeal_decision,
            'appeal_decided_at' => $report->appeal_decided_at,
            'created_at' => $report->created_at,
            'reporter' => $report->reporter,
            'content' => $this->contentPayload($report->reportable),
            'logs' => ModerationAuditLog::forCase($report)
                ->limit(20)
                ->get()
                ->map(fn ($log) => $this->logPayload($log)),
        ];
    }

    private function warningPayload(AccountWarning $warning): array
    {
        return [
            'id' => $warning->id,
            'severity' => $warning->severity,
            'points' => $warning->points,
            'reason' => $warning->reason,
            'created_at' => $warning->created_at,
            'user' => $warning->user,
            'flag' => $warning->flag ? [
                'id' => $warning->flag->id,
                'categories' => $warning->flag->categories ?: [],
                'matched_terms' => $warning->flag->matched_terms ?: [],
                'status' => $warning->flag->status,
                'automated_action' => $warning->flag->automated_action,
            ] : null,
        ];
    }

    private function contentPayload(?Model $model): ?array
    {
        if (! $model) {
            return null;
        }

        if (method_exists($model, 'user')) {
            $model->loadMissing('user:id,name');
        }

        if (method_exists($model, 'sender')) {
            $model->loadMissing('sender:id,name');
        }

        return [
            'type' => class_basename($model),
            'id' => $model->getKey(),
            'text' => str($model->content ?? $model->message ?? $model->caption ?? $this->fallbackContentText($model))->limit(500)->toString(),
            'image' => $model instanceof \App\Models\Post ? $model->image : ($model instanceof \App\Models\Story ? $model->media_path : null),
            'media_type' => $model instanceof \App\Models\Story ? $model->media_type : null,
            'moderation_status' => $model->moderation_status ?? null,
            'author' => $model->user?->name ?? $model->sender?->name ?? null,
        ];
    }

    private function fallbackContentText(Model $model): string
    {
        if ($model instanceof \App\Models\User) {
            return trim(implode("\n", array_filter([
                'Profil: '.$model->name,
                'E-Mail: '.$model->email,
                $model->bio ? 'Bio: '.$model->bio : null,
            ])));
        }

        return 'Datei oder gelöschter Inhalt';
    }

    private function logPayload($log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'previous_status' => $log->previous_status,
            'new_status' => $log->new_status,
            'reason' => $log->reason,
            'metadata' => $log->metadata ?: [],
            'created_at' => $log->created_at,
            'actor' => $log->actor ? [
                'id' => $log->actor->id,
                'name' => $log->actor->name,
            ] : null,
        ];
    }
}
