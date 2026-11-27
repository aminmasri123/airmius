<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebsiteRequest;
use App\Support\ClubPermissions;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebsiteProjectController extends Controller
{
    private function authorizeProject(Request $request, WebsiteRequest $project): void
    {
        $user = $request->user();
        $admin = $user->hasAnyRole(Roles::FULL_ACCESS) || $user->can('subscriptions.manage')
            || $user->can('marketplace.manage') || $user->can('system.manage');
        $participant = $project->club
            ? ClubPermissions::allows($project->club, $user, ClubPermissions::WEBSITE_REQUEST_CREATE)
            : (int) $project->user_id === (int) $user->id;
        abort_unless($admin || $participant, 403);
    }

    public function show(Request $request, WebsiteRequest $websiteRequest)
    {
        $this->authorizeProject($request, $websiteRequest);
        $messages = $websiteRequest->messages()->with('user:id,name')->orderByDesc('id')->paginate(30);

        return response()->json([
            'data' => $websiteRequest->only(['id', 'project_title', 'domain', 'goals', 'notes', 'target_audience', 'inspiration_urls', 'status', 'created_at']),
            'messages' => $messages->through(fn ($message) => [
                'id' => $message->id, 'kind' => $message->kind, 'status' => $message->status,
                'body' => $message->body, 'author' => $message->user?->name,
                'created_at' => $message->created_at,
            ]),
        ]);
    }

    public function storeMessage(Request $request, WebsiteRequest $websiteRequest)
    {
        $this->authorizeProject($request, $websiteRequest);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'request_key' => ['required', 'string', 'max:80'],
        ]);
        abort_if(trim($data['body']) === '', 422);
        DB::transaction(function () use ($websiteRequest, $request, $data) {
            $locked = WebsiteRequest::query()->lockForUpdate()->findOrFail($websiteRequest->id);
            $this->authorizeProject($request, $locked);
            $existing = $locked->messages()->where('user_id', $request->user()->id)->where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->body === trim($data['body']), 409);

                return;
            }
            $locked->messages()->create([
                'user_id' => $request->user()->id, 'kind' => 'comment',
                'body' => trim($data['body']), 'request_key' => $data['request_key'],
            ]);
        });

        return response()->json(['ok' => true]);
    }
}
