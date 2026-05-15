<?php

namespace App\Http\Controllers;

use App\Models\ContentReport;
use App\Models\ModerationFlag;
use App\Models\AccountWarning;
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
        ]);

        if ($data['remove_content'] ?? false) {
            $this->removeContent($flag->flaggable);
            $data['status'] = 'actioned';
        }

        $flag->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Moderationsfall wurde aktualisiert.');
    }

    public function updateReport(Request $request, ContentReport $report)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'dismissed', 'actioned'])],
            'remove_content' => ['nullable', 'boolean'],
        ]);

        if ($data['remove_content'] ?? false) {
            $this->removeContent($report->reportable);
            $data['status'] = 'actioned';
        }

        $report->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Meldung wurde aktualisiert.');
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
            'categories' => $flag->categories ?: [],
            'matched_terms' => $flag->matched_terms ?: [],
            'created_at' => $flag->created_at,
            'user' => $flag->user,
            'content' => $this->contentPayload($flag->flaggable),
        ];
    }

    private function reportPayload(ContentReport $report): array
    {
        return [
            'id' => $report->id,
            'reason' => $report->reason,
            'details' => $report->details,
            'status' => $report->status,
            'created_at' => $report->created_at,
            'reporter' => $report->reporter,
            'content' => $this->contentPayload($report->reportable),
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
            'text' => str($model->content ?? $model->message ?? $this->fallbackContentText($model))->limit(500)->toString(),
            'image' => $model instanceof \App\Models\Post ? $model->image : null,
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

        return 'Datei oder geloeschter Inhalt';
    }
}
