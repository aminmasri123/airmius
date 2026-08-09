<?php

namespace App\Services;

use App\Models\OrganizationJob;
use App\Models\OrganizationJobInterest;
use App\Models\User;
use App\Notifications\OrganizationJobInterestReceived;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganizationJobInterestService
{
    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'message' => ['nullable', 'string', 'max:2000'],
            'accepted_privacy' => ['accepted'],
            'shared_profile_fields' => ['nullable', 'array', 'max:2'],
            'shared_profile_fields.*' => ['string', Rule::in(RecruitingMatchExplanationService::SHAREABLE_FIELDS)],
            'accepted_profile_sharing' => ['nullable', 'boolean'],
            'allow_in_app_contact' => ['nullable', 'boolean'],
        ];
    }

    public function submit(
        OrganizationJob $job,
        array $data,
        ?User $user,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $requestLocale,
    ): OrganizationJobInterest {
        $sharedFields = collect($data['shared_profile_fields'] ?? [])
            ->intersect(RecruitingMatchExplanationService::SHAREABLE_FIELDS)
            ->unique()
            ->values()
            ->all();
        if ($sharedFields !== [] && ! $user) {
            throw ValidationException::withMessages([
                'shared_profile_fields' => __('recruiting.validation.profile_login_required'),
            ]);
        }
        if ($sharedFields !== [] && ! filter_var($data['accepted_profile_sharing'] ?? false, FILTER_VALIDATE_BOOL)) {
            throw ValidationException::withMessages([
                'accepted_profile_sharing' => __('recruiting.validation.profile_consent_required'),
            ]);
        }

        $interest = $job->interests()->create([
            ...collect($data)->except([
                'accepted_privacy',
                'accepted_profile_sharing',
                'shared_profile_fields',
                'allow_in_app_contact',
            ])->all(),
            'user_id' => $user?->id,
            'shared_profile_fields' => $sharedFields ?: null,
            'profile_consent_at' => $sharedFields !== [] ? now() : null,
            'allow_in_app_contact' => (bool) $user && (bool) ($data['allow_in_app_contact'] ?? false),
            'status' => 'new',
            'consent_at' => now(),
            'retention_expires_at' => now()->addMonths(6),
            // A stable, keyed pseudonym is sufficient for abuse correlation;
            // the raw address and browser fingerprint are not retained.
            'ip_address' => $this->pseudonymizeIp($ipAddress),
            'user_agent' => null,
        ]);

        $interest->load('job.club.owner', 'job.club.admins');
        $loadedJob = $interest->job;
        $club = $loadedJob->club;
        $recipients = $club->admins
            ->push($club->owner)
            ->filter()
            ->unique('id')
            ->values();
        $notification = new OrganizationJobInterestReceived($interest, $requestLocale);

        try {
            if ($loadedJob->contact_email) {
                Notification::route('mail', $loadedJob->contact_email)->notify($notification);
            }

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, $notification);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $interest;
    }

    private function pseudonymizeIp(?string $ipAddress): ?string
    {
        if (! filled($ipAddress)) {
            return null;
        }

        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            $key = $decoded === false ? $key : $decoded;
        }

        return base64_encode(hash_hmac('sha256', $ipAddress, $key ?: Crypt::class, true));
    }
}
