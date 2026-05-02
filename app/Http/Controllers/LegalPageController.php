<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LegalPageController extends Controller
{
    public function imprint(): Response
    {
        return $this->render('Impressum', [
            [
                'title' => 'Angaben nach § 5 DDG',
                'body' => [
                    'Airmius',
                    'TODO: Name/Firma des Diensteanbieters',
                    'TODO: Straße und Hausnummer',
                    'TODO: PLZ und Ort',
                    'Deutschland',
                ],
            ],
            [
                'title' => 'Kontakt',
                'body' => [
                    'E-Mail: TODO: kontakt@airmius.com',
                    'Telefon: TODO: Telefonnummer, falls vorhanden',
                ],
            ],
            [
                'title' => 'Vertretungsberechtigte Person',
                'body' => [
                    'TODO: Vor- und Nachname der vertretungsberechtigten Person.',
                ],
            ],
            [
                'title' => 'Register, Umsatzsteuer und Aufsicht',
                'body' => [
                    'Registereintrag: TODO: falls vorhanden, Registergericht und Registernummer eintragen.',
                    'Umsatzsteuer-ID: TODO: falls vorhanden eintragen.',
                    'Aufsichtsbehörde: TODO: nur falls eine erlaubnispflichtige Tätigkeit vorliegt.',
                ],
            ],
            [
                'title' => 'Verantwortlich für Inhalte',
                'body' => [
                    'TODO: Name und Anschrift der inhaltlich verantwortlichen Person, falls erforderlich.',
                ],
            ],
        ], 'Letzte Aktualisierung: 02.05.2026');
    }

    public function privacy(): Response
    {
        return $this->render('Datenschutzerklärung', [
            [
                'title' => '1. Verantwortlicher',
                'body' => [
                    'Verantwortlich für die Verarbeitung personenbezogener Daten ist: TODO: Name/Firma, Anschrift, E-Mail.',
                    'Datenschutzkontakt: TODO: datenschutz@airmius.com.',
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
                    'Datei- und Mediendaten: hochgeladene Bilder, Anhänge, Dateityp, Dateigröße, Speicherpfad und technische Auslieferungsdaten über Cloudflare R2/CDN.',
                    'Technische Daten: IP-Adresse, Browserdaten, Logdaten, Sitzungsdaten, Sprache, Zeitzone und Sicherheitsereignisse.',
                ],
            ],
            [
                'title' => '3. Zwecke der Verarbeitung',
                'body' => [
                    'Bereitstellung des sozialen Sportnetzwerks und der Nutzerkonten.',
                    'Organisation von Vereinen, Teams, Trainingseinheiten, Events, Chats und Dateien.',
                    'Verwaltung von Team-Beitrittsanfragen, Einladungen und Vereinsmitgliedschaften durch berechtigte Vereinsverantwortliche.',
                    'Zuordnung von Sportlern und Vereinen ueber sportliche Lizenznummern, Vereinsnummern und Mitgliedsnummern.',
                    'Verwaltung von Mitgliedsnummern, Beiträgen, Rechnungen, Zahlungshistorien, Zahlungsstatus und Zahlungserinnerungen im Auftrag bzw. Verantwortungsbereich des jeweiligen Vereins.',
                    'Sicherheit, Missbrauchsvermeidung, Moderation und Durchsetzung der Community-Regeln.',
                    'Eltern-/Erziehungsberechtigtenzustimmung bei minderjährigen Nutzern.',
                    'Bereitstellung eines Elternbereichs zur Prüfung verknüpfter Kinder, zum Widerruf der Zustimmung und zur optionalen Erstellung eines Elternkontos.',
                    'Benachrichtigungen, Support, Fehleranalyse und Verbesserung der Plattform.',
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
                    'Art. 6 Abs. 1 lit. c DSGVO: gesetzliche Pflichten.',
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
                'title' => '6. Empfänger und Dienstleister',
                'body' => [
                    'Airmius nutzt Hostinger als Hosting-Anbieter für den Betrieb der Plattform. Nach aktueller Konfiguration sollen die Plattformdaten innerhalb Europas verarbeitet und gespeichert werden.',
                    'Airmius nutzt Cloudflare für Objektspeicher und Medienauslieferung, insbesondere Cloudflare R2 und Cloudflare CDN. Cloudflare R2 wird für europäische Datenhaltung mit EU-Jurisdiction bzw. europäischer Region konfiguriert.',
                    'Cloudflare CDN kann für die schnelle und sichere Auslieferung von Medien und statischen Inhalten eingesetzt werden. Soweit personenbezogene Daten betroffen sind, wird die Konfiguration auf europäische Datenhaltung und DSGVO-konforme Verarbeitung ausgerichtet.',
                    'Airmius kann außerdem technische Dienstleister für E-Mail-Versand, Sicherheit, Fehleranalyse und Zahlungsabwicklung einsetzen, wenn dies für den Plattformbetrieb erforderlich ist.',
                    'Vereinsverantwortliche wie Owner, Admins und Manager können im Rahmen ihrer Berechtigungen Mitglieder-, Beitrags-, Rechnungs- und Zahlungsdaten ihres Vereins einsehen und bearbeiten.',
                    'Mit Auftragsverarbeitern werden Verträge nach Art. 28 DSGVO geschlossen.',
                    'Daten werden nur weitergegeben, wenn dies für den Plattformbetrieb erforderlich ist, eine Rechtsgrundlage besteht oder eine gesetzliche Pflicht vorliegt.',
                ],
            ],
            [
                'title' => '7. Speicherdauer',
                'body' => [
                    'Daten werden gelöscht oder anonymisiert, sobald sie für die genannten Zwecke nicht mehr erforderlich sind.',
                    'Accountdaten werden grundsätzlich bis zur Löschung des Kontos gespeichert, soweit keine gesetzlichen Aufbewahrungspflichten oder berechtigten Interessen entgegenstehen.',
                    'Rechnungs-, Zahlungs- und Beitragsdaten können aufgrund handels-, steuer- oder vereinsrechtlicher Nachweis- und Aufbewahrungspflichten länger gespeichert werden.',
                    'Hochgeladene Medien und Dateien werden grundsätzlich solange gespeichert, wie sie für Profil, Feed, Chat, Verein, Team, Event oder Dateiablage erforderlich sind.',
                    'Moderations- und Sicherheitsdaten können zur Nachvollziehbarkeit und Missbrauchsvermeidung länger gespeichert werden.',
                ],
            ],
            [
                'title' => '8. Rechte betroffener Personen',
                'body' => [
                    'Du hast Rechte auf Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit und Widerspruch.',
                    'Soweit die Verarbeitung auf Einwilligung beruht, kannst du diese mit Wirkung für die Zukunft widerrufen.',
                    'Du hast außerdem das Recht, dich bei einer Datenschutzaufsichtsbehörde zu beschweren.',
                ],
            ],
            [
                'title' => '9. Cookies und lokale Speicherung',
                'body' => [
                    'Airmius verwendet notwendige Cookies und lokale Speichermechanismen für Login, Sicherheit, Sprache, Theme und Sitzungsfunktionen.',
                    'Beim Abruf von Bildern, Dateien und statischen Inhalten können technisch notwendige Verbindungsdaten durch Hostinger und Cloudflare verarbeitet werden, um Hosting, Speicherung, Sicherheit und CDN-Auslieferung bereitzustellen.',
                    'Analyse- oder Marketing-Technologien werden nur eingesetzt, wenn sie in der Cookie-Seite genannt werden und eine erforderliche Einwilligung vorliegt.',
                ],
            ],
        ], 'Hostinger und Cloudflare sind als Dienstleister ergänzt. Bitte prüfe vor Livegang, ob Cloudflare R2 tatsächlich mit EU-Jurisdiction/Europa konfiguriert ist und ob CDN-Logs/Caches entsprechend deiner Aussage in Europa bleiben.');
    }

    public function terms(): Response
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
                    'Sportler koennen ihre sportliche Lizenznummer selbst hinterlegen. Vereine koennen diese Nummer zur Zuordnung sehen, soweit sie fuer die Vereins- oder Teamverwaltung erforderlich ist.',
                    'Offizielle Vereine muessen ihre Vereinsnummer hinterlegen. Mitgliedsnummern koennen vom Verein manuell eingetragen oder von der Plattform generiert werden.',
                    'Vereine koennen Personen auch zunaechst nur per E-Mail als externe Mitglieder erfassen. Eine Verknuepfung mit einem Airmius-Konto entsteht erst, wenn der Verein die Einladung/Verknuepfung aktiviert und ein passendes Konto existiert oder die eingeladene Person ein Konto erstellt.',
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
                'title' => '6. Verbotene Nutzung',
                'body' => [
                    'Verboten sind insbesondere Beleidigungen, Mobbing, Hassrede, Drohungen, sexuelle Inhalte gegenüber Minderjährigen, Gewaltaufrufe, Spam, Betrug und rechtswidrige Inhalte.',
                    'Verboten ist auch die Veröffentlichung fremder Bilder oder personenbezogener Daten ohne erforderliche Zustimmung.',
                    'Automatisierte Zugriffe, Sicherheitsumgehungen und missbräuchliche Nutzung sind untersagt.',
                    'Verboten ist auch die missbräuchliche Nutzung der Vereinsverwaltung, insbesondere falsche Mitgliedsdaten, falsche Zahlungsmarkierungen, unbegründete Mahnungen oder die Nutzung von Beitragsdaten zur Belästigung oder Bloßstellung.',
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
                    'Für von Vereinsverantwortlichen eingetragene Mitglieds-, Beitrags-, Rechnungs- und Zahlungsdaten ist grundsätzlich der jeweilige Verein bzw. die eingetragene verantwortliche Person zuständig.',
                ],
            ],
            [
                'title' => '10. Kündigung und Kontolöschung',
                'body' => [
                    'Nutzer können ihr Konto nach den verfügbaren Plattformfunktionen löschen oder die Löschung über den Support anfragen.',
                    'Airmius kann Konten bei schweren oder wiederholten Verstößen sperren oder kündigen.',
                ],
            ],
        ], 'Stand: 02.05.2026');
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
                    'Bitte kontaktiere dafür TODO: datenschutz@airmius.com oder den Support.',
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
                    'Hostinger verarbeitet technisch notwendige Verbindungsdaten für den Abruf der Plattform.',
                    'Cloudflare kann technisch notwendige Verbindungsdaten für Sicherheit, R2-Speicherabruf und CDN-Auslieferung verarbeiten.',
                    'Diese Technologien sind für den Betrieb der Plattform erforderlich.',
                ],
            ],
            [
                'title' => 'Optionale Technologien',
                'body' => [
                    'Analyse-, Marketing- oder Tracking-Technologien dürfen nur eingesetzt werden, wenn sie hier konkret benannt werden und eine erforderliche Einwilligung eingeholt wird.',
                    'Cloudflare wird hier für technisch erforderliche Speicherung, Sicherheit und CDN-Auslieferung genannt, nicht als Marketing- oder Analyse-Cookie-Anbieter.',
                    'TODO: Falls Analytics, Pixel, externe Videos, Karten oder Drittanbieter eingebunden werden, Anbieter, Zweck, Speicherdauer und Widerrufsmöglichkeit ergänzen.',
                ],
            ],
            [
                'title' => 'Einwilligung widerrufen',
                'body' => [
                    'Soweit optionale Cookies eingesetzt werden, kannst du deine Einwilligung jederzeit über die Cookie-Einstellungen widerrufen.',
                    'TODO: Cookie-Einstellungslink ergänzen, sobald ein Consent-Banner eingebaut ist.',
                ],
            ],
        ]);
    }

    public function withdrawal(): Response
    {
        return $this->render('Widerrufsbelehrung', [
            [
                'title' => 'Hinweis',
                'body' => [
                    'Diese Seite ist relevant, wenn Airmius kostenpflichtige Verträge mit Verbrauchern anbietet, zum Beispiel Premium-Funktionen, digitale Dienste oder Mitgliedschaften.',
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
                    'Kontakt: TODO: Anbieteranschrift und support@airmius.com.',
                    'Zur Fristwahrung genügt die rechtzeitige Absendung der Widerrufserklärung.',
                ],
            ],
            [
                'title' => 'Folgen des Widerrufs',
                'body' => [
                    'Im Fall eines wirksamen Widerrufs werden erhaltene Zahlungen nach den gesetzlichen Vorgaben zurückgewährt.',
                    'Bei digitalen Diensten oder Inhalten können besondere Regeln gelten, insbesondere wenn mit ausdrücklicher Zustimmung vor Ablauf der Widerrufsfrist begonnen wurde.',
                ],
            ],
        ], 'Vor Einführung kostenpflichtiger Funktionen bitte zwingend rechtlich finalisieren.');
    }

    public function reporting(): Response
    {
        return $this->render('Kontakt, Support und Inhalte melden', [
            [
                'title' => 'Support',
                'body' => [
                    'Allgemeine Fragen: TODO: support@airmius.com',
                    'Datenschutz: TODO: datenschutz@airmius.com',
                    'Rechtliche Hinweise: TODO: legal@airmius.com',
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

    private function render(string $title, array $sections, ?string $note = null): Response
    {
        return Inertia::render('Legal/Show', [
            'title' => $title,
            'sections' => $sections,
            'note' => $note,
        ]);
    }
}
