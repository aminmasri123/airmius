<?php

namespace App\Http\Controllers;

use App\Services\AirmiusLegalProfile;
use App\Services\LegalContentLocalizer;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class LegalPageController extends Controller
{
    /** @var array<int, string> */
    private array $protectedLegalValues = [];

    public function imprint(): Response
    {
        $legal = $this->legalProfile();

        return $this->render('Impressum', [
            [
                'title' => 'Angaben nach § 5 DDG',
                'body' => $this->addressLines($legal),
            ],
            [
                'title' => 'Kontakt',
                'body' => [
                    'E-Mail: '.$legal['email'],
                    'Telefon: '.$legal['phone'],
                ],
            ],
            [
                'title' => 'Vertretungsberechtigte Person',
                'body' => [
                    $legal['representative'],
                ],
            ],
            [
                'title' => 'Register, Umsatzsteuer und Aufsicht',
                'body' => [
                    'Registereintrag: '.$legal['register'],
                    'Umsatzsteuer-ID: '.$legal['vat_id'],
                    'Aufsichtsbehörde: '.$legal['supervisory_authority'],
                ],
            ],
            [
                'title' => 'Verantwortlich für Inhalte',
                'body' => [
                    $legal['content_responsible'],
                ],
            ],
        ], 'Letzte Aktualisierung: 02.05.2026');
    }

    public function privacy(): Response|JsonResponse
    {
        $legal = $this->legalProfile();

        return $this->render('Datenschutzerklärung', [
            [
                'title' => '1. Verantwortlicher',
                'body' => [
                    'Verantwortlich für die Verarbeitung personenbezogener Daten ist: '.$legal['provider_name'].', '.$this->singleLineAddress($legal).', E-Mail: '.$legal['email'].'.',
                    'Datenschutzkontakt: '.$legal['privacy_email'].'.',
                ],
            ],
            [
                'title' => '2. Welche Daten Airmius verarbeitet',
                'body' => [
                    'Registrierungsdaten: Vorname, Nachname, E-Mail-Adresse, Passwort, Geburtsdatum, Land und optional Adressdaten.',
                    'Bei Nutzern unter 16 Jahren: E-Mail-Adresse des Erziehungsberechtigten, Status der Zustimmung, Zeitpunkt der Anfrage und Entscheidung.',
                    'Elternbereich: gespeicherte Eltern-E-Mail, angeforderte Zugangscodes als Hash, Ablaufzeit, Nutzungszeitpunkt, Widerrufsstatus und optionale Verknüpfung mit einem Elternkonto.',
                    'Profil- und Plattformdaten: Profilfoto, Biografie, Sportarten, Skills, sportliche Lizenznummern, Teams, Vereine, Freundschaften, Empfehlungen, Gamification-Daten und Einstellungen.',
                    'Kommunikationsdaten: Posts, Kommentare, Chats, Reaktionen, Meldungen, Moderationsentscheidungen und Benachrichtigungen.',
                    'Vereinsverwaltungsdaten: Team-Beitrittsanfragen, Einladungen per E-Mail, externe Mitglieder ohne Airmius-Konto, Vereinsrollen, offizieller Vereinsstatus, Vereinsnummer, Mitgliedsstatus, Mitgliedsnummer, Eintrittsdatum, Beitragsbetrag, Beitragsintervall und vereinsinterne Mitgliedsnotizen.',
                    'Abrechnungsdaten: Rechnungsnummer, Rechnungstitel, Beschreibung, Betrag, Fälligkeitsdatum, Zahlungsstatus, Zahlungsdatum, Zahlungshistorie, Zahlungsreferenzen und Mahn-/Erinnerungsstatus.',
                    'Commerce- und Marketplace-Daten: Bestellungen, Add-ons, Marketplace-Angebote, Anbieterangaben, Provisionswerte, Auszahlungsstatus, Zahlungsanbieter, Checkout-Status und Zahlungsreferenzen.',
                    'Werbe- und Sponsoringdaten: Ads-Kampagnen, Ziel-URL, Budget, Laufzeit, Status, Impressionen, Klicks, CTR und technisch erforderliche Ereignisdaten zur Kampagnenmessung.',
                    'Bei erteilter Einwilligung zur Werbemessung können zusätzlich Ad-Ereignisse wie Impression, Klick, Warenkorb, Checkout-Start, Registrierung oder Kaufzuordnung mit Kampagnen-ID, Creative-ID, Platzierung, Zeitstempel, Bestell-/Referenzbezug und Wert in Cent gespeichert werden. Zahlungsdaten selbst werden nicht in den Ad-Ereignissen gespeichert.',
                    'Bei erteilter Einwilligung zu personalisierter Werbung können einfache Interessen aus der Plattformnutzung, insbesondere Marketplace-Kategorien und Produktinteressen, verwendet werden, um passendere Anzeigen auszuspielen.',
                    'Werbeagentur- und Website-Service-Daten: Website-Anfragen von Vereinen, gewünschte Domain, Ziele, Notizen, Angebotsstatus und Kommunikationsstand.',
                    'Datei- und Mediendaten: hochgeladene Bilder, Videos, Anhänge, Dateityp, Dateigröße, Speicherpfad, komprimierte Medienversionen, automatisch erzeugte Video-Vorschaubilder und technische Auslieferungsdaten über Cloudflare R2/CDN.',
                    'Trainings-, Ernährungs- und Trinkdaten: Trainingspläne, Einheiten, Logs, Übungen, Sätze, Sportart, Dauer, Distanz, Intensität, Mahlzeiten, Lebensmittel, Nährwerte, Trinkmengen, Ziele und Fortschrittswerte.',
                    'Sportkarten- und Routendaten: Startpunkte, Zielpunkte, Wegpunkte, Trackpunkte, Sportplätze, hochgeladene Sportplatzbilder, Ortsangaben, optionale Browser-Standortdaten sowie daraus berechnete Routen- und Distanzdaten.',
                    'KI-Nutzungsdaten: Anfragen an KI-Funktionen, zum Beispiel hochgeladene Essensbilder, daraus entfernte Metadaten, verkleinerte Bildversionen, Prompt-Kontext wie Mahlzeitentyp oder Ernährungsstil, KI-Antworten, Modellname, Anbieter, Token-/Nutzungszähler und Fehlerstatus.',
                    'Technische Daten: IP-Adresse, Browserdaten, Logdaten, Sitzungsdaten, Sprache, Zeitzone und Sicherheitsereignisse.',
                ],
            ],
            [
                'title' => '3. Zwecke der Verarbeitung',
                'body' => [
                    'Bereitstellung des sozialen Sportnetzwerks und der Nutzerkonten.',
                    'Organisation von Vereinen, Teams, Trainingseinheiten, Events, Chats und Dateien.',
                    'Verwaltung von Team-Beitrittsanfragen, Einladungen und Vereinsmitgliedschaften durch berechtigte Vereinsverantwortliche.',
                    'Zuordnung von Sportlern und Vereinen über sportliche Lizenznummern, Vereinsnummern und Mitgliedsnummern.',
                    'Verwaltung von Mitgliedsnummern, Beiträgen, Rechnungen, Zahlungshistorien, Zahlungsstatus und Zahlungserinnerungen im Auftrag bzw. Verantwortungsbereich des jeweiligen Vereins.',
                    'Sicherheit, Missbrauchsvermeidung, Moderation und Durchsetzung der Community-Regeln.',
                    'Eltern-/Erziehungsberechtigtenzustimmung bei minderjährigen Nutzern.',
                    'Bereitstellung eines Elternbereichs zur Prüfung verknüpfter Kinder, zum Widerruf der Zustimmung und zur optionalen Erstellung eines Elternkontos.',
                    'Benachrichtigungen, Support, Fehleranalyse und Verbesserung der Plattform.',
                    'Bereitstellung von Ernährungs-, Trink-, Trainings- und Sportkartenfunktionen, einschließlich Routenplanung, Tracking, Sportplatzsuche und Sportplatzeinträgen.',
                    'Bereitstellung optionaler KI-Funktionen, insbesondere zur Schätzung von Nährwerten aus Essensbildern, zur Unterstützung bei Trainings- und Ernährungsplanung sowie zur Erstellung oder Vorbereitung von Blog- und Hilfetexten.',
                    'Abwicklung von Airmius-Abos, Add-ons, Marketplace-Bestellungen, Anbieterprovisionen und Werbeagentur-/Website-Service-Anfragen.',
                    'Ausspielung und Messung von klar gekennzeichneten Sponsor- und Ads-Kampagnen, soweit dies für Betrieb, Abrechnung und Betrugsschutz erforderlich ist.',
                    'Personalisierte Ausspielung von Anzeigen und Retargeting nur nach vorheriger Einwilligung des eingeloggten Nutzers.',
                    'Conversion-Messung für Ads nur nach vorheriger Einwilligung des eingeloggten Nutzers, zum Beispiel um zu erkennen, ob aus einem Anzeigenklick eine Registrierung, Anfrage oder Bestellung entstanden ist.',
                    'Erfüllung gesetzlicher Pflichten und Verteidigung gegen Ansprüche.',
                ],
            ],
            [
                'title' => '4. Rechtsgrundlagen',
                'body' => [
                    'Art. 6 Abs. 1 lit. b DSGVO: Vertragserfüllung und vorvertragliche Maßnahmen.',
                    'Art. 6 Abs. 1 lit. b DSGVO: Verwaltung von Mitgliedschafts-, Beitrags- und Rechnungsfunktionen, soweit diese für die Nutzung von Vereinsfunktionen oder vereinbarte Leistungen erforderlich sind.',
                    'Art. 6 Abs. 1 lit. f DSGVO: berechtigtes Interesse an Sicherheit, Missbrauchsprävention, Moderation und Plattformbetrieb.',
                    'Art. 6 Abs. 1 lit. f DSGVO: berechtigte Interessen von Airmius und berechtigten Vereinsverantwortlichen an nachvollziehbarer Vereins-, Beitrags- und Zahlungsverwaltung.',
                    'Art. 6 Abs. 1 lit. b DSGVO: Bereitstellung von Trainings-, Ernährungs-, Trink-, Karten-, Routing- und Trackingfunktionen, soweit diese vom Nutzer verwendet werden.',
                    'Art. 6 Abs. 1 lit. a DSGVO: Einwilligung für optionale KI-Bildanalyse, Browser-Standortzugriff, Live-Tracking, personalisierte Trainings-/Ernährungsvorschläge und vergleichbare freiwillige Funktionen.',
                    'Art. 9 Abs. 2 lit. a DSGVO: ausdrückliche Einwilligung, soweit freiwillig eingegebene Trainings-, Gesundheits-, Ernährungs- oder Körperdaten als besondere Kategorien personenbezogener Daten einzuordnen sind.',
                    'Art. 6 Abs. 1 lit. c DSGVO: gesetzliche Pflichten.',
                    'Art. 6 Abs. 1 lit. b DSGVO: Zahlungsabwicklung, Add-on-Buchungen, Marketplace-Bestellungen und Werbeagentur-/Website-Service-Anfragen.',
                    'Art. 6 Abs. 1 lit. f DSGVO: berechtigtes Interesse an kontextueller Anzeigenbereitstellung, Missbrauchsvermeidung, Abrechnung von Provisionen und wirtschaftlichem Plattformbetrieb.',
                    'Art. 6 Abs. 1 lit. a DSGVO: Einwilligung für personalisierte Anzeigen, Retargeting und Conversion-Messung, soweit diese Verarbeitung nicht technisch erforderlich ist.',
                    'Art. 6 Abs. 1 lit. a DSGVO und Art. 8 DSGVO: Einwilligung, insbesondere bei zustimmungspflichtigen Minderjährigen.',
                ],
            ],
            [
                'title' => '5. Minderjährige und Elternzustimmung',
                'body' => [
                    'Nutzer unter 16 Jahren können soziale Funktionen nur nutzen, wenn ein Erziehungsberechtigter zustimmt.',
                    'Bis zur Entscheidung wird das Konto eingeschränkt. Das Kind kann sich anmelden, sieht aber den Hinweis, dass die Zustimmung noch aussteht oder abgelehnt wurde.',
                    'Erziehungsberechtigte können Zustimmung erteilen oder ablehnen. Eine erteilte Zustimmung kann für die Zukunft widerrufen werden.',
                    'Der Widerruf kann über den Elternbereich erfolgen. Dafür geben Erziehungsberechtigte die gespeicherte Eltern-E-Mail ein und bestätigen einen per E-Mail gesendeten Zugangscode.',
                    'Der Zugangscode wird nicht im Klartext gespeichert, sondern als Hash. Er ist zeitlich begrenzt und wird nach Nutzung markiert.',
                    'Erziehungsberechtigte können optional ein eigenes Airmius-Elternkonto erstellen oder ein vorhandenes Konto verknüpfen. In diesem Fall wird das Kind über die Eltern-E-Mail mit dem Elternkonto verknüpft.',
                ],
            ],
            [
                'title' => '6. KI-gestützte Funktionen',
                'body' => [
                    'KI-Funktionen sind optional. Vor einer Bildanalyse muss der Nutzer ausdrücklich bestätigen, dass das Bild zur Analyse an den konfigurierten KI-Anbieter gesendet werden darf.',
                    'Essensbilder werden vor der KI-Übermittlung verkleinert und Metadaten wie EXIF-Informationen werden entfernt, soweit dies technisch möglich ist. Die Bildanalyse erstellt nur einen Vorschlag; eine Mahlzeit wird erst gespeichert, wenn der Nutzer den Vorschlag prüft und übernimmt.',
                    'Airmius kann je nach Konfiguration Google Gemini, OpenAI, IONOS AI Model Hub oder andere vertraglich eingebundene KI-Anbieter verwenden. Der konkrete Anbieter kann aus technischen, datenschutzrechtlichen, Qualitäts- oder Kostengründen gewechselt werden.',
                    'Bei IONOS AI Model Hub ist eine OpenAI-kompatible API vorgesehen; Airmius kann diesen Anbieter bevorzugen, wenn europäische bzw. deutsche Datenverarbeitung vertraglich und technisch passend eingerichtet ist.',
                    'KI-Ergebnisse sind Schätzungen und dienen der Unterstützung. Sie ersetzen keine medizinische, ernährungswissenschaftliche, therapeutische oder sportmedizinische Beratung.',
                    'Airmius speichert keine rohen KI-Bild-Uploads dauerhaft, solange die Funktion entsprechend konfiguriert ist. Gespeichert werden nur vom Nutzer bestätigte Mahlzeiten, technische Nutzungszähler, Fehlerstatus und erforderliche Nachweise zur Sicherheit, Abrechnung oder Missbrauchsprävention.',
                ],
            ],
            [
                'title' => '7. Empfänger und Dienstleister',
                'body' => array_merge([
                    'Airmius nutzt Hostinger als Hosting-Anbieter für den Betrieb der Plattform. Mit Hostinger gilt nach Anbieterangabe ein Vertrag zur Auftragsverarbeitung nach Art. 28 DSGVO über die Konto- bzw. Vertragsannahme als abgeschlossen.',
                    'Airmius nutzt Cloudflare für Objektspeicher und Medienauslieferung, insbesondere Cloudflare R2 und Cloudflare CDN. Nach Anbieterangabe ist der Cloudflare Customer DPA für Self-Serve-Kunden Bestandteil der Cloudflare Self-Serve Subscription Agreement und umfasst unter anderem EU-Standardvertragsklauseln sowie Data-Privacy-Framework-Bezüge.',
                    'Cloudflare ist ein US-Anbieter. Internationale Datenübermittlungen können daher nicht pauschal ausgeschlossen werden; sie werden nach Anbieterangabe über DPA, SCCs und DPF abgesichert.',
                    'Soweit möglich wird die Konfiguration auf europäische Datenhaltung und DSGVO-konforme Verarbeitung ausgerichtet. Eine verbindliche Zusicherung, dass alle Cloudflare-Daten und Metadaten ausschließlich in der EU verbleiben, besteht nur, wenn passende Cloudflare-Datenlokalisierungsfunktionen wie Regional Services, Metadata Boundary oder Geo Key Manager tatsächlich gebucht und aktiviert sind.',
                    'Für KI-Funktionen können je nach Konfiguration Google, OpenAI, IONOS AI Model Hub oder andere vertraglich geprüfte Anbieter eingesetzt werden. Vor produktiver Nutzung müssen passende Auftragsverarbeitungsvereinbarungen, Datenübermittlungsmechanismen und Anbieterbedingungen geprüft und dokumentiert werden.',
                    'Für Sportkarte und Routenplanung können OpenStreetMap-Kartendaten und ein konfigurierter Routing-Dienst wie OSRM, openrouteservice, GraphHopper, Mapbox oder eine eigene Airmius-Infrastruktur genutzt werden. Dabei können Startpunkte, Ziele, Wegpunkte, Standortdaten und technische Verbindungsdaten an den jeweiligen Kartendienst oder Routing-Dienst übertragen werden.',
                ], $this->routingProviderLegalLines(), [
                    'Airmius kann außerdem technische Dienstleister für E-Mail-Versand, Sicherheit, Fehleranalyse und Zahlungsabwicklung einsetzen, wenn dies für den Plattformbetrieb erforderlich ist.',
                    'Für Zahlungen können Stripe, PayPal und Banküberweisung eingesetzt werden. Dabei werden die für Zahlung, Betrugsschutz, Rechnung und Nachweis erforderlichen Daten an den jeweiligen Zahlungsdienstleister übermittelt oder von diesem verarbeitet.',
                    'Marketplace-Anbieter, Sponsoren und Werbeagentur-/Website-Service-Anfragende erhalten nur die Daten, die für Angebot, Vertragserfüllung, Kommunikation, Abrechnung oder gesetzliche Pflichten erforderlich sind.',
                    'Werbekunden und Sponsoren erhalten Auswertungen grundsätzlich nur aggregiert oder kampagnenbezogen, zum Beispiel Impressionen, Klicks, Budgetverbrauch, CTR, Status und Conversion-Zahlen. Einzelne Nutzerprofile, Namen, E-Mail-Adressen oder private Kommunikationsinhalte werden nicht als Werbereport an Werbekunden weitergegeben.',
                    'Vereinsverantwortliche wie Owner, Admins und Manager können im Rahmen ihrer Berechtigungen Mitglieder-, Beitrags-, Rechnungs- und Zahlungsdaten ihres Vereins einsehen und bearbeiten.',
                    'Mit Auftragsverarbeitern werden Verträge nach Art. 28 DSGVO geschlossen.',
                    'Daten werden nur weitergegeben, wenn dies für den Plattformbetrieb erforderlich ist, eine Rechtsgrundlage besteht oder eine gesetzliche Pflicht vorliegt.',
                ]),
            ],
            [
                'title' => '8. Speicherdauer',
                'body' => [
                    'Daten werden gelöscht oder anonymisiert, sobald sie für die genannten Zwecke nicht mehr erforderlich sind.',
                    'Accountdaten werden grundsätzlich bis zur Löschung des Kontos gespeichert, soweit keine gesetzlichen Aufbewahrungspflichten oder berechtigten Interessen entgegenstehen.',
                    'Du kannst ausgewählte Daten auch ohne Kontolöschung in Einstellungen > Datenschutz > Daten löschen entfernen. Die öffentliche Anleitung ist unter /daten-loeschen erreichbar.',
                    'Bei längerer Inaktivität nutzt Airmius ein gestuftes Verfahren: Nach etwa 12 Monaten ohne Nutzung kann eine erste Erinnerung versendet werden, nach etwa 18 Monaten eine zweite Erinnerung und nach etwa 24 Monaten kann das Konto zur Anonymisierung vorgemerkt werden.',
                    'Vor der Anonymisierung wird grundsätzlich eine letzte Benachrichtigung mit einer Reaktionsfrist von etwa 30 Tagen versendet. Eine erneute Anmeldung setzt die Inaktivitätsprüfung zurück.',
                    'Bei der Anonymisierung werden personenbezogene Profilangaben, Social-Login-Verknüpfungen, Sport-App-Verknüpfungen, Tokens, Profilbilder, private Medien und persönliche Inhalte soweit möglich entfernt oder anonymisiert.',
                    'Rechnungs-, Zahlungs-, Bestell-, Vereins- und Nachweisdaten können weiter gespeichert bleiben, soweit gesetzliche Aufbewahrungspflichten, Vertragsnachweise, steuerliche Pflichten, Missbrauchsprävention oder berechtigte Interessen entgegenstehen.',
                    'Rechnungs-, Zahlungs- und Beitragsdaten können aufgrund handels-, steuer- oder vereinsrechtlicher Nachweis- und Aufbewahrungspflichten länger gespeichert werden.',
                    'Commerce-, Provisions-, Ads- und Werbeagentur-/Website-Service-Daten werden solange gespeichert, wie dies für Vertrag, Abrechnung, Nachweis, Support, Missbrauchsprävention oder gesetzliche Aufbewahrungspflichten erforderlich ist.',
                    'Ad-Ereignisse für Kampagnenmessung werden grundsätzlich nach der in der Plattform konfigurierten Aufbewahrungsfrist gelöscht, derzeit regelmäßig nach bis zu 180 Tagen, soweit keine gesetzlichen Aufbewahrungspflichten, Abrechnungsnachweise oder Missbrauchsfälle entgegenstehen.',
                    'Hochgeladene Medien und Dateien werden grundsätzlich solange gespeichert, wie sie für Profil, Feed, Chat, Verein, Team, Event oder Dateiablage erforderlich sind.',
                    'Rohbilder für KI-Analysen werden grundsätzlich nicht dauerhaft gespeichert, wenn die Funktion auf flüchtige Verarbeitung eingestellt ist. Bestätigte Mahlzeiten, Trinkmengen, Trainingsdaten, Routen, Tracks oder Sportplätze bleiben gespeichert, bis der Nutzer sie löscht oder gesetzliche bzw. berechtigte Gründe entgegenstehen.',
                    'Standort-, Track- und Routingdaten werden nur gespeichert, wenn der Nutzer sie speichert, veröffentlicht oder mit einem Training, Sportplatz oder Track verknüpft. Reine Vorschau- oder Berechnungsdaten werden möglichst kurz gehalten.',
                    'Moderations- und Sicherheitsdaten können zur Nachvollziehbarkeit und Missbrauchsvermeidung länger gespeichert werden.',
                ],
            ],
            [
                'title' => '9. Werbung, Personalisierung und Conversion-Messung',
                'body' => [
                    'Airmius kann kontextuelle Anzeigen, interne Hinweise und Sponsorflächen anzeigen. Kontextuell bedeutet, dass die Anzeige zum Bereich oder Inhalt der Seite passt, ohne dass dafür ein persönliches Interessenprofil verwendet wird.',
                    'Personalisierte Anzeigen, Retargeting und Conversion-Messung werden nur aktiviert, wenn du dies in deinen Datenschutzeinstellungen ausdrücklich erlaubst.',
                    'Ohne Einwilligung speichert Airmius keine personalisierten Retargeting-Signale und ordnet Anzeigenklicks nicht zu späteren Registrierungen oder Käufen zu.',
                    'Du kannst deine Einwilligung jederzeit in den Einstellungen im Bereich Datenschutz widerrufen. Der Widerruf gilt für die Zukunft.',
                    'Bei Widerruf werden neue personalisierte Anzeigen- und Conversion-Signale nicht mehr verarbeitet. Bereits entstandene Abrechnungs-, Nachweis- oder Sicherheitsdaten können weiterhin gespeichert bleiben, soweit dies erforderlich ist.',
                ],
            ],
            [
                'title' => '10. Rechte betroffener Personen',
                'body' => [
                    'Du hast Rechte auf Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit und Widerspruch.',
                    'Soweit die Verarbeitung auf Einwilligung beruht, kannst du diese mit Wirkung für die Zukunft widerrufen.',
                    'Du hast außerdem das Recht, dich bei einer Datenschutzaufsichtsbehörde zu beschweren.',
                ],
            ],
            [
                'title' => '11. Cookies und lokale Speicherung',
                'body' => [
                    'Airmius verwendet notwendige Cookies und lokale Speichermechanismen für Login, Sicherheit, Sprache, Theme und Sitzungsfunktionen.',
                    'Beim Abruf von Bildern, Dateien und statischen Inhalten können technisch notwendige Verbindungsdaten durch Hostinger und Cloudflare verarbeitet werden, um Hosting, Speicherung, Sicherheit und CDN-Auslieferung bereitzustellen.',
                    'Bei Ads-Kampagnen können Impressionen und Klicks kampagnenbezogen gezählt werden, damit Sponsoren und Airmius Leistung, Budgetverbrauch und Missbrauch erkennen können. Personalisierte Ads, Retargeting und Conversion-Zuordnung erfolgen nur nach Einwilligung.',
                    'Für die personalisierte Ads-Ausspielung können serverseitige Sitzungsinformationen wie zuletzt angeklickte Anzeige oder Marketplace-Interessen verwendet werden, wenn du dies erlaubt hast.',
                    'Analyse- oder Marketing-Technologien werden nur eingesetzt, wenn sie in der Cookie-Seite genannt werden und eine erforderliche Einwilligung vorliegt.',
                ],
            ],
        ], 'Stand: 25.05.2026. KI-, Karten-, Routing-, Ernährungs- und Trackingfunktionen sind ergänzt. Bitte lege die aktuellen AVV/DPA-Unterlagen der tatsächlich genutzten Anbieter intern ab und lasse die Texte vor Livegang rechtlich final prüfen.');
    }

    public function accountDeletion(): Response|JsonResponse
    {
        $legal = $this->legalProfile();

        return $this->render('Airmius-Konto löschen', [
            [
                'title' => 'Kontolöschung anfordern',
                'body' => [
                    'Auf dieser Seite erfährst du, wie du dein Airmius-Konto und die damit verknüpften personenbezogenen Daten dauerhaft löschen kannst. Die Kontolöschung steht in der Web-Version und in der Airmius-App zur Verfügung.',
                    'Bei der selbstständigen Kontolöschung in Web oder App wird die Löschung erst ausgeführt, nachdem du dich angemeldet, deine Identität bestätigt und den per E-Mail gesendeten Bestätigungscode eingegeben hast. Ohne Kontozugriff kannst du ein gesondertes Ersuchen an den Datenschutzkontakt richten; dabei prüfen wir deine Identität. Das bloße Öffnen dieser Seite löscht noch keine Daten.',
                ],
            ],
            [
                'title' => 'Kontolöschung in der Web-Version',
                'body' => [
                    'Melde dich bei Airmius an und öffne Profil > Konto löschen.',
                    'Bestätige deine Identität mit deinem Passwort. Wenn du Google oder Microsoft zur Anmeldung verwendest, bestätige stattdessen deine Konto-E-Mail-Adresse.',
                    'Fordere den Bestätigungscode an, gib den per E-Mail erhaltenen Code ein und bestätige anschließend „Konto endgültig löschen“.',
                ],
            ],
            [
                'title' => 'Kontolöschung in der Airmius-App',
                'body' => [
                    'Öffne in der App Einstellungen > Profil & Kontosicherheit > Konto löschen.',
                    'Bestätige deine Identität, fordere den Bestätigungscode an und gib den per E-Mail erhaltenen Code ein.',
                    'Bestätige anschließend die endgültige Kontolöschung. Nach erfolgreicher Löschung wirst du abgemeldet.',
                ],
            ],
            [
                'title' => 'Welche Daten gelöscht oder anonymisiert werden',
                'body' => [
                    'Das Nutzerkonto, Profil- und Kontaktdaten, Zugangsdaten, Social-Login-Verknüpfungen, persönliche Inhalte, private Medien, Gerätekennungen und weitere direkt mit dem Konto verbundene personenbezogene Daten werden gelöscht oder anonymisiert.',
                    'Beiträge, Kommentare, Storys, Dateien, Sport-, Standort-, Trainings- und Ernährungsdaten werden entfernt, soweit keine Rechte anderer Personen oder zwingenden Aufbewahrungsgründe entgegenstehen.',
                    'Vereinsinterne Beschlüsse, Dienststunden, Beschaffungsvorgänge und Buchungsnachweise können als Unterlagen des Vereins bestehen bleiben. Bei diesen Nachweisen wird die direkte Verknüpfung zum gelöschten Airmius-Konto gelöst; Angaben wie ein Name in einem Beschluss können weiterhin enthalten sein. Über Auskunft, Berichtigung, Löschung oder erforderliche Aufbewahrung dieser Vereinsdaten entscheidet der Verein als Verantwortlicher im Einzelfall.',
                    'Auch offene Vereinsausleihen oder Verlustfälle können als Vereinsvorgang bestehen bleiben. Die Kontoverknüpfung wird entfernt; bei noch offenen Fällen kann ein Namensnachweis im Vereinsvorgang verbleiben. Noch nicht genehmigte Ausleihanfragen werden storniert. Der Verein muss prüfen, ob und wie lange er den Namensnachweis für Rückgabe oder Ansprüche benötigt.',
                    'Bestellungen, Rechnungen, Zahlungen, Abonnements, Liefer- und Rückgabevorgänge sowie Sicherheits- oder Missbrauchsnachweise können anonymisiert oder bei bestehendem gesetzlichen beziehungsweise vertraglichen Aufbewahrungsgrund weiter gespeichert werden. Für Airmius-eigene Belege ist derzeit kein automatischer Fristablauf-Löschlauf implementiert. Nach Wegfall des jeweiligen Grundes müssen diese Daten gesondert geprüft und gelöscht oder anonymisiert werden.',
                ],
            ],
            [
                'title' => 'Dateien, Sicherungskopien und Wiederherstellung',
                'body' => [
                    'Die zu löschenden hochgeladenen Dateien werden teilweise in einem nachgelagerten Bereinigungslauf entfernt; fehlgeschlagene Löschversuche werden erneut verarbeitet.',
                    'Die Löschung entfernt Daten aus dem aktiven System und den zugeordneten Dateiablagen. Ältere Datenbank- oder Hosting-Sicherungen können die Daten bis zum Ablauf ihres gesonderten Sicherungszyklus noch enthalten. Eine sofortige Einzel-Löschung aus jeder Sicherung wird nicht zugesagt; bei einer Wiederherstellung müssen bereits ausgeführte Löschungen erneut berücksichtigt werden. Die konkrete Backup-Frist der Produktionsumgebung ist vor der Freigabe zu überprüfen.',
                ],
            ],
            [
                'title' => 'Kein Zugriff auf dein Konto?',
                'body' => [
                    'Nutze zuerst die Passwort-zurücksetzen-Funktion. Wenn du dich weiterhin nicht anmelden kannst, sende deine Kontolöschanfrage möglichst von der E-Mail-Adresse des betroffenen Kontos an '.$legal['privacy_email'].'.',
                    'Wenn du keinen Zugriff auf diese E-Mail-Adresse hast, schreibe von einer erreichbaren Adresse und nenne zur Zuordnung die bisherige Konto-E-Mail und deinen Namen. Bei begründeten Zweifeln an deiner Identität können wir nach Art. 12 Abs. 6 DSGVO weitere erforderliche Angaben anfordern; sende Ausweisdokumente nicht unaufgefordert.',
                    'Für ein Ersuchen nach Art. 17 DSGVO gilt Art. 12 Abs. 3 und 4 DSGVO: Der Verantwortliche muss unverzüglich, spätestens innerhalb eines Monats nach Eingang über die Bearbeitung informieren. Bei Bedarf kann er wegen Komplexität oder Zahl der Ersuchen um bis zu zwei weitere Monate verlängern, muss dies aber innerhalb des ersten Monats mit Gründen mitteilen. Wird nicht tätig geworden, sind die Gründe sowie Beschwerde- und Rechtsbehelfsmöglichkeiten mitzuteilen.',
                ],
            ],
            [
                'title' => 'Vereinsverwaltung und persönliches Konto sind getrennt',
                'body' => [
                    'Wenn du noch Besitzer eines Vereins bist, wird die Löschung deines persönlichen Kontos technisch abgelehnt. Übergib zuerst die Vereinsinhaberschaft an eine berechtigte Person oder lösche den Verein über seine Vereinseinstellungen. Das schützt Mitgliederdaten und verhindert eine unbemerkte Vereinslöschung durch den persönlichen Kontovorgang.',
                    'Die Kontolöschung ist kein Antrag auf Löschung der Daten eines Vereins. Informationen zur Vereinslöschung stehen auf einer eigenen öffentlichen Seite.',
                ],
            ],
            [
                'title' => 'Rechtsgrundlagen und Ausnahmen',
                'body' => [
                    'Das Löschungsrecht folgt aus Art. 17 Abs. 1 DSGVO. Eine Löschung kann nach Art. 17 Abs. 3 lit. b DSGVO eingeschränkt sein, soweit eine gesetzliche Aufbewahrungspflicht besteht; daneben können Ansprüche nach Art. 17 Abs. 3 lit. e DSGVO gesichert werden. Der verbleibende Datenumfang muss auf den jeweiligen Zweck begrenzt bleiben (Art. 5 Abs. 1 lit. c und e DSGVO).',
                    'Airmius-eigene Rechnungs- und Buchungsbelege können nach § 147 AO und, soweit anwendbar, § 257 HGB aufzubewahren sein. Die Frist hängt von der Unterlagenart und dem konkreten Rechtsverhältnis ab; nicht jeder Datensatz bleibt pauschal zehn Jahre gespeichert.',
                    '§ 147 Abs. 3 und 4 AO und § 257 Abs. 4 und 5 HGB unterscheiden grundsätzlich zehn Jahre für Bücher und Abschlüsse, acht Jahre für Buchungsbelege und sechs Jahre für weitere erfasste Unterlagen. § 14b Abs. 1 UStG sieht für Rechnungen von Unternehmern grundsätzlich acht Jahre vor. Die Frist beginnt regelmäßig erst mit dem Ende des Kalenderjahres, in dem die maßgebliche Unterlage entstanden oder die letzte Eintragung erfolgt ist. Ob die jeweilige Pflicht für Airmius oder einen Verein gilt und ob eine Frist im Einzelfall länger läuft, muss getrennt geprüft werden.',
                    'Die Abmeldung beendet den Zugriff auf das Konto; sie löscht nicht automatisch Dokumente oder Nachrichten anderer Personen. Für Auskunft, Berichtigung oder eine Einzelfallprüfung erreichst du den Datenschutzkontakt über /kontakt-und-melden.',
                    'Quellen: https://eur-lex.europa.eu/legal-content/DE/TXT/?uri=CELEX:32016R0679 ; https://www.gesetze-im-internet.de/ao_1977/__147.html ; https://www.gesetze-im-internet.de/hgb/__257.html ; https://www.gesetze-im-internet.de/ustg_1980/__14b.html',
                ],
            ],
        ], null, [
            'label' => 'Anmelden und Konto löschen',
            'href' => route('login', ['redirect' => '/user/profile']),
            'authenticated_label' => 'Konto jetzt löschen',
            'authenticated_href' => route('profile.show'),
        ]);
    }

    public function clubDeletion(): Response|JsonResponse
    {
        return $this->render('Airmius-Verein löschen', [
            [
                'title' => 'Zwei getrennte Vorgänge',
                'body' => [
                    'Ein Verein und ein persönliches Airmius-Konto werden getrennt gelöscht. Die Löschung eines Vereins entfernt nicht automatisch persönliche Konten seiner Mitglieder, Trainer oder Verantwortlichen. Für die persönliche Kontolöschung gibt es eine eigene öffentliche Informationsseite.',
                    'Der Verein entscheidet als Verantwortlicher für seine vereinseigenen Mitglieds- und Buchhaltungsdaten; Airmius verarbeitet solche Daten im vereinbarten Umfang als Dienstleister beziehungsweise Auftragsverarbeiter. Airmius-eigene Vertrags-, Rechnungs- und Sicherheitsdaten sind davon zu unterscheiden.',
                ],
            ],
            [
                'title' => 'Wer beantragen darf und wo',
                'body' => [
                    'Nur der berechtigte Vereinsbesitzer kann den Antrag stellen. Web: Mein Verein > Vereinsdaten > Vereinseinstellungen > Verein löschen. App: Mein Verein > Vereinseinstellungen > Profil > Verein löschen. Ein normaler Vereinsnutzer kann den Vorgang nicht auslösen.',
                    'Vor dem Antrag werden das Kontopasswort beziehungsweise bei reiner Social-Anmeldung die Konto-E-Mail, ein sechsstelliger E-Mail-Code und der exakte Bestätigungstext verlangt. Der E-Mail-Code ist 15 Minuten gültig. Das Öffnen dieser Informationsseite bewirkt keine Löschung.',
                ],
            ],
            [
                'title' => 'Frist, Nachricht und Rücknahme',
                'body' => [
                    'Bei einem zulässigen Antrag wird der früheste Löschtermin 30 Tage in der Zukunft gesetzt. Bis zur Ausführung bleibt der Verein nutzbar und der Besitzer kann den Antrag in denselben Einstellungen zurücknehmen. Ein wiederholter Antrag verschiebt die laufende Frist nicht.',
                    'Besitzer und eingetragene Vorstandsmitglieder werden benachrichtigt. Bei einem Besitzerwechsel wird ein offener Antrag nicht unter dem neuen Besitzer fortgeführt. Der tatsächliche Löschlauf erfolgt nach Fristablauf und nur, wenn keine Sperre besteht.',
                ],
            ],
            [
                'title' => 'Welche Daten betroffen sind',
                'body' => [
                    'Nach dem zulässigen Löschlauf werden vereinseigene Bereiche einschließlich Teams, Termine, Aufgaben, vereinsbezogener Mitgliedschaften, Inhalte und Dateien nach den technischen Zuordnungen entfernt. Verknüpfungen zu persönlichen Konten werden gelöst; die Konten und andere Vereine bleiben bestehen.',
                    'Dazu können insbesondere Team- und Rollenverknüpfungen, Trainings- und Wettkampfplanung, vereinsinterne Kommunikation, Gremien mit Sitzungen und Beschlüssen, Ehrenamts- und Dienststundenangaben sowie vereinseigene Rechnungen, Zahlungen und Buchungen gehören. Maßgeblich ist jeweils die Zuordnung zum gelöschten Verein; Unterlagen anderer Vereine und rein persönliche Inhalte werden durch diesen Vorgang nicht gelöscht.',
                    'Davon zu unterscheiden sind Airmius-eigene Support-, Zustell-, Vertrags- und Abrechnungsnachweise. Solche Nachweise können getrennt gespeichert bleiben, soweit dafür im Einzelfall eine Rechtsgrundlage und eine erforderliche Aufbewahrungsdauer bestehen; der Vereinsbezug wird bei der Löschung gegebenenfalls gelöst. Sie dürfen nicht unbegrenzt nur wegen ihrer früheren Vereinszuordnung aufbewahrt werden.',
                    'Vor dem Abschluss soll der Verein benötigte Daten und Belege exportieren oder anderweitig sichern. Ein Export einzelner Fachbereiche ist kein vollständiges revisionssicheres Archiv; die Verantwortung für die eigene Aufbewahrung bleibt beim Verein.',
                ],
            ],
            [
                'title' => 'Warum die automatische Löschung gesperrt sein kann',
                'body' => [
                    'Vorhandene Vereinsrechnungen, Zahlungen, Banktransaktionen, Finanzbuchungen oder SEPA-Stapel sperren derzeit die automatische Vereinslöschung. Auch ein aktives Abo beim Zahlungsanbieter kann sperren. Das ist keine dauerhafte Pflicht, den ganzen Verein sichtbar und aktiv zu halten, sondern ein Prüf- und Übergabefall für den Support.',
                    'Der Support muss vor einer manuellen Lösung klären, welche Unterlagen der Verein erhalten muss, welche Daten Airmius aus eigenen gesetzlichen Pflichten behalten muss und welche vereinseigenen Daten nach Weisung des Vereins zurückzugeben oder zu löschen sind. Eine automatische Archivierung der gesamten Vereinsbuchhaltung durch Airmius wird nicht zugesagt.',
                    'Bei Finanzdaten kann der Besitzer ein Datenpaket herunterladen, die eigenständige Archivierung der erforderlichen Originalunterlagen bestätigen und eine Supportprüfung anfordern. Danach kann er die 30-tägige Löschfrist beantragen. Der Löschlauf bleibt bis zur dokumentierten Freigabe durch einen Plattform-Admin gesperrt. Ändern sich Finanzdaten oder fehlt ein verknüpfter Beleg, ist ein neuer Export und eine erneute Prüfung nötig. Airmius-eigene Rechnungen können aufgrund eigener Pflichten getrennt aufbewahrt werden.',
                    'Die zu löschenden hochgeladenen Dateien werden teilweise in einem nachgelagerten Bereinigungslauf entfernt; fehlgeschlagene Löschversuche werden erneut verarbeitet.',
                    'Die Löschung entfernt Daten aus dem aktiven System und den zugeordneten Dateiablagen. Ältere Datenbank- oder Hosting-Sicherungen können die Daten bis zum Ablauf ihres gesonderten Sicherungszyklus noch enthalten. Eine sofortige Einzel-Löschung aus jeder Sicherung wird nicht zugesagt; bei einer Wiederherstellung müssen bereits ausgeführte Löschungen erneut berücksichtigt werden. Die konkrete Backup-Frist der Produktionsumgebung ist vor der Freigabe zu überprüfen.',
                ],
            ],
            [
                'title' => 'Rechtliche Einordnung',
                'body' => [
                    'Art. 17 DSGVO regelt das Recht auf Löschung und Ausnahmen; Art. 28 Abs. 3 lit. g DSGVO verlangt bei Auftragsverarbeitung grundsätzlich Rückgabe oder Löschung nach Ende der Leistung, sofern Unionsrecht oder nationales Recht eine Speicherung vorschreibt. Art. 5 Abs. 1 lit. c und e DSGVO begrenzt Umfang und Dauer der Speicherung.',
                    'Für aufbewahrungspflichtige Unterlagen kommen je nach Fall § 147 AO, § 257 HGB und § 14b UStG in Betracht. Ob und welche Vorschrift für den konkreten Verein gilt, hängt von seiner Rechts- und Steuersituation sowie der Unterlagenart ab. Airmius ersetzt keine steuerliche Archivierung oder Rechtsberatung.',
                    '§ 147 Abs. 3 und 4 AO und § 257 Abs. 4 und 5 HGB unterscheiden grundsätzlich zehn Jahre für Bücher und Abschlüsse, acht Jahre für Buchungsbelege und sechs Jahre für weitere erfasste Unterlagen. § 14b Abs. 1 UStG sieht für Rechnungen von Unternehmern grundsätzlich acht Jahre vor. Die Frist beginnt regelmäßig erst mit dem Ende des Kalenderjahres, in dem die maßgebliche Unterlage entstanden oder die letzte Eintragung erfolgt ist. Ob die jeweilige Pflicht für Airmius oder einen Verein gilt und ob eine Frist im Einzelfall länger läuft, muss getrennt geprüft werden.',
                    'Quellen: https://eur-lex.europa.eu/legal-content/DE/TXT/?uri=CELEX:32016R0679 ; https://www.gesetze-im-internet.de/ao_1977/__147.html ; https://www.gesetze-im-internet.de/hgb/__257.html ; https://www.gesetze-im-internet.de/ustg_1980/__14b.html',
                ],
            ],
            [
                'title' => 'Hilfe und manuelle Prüfung',
                'body' => [
                    'Ist der Antrag wegen Unterlagen oder Vertragspflichten gesperrt, sichere zunächst die verfügbaren Exporte und kontaktiere den Support über /kontakt-und-melden mit Vereinsname und Kontaktdaten. Der Support prüft Identität, Berechtigung und die erforderliche Aufbewahrung beziehungsweise Übergabe. Bis dahin wird keine automatische Löschung zugesagt.',
                    'Wenn du lediglich dein persönliches Konto löschen willst, nutze stattdessen die separate Kontolöschseite. Bei einem Löschungs- oder Auskunftsersuchen prüft Airmius den konkreten Einzelfall nach Art. 12 und 15 bis 18 DSGVO.',
                ],
            ],
        ], 'Stand: 15.10.2026. Die Darstellung beschreibt den derzeitigen Produktablauf; die rechtliche Bewertung im Einzelfall und die Auftragsverarbeitungsvereinbarung müssen vor einem produktiven Archiv-/Supportprozess fachlich geprüft werden.', [
            'label' => 'Anmelden und Verein verwalten',
            'href' => route('login', ['redirect' => '/club-cockpit']),
            'authenticated_label' => 'Zum Verein',
            'authenticated_href' => route('auth.club-cockpit.index'),
        ]);
    }

    public function dataErasure(): Response
    {
        $legal = $this->legalProfile();

        return $this->render('Daten löschen', [
            [
                'title' => 'Daten löschen, Konto behalten',
                'body' => [
                    'Airmius ermöglicht dir, ausgewählte personenbezogene Daten zu löschen, ohne dein Konto zu löschen. Die Funktion steht sowohl in der Web-Version als auch in der Airmius-App im Bereich Datenschutz zur Verfügung.',
                    'Melde dich an, öffne Einstellungen > Datenschutz > Daten löschen, wähle die gewünschten Datenbereiche aus und bestätige die Anfrage mit einem Code, den wir an deine Konto-E-Mail-Adresse senden.',
                ],
            ],
            [
                'title' => 'Welche Daten du entfernen kannst',
                'body' => [
                    'Du kannst unter anderem Profil- und Kontaktdaten, Beiträge, Kommentare, Storys, eigene Chat-Inhalte, Dateien, Sport-, Standort-, Trainings- und Ernährungsdaten, soziale Verbindungen, Gerätekennungen sowie gespeicherte Shop-Daten auswählen.',
                    'Eigene Beiträge, Kommentare, Storys, Dateien und persönliche Medien werden dauerhaft entfernt. Chat-Inhalte werden in bestehenden Unterhaltungen durch einen neutralen Löschhinweis ersetzt.',
                    'Lieferadressen, Warenkörbe, Merklisten und Bewertungen werden entfernt. Abgeschlossene Bestellungen ohne Rechnungs-, Zahlungs-, Liefer- oder offenen Klärungsbezug können anonymisiert werden.',
                ],
            ],
            [
                'title' => 'Was erhalten bleiben kann',
                'body' => [
                    'Dein Konto, deine E-Mail-Adresse und deine Zugangsdaten bleiben erhalten, damit du Airmius weiter nutzen kannst.',
                    'Wenn du stattdessen dein gesamtes Konto löschen möchtest, melde dich an und öffne Profil > Konto löschen. Bestätige dort dein Passwort – oder bei Google-/Microsoft-Anmeldung deine Konto-E-Mail-Adresse – sowie den per E-Mail gesendeten Code. Das Konto und die zugehörigen Daten werden dann nach dem Kontolöschprozess entfernt oder, soweit erforderlich, anonymisiert.',
                    'Rechnungen, Zahlungen, Abonnements, offene Bestellungen, Liefer- und Rückgabevorgänge sowie andere Nachweise können aufgrund gesetzlicher Aufbewahrungspflichten, Vertragserfüllung, Missbrauchsprävention oder berechtigter Interessen länger gespeichert bleiben.',
                    'Vereins-, Team- und Berechtigungszuordnungen werden durch die partielle Datenlöschung nicht automatisch entfernt, damit keine Daten anderer Personen oder Organisationen verloren gehen.',
                ],
            ],
            [
                'title' => 'Kein Zugriff auf dein Konto?',
                'body' => [
                    'Wenn du dich nicht mehr anmelden kannst, nutze zuerst die Passwort-zurücksetzen-Funktion. Falls das nicht möglich ist, sende deine Löschanfrage mit der E-Mail-Adresse des betroffenen Kontos an '.$legal['privacy_email'].'.',
                    'Bitte nenne nur die Datenbereiche, die gelöscht werden sollen. Wir können zur Sicherheit eine Identitätsprüfung verlangen, bevor wir die Anfrage bearbeiten.',
                ],
            ],
        ], 'Diese Seite beschreibt die Datenlöschung ohne Kontolöschung. Sie wird im Google-Play-Store als öffentliche Löschseite verlinkt.', [
            'label' => 'Anmelden und Daten löschen',
            'href' => route('login', ['redirect' => '/settings/daten-loeschen']),
            'authenticated_label' => 'Daten jetzt löschen',
            'authenticated_href' => route('auth.settings.privacy.erasure'),
        ]);
    }

    public function terms(): Response|JsonResponse
    {
        return $this->render('Allgemeine Nutzungsbedingungen', [
            [
                'title' => '1. Geltungsbereich',
                'body' => [
                    'Diese Nutzungsbedingungen gelten für die Nutzung von Airmius, einem sozialen Netzwerk für Sportler, Teams, Vereine und Organisationen.',
                    'Abweichende Bedingungen der Nutzer gelten nur, wenn Airmius ihnen ausdrücklich zustimmt.',
                ],
            ],
            [
                'title' => '2. Registrierung und Nutzerkonto',
                'body' => [
                    'Nutzer müssen bei der Registrierung richtige und aktuelle Angaben machen.',
                    'Zugangsdaten sind geheim zu halten. Nutzer sind für Aktivitäten über ihr Konto verantwortlich, soweit sie diese zu vertreten haben.',
                    'Airmius kann Registrierungen ablehnen oder Konten sperren, wenn falsche Angaben, Sicherheitsrisiken oder Regelverstöße vorliegen.',
                ],
            ],
            [
                'title' => '3. Minderjährige Nutzer',
                'body' => [
                    'Nutzer unter 16 Jahren benötigen für soziale Funktionen die Zustimmung eines Erziehungsberechtigten.',
                    'Ohne Zustimmung bleiben soziale Funktionen eingeschränkt.',
                    'Erziehungsberechtigte können die Zustimmung ohne eigenes Konto über den Elternbereich mit E-Mail und Zugangscode verwalten.',
                    'Erziehungsberechtigte dürfen den Elternbereich nur nutzen, wenn sie tatsächlich zur Entscheidung für das betroffene Kind berechtigt sind.',
                    'Die Zustimmung kann für die Zukunft widerrufen werden. Nach Widerruf werden soziale Funktionen des Kindes wieder eingeschränkt.',
                    'Ein Elternkonto ist optional. Wird ein Elternkonto erstellt oder verknüpft, kann Airmius die verknüpften Kinder diesem Konto zuordnen.',
                    'Airmius kann zusätzliche Schutzmaßnahmen für Minderjährige einsetzen.',
                ],
            ],
            [
                'title' => '4. Vereine, Teams und Mitgliedsverwaltung',
                'body' => [
                    'Vereine und Teams können innerhalb von Airmius verwaltet werden. Berechtigte Vereinsverantwortliche können Team-Beitrittsanfragen annehmen oder ablehnen.',
                    'Eine Team-Beitrittsanfrage führt erst nach Annahme zur Teammitgliedschaft. Die Person kann anschließend im Verein als Vereinsmitglied, Nichtmitglied, in Prüfung oder ehemaliges Mitglied markiert werden.',
                    'Sportler können ihre sportliche Lizenznummer selbst hinterlegen. Vereine können diese Nummer zur Zuordnung sehen, soweit sie für die Vereins- oder Teamverwaltung erforderlich ist.',
                    'Offizielle Vereine müssen ihre Vereinsnummer hinterlegen. Mitgliedsnummern können vom Verein manuell eingetragen oder von der Plattform generiert werden.',
                    'Vereine können Personen auch zunächst nur per E-Mail als externe Mitglieder erfassen. Eine Verknüpfung mit einem Airmius-Konto entsteht erst, wenn der Verein die Einladung/Verknüpfung aktiviert und ein passendes Konto existiert oder die eingeladene Person ein Konto erstellt.',
                    'Vereinsverantwortliche sind für die Richtigkeit der von ihnen gepflegten Mitgliedsnummern, Beitragsdaten, Rechnungen, Zahlungsmarkierungen und Mahnungen verantwortlich.',
                    'Airmius stellt hierfür Verwaltungsfunktionen bereit. Soweit nicht ausdrücklich anders vereinbart, wickelt Airmius selbst keine Vereinsbeiträge ein und ersetzt keine Buchhaltungs-, Steuer- oder Rechtsberatung.',
                    'Nutzer können ihre eigenen Rechnungen und als bezahlt markierten Zahlungen in ihrem Profil- bzw. Einstellungsbereich einsehen.',
                ],
            ],
            [
                'title' => '5. Inhalte der Nutzer',
                'body' => [
                    'Nutzer bleiben für ihre Beiträge, Kommentare, Chats, Bilder, Dateien und Profilangaben verantwortlich.',
                    'Nutzer dürfen nur Inhalte hochladen, für die sie die erforderlichen Rechte und Zustimmungen besitzen.',
                    'Mit dem Hochladen räumen Nutzer Airmius die für Anzeige, Speicherung, technische Verarbeitung und Bereitstellung innerhalb der Plattform erforderlichen Nutzungsrechte ein.',
                ],
            ],
            [
                'title' => '5a. Marketplace, Anbieter und Provisionen',
                'body' => [
                    'Nutzer, Vereine, Sponsoren oder Anbieter können Marketplace-Angebote wie Produkte, Kurse, Camps oder Dienstleistungen zur Prüfung einreichen.',
                    'Airmius kann Angebote vor Veröffentlichung prüfen, ablehnen, pausieren oder entfernen, insbesondere bei rechtlichen Risiken, Qualitätsproblemen oder Verstoß gegen Plattformregeln.',
                    'Anbieter sind für Beschreibung, Preisangaben, Lieferbarkeit, Leistungserbringung, Verbraucherinformationen, Gewährleistung, Steuern und sonstige rechtliche Pflichten ihrer Angebote verantwortlich, soweit Airmius nicht selbst ausdrücklich Vertragspartner ist.',
                    'Airmius kann für Marketplace-Bestellungen eine Provision berechnen. Die konkrete Provision wird im System oder in einer separaten Vereinbarung ausgewiesen.',
                    'Auszahlungen an Anbieter erfolgen erst nach interner Prüfung, Zahlungseingang und Abzug vereinbarter Provisionen sowie möglicher Rückzahlungen oder Stornos.',
                ],
            ],
            [
                'title' => '5b. Werbung, Sponsoring und Kampagnen',
                'body' => [
                    'Werbung und Sponsorinhalte müssen als solche erkennbar sein und dürfen Nutzer nicht täuschen.',
                    'Nicht erlaubt sind Werbung für verbotene, jugendgefährdende, diskriminierende, irreführende oder rechtswidrige Inhalte.',
                    'Airmius kann Kampagnen prüfen, ablehnen, pausieren oder beenden, wenn sie gegen Regeln, Gesetze, Jugendschutz, Datenschutz oder berechtigte Interessen von Nutzern und Vereinen verstoßen.',
                    'Impressionen, Klicks, CTR, Budgetverbrauch und Conversions können zur Abrechnung, Leistungsmessung, Optimierung und Missbrauchsvermeidung erfasst werden, soweit eine Rechtsgrundlage besteht.',
                    'Werbekunden erhalten keine Garantie auf bestimmte Reichweite, Klickzahlen, Verkäufe oder Registrierungen. Prognosen und Reports sind Leistungsindikatoren, keine Erfolgsgarantie.',
                    'Interne Airmius-Hinweise und eigene Plattformkampagnen können im System priorisiert werden. Dabei achtet Airmius auf Frequency Capping, Nutzererlebnis, Jugendschutz und faire Ausspielung gegenüber bezahlten Kampagnen.',
                    'Werbekunden müssen sicherstellen, dass Zielseiten, Bilder, Texte, Preise, Rabattangaben, Testimonials, Markenrechte und Tracking-Hinweise rechtmäßig sind.',
                    'Bei Minderjährigen achtet Airmius auf besondere Zurückhaltung und kann Zielgruppen, Platzierungen oder Kampagnen einschränken.',
                ],
            ],
            [
                'title' => '5c. Werbeagentur- und Website-Service für Vereine',
                'body' => [
                    'Vereine können Airmius als Werbeagentur mit der Erstellung oder Vorbereitung einer Vereinswebsite, Landingpage oder digitalen Kampagne anfragen.',
                    'Eine Anfrage ist noch kein verbindlicher Auftrag. Ein verbindlicher Auftrag entsteht erst durch ausdrückliche Annahme eines Angebots oder eine separate Vereinbarung.',
                    'Der Verein ist für bereitgestellte Inhalte, Logos, Bilder, Texte, Rechteklärung, Impressumsdaten und Datenschutzangaben seiner Website verantwortlich, soweit nicht etwas anderes vereinbart wird.',
                    'Domainregistrierung, Hosting, Pflege, Support, Zahlungsweise, Laufzeit und Kündigung werden im konkreten Angebot oder Vertrag geregelt.',
                ],
            ],
            [
                'title' => '5d. Sportkarte, Routenplanung und Standortfunktionen',
                'body' => array_merge([
                    'Sportkarte, Routenplanung, Tracking und Sportplatzfunktionen dienen der Planung und Dokumentation sportlicher Aktivitäten. Sie ersetzen keine eigene Prüfung der Umgebung, Verkehrsregeln, Wegbeschaffenheit, Wetterlage oder persönlichen Leistungsfähigkeit.',
                    'Standortzugriff und Live-Tracking werden nur genutzt, wenn der Nutzer dies im Browser oder Gerät erlaubt. Der Nutzer kann die Berechtigung jederzeit über Browser- oder Geräteeinstellungen widerrufen.',
                    'Automatisch generierte Routen sind Vorschläge. Nutzer müssen prüfen, ob Wege tatsächlich zugänglich, sicher, erlaubt und für die jeweilige Sportart geeignet sind.',
                ], $this->routingProviderTermsLines(), [
                    'Nutzer dürfen Sportplätze, Bilder und Ortsinformationen nur eintragen, wenn sie rechtmäßig erhoben wurden und keine Rechte Dritter verletzt werden.',
                ]),
            ],
            [
                'title' => '5e. KI-Funktionen, Ernährung und Trainingsvorschläge',
                'body' => [
                    'KI-Funktionen unterstützen bei Schätzungen, Strukturierung, Trainingsideen, Ernährungsvorschlägen, Blogtexten oder vergleichbaren Inhalten. Sie liefern keine verbindlichen Diagnosen, keine medizinische Beratung und keine Garantie für sportlichen Erfolg.',
                    'Kalorien-, Nährwert-, Trink- und Trainingsangaben können ungenau sein. Nutzer müssen KI-Vorschläge prüfen, bevor sie diese speichern oder darauf aufbauen.',
                    'Bei Beschwerden, Erkrankungen, Schwangerschaft, Essstörungen, Verletzungen oder besonderer Belastung sollte vor Nutzung von Trainings- oder Ernährungsempfehlungen fachlicher Rat eingeholt werden.',
                    'Nutzer dürfen keine Bilder, Gesundheitsdaten oder personenbezogenen Daten anderer Personen an KI-Funktionen übermitteln, wenn dafür keine erforderliche Berechtigung oder Einwilligung vorliegt.',
                    'Airmius kann KI-Funktionen beschränken, pausieren oder Anbieter wechseln, wenn dies aus Datenschutz-, Sicherheits-, Qualitäts-, Verfügbarkeits- oder Kostengründen erforderlich ist.',
                ],
            ],
            [
                'title' => '6. Verbotene Nutzung',
                'body' => [
                    'Verboten sind insbesondere Beleidigungen, Mobbing, Hassrede, Drohungen, sexuelle Inhalte gegenüber Minderjährigen, Gewaltaufrufe, Spam, Betrug und rechtswidrige Inhalte.',
                    'Verboten ist auch die Veröffentlichung fremder Bilder oder personenbezogener Daten ohne erforderliche Zustimmung.',
                    'Automatisierte Zugriffe, Sicherheitsumgehungen und missbräuchliche Nutzung sind untersagt.',
                    'Verboten sind irreführende Marketplace-Angebote, Scheinangebote, gefälschte Bewertungen, verbotene Produkte, Rechteverletzungen oder Umgehung der Airmius-Provisions- und Zahlungslogik.',
                    'Verboten sind Werbekampagnen, die Nutzer täuschen, Minderjährige unangemessen ansprechen oder gegen Jugendschutz, Datenschutz, Wettbewerbsrecht oder Plattformregeln verstoßen.',
                    'Verboten ist auch die missbräuchliche Nutzung der Vereinsverwaltung, insbesondere falsche Mitgliedsdaten, falsche Zahlungsmarkierungen, unbegründete Mahnungen oder die Nutzung von Beitragsdaten zur Belästigung oder Bloßstellung.',
                    'Verboten ist die Nutzung von KI-, Karten-, Routing- oder Trackingfunktionen zur Überwachung, Belästigung, Täuschung, Gefährdung oder rechtswidrigen Verarbeitung von Daten anderer Personen.',
                ],
            ],
            [
                'title' => '7. Moderation und Maßnahmen',
                'body' => [
                    'Airmius kann Inhalte automatisiert oder nach Meldung prüfen.',
                    'Bei Verstößen kann Airmius Inhalte entfernen, Sichtbarkeit einschränken, Nutzer verwarnen, Funktionen beschränken oder Konten sperren/löschen.',
                    'Nutzer können Inhalte melden und Entscheidungen über den Support anfragen.',
                    'Erziehungsberechtigte können über den Elternbereich die Zustimmung für verknüpfte Kinder widerrufen und bei Problemen den Support kontaktieren.',
                ],
            ],
            [
                'title' => '8. Verfügbarkeit und Änderungen',
                'body' => [
                    'Airmius bemüht sich um einen zuverlässigen Betrieb, schuldet aber keine ununterbrochene Verfügbarkeit.',
                    'Wartungen, Sicherheitsmaßnahmen und Weiterentwicklungen können Funktionen vorübergehend einschränken.',
                ],
            ],
            [
                'title' => '9. Haftung',
                'body' => [
                    'Airmius haftet unbeschränkt bei Vorsatz, grober Fahrlässigkeit, Verletzung von Leben, Körper oder Gesundheit sowie nach zwingenden gesetzlichen Vorschriften.',
                    'Bei leichter Fahrlässigkeit haftet Airmius nur bei Verletzung wesentlicher Vertragspflichten und beschränkt auf den vorhersehbaren Schaden.',
                    'Für Nutzerinhalte ist grundsätzlich der jeweilige Nutzer verantwortlich.',
                    'Für Marketplace-Angebote, Werbeaussagen und externe Zielseiten ist grundsätzlich der jeweilige Anbieter oder Sponsor verantwortlich, soweit Airmius nicht selbst Vertragspartner oder Anbieter der Leistung ist.',
                    'Für von Vereinsverantwortlichen eingetragene Mitglieds-, Beitrags-, Rechnungs- und Zahlungsdaten ist grundsätzlich der jeweilige Verein bzw. die eingetragene verantwortliche Person zuständig.',
                    'Für KI-Vorschläge, automatisch generierte Routen, Trackdaten, Kalorienschätzungen und Trainingshinweise gilt: Sie sind Hilfsmittel und müssen eigenverantwortlich geprüft werden.',
                ],
            ],
            [
                'title' => '10. Kündigung und Kontolöschung',
                'body' => [
                    'Nutzer können ihr Konto nach den verfügbaren Plattformfunktionen löschen oder die Löschung über den Support anfragen.',
                    'Airmius kann dauerhaft inaktive Konten nach vorheriger Benachrichtigung deaktivieren und anonymisieren, wenn keine erneute Nutzung erfolgt.',
                    'Eine erneute Anmeldung vor der angekündigten Anonymisierung hält das Konto aktiv und setzt die Inaktivitätsprüfung zurück.',
                    'Gesetzlich aufzubewahrende Rechnungs-, Zahlungs-, Bestell- und Nachweisdaten können trotz Kontolöschung oder Anonymisierung weiter gespeichert bleiben.',
                    'Airmius kann Konten bei schweren oder wiederholten Verstößen sperren oder kündigen.',
                ],
            ],
        ], 'Stand: 25.05.2026');
    }

    public function community(): Response
    {
        return $this->render('Community-Richtlinien', [
            [
                'title' => 'Unser Grundsatz',
                'body' => [
                    'Airmius soll ein sicherer Ort für Sport, Vereine, Teams und junge Menschen sein.',
                    'Respekt, Fairness und Schutz der Privatsphäre gelten in Feed, Chat, Profilen, Dateien, Events und Empfehlungen.',
                ],
            ],
            [
                'title' => 'Nicht erlaubt',
                'body' => [
                    'Beleidigungen, Demütigungen, Mobbing und gezieltes Bloßstellen.',
                    'Hassrede gegen Menschen oder Gruppen wegen Herkunft, Religion, Geschlecht, sexueller Orientierung, Behinderung oder anderer geschützter Merkmale.',
                    'Drohungen, Gewaltaufrufe, Selbstgefährdungsaufforderungen und gefährliche Challenges.',
                    'Sexuelle Inhalte, sexualisierte Ansprache oder Ausnutzung, insbesondere gegenüber Minderjährigen.',
                    'Bilder, Videos oder personenbezogene Daten anderer Personen ohne erforderliche Zustimmung.',
                    'Spam, Betrug, Phishing, manipulatives Verhalten und unerlaubte Werbung.',
                    'Irreführende Werbung, verschleierte Sponsorinhalte, gefälschte Angebote, verbotene Produkte oder Links auf unsichere bzw. rechtswidrige externe Seiten.',
                    'Missbrauch von Vereins- oder Teamverwaltungsfunktionen, zum Beispiel falsche Zahlungsmarkierungen, unbegründete Mahnungen, Druck auf Mitglieder oder Veröffentlichung interner Beitragsdaten.',
                ],
            ],
            [
                'title' => 'Moderation',
                'body' => [
                    'Airmius nutzt automatische Hinweise, Nutzer-Meldungen und manuelle Prüfung.',
                    'Je nach Risiko können Inhalte markiert, entfernt oder an Moderatoren weitergeleitet werden.',
                    'Bei schweren Verstößen können Nutzer sofort eingeschränkt oder gesperrt werden.',
                ],
            ],
            [
                'title' => 'Melden',
                'body' => [
                    'Nutze die Meldefunktion bei Posts, Kommentaren und Chat-Nachrichten.',
                    'Erziehungsberechtigte können bei Problemen mit minderjährigen Nutzern zusätzlich den Elternbereich oder den Support nutzen.',
                    'Bei akuter Gefahr kontaktiere zusätzlich lokale Notruf- oder Hilfsstellen.',
                ],
            ],
        ]);
    }

    public function minors(): Response
    {
        $legal = $this->legalProfile();

        return $this->render('Jugendschutz und Elternzustimmung', [
            [
                'title' => 'Warum Zustimmung erforderlich ist',
                'body' => [
                    'Airmius verarbeitet personenbezogene Daten und bietet soziale Funktionen wie Profile, Feed, Chat, Teams und Vereine.',
                    'Vereine können zudem Mitgliedschaften, Teamzugehörigkeiten und Beitrags-/Rechnungsinformationen verwalten, soweit dies für den jeweiligen Verein erforderlich ist.',
                    'Für Nutzer unter 16 Jahren werden diese Funktionen erst nach Zustimmung eines Erziehungsberechtigten freigegeben.',
                ],
            ],
            [
                'title' => 'Ablauf',
                'body' => [
                    'Das Kind registriert sich mit Geburtsdatum und E-Mail-Adresse eines Erziehungsberechtigten.',
                    'Airmius sendet eine E-Mail mit Möglichkeit zur Zustimmung oder Ablehnung.',
                    'Bis zur Entscheidung sieht das Kind einen Hinweis, dass die Zustimmung aussteht.',
                    'Bei Ablehnung bleiben soziale Funktionen eingeschränkt.',
                    'Nach Zustimmung bleibt die Eltern-E-Mail gespeichert, solange das Kind unter 16 Jahre alt ist und die Speicherung für Zustimmung, Widerruf oder Nachvollziehbarkeit erforderlich ist.',
                    'Erziehungsberechtigte können später über den Elternbereich mit E-Mail und Zugangscode auf die verknüpften Kinder zugreifen.',
                    'Der Elternbereich erlaubt das Prüfen der Zustimmung und den Widerruf für die Zukunft.',
                    'Optional können Erziehungsberechtigte ein eigenes Airmius-Elternkonto erstellen oder ein vorhandenes Konto verknüpfen.',
                    'Wenn ein Kind einem Verein oder Team angehört, können berechtigte Vereinsverantwortliche vereinsbezogene Mitgliedsdaten und Beitrags-/Rechnungsinformationen verwalten.',
                ],
            ],
            [
                'title' => 'Rechte der Erziehungsberechtigten',
                'body' => [
                    'Erziehungsberechtigte können Auskunft verlangen, Zustimmung verweigern oder eine erteilte Zustimmung für die Zukunft widerrufen.',
                    'Beim Widerruf werden soziale Funktionen des Kindes wieder gesperrt, bis eine neue wirksame Zustimmung vorliegt.',
                    'Fragen zu vereinsinternen Beiträgen, Rechnungen oder Mitgliedsnummern sollten zusätzlich direkt mit dem jeweiligen Verein geklärt werden.',
                    'Der Widerruf kann über den Elternbereich erfolgen oder über den Support angefragt werden.',
                    'Bitte kontaktiere dafür '.$legal['privacy_email'].' oder den Support unter '.$legal['support_email'].'.',
                ],
            ],
            [
                'title' => 'Schutzmaßnahmen',
                'body' => [
                    'Airmius kann Inhalte moderieren, Meldungen prüfen, Kontakte einschränken und Konten bei Risiken sperren.',
                    'Minderjährige sollten keine sensiblen Daten, privaten Adressen oder Bilder anderer Personen ohne Zustimmung veröffentlichen.',
                ],
            ],
        ]);
    }

    public function cookies(): Response
    {
        return $this->render('Cookie-Hinweise', [
            [
                'title' => 'Notwendige Cookies',
                'body' => [
                    'Airmius nutzt notwendige Cookies und lokale Speichermechanismen für Login, Sicherheit, Sprache, Theme, CSRF-Schutz und Sitzungsverwaltung.',
                    'Weitere lokale Speicherwerte wie Theme, Sprache, Dashboard-Anordnung oder Landingpage-Variante werden nur für die gewünschte Darstellung und Bedienung der Plattform genutzt.',
                    'Hostinger verarbeitet technisch notwendige Verbindungsdaten für den Abruf der Plattform.',
                    'Cloudflare kann technisch notwendige Verbindungsdaten für Sicherheit, R2-Speicherabruf und CDN-Auslieferung verarbeiten.',
                    'Diese Technologien sind für den Betrieb der Plattform erforderlich.',
                ],
            ],
            [
                'title' => 'Optionale Technologien',
                'body' => [
                    'Analyse-, Marketing- oder Tracking-Technologien dürfen nur eingesetzt werden, wenn sie hier konkret benannt werden und eine erforderliche Einwilligung eingeholt wird.',
                    'Aktuell bindet Airmius im Weblayout keine externen Analytics-, Marketing-, Retargeting- oder Pixel-Skripte ein.',
                    'Clientseitige Landingpage-Events an dataLayer oder gtag werden technisch blockiert, solange keine Einwilligung zur Werbe-/Conversion-Messung gespeichert ist.',
                    'Airmius kann für eigene Ads- und Sponsorbereiche Kampagnenmessung einsetzen, insbesondere Impressionen, Klicks, Budgetverbrauch, Frequenzbegrenzung und Conversion-Zuordnung. Diese Messung dient Abrechnung, Reporting, Budgetkontrolle, Optimierung und Missbrauchsvermeidung.',
                    'Personalisierte Werbung, Retargeting, Conversion-Messung, externe Pixel oder Cross-Site-Tracking werden nur eingesetzt, wenn du vorher eingewilligt hast.',
                    'Wenn du personalisierte Werbung erlaubst, kann Airmius einfache Marketplace-Interessen und Anzeigeninteraktionen verwenden, um passendere Anzeigen auszuspielen.',
                    'Wenn du Conversion-Messung erlaubst, kann Airmius erkennen, ob aus einem Anzeigenkontakt später eine Registrierung, Anfrage, Warenkorb-Aktion, Bestellung oder Zahlung entstanden ist.',
                    'Cloudflare wird hier für technisch erforderliche Speicherung, Sicherheit und CDN-Auslieferung genannt, nicht als Marketing- oder Analyse-Cookie-Anbieter.',
                    'Weitere optionale Anbieter werden erst nach ausdrücklicher Benennung auf dieser Seite und nach erforderlicher Einwilligung eingesetzt.',
                ],
            ],
            [
                'title' => 'Einwilligung widerrufen',
                'body' => [
                    'Soweit optionale Cookies oder einwilligungspflichtige Werbe-/Messfunktionen eingesetzt werden, kannst du deine Einwilligung jederzeit über die Cookie-Einstellungen oder in den Datenschutzeinstellungen widerrufen.',
                    'Nach dem Widerruf werden neue personalisierte Ads- und Conversion-Signale nicht mehr verarbeitet. Notwendige Cookies für Login, Warenkorb, Sicherheit und CSRF-Schutz bleiben aktiv.',
                    'Die Cookie-Einstellungen sind auf jeder Webseite über das Schild-Symbol und im öffentlichen Footer erreichbar. Angemeldete Personen können Werbe- und Mess-Einwilligungen zusätzlich in den Datenschutzeinstellungen ihres Kontos verwalten; diese Kontoeinstellungen haben Vorrang.',
                ],
            ],
        ]);
    }

    public function withdrawal(): Response
    {
        $legal = $this->legalProfile();

        return $this->render('Widerrufsbelehrung', [
            [
                'title' => 'Hinweis',
                'body' => [
                    'Diese Seite ist relevant, wenn Airmius kostenpflichtige Verträge mit Verbrauchern anbietet, zum Beispiel Premium-Funktionen, digitale Dienste oder Mitgliedschaften.',
                    'Sie kann auch für kostenpflichtige Add-ons, Marketplace-Käufe, digitale Inhalte, Werbeagentur-/Website-Services oder vergleichbare Fernabsatzverträge relevant sein.',
                    'Solange Airmius ausschließlich kostenlos genutzt wird, kann diese Seite als vorsorgliche Verbraucherinformation dienen.',
                ],
            ],
            [
                'title' => 'Widerrufsrecht',
                'body' => [
                    'Verbraucher haben grundsätzlich das Recht, binnen 14 Tagen ohne Angabe von Gründen einen Fernabsatzvertrag zu widerrufen.',
                    'Die Widerrufsfrist beträgt 14 Tage ab Vertragsschluss, soweit keine abweichenden gesetzlichen Regelungen gelten.',
                ],
            ],
            [
                'title' => 'Ausübung des Widerrufs',
                'body' => [
                    'Um das Widerrufsrecht auszuüben, musst du Airmius mittels eindeutiger Erklärung informieren.',
                    'Kontakt: '.$legal['provider_name'].', '.$this->singleLineAddress($legal).', '.$legal['support_email'].'.',
                    'Zur Fristwahrung genügt die rechtzeitige Absendung der Widerrufserklärung.',
                ],
            ],
            [
                'title' => 'Folgen des Widerrufs',
                'body' => [
                    'Im Fall eines wirksamen Widerrufs werden erhaltene Zahlungen nach den gesetzlichen Vorgaben zurückgewährt.',
                    'Bei digitalen Diensten oder Inhalten können besondere Regeln gelten, insbesondere wenn mit ausdrücklicher Zustimmung vor Ablauf der Widerrufsfrist begonnen wurde.',
                    'Bei individuell erstellten Werbeagentur-/Website-Leistungen, digitalen Leistungen oder sofort aktivierten Add-ons können besondere gesetzliche Ausnahmen oder Wertersatzregeln gelten. Diese müssen vor produktivem Verkauf final rechtlich geprüft und im Checkout sauber bestätigt werden.',
                ],
            ],
        ], 'Kostenpflichtige Funktionen, Marketplace, Ads und Werbeagentur-/Website-Service sind jetzt berücksichtigt. Vor Livegang bitte mit echten Anbieter-, Zahlungs- und Widerrufsdaten rechtlich final prüfen.');
    }

    public function reporting(): Response
    {
        $legal = $this->legalProfile();

        return $this->render('Kontakt, Support und Inhalte melden', [
            [
                'title' => 'Support',
                'body' => [
                    'Allgemeine Fragen: '.$legal['support_email'],
                    'Datenschutz: '.$legal['privacy_email'],
                    'Rechtliche Hinweise: '.$legal['legal_email'],
                ],
            ],
            [
                'title' => 'Inhalte melden',
                'body' => [
                    'In der Plattform kannst du Beiträge, Kommentare und Chat-Nachrichten direkt melden.',
                    'Bitte wähle den passenden Grund, zum Beispiel Beleidigung, Mobbing, Hassrede, sexuelle Inhalte, Gewalt, Drohung, Bild ohne Zustimmung oder Spam.',
                    'Moderatoren prüfen Meldungen und können Inhalte entfernen oder Konten einschränken.',
                ],
            ],
            [
                'title' => 'Dringende Fälle',
                'body' => [
                    'Bei akuter Gefahr, Gewaltandrohung oder Notfällen wende dich sofort an Polizei, Notruf oder eine zuständige Beratungsstelle.',
                    'Airmius ersetzt keine Notfall- oder Rechtsberatung.',
                ],
            ],
        ]);
    }

    private function render(string $title, array $sections, ?string $note = null, ?array $action = null): Response|JsonResponse
    {
        $content = app(LegalContentLocalizer::class)->localize(
            $title,
            $sections,
            $note,
            $action,
            $this->protectedLegalValues,
        );

        if (request()->query('format') === 'json'
            && request()->routeIs('legal.account-deletion', 'legal.club-deletion', 'terms.show', 'policy.show')) {
            return response()->json($content)->header('Cache-Control', 'public, max-age=300');
        }

        return Inertia::render('Legal/Show', $content);
    }

    private function legalProfile(): array
    {
        $billing = app(AirmiusLegalProfile::class)->data();

        $providerName = $this->cleanLegalValue($billing['legal_name'] ?? '')
            ?: $this->cleanLegalValue($billing['company_name'] ?? '')
            ?: (string) config('legal.provider_name');

        $street = $this->cleanLegalValue($billing['street'] ?? '') ?: (string) config('legal.street');
        $postalCode = $this->cleanLegalValue($billing['postal_code'] ?? '');
        $city = $this->cleanLegalValue($billing['city'] ?? '') ?: (string) config('legal.city');
        $country = $this->cleanLegalValue($billing['country'] ?? '') ?: (string) config('legal.country');
        $email = $this->cleanLegalValue($billing['email'] ?? '') ?: (string) config('legal.email');
        $vatId = $this->cleanLegalValue($billing['vat_id'] ?? '') ?: (string) config('legal.vat_id');
        $registerParts = array_values(array_filter([
            $this->cleanLegalValue($billing['court'] ?? ''),
            $this->cleanLegalValue($billing['registration_number'] ?? ''),
        ]));
        $representative = $this->cleanLegalValue($billing['managing_director'] ?? '') ?: (string) config('legal.representative');

        $profile = [
            'provider_name' => $providerName,
            'street' => $street,
            'postal_code' => $postalCode,
            'city' => $city,
            'country' => $country,
            'email' => $email,
            'support_email' => (string) config('legal.support_email'),
            'privacy_email' => (string) config('legal.privacy_email'),
            'legal_email' => (string) config('legal.legal_email'),
            'phone' => (string) config('legal.phone'),
            'representative' => $representative,
            'register' => $registerParts ? implode(', ', $registerParts) : (string) config('legal.register'),
            'vat_id' => $vatId,
            'supervisory_authority' => (string) config('legal.supervisory_authority'),
            'content_responsible' => $providerName.', '.$this->singleLineAddress([
                'provider_name' => '',
                'street' => $street,
                'postal_code' => $postalCode,
                'city' => $city,
                'country' => $country,
            ]),
        ];

        $this->protectedLegalValues = array_values($profile);

        return $profile;
    }

    private function addressLines(array $legal): array
    {
        $cityLine = trim(implode(' ', array_filter([
            $legal['postal_code'] ?? '',
            $legal['city'] ?? '',
        ])));

        return array_values(array_filter([
            $legal['provider_name'],
            $legal['street'],
            $cityLine,
            $legal['country'],
        ]));
    }

    private function singleLineAddress(array $legal): string
    {
        return implode(', ', $this->addressLines($legal));
    }

    private function cleanLegalValue(?string $value): string
    {
        $value = trim((string) $value);

        return in_array($value, ['', 'Nicht angegeben'], true) ? '' : $value;
    }

    private function routingProviderLegalLines(): array
    {
        $providers = collect([
            config('sport_map.routing.provider'),
            config('sport_map.routing.route_generator_provider'),
        ])
            ->filter()
            ->map(fn ($provider) => strtolower((string) $provider))
            ->unique()
            ->values();

        if (! $providers->contains('graphhopper')) {
            return [];
        }

        return [
            'Aktuell ist GraphHopper als Routing-Dienst für Routenberechnung bzw. Routengenerierung konfiguriert. Bei Nutzung der Sportkarte können dafür Startpunkt, Zielpunkt, Wegpunkte, Sportart, Routingprofil und technische Verbindungsdaten an GraphHopper übertragen werden.',
        ];
    }

    private function routingProviderTermsLines(): array
    {
        $providers = collect([
            config('sport_map.routing.provider'),
            config('sport_map.routing.route_generator_provider'),
        ])
            ->filter()
            ->map(fn ($provider) => strtolower((string) $provider))
            ->unique()
            ->values();

        if (! $providers->contains('graphhopper')) {
            return [];
        }

        return [
            'Für die technische Routenberechnung kann Airmius aktuell GraphHopper einsetzen. Die Route bleibt trotzdem ein Vorschlag; maßgeblich sind immer reale Weglage, Verkehrsregeln, Sperrungen und deine eigene Sicherheit.',
        ];
    }
}
