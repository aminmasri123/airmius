<?php

namespace App\Support;

use App\Models\ClubAnnouncement;
use App\Services\ModerationService;
use Illuminate\Support\Facades\DB;

class ClubAnnouncementPublisher
{
    public function publishDue(int $limit = 100): int
    {
        $ids = ClubAnnouncement::query()
            ->where('workflow_status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNull('notified_at')
            ->orderBy('published_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id): void {
                $item = ClubAnnouncement::query()->with(['club', 'team'])
                    ->lockForUpdate()->find($id);
                if (! $item || $item->workflow_status !== 'published' || $item->notified_at || ! $item->published_at || $item->published_at->isFuture()) {
                    return;
                }

                $segment = app(CommunicationRecipientSegment::class);
                $snapshot = $segment->snapshot($item->club, $item->audience_type, $item->team_id);
                $recipients = $segment->recipientIds($item->club, $item->audience_type, $item->team_id);
                app(ModerationService::class)->flagIfNeeded($item, $item->title."\n".$item->body, $item->user_id);

                foreach ($recipients->unique()->reject(fn ($userId) => (int) $userId === (int) $item->user_id) as $userId) {
                    AppNotification::send((int) $userId, 'club.announcement', [
                        'title' => $item->title,
                        'body' => str($item->body)->limit(140)->toString(),
                        'url' => '/clubs/'.$item->club_id.'/announcements/'.$item->id,
                        'club_id' => $item->club_id,
                        'announcement_id' => $item->id,
                    ], ['dedupe_key' => 'club-announcement:'.$item->id]);
                }
                $item->update([
                    'recipient_snapshot_count' => $snapshot['recipient_count'],
                    'recipient_snapshot_hash' => $snapshot['recipient_hash'],
                    'recipient_snapshot_at' => now(),
                    'notified_at' => now(),
                ]);
            });
        }

        return $ids->count();
    }
}
