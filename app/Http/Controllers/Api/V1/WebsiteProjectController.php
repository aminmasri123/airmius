<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebsiteRequest;
use App\Models\WebsiteRequestAttachment;
use App\Services\WebsiteProjectAccess;
use App\Services\WebsiteProjectNotifications;
use App\Services\WebsiteRequestService;
use App\Support\AccountAccess;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

class WebsiteProjectController extends Controller
{
    public function __construct(private WebsiteProjectAccess $access, private WebsiteProjectNotifications $notifications) {}

    public function page(Request $request, WebsiteRequest $websiteRequest)
    {
        abort_unless($this->access->view($request->user(), $websiteRequest), 403);

        return inertia('Auth/Dashboard/Commerce/WebsiteProject', ['projectId' => $websiteRequest->id]);
    }

    public function show(Request $request, WebsiteRequest $websiteRequest)
    {
        $user = $request->user();
        abort_unless($this->access->view($user, $websiteRequest), 403);
        $messages = $websiteRequest->messages()->with(['user:id,name', 'attachments'])->orderByDesc('id')->paginate(30);

        return response()->json([
            'data' => [
                ...$websiteRequest->only([
                    'id', 'request_type', 'project_title', 'domain', 'goals', 'deliverables',
                    'channels', 'budget_range', 'desired_deadline', 'brand_guidelines',
                    'contact_preference', 'notes', 'target_audience', 'inspiration_urls',
                    'quote_details',
                    'status', 'created_at', 'withdrawn_at',
                ]),
                'can_edit' => $this->access->edit($user, $websiteRequest),
                'can_withdraw' => $this->access->withdraw($user, $websiteRequest),
                'can_comment' => $this->access->comment($user, $websiteRequest),
                'is_admin' => $this->access->admin($user),
            ],
            'messages' => $messages->through(fn ($message) => [
                'id' => $message->id, 'kind' => $message->kind, 'status' => $message->status,
                'body' => $message->body, 'author' => $message->user?->name, 'created_at' => $message->created_at,
                'attachments' => $message->attachments->map(fn ($file) => [
                    ...$file->only(['id', 'name', 'mime_type', 'size']),
                    'download_url' => URL::temporarySignedRoute('website-project.attachment', now()->addMinutes(5), [
                        'websiteRequest' => $websiteRequest->id, 'attachment' => $file->id, 'actor' => $user->id,
                    ], absolute: false),
                ]),
            ]),
        ]);
    }

    public function update(Request $request, WebsiteRequest $websiteRequest)
    {
        abort_unless($this->access->participant($request->user(), $websiteRequest), 403);
        $rules = WebsiteRequestService::authenticatedRules();
        unset($rules['club_id'], $rules['accepted_privacy']);
        $data = $request->validate($rules);
        DB::transaction(function () use ($request, $websiteRequest, $data) {
            $locked = WebsiteRequest::query()->lockForUpdate()->findOrFail($websiteRequest->id);
            abort_unless($this->access->edit($request->user(), $locked), 409, __('website_project.edit_locked'));
            abort_if($locked->status !== 'draft' && ($data['status'] ?? '') === 'draft', 409);
            $wasDraft = $locked->status === 'draft';
            abort_if(! $wasDraft && isset($data['status']) && $data['status'] !== $locked->status, 409);
            $locked->fill($data);
            if (! $locked->isDirty()) {
                return;
            }
            $locked->save();
            if (! $wasDraft || $locked->status !== 'draft') {
                $entry = $locked->messages()->create(['user_id' => $request->user()->id, 'kind' => $wasDraft ? 'status' : 'updated', 'status' => $locked->status]);
                $this->notifications->send($locked, $request->user(), 'updated', 'updated:'.$entry->id);
            }
        }, 3);

        return response()->json(['ok' => true]);
    }

    public function withdraw(Request $request, WebsiteRequest $websiteRequest)
    {
        abort_unless($this->access->participant($request->user(), $websiteRequest), 403);
        DB::transaction(function () use ($request, $websiteRequest) {
            $locked = WebsiteRequest::query()->lockForUpdate()->findOrFail($websiteRequest->id);
            if ($locked->withdrawn_at) {
                return;
            }
            abort_unless($this->access->withdraw($request->user(), $locked), 409, __('website_project.closed'));
            $wasDraft = $locked->status === 'draft';
            $locked->forceFill(['status' => $wasDraft ? 'draft' : 'cancelled', 'withdrawn_at' => now(), 'status_changed_at' => now(),
                'status_changed_by' => $request->user()->id, 'retention_expires_at' => now()->addMonths(6)])->save();
            $entry = $locked->messages()->create(['user_id' => $request->user()->id, 'kind' => 'status', 'status' => 'cancelled']);
            if (! $wasDraft) {
                $this->notifications->send($locked, $request->user(), 'withdrawn', 'withdrawn:'.$entry->id);
            }
        }, 3);

        return response()->json(['ok' => true]);
    }

    public function storeMessage(Request $request, WebsiteRequest $websiteRequest)
    {
        abort_unless($this->access->view($request->user(), $websiteRequest), 403);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:4000'], 'request_key' => ['required', 'string', 'max:80'],
            'files' => ['sometimes', 'array', 'max:5'],
            'files.*' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png', 'webp', 'docx', 'pptx', 'xlsx', 'zip'])->max('10mb'),
                'extensions:pdf,jpg,jpeg,png,webp,docx,pptx,xlsx,zip'],
        ]);
        $body = trim($data['body'] ?? '');
        $files = $request->file('files', []);
        abort_if($body === '' && ! $files, 422, __('website_project.empty'));
        $manifest = collect($files)->map(fn ($file) => [
            'name' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 240, ''),
            'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()),
        ])->all();
        $stored = [];
        try {
            DB::transaction(function () use ($request, $websiteRequest, $data, $body, $files, $manifest, &$stored) {
                $locked = WebsiteRequest::query()->lockForUpdate()->findOrFail($websiteRequest->id);
                abort_unless($this->access->view($request->user(), $locked), 403);
                $existing = $locked->messages()->where('user_id', $request->user()->id)->where('request_key', $data['request_key'])->first();
                if ($existing) {
                    abort_unless($existing->body === $body && $existing->attachments()->orderBy('id')->get()
                        ->map(fn ($f) => ['name' => $f->name, 'size' => (int) $f->size, 'sha256' => $f->sha256])->all() === $manifest, 409);

                    return;
                }
                abort_unless($this->access->comment($request->user(), $locked), 409, __('website_project.closed'));
                $used = WebsiteRequestAttachment::whereIn('website_request_message_id', $locked->messages()->select('id'))->sum('size');
                abort_if($used + collect($manifest)->sum('size') > 100 * 1024 * 1024, 422, __('website_project.quota'));
                $message = $locked->messages()->create(['user_id' => $request->user()->id, 'kind' => 'comment', 'body' => $body, 'request_key' => $data['request_key']]);
                foreach ($files as $index => $file) {
                    $disk = UploadStorage::privateDisk();
                    $path = $file->storeAs(UploadStorage::privatePath('website-projects/'.$locked->id), Str::uuid().'.'.$file->extension(), $disk);
                    throw_unless($path, new \RuntimeException('Private project upload failed'));
                    $stored[] = [$disk, $path];
                    $message->attachments()->create([...$manifest[$index], 'disk' => $disk, 'path' => $path, 'mime_type' => $file->getMimeType()]);
                }
                $this->notifications->send($locked, $request->user(), 'comment', 'comment:'.$message->id);
            });
        } catch (\Throwable $failure) {
            foreach ($stored as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            throw $failure;
        }

        return response()->json(['ok' => true]);
    }

    public function attachment(Request $request, WebsiteRequest $websiteRequest, WebsiteRequestAttachment $attachment)
    {
        abort_unless($request->hasValidSignature(false), 403);
        $actor = User::find($request->integer('actor'));
        abort_unless($actor && $this->access->view($actor, $websiteRequest), 403);
        AccountAccess::ensureAllowed($actor);
        abort_unless((int) $attachment->message?->website_request_id === (int) $websiteRequest->id, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->name, [
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox", 'Cache-Control' => 'private, no-store',
        ]);
    }
}
