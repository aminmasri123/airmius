<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebsiteRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WebsiteRequestService
{
    public const STATUSES = ['new', 'contacted', 'quoted', 'in_progress', 'done', 'cancelled'];
    public const REQUEST_TYPES = ['website', 'campaign', 'social_media', 'print', 'logo_branding', 'video', 'photo', 'other'];
    public const DELIVERABLES = ['strategy', 'copy', 'design', 'banner', 'social_post', 'print_file', 'logo', 'photo', 'video', 'landing_page', 'other'];
    public const CHANNELS = ['website', 'app', 'instagram', 'facebook', 'linkedin', 'tiktok', 'youtube', 'email', 'print', 'outdoor', 'other'];

    /** @return array<string, array<int, mixed>> */
    public static function publicRules(): array
    {
        return [
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:80'],
            'club_name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'goals' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'accepted_privacy' => ['accepted'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function authenticatedRules(): array
    {
        return [
            'request_type' => ['sometimes', Rule::in(self::REQUEST_TYPES)],
            'project_title' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['draft', 'new'])],
            'target_audience' => ['nullable', 'string', 'max:2000'],
            'inspiration_urls' => ['nullable', 'array', 'max:5'],
            'inspiration_urls.*' => ['required', 'url:http,https', 'max:2048'],
            'club_id' => ['nullable', Rule::exists('clubs', 'id')],
            'domain' => ['nullable', 'string', 'max:255'],
            'goals' => ['nullable', 'string', 'max:2000'],
            'deliverables' => ['nullable', 'array', 'max:11'],
            'deliverables.*' => ['required', Rule::in(self::DELIVERABLES)],
            'channels' => ['nullable', 'array', 'max:11'],
            'channels.*' => ['required', Rule::in(self::CHANNELS)],
            'budget_range' => ['nullable', 'string', 'max:80'],
            'desired_deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'brand_guidelines' => ['nullable', 'string', 'max:2000'],
            'contact_preference' => ['nullable', Rule::in(['app', 'email', 'phone'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'accepted_privacy' => ['accepted'],
        ];
    }

    public function createPublic(array $data, ?User $user = null): WebsiteRequest
    {
        return $this->create([
            ...$data,
            'user_id' => $user?->id,
            'status' => 'new',
        ]);
    }

    public function createForUser(User $user, array $data): WebsiteRequest
    {
        return $this->create([
            ...$data,
            'user_id' => $user->id,
        ]);
    }

    /** @param array{status: string, notes?: string|null} $data */
    public function updateWorkflow(WebsiteRequest $websiteRequest, User $actor, array $data): WebsiteRequest
    {
        return DB::transaction(function () use ($websiteRequest, $actor, $data): WebsiteRequest {
            $locked = WebsiteRequest::query()->lockForUpdate()->findOrFail($websiteRequest->id);
            abort_if($locked->status === 'draft' || ($locked->withdrawn_at && $data['status'] !== 'cancelled'), 409, __('website_project.closed'));
            $statusChanged = $locked->status !== $data['status'];

            $locked->forceFill([
                'status' => $data['status'],
                'processing_started_at' => $locked->processing_started_at
                    ?? (in_array($data['status'], ['in_progress', 'done'], true) ? now() : null),
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $locked->notes,
                'status_changed_at' => $statusChanged ? now() : $locked->status_changed_at,
                'status_changed_by' => $statusChanged ? $actor->id : $locked->status_changed_by,
                'retention_expires_at' => in_array($data['status'], ['done', 'cancelled'], true)
                    ? now()->addMonths(6)
                    : now()->addYear(),
            ])->save();

            if ($statusChanged) {
                $message = $locked->messages()->create([
                    'user_id' => $actor->id, 'kind' => 'status', 'status' => $data['status'],
                ]);
                app(WebsiteProjectNotifications::class)->send($locked, $actor, 'status', 'status:'.$message->id);
            }

            return $locked->fresh(['user', 'club', 'statusChangedBy']);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function create(array $data): WebsiteRequest
    {
        return DB::transaction(function () use ($data) {
            $project = WebsiteRequest::query()->create([
                ...collect($data)->except('accepted_privacy')->all(),
                'status' => $data['status'] ?? 'new',
                'package' => ($data['request_type'] ?? 'website') === 'website' ? 'website_plus' : 'agency_brief',
                'consent_at' => now(),
                'status_changed_at' => now(),
                'retention_expires_at' => now()->addYear(),
            ]);
            app(WebsiteProjectNotifications::class)->send($project, $project->user, 'created', 'created:'.$project->id);

            return $project;
        });
    }
}
