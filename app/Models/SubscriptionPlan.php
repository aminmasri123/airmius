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
        if ($this->slug === 'sportler-pro') {
            $features = array_map(static fn ($feature) => match ($feature) {
                'Portfolio und Medien' => 'Sportlerportfolio mit Medien (geplant)',
                'Profilstatistiken vorbereitet' => 'Profilstatistiken (geplant)',
                default => $feature,
            }, $features);
        }
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
            $features[] = 'Auch bei zugewiesener Trainerrolle verhindert eine ausdrückliche Sperre von organization.edit das Anlegen einer Vereins-Trainingsgruppe. Die Gruppenverwaltung in Web und App speichert jede Trainingsgruppe im ausgewählten Verein. Dies gilt auch für Trainer Pro. Trainingsgruppen im Verein mit organization.edit anlegen, einem Standort zuordnen und umbenennen. Zum Löschen ist organization.delete erforderlich und es darf kein Team mehr zugeordnet sein.';
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
            $features[] = 'Nach Abweisung einer Bildrechtsmeldung kann die meldende Person Beschwerde einreichen. Solange die Meldung oder eine erste Beschwerde offen ist, wird eine weitere Beschwerde abgewiesen. Der Autor des gemeldeten Beitrags konnte für die fremde Meldung keine Beschwerde einreichen.';
            $features[] = 'Nimmt das eingeladene Konto die Vereinseinladung an, übernimmt seine Mitgliedschaft die hinterlegte Mitgliedsnummer, den Beitragsbetrag, das zugeordnete Zahlerkonto und die Familiengruppe. Dies wurde über Web und App-Schnittstelle für alle 13 Empfängertarife geprüft.';
            $features[] = 'Beim getesteten 15-jährigen Mitglied blieb die Teamzuordnung nach Annahme der Vereinseinladung offen. Das Zahlerkonto erhielt keine Elternverknüpfung und die elterliche Einwilligung blieb ausstehend.';
            $features[] = 'Aktive Vereins- und Teammitglieder finden ihr eigenes Team in der Übersicht und können das Teamprofil öffnen.';
            $features[] = 'Vereinsmitglieder können den Beitritt zu einem Team ihres Vereins beantragen. Erst die Genehmigung durch eine berechtigte Person fügt das Mitglied zum Team hinzu.';
            $features[] = 'Bestehende Vereins- und Teamübungen können auch im Free-Verein mit training_exercises.edit bearbeitet werden. Die Entfernen-Aktion der App deaktiviert die Übung und erfordert training_exercises.delete.';
            $features[] = 'Gemeinsame Übungen anlegen ab Vereinsplan Starter: Mit training_exercises.edit für Verein und Team, als Teamtrainer für das eigene Team. Eine ausdrückliche Sperre des Bearbeitungsrechts gilt auch für Teamtrainer.';
            $features[] = 'Persönliche Trainingsübungen anlegen, suchen und bearbeiten. Über die App-Schnittstelle können Ersteller eigene Übungen aus der Bibliothek entfernen. Private Übungen bleiben anderen normalen Mitgliedern verborgen.';
            $features[] = 'Eigenes Training dokumentieren. Im Web können Ersteller Trainingsentwürfe korrigieren und löschen. Über die App-Schnittstelle lassen sich auch eigene geplante und abgeschlossene Einträge korrigieren und löschen.';
            $features[] = 'Sportplätze auf der Web-Sportkarte und in der Platzliste der App finden. Angemeldete Mitglieder können Plätze anlegen und ihre eigenen Plätze bearbeiten oder löschen.';
            $features[] = 'Vereinsablage in Web und App-Schnittstelle: Aktive Mitglieder mit files.view sehen Dateien ihres Vereins. Eine ausdrückliche Sperre dieses Rechts schließt die Übersicht.';
            $features[] = 'Für einen eigenen Werbeentwurf im Sponsorenbereich lässt sich die Zahlung per Überweisung im Web und über die App-Schnittstelle vormerken. Wiederholtes Absenden erzeugt keinen zweiten Auftrag. Bei Werbeaufträgen für Sponsorenbereich und Marketplace-Karte können Kampagneneigentümer ohne Verwaltungsrecht ihre Überweisung nicht selbst bestätigen. Nach Bestätigung durch einen Plattformadministrator ist der Auftrag abgeschlossen. Die Anzeige wartet auf Prüfung. Erst mit der Freigabe durch einen Plattformadministrator wird sie am gebuchten Werbeplatz ausgeliefert. Pausieren stoppt die Auslieferung. Eine reguläre Buchung im Sponsorenbereich oder auf der Marketplace-Karte erhält auch nach Zahlungsbestätigung und Freigabe keinen automatischen Vorrang. Die Angabe eines anderen Vereins beim Anzeigenabruf blendet eine Vereinsanzeige nicht aus. Eine aktive interne Kampagne mit gesetztem Vorrang wurde auf beiden Werbeplätzen vor der regulären Kampagne ausgeliefert. Der öffentliche Anzeigenabruf der App fragt den Feed ab. Eine ausschließlich im Marketplace oder Sponsorenbereich platzierte Anzeige wird bei diesem Abruf nicht geliefert. Der Kampagneneigentümer ohne Verwaltungsrecht kann die Anzeige nicht selbst freigeben. Im öffentlichen Sponsorenverzeichnis sehen Konten aller 13 Tarife verifizierte Vereinssponsoren während ihrer Laufzeit auch ohne Recht im jeweiligen Verein. Kontaktperson und E-Mail eines Vereinssponsors können aktive Mitglieder mit sponsors.edit ab Vereinsplan Club im Web und über die App-Schnittstelle ändern. Eine neue Kündigungsfrist von 45 Tagen wurde über die App-Schnittstelle gespeichert. Im Web-Vereinsformular blieb die bisherige Frist erhalten. Über die App-Schnittstelle können aktive Mitglieder mit sponsors.edit und finance.approve ab Vereinsplan Club einen bereits eingereichten Sponsorenvertrag einer zweiten Person genehmigen. Bei der eigenen Einreichung wurde die Genehmigung abgewiesen. Über das Web-Vereinsformular blieb der Vertrag auch bei einer zweiten Person mit beiden Rechten in Prüfung. In Club, Pro und Elite wurde die gleichzeitig geänderte Kontaktperson gespeichert. Für die Verwaltung von Vereinssponsoren bestimmen Vereinsplan und Vereinsrecht den Zugriff. Anlegen und Bearbeiten erfordern sponsors.edit und mindestens den Club-Plan. Löschen erfordert sponsors.delete, im Web zusätzlich mindestens den Club-Plan. Die App-Schnittstelle erlaubt das Löschen auch in Free und Starter.';
            $features[] = 'Die Verwaltungsliste der App zeigt Vereinssponsoren mit sponsors.edit oder sponsors.delete im eigenen Verein. Die zentrale Web-Adminliste erfordert weitergehende Rechte.';
            $features[] = 'Teamspieler mit team_cashbox.manage können Strafregeln ihrer Teamkasse anlegen und umbenennen. Beim Löschen wird die Regel deaktiviert und bleibt gespeichert. Aktive Vereinsmitglieder können Leistungen bestehender Vereinssponsoren mit sponsors.edit anlegen und bearbeiten. Mit einem Textnachweis können sie die Leistung als erfüllt bestätigen. Bei fehlendem Text- und Bildnachweis bleibt eine geplante Leistung beim Bestätigungsversuch geplant. Zum Löschen ist sponsors.delete erforderlich.';
            $features[] = 'Persönliches Dashboard und Tagesübersicht öffnen. Empfänger können eigene Mitteilungen als gelesen oder ungelesen markieren, alle als gelesen markieren und einzelne löschen.';
            $features[] = 'Vereins-Dashboard für aktive Mitglieder mit cockpit.view. Im Web meldet der Verein mit Free oder Starter eine Tarifsperre, mit Club, Pro oder Elite ist das Dashboard freigeschaltet. Die App-Schnittstelle meldet das Ansichtsrecht auch im Free-Verein.';
            $features[] = 'Trainer-Cockpit in Web und App-API öffnen mit der globalen Rolle coach. Ein normales Mitglied ohne Trainerzugang erhält keinen Zugriff.';
            $features[] = 'Bei unvollständigem Sportprofil und ausgeschalteter Profilschätzung nennt die KI-Trainingsplanvorschau fehlende Profilangaben und verweist auf die Profileinstellungen. Webroute und App-Schnittstelle lieferten diesen Hinweis für alle 13 Tarife vor der Anbieteranfrage.';
            $features[] = 'Mit training_plans.edit, freiem Kontingent und erlaubter Profilschätzung lieferten Webroute und App-Schnittstelle bei allen 13 Tarifen eine Wochenplanvorschau mit einer Trainingseinheit, Qualitätsprüfung und Sicherheitstoken. Die Anwendung prüfte Steigerung und Belastungsverteilung, warnte beim unvollständigen Sportprofil und lieferte Verbesserungsvorschläge. Nach der Sicherheitsbestätigung wurde der Entwurf mit der Einheit gespeichert. Ohne Bestätigung wurde das Speichern abgewiesen. Mit zusätzlicher Trainerrolle lieferten Club, Pro, Elite und Trainer Pro die geprüfte Wochenplanvorschau. Die neun übrigen Tarife wiesen dieselbe Anfrage vor dem Anbieteraufruf ab. Der Test verwendete eine simulierte Anbieterantwort.';
            $features[] = 'Im Web-Trainer-Cockpit sah der Teamtrainer mit globaler Rolle coach die Wochenwerte der drei freigegebenen Einheiten und den Spieler mit Schmerzangabe, subjektiver Anstrengung und Anzahl belastender Einheiten in der Risikoliste. Die App-Schnittstelle meldete wiederholten Schmerz und wiederholt hohe Anstrengung. Der private Trainingseintrag blieb aus der Wochensumme ausgeschlossen. Die Überlastungsanzeige für Teamtrainer in der App-Schnittstelle berücksichtigt freigegebene Trainingseinträge. Ein privater Eintrag eines Spielers ohne Trainerfreigabe blieb bei allen 13 Tarifen aus der Anzahl und dem Belastungswert ausgeschlossen.';
            $features[] = 'Inventarbilder können aktive Vereinsmitglieder mit inventory.view und inventory.edit über die Vereins-API hochladen und entfernen. Nach dem Entfernen war auch die Bilddatei im Testspeicher gelöscht. Das Löschrecht für Inventarartikel allein genügte dafür in keinem der 13 Tarife. Aktive Vereinsmitglieder können eine interne PDF aus der Vereinsablage als Version der Vereinsordnung hinterlegen und umbenennen. Geprüft mit files.view, files.edit, policy_documents.view und policy_documents.edit sowie policy_documents.download für den Download. In der App öffnet der Dateiname die Vereinsablage mit einer Suche nach dieser Datei. Die verknüpfte Datei muss zum selben Verein gehören. Das gilt auch für Mitglieder mit Bearbeitungsrecht in beiden Vereinen. Das Löschen einer unbenutzten Dokumentversion erfordert policy_documents.delete. Die Dateiablage eines fremden Vereins bleibt auch bei veröffentlichten Vereinsordnungen für Außenstehende gesperrt. Über den Dokumentlink können Mitglieder anderer Vereine die veröffentlichte Ordnung eines gelisteten Vereins ansehen und herunterladen. Wird die Veröffentlichung zurückgenommen, verschwindet die Ordnung aus ihrer Liste und der bisherige Downloadlink liefert ihnen keine Datei mehr. Im Aufnahmeantrag kann eine freiwillige Zustimmung zu Vereinsfotos ausgelassen werden. Ein als Pflichtdokument eingerichteter Datenschutzhinweis muss bestätigt werden. Bei einer offenen Rückfrage kann die antragstellende Person die freiwillige Fotoauswahl in beide Richtungen ändern. Der bestätigte Datenschutzhinweis bleibt gespeichert. Nach der Aufnahme kann das Mitglied die gespeicherte Fotoauswahl über die Rückfragefunktion des genehmigten Antrags weder ergänzen noch zurücknehmen. Der allgemeine Widerruf aller Kontoeinwilligungen deaktiviert die zuvor erlaubte Produktanalyse. Die dokumentierte Fotoauswahl im genehmigten Vereinsantrag ändert sich dabei nicht. Beide Entscheidungen wurden für alle 13 Tarifkonten in Web und App-Schnittstelle geprüft. Mit members.approve können aktive Vereinsmitglieder einen offenen Aufnahmeantrag begründet auf die Warteliste setzen, auch im Free-Verein. Dadurch entsteht noch keine Mitgliedschaft. Mit members.approve kann der Verein einen wartenden Antrag annehmen oder ablehnen. Bei Annahme wird die Mitgliedschaft aktiv. Bei Ablehnung bleibt die Person außerhalb des Vereins. Beide Entscheidungen speichern die prüfende Person und ihre Notiz. Mit members.approve kann das prüfende Mitglied eine Rückfrage stellen. Die antragstellende Person kann darauf antworten. Ihre Antwort wird am Antrag gespeichert und dieser steht wieder zur Prüfung. Web-Cockpit und App-Vereinsdetail melden Mitglieder-Onboarding ab Vereinsplan Starter als freigeschaltet. Bei persönlichen Tarifen entscheidet der Zielverein. Im automatisierten App-Oberflächentest führte der Antrag aus dem Vereinsprofil über alle sieben Schritte zur Statusansicht. Die freiwillige Fotozustimmung blieb abgewählt. Ein vollständiger Antrag auf Android mit jedem Tarifkonto ist offen. Für Materialverwaltung, Medienrechte, Belastungsanalyse, Verletzungsrisiko-Warnungen und KI-Regelprüfung melden sie die Freischaltung ab Vereinsplan Pro. Einzelaktionen haben eigene Zugangsregeln. Mit Trainerrolle und Teamzuordnung lieferten Web-Cockpit und App-Cockpit-Schnittstelle bei allen 13 Testkonten die Wochenwerte der freigegebenen Einheiten und den betroffenen Sportler in der Risikoliste. Die aktuelle Auswertung umfasst heute und die sechs Vortage, der Vergleich die sieben Tage unmittelbar davor. Im Oberflächentest zeigte die App dort den Namen, die gemeldete Anstrengung, den Schmerzwert und die Anzahl hoch belastender Einheiten. Sponsoren-CRM und eigene Regeln werden im Web-Cockpit und im Vereinsdetail der App-Schnittstelle für Elite-Vereine als freigeschaltet gemeldet. Für die Webansicht benötigt ein aktives Mitglied cockpit.view. Die App-Schnittstelle liefert den Tarifstatus auch bei gesperrtem Cockpitrecht. Bei eigenen jährlichen Beitragsregeln wurden Anlegen und Umbenennen für alle 13 Tarifkonten geprüft. Im Web benötigte das aktive Vereinsmitglied finance.edit, über die App-Schnittstelle members.manage. Ein allgemeiner Regeleditor und die vollständige CRM-Bedienung auf dem Gerät sind offen.';
            $features[] = 'Spieler können im eigenen Team über Web und App-Schnittstelle einen Teamchat mit einer ersten Nachricht anlegen. Weitere Teammitglieder werden dem Chat zugeordnet. Ohne Teammitgliedschaft ist das Anlegen für normale Konten gesperrt.';
            $routingLimit = in_array($this->slug, ['club', 'pro', 'elite', 'sportler-pro', 'trainer-pro'], true) ? 150 : 10;
            $features[] = sprintf('Automatische Routenplanung mit diesem Tarif: %d Routinganfragen pro Mitglied und Kalendermonat. Web und App teilen das Kontingent. Ein zusätzlicher berechtigter Vereins- oder persönlicher Tarif kann das Kontingent auf 150 erhöhen.', $routingLimit);
            $features[] = 'Eigene GPS-Strecken mit Koordinaten anlegen, umbenennen und löschen. Über die App-Schnittstelle können Mitglieder weitere Punkte an ihre laufende Aufzeichnung anhängen und die Strecke abschließen. Änderungen und Löschung fremder Strecken sind für normale Konten gesperrt.';
            $features[] = 'Eigenes Sportprofil je Sportart anlegen und löschen. Mitglieder können die Sichtbarkeit auf privat, für Trainer oder öffentlich setzen.';
            $features[] = 'Vereinsinventar mit inventory.view ansehen. Mitglieder mit zusätzlichem inventory.edit können Gegenstände anlegen und umbenennen. Für das Löschen eines unbenutzten Gegenstands genügt zusätzliches inventory.delete. Mit reinem Ansichtsrecht bleiben alle drei Schreibaktionen gesperrt. Das Löschrecht allein ergänzt keine Bearbeitungsrechte.';
            $features[] = 'Saisonpläne mit Phasen anlegen, Ziele bearbeiten und Pläne löschen: Mitglieder benötigen training_plans.edit für das Team und einen Verein mit Pro oder Elite. Veröffentlichte Saisonpläne bleiben für aktive Vereins- und Teammitglieder auch ohne Bearbeitungsrecht in der Teamübersicht sichtbar.';
            $features[] = 'Aktive Vereinsmitglieder können freie Hallenzeiten über das Vereinsinventar buchen. Bei Genehmigungspflicht können sie ihre ausstehende Anfrage zurückziehen. Ein anderes Vereinsmitglied mit inventory.approve kann die Anfrage genehmigen oder ablehnen. Ein bereits aktiv gebuchter Zeitraum ist für eine weitere Buchung derselben Halle gesperrt.';
            $features[] = 'Mit der Rolle sponsor ein eigenes Markenprofil anlegen und den Markennamen ändern. Nach dem Speichern steht das Profil zur Prüfung aus.';
            $features[] = 'Eigentümer einer Marketplace-Werbekampagne können das Budget bei hinterlegter Bankverbindung per Überweisung bestellen. Bis zum Zahlungseingang bleibt die Bestellung offen. Ein erneuter Aufruf erzeugte im Test keine zweite Bestellung. Die Webroute für Marketplace und Sponsorenbereich lieferte angemeldeten Käufern aller 13 Tarife die aktive Vereinsanzeige für den jeweiligen Platz bei verfügbarem Budget. Als Entwurf und nach Verbrauch des gesamten Budgets blieb die Anzeige aus. Aktive Vereinsmitglieder mit advertising.edit können einen eigenen Feed-Kampagnenentwurf für den Verein anlegen, umbenennen, auf ein breites Banner im Sponsorenbereich umstellen und löschen. Die Anlage erfordert keine Zahlung. Mit der Rolle sponsor konnten Markeninhaber für ihren eigenen Verein eine Partnerschaft mit Bannerrecht anfragen und die noch offene Anfrage zurückziehen. Mit sponsors.edit konnten aktive Mitglieder des angefragten Vereins offene Partnerschaften über die App-Schnittstelle genehmigen oder ablehnen. Eine zweite Entscheidung wurde abgewiesen. Im Web-Adminbereich genügte dieses Vereinsrecht allein bei keinem der 13 Tarife. Mit dem globalen Recht system.manage konnte ein Testkonto jedes Tarifs die Partnerschaft dort genehmigen oder ablehnen. Mit eigenem Markenprofil und der Rolle sponsor konnten Testkonten aller 13 Tarife eine Bannerkampagne ohne Vereinszuordnung als Entwurf anlegen, umbenennen und löschen. Eine Zahlung war dafür nicht erforderlich. Aktive Markenmitglieder mit Sponsorrolle und den Teamrechten administrator oder campaign_manager konnten Markenentwürfe anlegen, einen Entwurf des Markeninhabers umbenennen und den selbst angelegten Entwurf löschen. Für finance und viewer waren das Anlegen sowie das Umbenennen und Löschen des Inhaberentwurfs gesperrt. Sponsoren mit eigenem Markenprofil sehen Einblendungen, Klicks und Klickrate ihrer Kampagnen im Sponsor-Cockpit. Kampagnen anderer Marken bleiben außerhalb ihrer Auswertung. Im Web zählen Einblendungen und Klicks bei Feed-Anzeigen mit Einwilligung zur Anzeigenmessung. Unmittelbare Wiederholungen in derselben Sitzung erhöhen die Zähler nicht.';
            $features[] = 'Käufer können veröffentlichte Produkte und Online-Kursangebote per Überweisung bestellen. Der Web-Direktkauf und der App-Warenkorb erzeugen eine Bestellung mit ausstehendem Zahlungseingang. Bei Angeboten externer Verkäufer wird eine konfigurierte Kategorieprovision bereits beim Bestellen gespeichert. Der Verkäuferbericht für Warenkorbbestellungen kann davon abweichen, weil er den Produktprovisionssatz auf den Positionsbetrag einschließlich Versand anwendet. Käufer können veröffentlichte Produkte nach Hauptkategorie, Unterkategorie und Sportart filtern. Veröffentlichte Bestandsprodukte ohne diese Zuordnung bleiben über den Titel auffindbar. Entwürfe und Angebote in Prüfung erscheinen nicht in der Käufersuche. Verkäufer mit genehmigtem Shop-Antrag können eigene Produkte mit Hauptkategorie, Unterkategorie und mehreren Sportarten anlegen. Beim Wechsel der Kategorie werden die mitgesendeten Sportarten gespeichert. Mit genehmigtem Shop-Antrag können Mitglieder Produkte und Online-Kursangebote zur Prüfung einreichen, umbenennen und löschen. Angemeldete Mitglieder können ihr eigenes Anbieterprofil anlegen und umbenennen. Eigene Filialen und Abholstationen lassen sich anlegen, umbenennen und löschen. Andere Mitglieder können diese Standorte weder umbenennen noch löschen.';
            $features[] = 'Teamtrainer können über die App-Schnittstelle zusammengefasste Überlastungshinweise für Sportler ihres Teams abrufen. Bei drei freigegebenen Einheiten mit wiederholt hoher Anstrengung und gemeldeten Schmerzen erschien die Stufe hoch. Ohne Einheiten meldete die Auswertung unzureichende Daten. Die persönliche Trainingsauswertung der App berechnet die Belastung aus Dauer und empfundener Anstrengung. Eine private Einheit mit Anstrengung 9 von 10 erschien beim Eigentümer als Hinweis. Ein anderes Mitglied ohne Freigabe hatte keinen Zugriff auf diese Auswertung. Zugewiesene Teamtrainer können Feedback zu abgeschlossenen, für Trainer freigegebenen Einheiten ihrer Sportler speichern. Feedback zu eigenen Trainingsentwürfen ist gesperrt. Eigene private Trainingseinheiten anlegen und im Detail öffnen. Andere Mitglieder ohne Freigabe erhalten keinen Zugriff auf diese Einträge.';
            $features[] = 'Eingeladene Konten können eine gültige Einladung einmal annehmen. Die bestehende Mitgliedsnummer wird beim Beitritt übernommen. Empfänger können offene Einladungen über die App-Schnittstelle ablehnen. Abgelaufene oder abgelehnte Einladungen erlauben keinen Beitritt. Externe Vereinsmitglieder per E-Mail einladen: Im Free-Verein sind drei Einladungen pro Tag möglich. Bei Starter, Club, Pro und Elite war auch die vierte Einladung am selben Tag möglich. Im Web genügt members.edit. Die App-Schnittstelle erfordert zusätzlich members.manage. Eine ausdrückliche Sperre von members.edit verhindert das Einladen auf beiden Wegen.';
            $features[] = 'Mit members.roles und files.view können aktive Mitglieder eine eigene Vereinsrolle mit Dateileserecht anlegen, ansehen, umbenennen, zuweisen und wieder entziehen. Solange die Rolle einem Mitglied zugewiesen ist, bleibt das Löschen gesperrt.';
            $features[] = 'Fällige monatliche Mitgliedsbeiträge werden im Verein mit Pro oder Elite automatisch abgerechnet. Ein zweiter Lauf zum selben Stichtag erzeugt keine zweite Rechnung für denselben Beitrag. Zum nächsten Monatsbeginn entsteht eine eigene Rechnung für den neuen Kalendermonat.';
            $features[] = 'Der native Android-Anmeldebildschirm bietet für alle Tarife E-Mail und Passwort sowie Google und Microsoft an. Ein direkter Einstieg per Eltern-E-Mail-Code fehlt dort. Im Web öffnet ein gültiger Einmalcode an die beim Kind hinterlegte Eltern-E-Mail-Adresse das Elternportal mit dem zugehörigen Kind. Mit dem Kind verknüpfte Erziehungsberechtigte (guardian) können die Einwilligung über Web und App-Schnittstelle widerrufen und erneut erteilen. Beim Widerruf erhält das Kind die Rolle minor_pending_consent, bei erneuter Zustimmung wieder minor_player. Im Familienkalender können bestätigte Erziehungsberechtigte bei vorliegender Einwilligung das verknüpfte Kind sehen und Abholtermine eintragen. Nach Widerruf der Beziehung werden Kind und zugehörige Termine ausgeblendet.';
            $features[] = 'Die KI-Vorschau in der Trainingsplanverwaltung benötigt das Recht zur Planverwaltung. Bei einem gewöhnlichen Mitglied ohne dieses Recht wurde die Vorschau trotz freiem Monatskontingent abgewiesen. Bei ausgeschöpftem persönlichem Monatskontingent wird die synchrone Vorschau auch mit training_plans.edit vor der Anbieteranfrage abgewiesen. Die Anzeige des persönlichen Restkontingents zählt erfolgreich protokollierte Planerstellungen. Fehlgeschlagene Versuche verbrauchen keinen Platz. Zum Beginn des nächsten Kalendermonats steht wieder das volle Monatskontingent in der Anzeige. Ohne Trainer- oder Verwaltungsrolle zeigt die KI-Planung 3 Pläne monatlich mit höchstens 4 Wochen an. Bei Sportler Pro, Trainer Pro, Club, Pro und Elite sind es 10 mit höchstens 12 Wochen. Mit Trainerrolle sind bei Trainer Pro, Club, Pro und Elite 30 Pläne mit höchstens 26 Wochen ausgewiesen, bei den übrigen einzeln getesteten Tarifen ist das Trainerkontingent null. Die Ernährungsübersicht zeigt die KI-Bildanalyse für Sportler Pro, Trainer Pro sowie die Vereinstarife Club, Pro und Elite als tariflich freigeschaltet an. Jede Bildanfrage erfordert eine ausdrückliche KI-Einwilligung. Bei den übrigen acht Standardtarifen wird die Anfrage auch mit Einwilligung vor der Übermittlung an den KI-Anbieter abgewiesen. Die persönliche Einstiegscheckliste erkennt den ersten eigenen Trainingseintrag und erhöht den Fortschritt um zehn Prozentpunkte. Die Selbstbedienung für persönliche API-Tokens externer Anwendungen ist auch bei Elite nur vorbereitet. Die Web-Tokenverwaltung ist deaktiviert, die Android-Tokenmaske stellt keine Tokens aus. Gemeinsamer Basiszugang: Feed, Chatübersicht, Benachrichtigungen, Dateiübersicht, Abzeichen und Marketplace-Katalog.';
            $features[] = 'Angemeldete Mitglieder können eigene kostenlose Kurs- und Campentwürfe im Lernstudio anlegen, umbenennen und löschen. Im Lernstudio können Kursersteller ein bestehendes Konto per E-Mail für ihren eigenen Kurs freischalten und den Zugang widerrufen. Bei fremden Kursen sind beide Aktionen für normale Konten gesperrt. Der Sportarten-Zähler im öffentlichen Sportprofil berücksichtigt die Sichtbarkeit für den Betrachter. Der Profilinhaber sieht auch seine privaten Sportarten im Zähler. Angemeldete Mitglieder können das eigene Profilfoto hochladen, ersetzen und entfernen. Beim Ersetzen und Entfernen wird die bisherige Bilddatei gelöscht. Eine PDF-Datei wurde im Profilfoto-Upload in Web und App-Schnittstelle abgewiesen. Das gespeicherte Profilfoto blieb erhalten. Angemeldete Mitglieder können einen öffentlichen Bildbeitrag eines anderen Mitglieds wegen Bildrechten melden. Die Meldung wird zur Moderation mit dem Status offen gespeichert. Eigene Feedbeiträge und Kommentare anlegen, bearbeiten und löschen. Persönliche Ordner anlegen, umbenennen und löschen.';
            $features[] = 'Team- und Vereinsdateien: Geprüft mit aktiver Mitgliedschaft und files.view. Die App-Vorschau lieferte mit files.view den geprüften Dateiinhalt. Upload benötigt zusätzlich files.edit, Web-Download files.export und das Teilen mit befreundeten Konten files.share. Die direkte Freigabe an ein anderes Konto erfordert eine Freundschaft. Ohne Freundschaft wurde in allen 169 Tarifpaaren die Freigabe abgewiesen und keine Empfängerkopie angelegt. Eine private Textdatei ließ sich zwischen befreundeten Konten in allen 169 Kombinationen von Absender- und Empfängertarif teilen und vom Empfänger abrufen. Nach dem Löschen des persönlichen Originals durch den Absender lieferten Web-Download und App-Vorschau die Empfängerkopie in allen 169 Tarifpaaren weiterhin vollständig aus. Nach direkter Freigabe sieht das befreundete Empfängerkonto die Datei in seiner persönlichen Ablage. App-Vorschau und Web-Download lieferten den geteilten Inhalt. Das befreundete Empfängerkonto konnte seine Kopie umbenennen. Beim Web-Download wurde der neue Name verwendet, die Originaldatei behielt ihren Namen. Nach dem Löschen der persönlichen Kopie durch den Empfänger blieb die Originaldatei in der Team- oder Vereinsablage vollständig abrufbar. Die App unterstützt öffentliche Freigabelinks und deren Widerruf. Gäste mit gültigem öffentlichem Link konnten die Datei herunterladen. Ein auf einen Tag befristeter Link lieferte nach 25 Stunden HTTP 410 ohne Dateiinhalt. Der Widerruf erfasste beide selbst erstellten Links derselben Datei. Beide lieferten danach HTTP 404, der Link eines anderen Dateieintrags blieb abrufbar. Auch der Link eines zweiten berechtigten Mitglieds zur selben Datei funktionierte weiterhin. Das zweite Mitglied konnte seine eigene Freigabe separat widerrufen. Nach Widerruf eines öffentlichen Links blieb eine bereits direkt geteilte Datei für das befreundete Empfängerkonto in der App-Vorschau und im Web-Download abrufbar. Für die authentifizierte Dateivorschau genügte in den getesteten Ablagen files.view. Der Menüpunkt „Datei herunterladen“ öffnet in der Android-App die Vorschau. Android-Chrome speicherte die lokal wiedergegebene Antwort eines öffentlichen Freigabelinks im Downloadordner. Der gespeicherte Textinhalt wurde auf dem Emulator geprüft. Die Vorschauadresse einer privaten Textdatei aus der App-Dateiliste liefert dem angemeldeten Eigentümer den Dateiinhalt. Ein Gast ohne gültige Sitzung oder Token erhält HTTP 401. Beim externen Öffnen einer privaten Textdatei übergibt die Android-App laut Implementierung nur deren Adresse. Die externe Anwendung benötigt für diese Adresse eine eigene gültige Anmeldung. Die Web-Downloadroute liefert dem angemeldeten Eigentümer die private Textdatei als Anhang mit Dateinamen. Der Link aus der App-Dateiliste liefert denselben Inhalt als Vorschau. Mit einem gültigen Dateifreigabelink speicherte Android-Chrome die Vereinsordnungs-PDF aller 13 Testkonten im Downloadordner. Alle Inhalte wurden bytegenau geprüft. Der Android-PDF-Betrachter öffnete alle 13 gespeicherten Dateien anschließend aus dem Downloadordner und zeigte die erste Seite an. Alle 13 über Chrome gespeicherten Testtextdateien aus wiedergegebenen Freigabeantworten waren nach erneutem Emulatorstart im Downloadordner vorhanden. Ihre SHA-256-Prüfsummen stimmten mit den Ursprungsdaten überein. Beim Gerätetest in Android-App 1.0.76 wechselten alle 13 angemeldeten Testkonten beim Download ihrer privaten Textdatei zu Chrome. Ohne eigene Browsersitzung meldete Chrome fehlende Anmeldung.';
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
