<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubNewsletterCampaign;
use App\Models\ClubNewsletterDelivery;
use App\Models\ClubNewsletterSubscription;
use App\Models\ClubNewsletterSuppression;
use App\Models\User;
use App\Support\EmailTemplate;
use App\Support\TransactionalMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class ClubNewsletterService
{
    public const CONFIRMATION_RESEND_MINUTES = 5;

    private const MAX_CONFIRMATION_ATTEMPTS = 3;

    public function subscribers(Club $club): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return ClubNewsletterSubscription::query()
            ->where('club_id', $club->id)
            ->latest('id')
            ->paginate(30, ['id', 'name', 'email', 'status', 'created_at', 'confirmed_at'], 'subscribers_page');
    }

    public function requestOptIn(Club $club, string $email, ?string $name = null, ?User $user = null): array
    {
        $email = Str::lower(trim($email));
        [$subscription, $token] = DB::transaction(function () use ($club, $email, $name, $user) {
            // Serialize the initial insert as well as retries for existing subscriptions.
            Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();
            $existing = ClubNewsletterSubscription::query()
                ->where('club_id', $club->id)->where('email', $email)->lockForUpdate()->first();
            $pending = $existing?->status === 'pending';
            $attempts = $pending ? $this->confirmationAttempts($existing) : 0;
            $suppression = ClubNewsletterSuppression::query()
                ->where('club_id', $club->id)->where('email', $email)->first();

            if ($existing && (
                ($existing->status === 'confirmed' && ! $suppression)
                || in_array($suppression?->reason, ['hard_bounce', 'complaint'], true)
                || ($pending && ($attempts >= self::MAX_CONFIRMATION_ATTEMPTS
                    || $existing->confirmation_sent_at?->gt(now()->subMinutes(self::CONFIRMATION_RESEND_MINUTES))))
            )) {
                return [$existing, null];
            }

            $token = Str::random(64);
            $subscription = ClubNewsletterSubscription::query()->updateOrCreate(
                ['club_id' => $club->id, 'email' => $email],
                [
                    'user_id' => $pending ? $existing->user_id : ($email === Str::lower(trim($user?->email ?? '')) ? $user?->id : null),
                    'name' => $pending ? $existing->name : $name,
                    'status' => 'pending',
                    'confirmation_token_hash' => hash('sha256', $token),
                    'confirmation_sent_at' => now(),
                    'confirmed_at' => null,
                    'unsubscribe_token_hash' => null,
                    'unsubscribed_at' => null,
                    'metadata' => [
                        ...($existing?->metadata ?? []),
                        'confirmation_attempts' => $attempts + 1,
                        'confirmation_delivery' => 'sending',
                    ],
                ],
            );

            return [$subscription, $token];
        });

        if ($token === null) {
            return [$subscription, null];
        }

        $confirmationUrl = route('guest.newsletter.confirm', ['token' => $token]);
        $sent = false;
        try {
            $mail = app(TransactionalMail::class);
            $sender = $mail->transportFor('system');
            if (app()->environment('production') && ! $this->canDeliverConfirmation($sender['mailer'])) {
                $sender = $mail->transportFor('security');
            }
            if (! app()->environment('production') || $this->canDeliverConfirmation($sender['mailer'])) {
                $message = Mail::mailer($sender['mailer'])->send(
                    [
                        'html' => 'emails.club-newsletter-confirmation',
                        'text' => 'emails.club-newsletter-confirmation-text',
                    ],
                    [
                        'subscriberName' => $subscription->name,
                        'clubName' => $club->name,
                        'confirmationUrl' => $confirmationUrl,
                        'expiresHours' => 24,
                    ],
                    fn ($message) => $message->from($sender['address'], $sender['name'])
                        ->to($email)->subject('Bitte bestätige deine Anmeldung – '.$club->name),
                );
                $sent = $message !== null || app()->environment('testing');
            }
        } catch (Throwable $exception) {
            Log::warning('Newsletter confirmation transport failed.', [
                'subscription_id' => $subscription->id,
                'exception_type' => $exception::class,
            ]);
        }

        $metadata = [...$subscription->metadata, 'confirmation_delivery' => $sent ? 'sent' : 'failed'];
        ClubNewsletterSubscription::query()->whereKey($subscription->id)
            ->where('confirmation_token_hash', $subscription->confirmation_token_hash)
            ->update(['metadata' => json_encode($metadata, JSON_THROW_ON_ERROR)]);
        $subscription->refresh();

        return [$subscription, $sent ? $token : null];
    }

    private function canDeliverConfirmation(?string $mailer): bool
    {
        $transport = config("mail.mailers.{$mailer}.transport");

        return ! in_array($transport, [null, 'log', 'array'], true)
            && ($transport !== 'smtp' || filled(config("mail.mailers.{$mailer}.host")));
    }

    public function statusFor(Club $club, User $user): array
    {
        $subscription = ClubNewsletterSubscription::query()
            ->where('club_id', $club->id)
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('email', Str::lower(trim($user->email)));
            })
            ->latest('id')
            ->first();

        return [
            ...$this->confirmationStatus($subscription),
            'email' => $subscription?->status === 'pending' ? $subscription->email : null,
        ];
    }

    public function confirmationStatus(?ClubNewsletterSubscription $subscription): array
    {
        $pending = $subscription?->status === 'pending';
        $attempts = $pending ? $this->confirmationAttempts($subscription) : 0;
        $blocked = $pending && ClubNewsletterSuppression::query()
            ->where('club_id', $subscription->club_id)->where('email', $subscription->email)
            ->whereIn('reason', ['hard_bounce', 'complaint'])->exists();
        $supportRequired = $blocked || $attempts >= self::MAX_CONFIRMATION_ATTEMPTS;
        $resendAt = $pending && ! $supportRequired
            ? $subscription->confirmation_sent_at?->copy()->addMinutes(self::CONFIRMATION_RESEND_MINUTES)
            : null;

        return [
            'status' => $subscription?->status ?? 'none',
            'confirmation_sent_at' => $subscription?->confirmation_sent_at?->toJSON(),
            'can_resend_at' => $resendAt?->toJSON(),
            'retry_after_seconds' => $resendAt ? max(0, $resendAt->timestamp - now()->timestamp) : 0,
            'support_required' => $supportRequired,
            'confirmation_delivery_failed' => $pending && data_get($subscription->metadata, 'confirmation_delivery') === 'failed',
        ];
    }

    private function confirmationAttempts(ClubNewsletterSubscription $subscription): int
    {
        return (int) data_get($subscription->metadata, 'confirmation_attempts', $subscription->confirmation_sent_at ? 1 : 0);
    }

    public function confirm(string $token): ?ClubNewsletterSubscription
    {
        $subscription = ClubNewsletterSubscription::query()
            ->where('confirmation_token_hash', hash('sha256', $token))
            ->first();

        if (! $subscription) {
            return null;
        }

        if (! $subscription->confirmation_sent_at || $subscription->confirmation_sent_at->lt(now()->subDay())) {
            return null;
        }

        if ($subscription->status !== 'confirmed') {
            $updated = ClubNewsletterSubscription::query()->whereKey($subscription->id)
                ->where('confirmation_token_hash', hash('sha256', $token))
                ->where('status', 'pending')
                ->update([
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                    'confirmation_token_hash' => null,
                    'unsubscribe_token_hash' => hash('sha256', Str::random(64)),
                    'unsubscribed_at' => null,
                ]);
            if (! $updated) {
                return null;
            }
            ClubNewsletterSuppression::query()
                ->where('club_id', $subscription->club_id)
                ->where('email', $subscription->email)
                ->delete();
        }

        return $subscription->fresh();
    }

    public function unsubscribe(string $token, string $reason = 'unsubscribe'): ?ClubNewsletterSubscription
    {
        $subscription = ClubNewsletterSubscription::query()
            ->where('unsubscribe_token_hash', hash('sha256', $token))
            ->first() ?? $this->subscriptionFromSignedUnsubscribeToken($token);

        if (! $subscription) {
            return null;
        }

        DB::transaction(function () use ($subscription, $reason): void {
            $subscription->forceFill([
                'status' => 'unsubscribed',
                'unsubscribed_at' => $subscription->unsubscribed_at ?? now(),
            ])->save();

            $this->suppress($subscription->club_id, $subscription->email, $reason);
        });

        return $subscription->fresh();
    }

    public function suppress(int $clubId, string $email, string $reason = 'manual', array $metadata = []): ClubNewsletterSuppression
    {
        return ClubNewsletterSuppression::query()->updateOrCreate(
            ['club_id' => $clubId, 'email' => Str::lower(trim($email))],
            ['reason' => $reason, 'suppressed_at' => now(), 'metadata' => $metadata],
        );
    }

    public function sendCampaign(Club $club, User $sender, array $data): ClubNewsletterCampaign
    {
        return DB::transaction(function () use ($club, $sender, $data) {
            $key = $data['idempotency_key'] ?? null;
            if ($key) {
                $existing = ClubNewsletterCampaign::query()
                    ->where('club_id', $club->id)
                    ->where('idempotency_key', $key)
                    ->first();
                if ($existing) {
                    return $existing->load('deliveries');
                }
            }

            $content = $this->campaignContent($data);
            $campaign = ClubNewsletterCampaign::query()->create([
                'club_id' => $club->id,
                'created_by' => $sender->id,
                'subject' => $content['subject'],
                'body' => $content['body'],
                'template_key' => $data['template_key'] ?? null,
                'idempotency_key' => $key,
                'status' => 'sending',
            ]);

            $suppressed = ClubNewsletterSuppression::query()
                ->where('club_id', $club->id)
                ->pluck('email')
                ->all();

            ClubNewsletterSubscription::query()
                ->where('club_id', $club->id)
                ->where('status', 'confirmed')
                ->whereNotIn('email', $suppressed)
                ->orderBy('id')
                ->each(function (ClubNewsletterSubscription $subscription) use ($campaign): void {
                    $delivery = ClubNewsletterDelivery::query()->create([
                        'club_newsletter_campaign_id' => $campaign->id,
                        'club_newsletter_subscription_id' => $subscription->id,
                        'club_id' => $subscription->club_id,
                        'email' => $subscription->email,
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);

                    $body = $campaign->body."\n\nNewsletter abbestellen: ".route('guest.newsletter.unsubscribe', [
                        'token' => $this->unsubscribeToken($subscription),
                    ]);

                    $sent = $this->sendMail($body, $subscription->email, $campaign->subject);

                    $delivery->forceFill(['provider_message_id' => $sent?->getMessageId()])->save();
                });

            $campaign->forceFill(['status' => 'sent', 'sent_at' => now()])->save();

            return $campaign->load('deliveries');
        });
    }

    public function markBounced(Club $club, ClubNewsletterDelivery $delivery, array $data): ClubNewsletterDelivery
    {
        abort_unless((int) $delivery->club_id === (int) $club->id, 404);

        $delivery->forceFill([
            'status' => 'bounced',
            'bounce_type' => $data['bounce_type'] ?? 'unknown',
            'failure_reason' => $data['failure_reason'] ?? null,
            'bounced_at' => $delivery->bounced_at ?? now(),
        ])->save();

        if (in_array($data['bounce_type'] ?? null, ['hard', 'complaint'], true)) {
            $this->suppress($club->id, $delivery->email, $data['bounce_type'] === 'complaint' ? 'complaint' : 'hard_bounce', ['delivery_id' => $delivery->id]);
        }

        return $delivery->fresh();
    }

    public function unsubscribeToken(ClubNewsletterSubscription $subscription): string
    {
        $basis = $subscription->id.'|'.$subscription->email.'|'.$subscription->confirmed_at?->timestamp;

        return $subscription->id.'.'.hash_hmac('sha256', $basis, (string) config('app.key'));
    }

    private function subscriptionFromSignedUnsubscribeToken(string $token): ?ClubNewsletterSubscription
    {
        if (! str_contains($token, '.')) {
            return null;
        }

        [$id, $signature] = explode('.', $token, 2);
        if (! ctype_digit($id) || ! $signature) {
            return null;
        }

        $subscription = ClubNewsletterSubscription::query()->find((int) $id);
        if (! $subscription || ! $subscription->confirmed_at) {
            return null;
        }

        return hash_equals($this->unsubscribeToken($subscription), $token) ? $subscription : null;
    }

    private function sendMail(string $body, string $email, string $subject): ?\Illuminate\Mail\SentMessage
    {
        $sender = app(TransactionalMail::class)->transportFor('system');

        return Mail::mailer($sender['mailer'])->raw($body, function ($message) use ($sender, $email, $subject): void {
            $message->from($sender['address'], $sender['name'])->to($email)->subject($subject);
        });
    }

    private function campaignContent(array $data): array
    {
        if (! empty($data['template_key'])) {
            $content = EmailTemplate::content($data['template_key'], $data['variables'] ?? []);

            return ['subject' => $content['subject'], 'body' => $content['body']];
        }

        return ['subject' => trim($data['subject']), 'body' => trim($data['body'])];
    }
}
