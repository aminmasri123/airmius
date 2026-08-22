<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClubAuditLog
{
    public const LABELS = [
        'club.invoice.created' => 'Rechnung erstellt',
        'club.invoice.status_updated' => 'Rechnungsstatus geändert',
        'club.invoice.reminder_sent' => 'Zahlungserinnerung gesendet',
        'club.payment.recorded' => 'Zahlung erfasst',
        'club.member.invited' => 'Mitglied eingeladen',
        'club.member.updated' => 'Mitglied aktualisiert',
        'club.member.role_updated' => 'Rolle geändert',
        'club.member.removed' => 'Mitglied entfernt',
        'club.contribution_rule.created' => 'Beitragsregel erstellt',
        'club.contribution_rule.updated' => 'Beitragsregel aktualisiert',
        'club.membership_request.approved' => 'Mitgliedsantrag angenommen',
        'club.membership_request.declined' => 'Mitgliedsantrag abgelehnt',
        'club.membership_request.submitted' => 'Mitgliedsantrag eingereicht',
        'club.membership_request.withdrawn' => 'Mitgliedsantrag zurückgezogen',
        'club.membership_pause.requested' => 'Mitgliedschaftspause beantragt',
        'club.membership_termination.requested' => 'Austritt beantragt',
        'club.recruiting.application_updated' => 'Bewerbungsstatus geändert',
        'club.recruiting.application_erased' => 'Bewerbung datenschutzkonform gelöscht',
    ];

    public static function record(Club $club, ?User $actor, string $type, ?Model $subject = null, array $data = []): Activity
    {
        return Activity::query()->create([
            'user_id' => $actor?->id,
            'club_id' => $club->id,
            'team_id' => $data['team_id'] ?? null,
            'type' => $type,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'data' => $data,
        ]);
    }

    public static function forClub(Club $club, int $limit = 50): array
    {
        return Activity::query()
            ->where('club_id', $club->id)
            ->with('user:id,name,email')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Activity $activity) => self::payload($activity))
            ->values()
            ->all();
    }

    public static function payload(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'type' => $activity->type,
            'label' => self::LABELS[$activity->type] ?? str_replace(['club.', '_'], ['', ' '], $activity->type),
            'actor' => $activity->user ? [
                'id' => $activity->user->id,
                'name' => $activity->user->name,
                'email' => $activity->user->email,
            ] : null,
            'subject_type' => $activity->subject_type,
            'subject_id' => $activity->subject_id,
            'data' => $activity->data ?: [],
            'created_at' => $activity->created_at?->toJSON(),
        ];
    }
}
