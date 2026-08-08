<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebsiteRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WebsiteRequestService
{
    public const STATUSES = ['new', 'contacted', 'quoted', 'in_progress', 'done', 'cancelled'];

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
            'club_id' => ['nullable', Rule::exists('clubs', 'id')],
            'domain' => ['nullable', 'string', 'max:255'],
            'goals' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'accepted_privacy' => ['accepted'],
        ];
    }

    public function createPublic(array $data, ?User $user = null): WebsiteRequest
    {
        return $this->create([
            ...$data,
            'user_id' => $user?->id,
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
            $statusChanged = $locked->status !== $data['status'];

            $locked->forceFill([
                'status' => $data['status'],
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $locked->notes,
                'status_changed_at' => $statusChanged ? now() : $locked->status_changed_at,
                'status_changed_by' => $statusChanged ? $actor->id : $locked->status_changed_by,
                'retention_expires_at' => in_array($data['status'], ['done', 'cancelled'], true)
                    ? now()->addMonths(6)
                    : now()->addYear(),
            ])->save();

            return $locked->fresh(['user', 'club', 'statusChangedBy']);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function create(array $data): WebsiteRequest
    {
        return WebsiteRequest::query()->create([
            ...collect($data)->except('accepted_privacy')->all(),
            'status' => 'new',
            'package' => 'website_plus',
            'consent_at' => now(),
            'status_changed_at' => now(),
            'retention_expires_at' => now()->addYear(),
        ]);
    }
}
