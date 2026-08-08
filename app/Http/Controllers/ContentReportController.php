<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Club;
use App\Models\ContentReport;
use App\Models\Message;
use App\Models\ModerationFlag;
use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use App\Support\ModerationAuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentReportController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['post', 'comment', 'message', 'story', 'user', 'club'])],
            'id' => ['required', 'integer'],
            'reason' => ['required', Rule::in(['insult', 'bullying', 'hate', 'sexual', 'violence', 'threat', 'image_rights', 'spam', 'other'])],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $model = $this->findReportable($data['type'], (int) $data['id']);
        $this->authorizeReport($model);

        $report = ContentReport::create([
            'reporter_id' => $request->user()->id,
            'reportable_type' => $model::class,
            'reportable_id' => $model->getKey(),
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'status' => 'open',
        ]);

        ModerationFlag::create([
            'flaggable_type' => $model::class,
            'flaggable_id' => $model->getKey(),
            'user_id' => $request->user()->id,
            'source' => 'user_report',
            'severity' => in_array($data['reason'], ['sexual', 'violence', 'threat', 'hate'], true) ? 'high' : 'medium',
            'categories' => [$data['reason']],
            'matched_terms' => [],
        ]);

        if ($model->isFillable('moderation_status')) {
            $model->forceFill(['moderation_status' => 'reported'])->save();
        }

        ModerationAuditLog::record(
            $report,
            'reported',
            $request->user(),
            null,
            $report->status,
            $report->reason,
            ModerationAuditLog::contentMetadata($model)
        );

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $report->id,
                    'type' => $data['type'],
                    'reportable_id' => $model->getKey(),
                    'reason' => $report->reason,
                    'status' => $report->status,
                ],
                'message' => __('platform.social.report_sent'),
            ], 201);
        }

        return back()->with('success', __('platform.social.report_sent'));
    }

    public function appeal(Request $request, ContentReport $report)
    {
        abort_unless((int) $report->reporter_id === (int) $request->user()->id, 403);
        abort_if($report->status === 'open', 422, 'Eine Beschwerde ist erst nach einer Moderationsentscheidung möglich.');
        abort_if($report->appeal_status === 'pending', 422, 'Zu dieser Meldung ist bereits eine Beschwerde offen.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $previousStatus = $report->appeal_status;

        $report->forceFill([
            'appeal_reason' => $data['reason'],
            'appeal_status' => 'pending',
            'appealed_at' => now(),
            'appeal_decision' => null,
            'appeal_decided_by' => null,
            'appeal_decided_at' => null,
        ])->save();

        ModerationAuditLog::record(
            $report,
            'appeal_submitted',
            $request->user(),
            $previousStatus,
            'pending',
            $data['reason'],
            ModerationAuditLog::contentMetadata($report->reportable)
        );

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $report->id,
                    'status' => $report->status,
                    'appeal_status' => $report->appeal_status,
                    'appealed_at' => $report->appealed_at?->toJSON(),
                ],
                'message' => __('platform.social.appeal_sent'),
            ], 201);
        }

        return back()->with('success', __('platform.social.appeal_sent'));
    }

    private function findReportable(string $type, int $id)
    {
        return match ($type) {
            'post' => Post::findOrFail($id),
            'comment' => Comment::findOrFail($id),
            'message' => Message::findOrFail($id),
            'story' => Story::findOrFail($id),
            'user' => User::findOrFail($id),
            'club' => Club::findOrFail($id),
        };
    }

    private function authorizeReport($model): void
    {
        if ($model instanceof Post) {
            $this->authorize('view', $model);

            return;
        }

        if ($model instanceof Comment) {
            $this->authorize('view', $model->post);

            return;
        }

        if ($model instanceof Message) {
            abort_unless($model->conversation->users()->where('users.id', auth()->id())->exists(), 403);
        }

        if ($model instanceof Story) {
            $this->authorize('view', $model);

            return;
        }

        if ($model instanceof User) {
            abort_unless(! $model->is(auth()->user()), 422);

            return;
        }

        if ($model instanceof Club) {
            $this->authorize('view', $model);
            abort_if((int) $model->owner_id === (int) auth()->id(), 422);
        }
    }
}
