<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\ContentReport;
use App\Models\Message;
use App\Models\ModerationFlag;
use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentReportController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['post', 'comment', 'message', 'story', 'user'])],
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

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $report->id,
                    'type' => $data['type'],
                    'reportable_id' => $model->getKey(),
                    'reason' => $report->reason,
                    'status' => $report->status,
                ],
                'message' => 'Danke. Die Meldung wurde an die Moderation gesendet.',
            ], 201);
        }

        return back()->with('success', 'Danke. Die Meldung wurde an die Moderation gesendet.');
    }

    private function findReportable(string $type, int $id)
    {
        return match ($type) {
            'post' => Post::findOrFail($id),
            'comment' => Comment::findOrFail($id),
            'message' => Message::findOrFail($id),
            'story' => Story::findOrFail($id),
            'user' => User::findOrFail($id),
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
        }
    }
}
