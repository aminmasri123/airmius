<?php

namespace App\Services;

use App\Models\ClubNewsletterDelivery;
use App\Models\Club;
use Illuminate\Support\Str;

class PostmarkNewsletterWebhookService
{
    public function handle(array $payload): string
    {
        if (strtolower((string) $payload['RecordType']) !== 'bounce') {
            return 'ignored';
        }

        $messageId = strtolower(trim(trim((string) $payload['MessageID']), '<>'));
        $email = Str::lower(trim((string) $payload['Email']));
        $delivery = ClubNewsletterDelivery::query()
            ->whereIn('provider_message_id', [$messageId, '<'.$messageId.'>'])
            ->where('email', $email)
            ->first();
        if (! $delivery) {
            return 'ignored';
        }

        $type = strtolower((string) ($payload['Type'] ?? ''));
        $bounceType = str_contains($type, 'spam') || str_contains($type, 'complaint')
            ? 'complaint'
            : ((bool) ($payload['Inactive'] ?? false) || str_contains($type, 'hard') ? 'hard' : 'soft');

        if ($delivery->status === 'bounced' && $delivery->bounce_type === $bounceType) {
            return 'duplicate';
        }

        app(ClubNewsletterService::class)->markBounced(Club::query()->findOrFail($delivery->club_id), $delivery, [
            'bounce_type' => $bounceType,
            'failure_reason' => mb_substr((string) ($payload['Description'] ?? $payload['Details'] ?? $payload['Type'] ?? ''), 0, 1000),
        ]);

        return 'processed';
    }
}
