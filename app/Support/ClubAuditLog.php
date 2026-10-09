<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClubAuditLog
{
    public const LABELS = [
        'club.legal_master_data.updated' => 'Rechtliche Stammdaten aktualisiert',
        'club.contact_master_data.updated' => 'Vereinskontakte aktualisiert',
        'club.master_data_change.requested' => 'Stammdatenänderung beantragt',
        'club.master_data_change.applied' => 'Stammdatenänderung angewendet',
        'club.master_data_change.approved' => 'Stammdatenänderung freigegeben',
        'club.master_data_change.rejected' => 'Stammdatenänderung abgelehnt',
        'club.master_data_change.conflict_detected' => 'Stammdatenänderung mit Konflikt erkannt',
        'club.branding.updated' => 'Vereinsauftritt aktualisiert',
        'club.organization.department.created' => 'Abteilung angelegt',
        'club.organization.department.updated' => 'Abteilung aktualisiert',
        'club.organization.department.deleted' => 'Abteilung gelöscht',
        'club.organization.location.created' => 'Vereinsstandort angelegt',
        'club.organization.location.updated' => 'Vereinsstandort aktualisiert',
        'club.organization.location.deleted' => 'Vereinsstandort gelöscht',
        'club.organization.training_group.created' => 'Trainingsgruppe angelegt',
        'club.organization.training_group.updated' => 'Trainingsgruppe aktualisiert',
        'club.organization.training_group.deleted' => 'Trainingsgruppe gelöscht',
        'club.organization.team_assigned' => 'Mannschaft organisatorisch zugeordnet',
        'club.governance.body.created' => 'Vereinsgremium angelegt',
        'club.governance.body.updated' => 'Vereinsgremium aktualisiert',
        'club.governance.body.deleted' => 'Vereinsgremium gelöscht',
        'club.governance.assignment.created' => 'Verantwortlichkeit zugeordnet',
        'club.governance.assignment.updated' => 'Verantwortlichkeit aktualisiert',
        'club.governance.assignment.deleted' => 'Verantwortlichkeit entfernt',
        'club.year_period.created' => 'Vereinsjahr angelegt',
        'club.year_period.updated' => 'Vereinsjahr aktualisiert',
        'club.year_period.deleted' => 'Vereinsjahr gelöscht',
        'club.policy_document.created' => 'Vereinsdokumentfassung angelegt',
        'club.policy_document.updated' => 'Vereinsdokumentfassung aktualisiert',
        'club.policy_document.deleted' => 'Vereinsdokumentfassung gelöscht',
        'club.custom_field.created' => 'Eigenes Datenfeld angelegt',
        'club.custom_field.updated' => 'Eigenes Datenfeld aktualisiert',
        'club.custom_field.deleted' => 'Eigenes Datenfeld gelöscht',
        'club.category.created' => 'Vereinskategorie angelegt',
        'club.category.updated' => 'Vereinskategorie aktualisiert',
        'club.category.deleted' => 'Vereinskategorie gelöscht',
        'club.number_range.created' => 'Nummernkreis angelegt',
        'club.number_range.updated' => 'Nummernkreis aktualisiert',
        'club.number_range.deleted' => 'Nummernkreis gelöscht',
        'club.number_range.allocated' => 'Nummer vergeben',
        'club.number_range.default_set' => 'Standardnummernkreis festgelegt',
        'club.number_range.default_cleared' => 'Standardnummernkreis entfernt',
        'club.metadata_subject.updated' => 'Eigene Daten und Kategorien aktualisiert',
        'club.invoice.created' => 'Rechnung erstellt',
        'club.invoice.member_question' => 'Rueckfrage zur Rechnung eingegangen',
        'club.document.downloaded' => 'Geschütztes Dokument heruntergeladen',
        'club.invoice.status_updated' => 'Rechnungsstatus geändert',
        'club.funding_program.created' => 'Förderprogramm angelegt',
        'club.funding_program.updated' => 'Förderprogramm aktualisiert',
        'club.funding_program.status_changed' => 'Förderprogrammstatus geändert',
        'club.invoice.reminder_sent' => 'Zahlungserinnerung gesendet',
        'club.payment.recorded' => 'Zahlung erfasst',
        'club.donation.recorded' => 'Spende erfasst',
        'club.sepa.created' => 'Lastschriftlauf vorbereitet',
        'club.sepa.approved' => 'Lastschriftlauf freigegeben',
        'club.sepa.notice_recorded' => 'Vorabinformation dokumentiert',
        'club.sepa.notices_prepared' => 'Vorabinformationen vorbereitet',
        'club.sepa.notices_queued' => 'Vorabinformationen zum Versand freigegeben',
        'club.sepa.notice_sent' => 'Vorabinformation an Mailtransport übergeben',
        'club.sepa.notice_delivery' => 'Zustellung der Vorabinformation vom Mailanbieter bestätigt',
        'club.sepa.notice_bounce' => 'Vorabinformation vom Mailanbieter zurückgewiesen',
        'club.sepa.settled' => 'SEPA-Zahlungseingang bestätigt',
        'club.sepa.returned' => 'SEPA-Rückgabe erfasst',
        'club.sepa.fee_recharge_void_requested' => 'Storno der Gebührenrechnung beantragt',
        'club.sepa.fee_recharge_voided' => 'Gebührenrechnung nach Freigabe storniert',
        'club.sepa.fee_recharge_void_withdrawn' => 'Stornoantrag für Gebührenrechnung zurückgezogen',
        'club.sepa.fee_recharge_credit_requested' => 'Gutschrift für Gebührenrechnung beantragt',
        'club.sepa.fee_recharge_credited' => 'Gutschrift für Gebührenrechnung freigegeben',
        'club.sepa.fee_recharge_refunded' => 'Erstattung einer Gebührenrechnung dokumentiert',
        'club.sepa.fee_recharge_credit_withdrawn' => 'Gutschriftantrag zurückgezogen',
        'club.sepa.fee_recharge_approved' => 'Gebührenweiterbelastung freigegeben und Rechnung erstellt',
        'club.sepa.fee_recharge_proposed' => 'Gebührenweiterbelastung vorgeschlagen',
        'club.sepa.fee_recharge_cancelled' => 'Vorschlag zur Gebührenweiterbelastung zurückgezogen',
        'club.sepa.fee_corrected' => 'SEPA-Rückgabegebühr korrigiert',
        'club.sepa.fee_recorded' => 'SEPA-Rückgabegebühr gebucht oder verknüpft',
        'club.sepa.retry_authorized' => 'Erneuter Einzug nach Rückgabe freigegeben',
        'club.sepa.exported' => 'Lastschriftlauf exportiert',
        'club.sepa.cancelled' => 'Lastschriftlauf storniert',
        'club.payment.corrected' => 'Zahlung korrigiert',
        'club.member.invited' => 'Mitglied eingeladen',
        'club.member.updated' => 'Mitglied aktualisiert',
        'club.member_relationship.saved' => 'Mitgliedsbeziehung aktualisiert',
        'club.member.external_updated' => 'Externe Personenakte aktualisiert',
        'club.member.duplicate_merged' => 'Mitgliedsdublette zusammengeführt',
        'club.member.timeline.created' => 'Mitgliedsverlauf ergänzt',
        'club.member.timeline.deleted' => 'Mitgliedsverlaufseintrag entfernt',
        'club.member.role_updated' => 'Rolle geändert',
        'club.permission_delegation.created' => 'Vertretungsrechte erteilt',
        'club.permission_delegation.revoked' => 'Vertretungsrechte widerrufen',
        'club.role_definition.created' => 'Vereinsrolle angelegt',
        'club.role_definition.updated' => 'Vereinsrolle aktualisiert',
        'club.role_definition.deleted' => 'Vereinsrolle gelöscht',
        'club.role_assignment.updated' => 'Vereinsrollen zugeordnet',
        'club.role_access.ended' => 'Rollen- und Vertretungsrechte beim Tätigkeitsende entzogen',
        'club.role_access_handover.proposed' => 'Nachfolge für Zugriffsrechte vorgeschlagen',
        'club.role_access_handover.approved' => 'Nachfolge für Zugriffsrechte freigegeben',
        'club.role_access_handover.applied' => 'Nachfolge für Zugriffsrechte umgesetzt',
        'club.inventory.loan_requested' => 'Inventarausleihe zur Freigabe beantragt',
        'club.inventory.loan_checked_out' => 'Inventar ausgegeben',
        'club.inventory.loan_approved' => 'Inventarausleihe freigegeben',
        'club.inventory.loan_rejected' => 'Inventarausleihe abgelehnt',
        'club.inventory.damage_reported' => 'Inventarschaden gemeldet',
        'club.inventory.financial_exception_approved' => 'Inventar-Wertgrenzenausnahme freigegeben',
        'club.member.removed' => 'Mitglied entfernt',
        'club.event.attendance_corrected' => 'Event-Anwesenheit korrigiert',
        'club.event.check_in_token_issued' => 'Event-Check-in-Code ausgegeben',
        'club.event.check_in_completed' => 'Event-Check-in abgeschlossen',
        'club.contribution_rule.created' => 'Beitragsregel erstellt',
        'club.contribution_rule.updated' => 'Beitragsregel aktualisiert',
        'club.membership_request.approved' => 'Mitgliedsantrag angenommen',
        'club.membership_request.declined' => 'Mitgliedsantrag abgelehnt',
        'club.membership_request.submitted' => 'Mitgliedsantrag eingereicht',
        'club.membership_request.withdrawn' => 'Mitgliedsantrag zurückgezogen',
        'club.membership_prospect.created' => 'Interessent angelegt',
        'club.membership_prospect.updated' => 'Interessent aktualisiert',
        'club.membership_prospect.archived' => 'Interessent archiviert',
        'club.membership_prospect.converted' => 'Interessent als Mitglied übernommen',
        'club.membership_request.information_requested' => 'Ergänzende Angaben angefordert',
        'club.membership_request.information_provided' => 'Ergänzende Angaben eingereicht',
        'club.membership_request.waitlisted' => 'Mitgliedsantrag auf Warteliste gesetzt',
        'club.membership_pause.requested' => 'Mitgliedschaftspause beantragt',
        'club.membership_termination.requested' => 'Austritt beantragt',
        'club.recruiting.application_updated' => 'Bewerbungsstatus geändert',
        'club.recruiting.application_erased' => 'Bewerbung datenschutzkonform gelöscht',
        'club.work_automation.queued' => 'Arbeitsautomation eingereiht',
        'club.work_automation.completed' => 'Arbeitsautomation abgeschlossen',
        'club.work_automation.failed' => 'Arbeitsautomation fehlgeschlagen',
        'club.work_automation.retry_queued' => 'Arbeitsautomation erneut eingereiht',
        'club.procurement.requested' => 'Beschaffungsanforderung angelegt',
        'club.procurement.approved' => 'Beschaffungsanforderung freigegeben',
        'club.procurement.rejected' => 'Beschaffungsanforderung abgelehnt',
        'club.procurement.ordered' => 'Beschaffung bestellt',
        'club.procurement.received' => 'Beschaffung als Wareneingang gebucht',
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
