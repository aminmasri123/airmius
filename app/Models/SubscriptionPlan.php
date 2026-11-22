<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'target_actor',
        'name',
        'description',
        'monthly_price_cents',
        'yearly_price_cents',
        'currency',
        'member_limit',
        'team_limit',
        'storage_gb',
        'features',
        'minimum_term_months',
        'cancellation_notice_days',
        'cta_label',
        'badge',
        'paypal_product_id',
        'paypal_plan_id',
        'paypal_plan_signature',
        'paypal_payload',
        'sort_order',
        'is_public',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'minimum_term_months' => 'integer',
            'cancellation_notice_days' => 'integer',
            'paypal_payload' => 'array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function catalogFeatures(): array
    {
        $sponsorAccess = match ($this->slug) {
            'free', 'starter' => 'Neue Vereinssponsoren können ab dem Club-Tarif angelegt werden.',
            'club', 'pro', 'elite' => 'Vereinssponsoren anlegen mit dem Vereinsrecht „Vereinssponsoren anlegen und bearbeiten“.',
            default => null,
        };

        $features = $this->features ?? [];
        if ($this->slug === 'enterprise-verband' && in_array('Mandantenfähigkeit vorbereitet', $features, true)) {
            $features[] = 'Die Mandantenfähigkeit ist als vorbereitet ausgewiesen. Welche Schnittstellen, Migrations- und Schulungsleistungen sowie Vertragsunterlagen bereitgestellt werden, ergibt sich aus dem individuellen Angebot.';
        }
        if (in_array($this->slug, [
            'free', 'starter', 'club', 'pro', 'elite', 'sportler-free', 'sportler-pro',
            'trainer-free', 'trainer-pro', 'eltern-free', 'sponsor-local',
            'anbieter-marketplace', 'enterprise-verband',
        ], true)) {
            $features[] = 'QR-Anwesenheit bei privaten Teamterminen aktivieren. Der Vereinsinhaber kann einen persönlichen Check-in-Code ausstellen. Beim erfolgreichen Einlösen über die App-Schnittstelle wird das Teammitglied als anwesend gespeichert.';
            $features[] = 'Öffentliche Einzeltermine ohne Vereins- oder Teambezug: Für normale Mitglieder ohne persönliches Abo gilt ein Monatslimit von zwei. Bei aktiven Nutzerabos der acht persönlichen und Verbandstarife war keine Monatsgrenze hinterlegt.';
            $features[] = 'Neue private Termine benötigen eine Teamzuordnung. Mit events.edit können Teammitglieder private Teamtermine mit QR-Anwesenheit anlegen und umbenennen. Zum Löschen genügt events.delete. Eigene private Termine erscheinen in der Veranstaltungsübersicht und lassen sich im Detail öffnen. Private persönliche Termine anderer Konten ohne Freigabe bleiben verborgen.';
            $features[] = 'Verifizierte, öffentlich gelistete Vereine sind für Gäste auf der Web-Profilseite und im öffentlichen Vereinskatalog der App sichtbar.';
            $features[] = 'Auch bei zugewiesener Trainerrolle verhindert eine ausdrückliche Sperre von organization.edit das Anlegen einer Vereins-Trainingsgruppe. Trainingsgruppen im Verein mit organization.edit anlegen, einem Standort zuordnen und umbenennen. Zum Löschen ist organization.delete erforderlich und es darf kein Team mehr zugeordnet sein.';
            $features[] = 'Mehrere Vereinsstandorte anlegen und bearbeiten mit organization.edit. Für das Löschen eines unbenutzten Standorts ist organization.delete erforderlich. Solange ein Team dem Standort zugeordnet ist, bleibt die Löschung auch mit diesem Recht gesperrt.';
            $features[] = 'Die Web-Mitgliederverwaltung liefert das Vereinsprotokoll mit finance.view, members.manage oder members.roles. In der Finanzübersicht der App-Schnittstelle ist finance.view erforderlich.';
            $features[] = 'SEPA-Lastschriften für offene Rechnungen mit aktivem Mandat als XML exportieren ab Vereinsplan Pro mit finance.export.';
            $features[] = 'Bankumsätze aus CSV importieren und die Zuordnung zu Vereinsrechnungen bestätigen ab Vereinsplan Pro. Im Web ist finance.edit erforderlich, über die App-Schnittstelle zusätzlich finance.manage.';
            $features[] = 'DATEV-Export gebuchter Zahlungen als CSV ab Vereinsplan Pro mit dem Vereinsrecht finance.export.';
            $features[] = 'Überfällige Vereinsrechnungen mahnen ab Vereinsplan Club. Im Web ist finance.edit erforderlich, über die App-Schnittstelle zusätzlich finance.manage. Der Rechnungsempfänger erhält eine interne Benachrichtigung.';
            $features[] = 'Beitragsrechnungen für registrierte Mitglieder anlegen ab Vereinsplan Starter. Im Web ist finance.edit erforderlich, über die App-Schnittstelle zusätzlich finance.manage.';
            $features[] = 'Zahlungseingänge auf bestehende Vereinsrechnungen manuell buchen: im Web mit finance.edit auch im Free-Verein, über die App-Schnittstelle mit finance.manage ab Vereinsplan Starter.';
            $features[] = 'Mitgliederimport aus CSV oder XLSX ab Vereinsplan Starter. Im Web ist members.edit erforderlich, über die App-Schnittstelle zusätzlich members.manage.';
            $features[] = 'Mitgliedsnummern für registrierte und externe Mitglieder im jeweiligen Verein erzeugen: im Web mit metadata.edit, über die App-Schnittstelle mit metadata.edit und members.manage im Verein.';
            $features[] = 'Manuelle Mitgliederaufnahme ohne Einladung: Free bis 25, Starter bis 50, Club bis 150, Pro bis 500 Mitglieder je Verein. Elite hat keine konfigurierte Mitgliederobergrenze. Vereinskonten und externe Mitglieder zählen gemeinsam. Bei der Aufnahme externer Mitglieder lassen sich Lizenznummer und Gültigkeitsdatum speichern (Web: members.edit, App-Schnittstelle: zusätzlich members.manage).';
            $features[] = 'Teams anlegen mit dem Vereinsrecht teams.edit. Je Verein erlaubt Free ein Team und Starter fünf Teams. Club, Pro und Elite haben keine konfigurierte Teamobergrenze.';
            $features[] = 'Aktive Vereins- und Teammitglieder finden ihr eigenes Team in der Übersicht und können das Teamprofil öffnen.';
            $features[] = 'Vereinsmitglieder können den Beitritt zu einem Team ihres Vereins beantragen. Erst die Genehmigung durch eine berechtigte Person fügt das Mitglied zum Team hinzu.';
            $features[] = 'Bestehende Vereins- und Teamübungen können auch im Free-Verein mit training_exercises.edit bearbeitet werden. Die Entfernen-Aktion der App deaktiviert die Übung und erfordert training_exercises.delete.';
            $features[] = 'Gemeinsame Übungen anlegen ab Vereinsplan Starter: Mit training_exercises.edit für Verein und Team, als Teamtrainer für das eigene Team. Eine ausdrückliche Sperre des Bearbeitungsrechts gilt auch für Teamtrainer.';
            $features[] = 'Persönliche Trainingsübungen anlegen, suchen und bearbeiten. Über die App-Schnittstelle können Ersteller eigene Übungen aus der Bibliothek entfernen. Private Übungen bleiben anderen normalen Mitgliedern verborgen.';
            $features[] = 'Eigenes Training dokumentieren. Im Web können Ersteller Trainingsentwürfe korrigieren und löschen. Über die App-Schnittstelle lassen sich auch eigene geplante und abgeschlossene Einträge korrigieren und löschen.';
            $features[] = 'Sportplätze auf der Web-Sportkarte und in der Platzliste der App finden. Angemeldete Mitglieder können Plätze anlegen und ihre eigenen Plätze bearbeiten oder löschen.';
            $features[] = 'Vereinsablage in Web und App-Schnittstelle: Aktive Mitglieder mit files.view sehen Dateien ihres Vereins. Eine ausdrückliche Sperre dieses Rechts schließt die Übersicht.';
            $features[] = 'Für Vereinssponsoren bestimmen Vereinsplan und Vereinsrecht den Zugriff. Anlegen und Bearbeiten erfordern sponsors.edit und mindestens den Club-Plan. Löschen erfordert sponsors.delete, im Web zusätzlich mindestens den Club-Plan. Die App-Schnittstelle erlaubt das Löschen auch in Free und Starter.';
            $features[] = 'Die Verwaltungsliste der App zeigt Vereinssponsoren mit sponsors.edit oder sponsors.delete im eigenen Verein. Die zentrale Web-Adminliste erfordert weitergehende Rechte.';
            $features[] = 'Aktive Vereinsmitglieder können Leistungen bestehender Vereinssponsoren mit sponsors.edit anlegen und bearbeiten. Zum Löschen ist sponsors.delete erforderlich.';
            $features[] = 'Persönliches Dashboard und Tagesübersicht öffnen. Empfänger können eigene Mitteilungen als gelesen oder ungelesen markieren, alle als gelesen markieren und einzelne löschen.';
            $features[] = 'Vereins-Dashboard für aktive Mitglieder mit cockpit.view. Im Web meldet der Verein mit Free oder Starter eine Tarifsperre, mit Club, Pro oder Elite ist das Dashboard freigeschaltet. Die App-Schnittstelle meldet das Ansichtsrecht auch im Free-Verein.';
            $features[] = 'Trainer-Cockpit in Web und App-API öffnen mit der globalen Rolle coach. Ein normales Mitglied ohne Trainerzugang erhält keinen Zugriff.';
            $features[] = 'Spieler können im eigenen Team über Web und App-Schnittstelle einen Teamchat mit einer ersten Nachricht anlegen. Weitere Teammitglieder werden dem Chat zugeordnet. Ohne Teammitgliedschaft ist das Anlegen für normale Konten gesperrt.';
            $routingLimit = in_array($this->slug, ['club', 'pro', 'elite', 'sportler-pro', 'trainer-pro'], true) ? 150 : 10;
            $features[] = sprintf('Automatische Routenplanung mit diesem Tarif: %d Routinganfragen pro Mitglied und Kalendermonat. Web und App teilen das Kontingent. Ein zusätzlicher berechtigter Vereins- oder persönlicher Tarif kann das Kontingent auf 150 erhöhen.', $routingLimit);
            $features[] = 'Eigene GPS-Strecken mit Koordinaten anlegen, umbenennen und löschen. Über die App-Schnittstelle können Mitglieder weitere Punkte an ihre laufende Aufzeichnung anhängen und die Strecke abschließen. Änderungen und Löschung fremder Strecken sind für normale Konten gesperrt.';
            $features[] = 'Eigenes Sportprofil je Sportart anlegen und löschen. Mitglieder können die Sichtbarkeit auf privat, für Trainer oder öffentlich setzen.';
            $features[] = 'Vereinsinventar mit inventory.view ansehen. Mitglieder mit zusätzlichem inventory.edit können Gegenstände anlegen und umbenennen. Für das Löschen eines unbenutzten Gegenstands genügt zusätzliches inventory.delete. Mit reinem Ansichtsrecht bleiben alle drei Schreibaktionen gesperrt. Das Löschrecht allein ergänzt keine Bearbeitungsrechte.';
            $features[] = 'Saisonpläne mit Phasen anlegen, Ziele bearbeiten und Pläne löschen: Mitglieder benötigen training_plans.edit für das Team und einen Verein mit Pro oder Elite. Veröffentlichte Saisonpläne bleiben für aktive Vereins- und Teammitglieder auch ohne Bearbeitungsrecht in der Teamübersicht sichtbar.';
            $features[] = 'Aktive Vereinsmitglieder können freie Hallenzeiten über das Vereinsinventar buchen. Bei Genehmigungspflicht können sie ihre ausstehende Anfrage zurückziehen. Ein anderes Vereinsmitglied mit inventory.approve kann die Anfrage genehmigen oder ablehnen. Ein bereits aktiv gebuchter Zeitraum ist für eine weitere Buchung derselben Halle gesperrt.';
            $features[] = 'Mit der Rolle sponsor ein eigenes Markenprofil anlegen und den Markennamen ändern. Nach dem Speichern steht das Profil zur Prüfung aus.';
            $features[] = 'Sponsoren mit eigenem Markenprofil sehen Einblendungen, Klicks und Klickrate ihrer Kampagnen im Sponsor-Cockpit. Kampagnen anderer Marken bleiben außerhalb ihrer Auswertung. Im Web zählen Einblendungen und Klicks bei Feed-Anzeigen mit Einwilligung zur Anzeigenmessung. Unmittelbare Wiederholungen in derselben Sitzung erhöhen die Zähler nicht.';
            $features[] = 'Käufer können veröffentlichte Produkte und Online-Kursangebote per Überweisung bestellen. Der Web-Direktkauf und der App-Warenkorb erzeugen eine Bestellung mit ausstehendem Zahlungseingang. Bei Angeboten externer Verkäufer wird eine konfigurierte Kategorieprovision bereits beim Bestellen gespeichert. Der Verkäuferbericht für Warenkorbbestellungen kann davon abweichen, weil er den Produktprovisionssatz auf den Positionsbetrag einschließlich Versand anwendet. Käufer können veröffentlichte Produkte nach Hauptkategorie, Unterkategorie und Sportart filtern. Veröffentlichte Bestandsprodukte ohne diese Zuordnung bleiben über den Titel auffindbar. Entwürfe und Angebote in Prüfung erscheinen nicht in der Käufersuche. Mit genehmigtem Shop-Antrag können Mitglieder Produkte und Online-Kursangebote zur Prüfung einreichen, umbenennen und löschen. Angemeldete Mitglieder können ihr eigenes Anbieterprofil anlegen und umbenennen. Eigene Filialen und Abholstationen lassen sich anlegen, umbenennen und löschen. Andere Mitglieder können diese Standorte weder umbenennen noch löschen.';
            $features[] = 'Teamtrainer können über die App-Schnittstelle zusammengefasste Überlastungshinweise für Sportler ihres Teams abrufen. Bei drei freigegebenen Einheiten mit wiederholt hoher Anstrengung und gemeldeten Schmerzen erschien die Stufe hoch. Ohne Einheiten meldete die Auswertung unzureichende Daten. Die persönliche Trainingsauswertung der App berechnet die Belastung aus Dauer und empfundener Anstrengung. Eine private Einheit mit Anstrengung 9 von 10 erschien beim Eigentümer als Hinweis. Ein anderes Mitglied ohne Freigabe hatte keinen Zugriff auf diese Auswertung. Zugewiesene Teamtrainer können Feedback zu abgeschlossenen, für Trainer freigegebenen Einheiten ihrer Sportler speichern. Feedback zu eigenen Trainingsentwürfen ist gesperrt. Eigene private Trainingseinheiten anlegen und im Detail öffnen. Andere Mitglieder ohne Freigabe erhalten keinen Zugriff auf diese Einträge.';
            $features[] = 'Eingeladene Konten können eine gültige Einladung einmal annehmen. Die bestehende Mitgliedsnummer wird beim Beitritt übernommen. Empfänger können offene Einladungen über die App-Schnittstelle ablehnen. Abgelaufene oder abgelehnte Einladungen erlauben keinen Beitritt. Externe Vereinsmitglieder per E-Mail einladen: Im Free-Verein sind drei Einladungen pro Tag möglich. Bei Starter, Club, Pro und Elite war auch die vierte Einladung am selben Tag möglich. Im Web genügt members.edit. Die App-Schnittstelle erfordert zusätzlich members.manage. Eine ausdrückliche Sperre von members.edit verhindert das Einladen auf beiden Wegen.';
            $features[] = 'Mit members.roles und files.view können aktive Mitglieder eine eigene Vereinsrolle mit Dateileserecht anlegen, ansehen, umbenennen, zuweisen und wieder entziehen. Solange die Rolle einem Mitglied zugewiesen ist, bleibt das Löschen gesperrt.';
            $features[] = 'Fällige monatliche Mitgliedsbeiträge werden im Verein mit Pro oder Elite automatisch abgerechnet. Ein zweiter Lauf zum selben Stichtag erzeugt keine zweite Rechnung für denselben Beitrag. Zum nächsten Monatsbeginn entsteht eine eigene Rechnung für den neuen Kalendermonat.';
            $features[] = 'Im Web öffnet ein gültiger Einmalcode an die beim Kind hinterlegte Eltern-E-Mail-Adresse das Elternportal mit dem zugehörigen Kind. Mit dem Kind verknüpfte Erziehungsberechtigte (guardian) können die Einwilligung über Web und App-Schnittstelle widerrufen und erneut erteilen. Beim Widerruf erhält das Kind die Rolle minor_pending_consent, bei erneuter Zustimmung wieder minor_player. Im Familienkalender können bestätigte Erziehungsberechtigte bei vorliegender Einwilligung das verknüpfte Kind sehen und Abholtermine eintragen. Nach Widerruf der Beziehung werden Kind und zugehörige Termine ausgeblendet.';
            $features[] = 'Gemeinsamer Basiszugang: Feed, Chatübersicht, Benachrichtigungen, Dateiübersicht, Abzeichen und Marketplace-Katalog.';
            $features[] = 'Angemeldete Mitglieder können eigene kostenlose Kurs- und Campentwürfe im Lernstudio anlegen, umbenennen und löschen. Im Lernstudio können Kursersteller ein bestehendes Konto per E-Mail für ihren eigenen Kurs freischalten und den Zugang widerrufen. Bei fremden Kursen sind beide Aktionen für normale Konten gesperrt. Eigene Feedbeiträge und Kommentare anlegen, bearbeiten und löschen. Persönliche Ordner anlegen, umbenennen und löschen.';
            $features[] = 'Team- und Vereinsdateien: Geprüft mit aktiver Mitgliedschaft und files.view. Upload benötigt zusätzlich files.edit, Web-Download files.export und das Teilen mit befreundeten Konten files.share. Die App unterstützt öffentliche Freigabelinks und deren Widerruf. Gäste mit gültigem öffentlichem Link konnten die Datei herunterladen. Nach Widerruf lieferte derselbe Link HTTP 404. Für die authentifizierte Dateivorschau genügte in den getesteten Ablagen files.view. Der Menüpunkt „Datei herunterladen“ öffnet in der Android-App die Vorschau. Ob eine Datei auf dem Gerät gespeichert wird, ist noch ungeprüft.';
            $features[] = 'Team- und Vereinsordner mit passenden Dateirechten anlegen, umbenennen und löschen. Fremde persönliche Ordner, Beiträge und Kommentare unter fremden Beiträgen sind für normale Mitglieder vor Änderungen geschützt.';
        }
        if ($sponsorAccess !== null && ! in_array($sponsorAccess, $features, true)) {
            $features[] = $sponsorAccess;
        }

        return $features;
    }

    public function clubSubscriptions()
    {
        return $this->hasMany(ClubSubscription::class);
    }

    public function userSubscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function countryPrices()
    {
        return $this->hasMany(SubscriptionPlanPrice::class);
    }

    public function priceForCountry(?string $country): array
    {
        $country = strtoupper((string) $country);
        $price = $country !== ''
            ? $this->countryPrices->firstWhere('country_code', $country)
            : null;

        if ($price && ! $price->is_active) {
            return [
                'available' => false,
                'country_code' => $country,
                'currency' => $price->currency,
                'monthly_price_cents' => $price->monthly_price_cents,
                'yearly_price_cents' => $price->yearly_price_cents,
                'localized' => true,
            ];
        }

        return [
            'available' => true,
            'country_code' => $price?->country_code ?: ($country ?: null),
            'currency' => $price?->currency ?: ($this->currency ?: 'EUR'),
            'monthly_price_cents' => $price?->monthly_price_cents ?? $this->monthly_price_cents,
            'yearly_price_cents' => $price?->yearly_price_cents ?? $this->yearly_price_cents,
            'localized' => (bool) $price,
        ];
    }

    public static function free(): ?self
    {
        return static::query()->where('slug', 'free')->first();
    }
}
