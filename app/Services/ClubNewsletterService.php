<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubNewsletterCampaign;
use App\Models\ClubNewsletterDelivery;
use App\Models\ClubNewsletterSubscription;
use App\Models\ClubNewsletterSuppression;
use App\Models\User;
use App\Support\EmailTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ClubNewsletterService
{
    public function requestOptIn(Club $club, string $email, ?string $name = null, ?User $user = null): array
    {
        $email = Str::lower(trim($email));
        $token = Str::random(64);

        $subscription = DB::transaction(function () use ($club, $email, $name, $user, $token) {
            ClubNewsletterSuppression::query()
                ->where('club_id', $club->id)
                ->where('email', $email)
                ->delete();

            return ClubNewsletterSubscription::query()->updateOrCreate(
                ['club_id' => $club->id, 'email' => $email],
                [
                    'user_id' => $user?->id,
                    'name' => $name,
                    'status' => 'pending',
                    'confirmation_token_hash' => hash('sha256', $token),
                    'confirmation_sent_at' => now(),
                    'confirmed_at' => null,
                    'unsubscribe_token_hash' => null,
                    'unsubscribed_at' => null,
                ],
            );
        });

        return [$subscription, $token];
    }

    public function confirm(string $token): ?ClubNewsletterSubscription
    {
        $subscription = ClubNewsletterSubscription::query()
            ->where('confirmation_token_hash', hash('sha256', $token))
            ->first();

        if (! $subscription) {
            return null;
        }

        if ($subscription->status !== 'confirmed') {
            $subscription->forceFill([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'confirmation_token_hash' => null,
                'unsubscribe_token_hash' => hash('sha256', Str::random(64)),
                'unsubscribed_at' => null,
            ])->save();
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

                    $body = $campaign->body."\n\nAbmelden: ".url('/api/v1/newsletter/unsubscribe/'.$this->unsubscribeToken($subscription));

                    Mail::raw($body, function ($message) use ($campaign, $subscription): void {
                        $message->to($subscription->email)->subject($campaign->subject);
                    });

                    $delivery->forceFill(['provider_message_id' => 'local-'.$delivery->id])->save();
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
            'bounced_at' => now(),
        ])->save();

        if (($data['bounce_type'] ?? null) === 'hard') {
            $this->suppress($club->id, $delivery->email, 'hard_bounce', ['delivery_id' => $delivery->id]);
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

    private function campaignContent(array $data): array
    {
        if (! empty($data['template_key'])) {
            $content = EmailTemplate::content($data['template_key'], $data['variables'] ?? []);

            return ['subject' => $content['subject'], 'body' => $content['body']];
        }

        return ['subject' => trim($data['subject']), 'body' => trim($data['body'])];
    }
}
