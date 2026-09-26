<?php

namespace App\Services;

use App\Models\Club;
use App\Models\MailDelivery;
use App\Models\User;
use App\Notifications\ScheduledCommunicationMail;
use App\Support\ClubAuditLog;
use App\Support\TransactionalMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScheduledCommunicationService
{
    public const ALLOWED_VARIABLES = [
        'club.name',
        'date.today',
        'member.joined_on',
        'member.number',
        'member.status',
        'user.email',
        'user.name',
    ];

    public function preview(array $data): array
    {
        $this->assertAllowedVariables($data);

        $subject = trim((string) ($data['subject'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        $timezone = $data['timezone'] ?? config('app.timezone', 'UTC');
        $scheduledAt = Carbon::parse($data['scheduled_at'], $timezone)->setTimezone('UTC');
        $snapshot = $this->recipientSnapshot((int) $data['club_id'], $data['recipient_ids'] ?? []);
        $sampleRecipient = $snapshot[0] ?? null;

        return [
            'subject' => $subject,
            'body' => $body,
            'rendered_subject' => $sampleRecipient ? $this->render($subject, $sampleRecipient, $timezone) : $subject,
            'rendered_body' => $sampleRecipient ? $this->render($body, $sampleRecipient, $timezone) : $body,
            'template_key' => $data['template_key'] ?? 'custom',
            'timezone' => $timezone,
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'scheduled_local' => $scheduledAt->copy()->setTimezone($timezone)->toIso8601String(),
            'recipient_count' => count($snapshot),
            'recipient_snapshot' => $this->safeRecipientSnapshot($snapshot),
            'allowed_variables' => self::ALLOWED_VARIABLES,
            'used_variables' => $this->variablesIn($subject."\n".$body),
            'dedupe_key' => $this->dedupeKey($data),
        ];
    }

    public function schedule(User $actor, array $data): MailDelivery
    {
        $preview = $this->preview($data);
        $dedupeKey = $preview['dedupe_key'];

        return DB::transaction(function () use ($actor, $data, $preview, $dedupeKey): MailDelivery {
            $existing = MailDelivery::query()
                ->where('dedupe_key', $dedupeKey)
                ->whereIn('status', ['scheduled', 'sent'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $delivery = MailDelivery::query()->create([
                'dedupe_key' => $dedupeKey,
                'mail_type' => 'communication.scheduled',
                'club_id' => $data['club_id'],
                'recipient_id' => null,
                'recipient_email' => null,
                'recipient_name' => null,
                'status' => 'scheduled',
                'template_key' => $preview['template_key'],
                'subject' => $preview['subject'],
                'body' => $preview['body'],
                'primary_category' => $data['category'] ?? 'system',
                'context' => [
                    'preview' => collect($preview)->except(['recipient_snapshot', 'rendered_body'])->all(),
                    'recipient_ids' => collect($preview['recipient_snapshot'])->pluck('id')->all(),
                    'recipient_snapshot' => $preview['recipient_snapshot'],
                    'allowed_variables' => self::ALLOWED_VARIABLES,
                    'used_variables' => $preview['used_variables'],
                ],
                'scheduled_at' => Carbon::parse($preview['scheduled_at']),
                'timezone' => $preview['timezone'],
                'created_by' => $actor->id,
            ]);

            if ($club = Club::query()->find($data['club_id'])) {
                ClubAuditLog::record($club, $actor, 'club.communication.scheduled', $delivery, [
                    'mail_delivery_id' => $delivery->id,
                    'template_key' => $delivery->template_key,
                    'recipient_count' => $preview['recipient_count'],
                    'scheduled_at' => $preview['scheduled_at'],
                    'timezone' => $preview['timezone'],
                    'used_variables' => $preview['used_variables'],
                    'content_sha256' => hash('sha256', $preview['subject']."\n".$preview['body']),
                ]);
            }

            return $delivery;
        });
    }

    public function cancel(MailDelivery $delivery, User $actor): bool
    {
        if ($delivery->status !== 'scheduled' || $delivery->sent_at !== null) {
            return false;
        }

        return $delivery->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'context' => ($delivery->context ?: []) + [
                'cancelled_by' => $actor->id,
                'cancelled_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function sendDue(int $limit = 100): int
    {
        $sent = 0;

        MailDelivery::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get()
            ->each(function (MailDelivery $delivery) use (&$sent): void {
                $transport = app(TransactionalMail::class)->transportFor($delivery->primary_category ?: 'system');
                $snapshotData = $delivery->context['recipient_snapshot'] ?? null;
                if (! is_array($snapshotData) && $delivery->club_id) {
                    $snapshotData = $this->recipientSnapshot((int) $delivery->club_id, $delivery->context['recipient_ids'] ?? []);
                }

                $snapshot = collect($snapshotData ?? [])
                    ->filter(fn ($recipient) => is_array($recipient) && ! empty($recipient['id']) && ! empty($recipient['email']))
                    ->keyBy('id');
                $recipientIds = $snapshot->keys();
                $recipients = User::query()->whereIn('id', $recipientIds)->get()->keyBy('id');

                foreach ($snapshot as $recipientId => $recipientSnapshot) {
                    $recipient = $recipients->get($recipientId);
                    if (! $recipient) {
                        continue;
                    }

                    $recipient->notify(new ScheduledCommunicationMail(
                        $this->render((string) $delivery->subject, $recipientSnapshot, $delivery->timezone ?: config('app.timezone', 'UTC')),
                        $this->render((string) $delivery->body, $recipientSnapshot, $delivery->timezone ?: config('app.timezone', 'UTC')),
                        $transport['mailer'] ?? null,
                        $transport['address'] ?? null,
                        $transport['name'] ?? null,
                    ));
                }

                $delivery->update([
                    'status' => 'sent',
                    'used_category' => $transport['category'] ?? null,
                    'mailer' => $transport['mailer'] ?? null,
                    'from_address' => $transport['address'] ?? null,
                    'sent_at' => now(),
                ]);

                $sent++;
            });

        return $sent;
    }

    private function dedupeKey(array $data): string
    {
        $fingerprint = implode('|', [
            $data['club_id'] ?? '',
            $data['template_key'] ?? 'custom',
            $data['subject'] ?? '',
            $data['body'] ?? '',
            Carbon::parse($data['scheduled_at'], $data['timezone'] ?? config('app.timezone', 'UTC'))->setTimezone('UTC')->toIso8601String(),
            implode(',', collect($data['recipient_ids'] ?? [])->unique()->sort()->values()->all()),
        ]);

        return 'scheduled-communication:'.Str::limit(sha1($fingerprint), 64, '');
    }

    private function assertAllowedVariables(array $data): void
    {
        $unknown = array_values(array_diff(
            $this->variablesIn(($data['subject'] ?? '')."\n".($data['body'] ?? '')),
            self::ALLOWED_VARIABLES,
        ));

        abort_if($unknown !== [], 422, 'Nicht erlaubte Serienbrief-Variablen: '.implode(', ', $unknown));
    }

    private function variablesIn(string $content): array
    {
        preg_match_all('/\{\{\s*([a-z][a-z0-9_.-]*)\s*\}\}/i', $content, $matches);

        return collect($matches[1] ?? [])->map(fn (string $key) => Str::lower($key))->unique()->sort()->values()->all();
    }

    private function recipientSnapshot(int $clubId, array $recipientIds): array
    {
        $ids = collect($recipientIds)->filter()->unique()->values();

        return User::query()
            ->whereIn('id', $ids)
            ->whereHas('clubs', fn ($query) => $query->where('clubs.id', $clubId))
            ->with(['clubs' => fn ($query) => $query->where('clubs.id', $clubId)])
            ->orderBy('id')
            ->get()
            ->map(function (User $user) use ($clubId): array {
                $club = $user->clubs->firstWhere('id', $clubId);

                return [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'club_name' => $club?->name,
                    'member_number' => $club?->pivot?->member_number,
                    'member_status' => $club?->pivot?->membership_status,
                    'joined_on' => $club?->pivot?->joined_on,
                    'snapshot_at' => now()->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    private function safeRecipientSnapshot(array $snapshot): array
    {
        return collect($snapshot)
            ->map(fn (array $recipient): array => [
                'id' => $recipient['id'],
                'email' => $recipient['email'],
                'name' => $recipient['name'],
                'club_name' => $recipient['club_name'],
                'member_number' => $recipient['member_number'],
                'member_status' => $recipient['member_status'],
                'snapshot_at' => $recipient['snapshot_at'],
            ])
            ->values()
            ->all();
    }

    private function render(string $content, array $recipient, string $timezone): string
    {
        $values = [
            'club.name' => $recipient['club_name'] ?? '',
            'date.today' => now()->setTimezone($timezone)->toDateString(),
            'member.joined_on' => (string) ($recipient['joined_on'] ?? ''),
            'member.number' => (string) ($recipient['member_number'] ?? ''),
            'member.status' => (string) ($recipient['member_status'] ?? ''),
            'user.email' => (string) ($recipient['email'] ?? ''),
            'user.name' => (string) ($recipient['name'] ?? ''),
        ];

        return preg_replace_callback('/\{\{\s*([a-z][a-z0-9_.-]*)\s*\}\}/i', function (array $match) use ($values): string {
            return $values[Str::lower($match[1])] ?? '';
        }, $content) ?? $content;
    }
}
