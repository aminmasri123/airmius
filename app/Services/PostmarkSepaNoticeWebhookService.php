<?php

namespace App\Services;

use App\Models\ClubSepaNotice;
use App\Models\MailDelivery;
use App\Support\ClubAuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PostmarkSepaNoticeWebhookService
{
    public function handle(array $payload): string
    {
        $eventType = strtolower((string) $payload['RecordType']);
        $messageId = (string) $payload['MessageID'];
        $recipient = (string) ($eventType === 'delivery' ? $payload['Recipient'] : $payload['Email']);
        $occurredAt = CarbonImmutable::parse((string) ($eventType === 'delivery' ? $payload['DeliveredAt'] : $payload['BouncedAt']));

        $notice = $this->findNotice($messageId);
        if (! $notice || ! hash_equals(strtolower(trim((string) $notice->content['email'])), strtolower(trim($recipient)))) {
            return 'ignored';
        }

        return DB::transaction(function () use ($notice, $payload, $eventType, $messageId, $occurredAt) {
            $notice = ClubSepaNotice::query()->lockForUpdate()->find($notice->id);
            if (! $notice || $notice->status !== 'sent' || $this->normalizeMessageId($notice->message_id) !== $this->normalizeMessageId($messageId)) {
                return 'ignored';
            }

            $eventKey = hash('sha256', implode('|', [
                'postmark', $notice->id, $eventType, $this->normalizeMessageId($messageId),
                $occurredAt->utc()->format('Y-m-d\TH:i:s.u\Z'),
                $eventType === 'bounce' ? (string) ($payload['ID'] ?? '') : '',
            ]));
            $inserted = DB::table('club_sepa_notice_provider_events')->insertOrIgnore([
                'club_sepa_notice_id' => $notice->id,
                'provider' => 'postmark',
                'event_key' => $eventKey,
                'event_type' => $eventType,
                'occurred_at' => $occurredAt,
                'metadata' => json_encode($this->metadata($payload, $eventType), JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($inserted === 0) {
                return 'duplicate';
            }

            if ($eventType === 'bounce') {
                $notice->update([
                    'provider_status' => 'bounced',
                    'bounced_at' => $occurredAt,
                    'bounce_type' => $this->bounceType($payload),
                ]);
            } elseif ($notice->provider_status !== 'bounced') {
                $notice->update(['provider_status' => 'delivered', 'delivered_at' => $occurredAt]);
            }

            $delivery = MailDelivery::query()->where('dedupe_key', 'club-sepa-notice:'.$notice->id)->first();
            if ($delivery) {
                $delivery->update(['context' => array_replace($delivery->context ?: [], [
                    'provider' => 'postmark',
                    'provider_status' => $notice->fresh()->provider_status,
                    'provider_feedback_at' => $occurredAt->utc()->toJSON(),
                    ...($eventType === 'bounce' ? ['bounce_type' => $this->bounceType($payload)] : []),
                ])]);
            }

            ClubAuditLog::record(
                $notice->batch->club,
                null,
                'club.sepa.notice_'.$eventType,
                $notice->batch,
                ['notice_id' => $notice->id, ...($eventType === 'bounce' ? ['bounce_type' => $this->bounceType($payload)] : [])],
            );

            return 'processed';
        });
    }

    private function findNotice(string $messageId): ?ClubSepaNotice
    {
        $plain = trim(trim($messageId), '<>');
        $candidates = array_values(array_unique([$messageId, trim($messageId), $plain, '<'.$plain.'>']));

        return ClubSepaNotice::query()
            ->where('status', 'sent')
            ->whereIn('message_id', $candidates)
            ->get()
            ->first(fn (ClubSepaNotice $notice) => $this->normalizeMessageId($notice->message_id) === $this->normalizeMessageId($messageId));
    }

    private function normalizeMessageId(?string $messageId): string
    {
        return strtolower(trim(trim((string) $messageId), '<>'));
    }

    private function bounceType(array $payload): string
    {
        return mb_substr(trim((string) ($payload['Type'] ?? 'Unknown')), 0, 80);
    }

    private function metadata(array $payload, string $eventType): array
    {
        if ($eventType === 'delivery') {
            return ['record_type' => 'Delivery'];
        }

        return array_filter([
            'record_type' => 'Bounce',
            'bounce_type' => $this->bounceType($payload),
            'type_code' => isset($payload['TypeCode']) ? (int) $payload['TypeCode'] : null,
            'inactive' => isset($payload['Inactive']) ? (bool) $payload['Inactive'] : null,
        ], fn ($value) => $value !== null);
    }
}
