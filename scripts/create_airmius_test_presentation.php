<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$source = $root . '/docs/Airmius_User_Case_Checkliste_Schritt_fuer_Schritt.md';
$outDir = $root . '/docs/presentations';
$odpPath = $outDir . '/Airmius_Vollstaendige_User_Case_Testanleitung.odp';
$pptxPath = $outDir . '/Airmius_Vollstaendige_User_Case_Testanleitung.pptx';

if (! is_file($source)) {
    throw new RuntimeException("Markdown source not found: {$source}");
}

if (! is_dir($outDir) && ! mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
    throw new RuntimeException("Could not create output directory: {$outDir}");
}

$cases = parseCases((string) file_get_contents($source));
$slides = [];

$slides[] = coverSlide();
$slides[] = deviceSlide();
$slides[] = howToSlide();
$slides[] = visualFlowSlide();
$slides[] = roleMatrixSlide();

foreach (sectionDefinitions() as $section) {
    $slides[] = sectionSlide($section);
}

foreach ($cases as $case) {
    $chunks = array_chunk($case['steps'], 10);
    if ($chunks === []) {
        $chunks = [[]];
    }
    foreach ($chunks as $index => $chunk) {
        $slides[] = useCaseSlide($case, $chunk, $index, count($chunks));
    }
}

$slides[] = routeCatalogSlide(1);
$slides[] = routeCatalogSlide(2);
$slides[] = routeCatalogSlide(3);
$slides[] = evidenceSlide();
$slides[] = finalSlide();

$content = contentXml($slides);
$styles = stylesXml();
$manifest = manifestXml($slides);

$zip = new ZipArchive();
if ($zip->open($odpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException("Could not create {$odpPath}");
}

$zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.presentation');
$zip->setCompressionName('mimetype', ZipArchive::CM_STORE);
$zip->addFromString('content.xml', $content);
$zip->addFromString('styles.xml', $styles);
$zip->addFromString('settings.xml', settingsXml());
$zip->addFromString('META-INF/manifest.xml', $manifest);

$images = [
    'Pictures/cover.png' => $root . '/docs/presentations/assets/airmius-testlab-cover.png',
    'Pictures/flow.png' => $root . '/docs/presentations/assets/airmius-feature-flow.png',
    'Pictures/feature.png' => $root . '/artifacts/play-store/airmius-feature-graphic-1024x500.png',
    'Pictures/icon.png' => $root . '/public/icons/airmius-icon-512.png',
];
foreach ($images as $archivePath => $filePath) {
    if (is_file($filePath)) {
        $zip->addFile($filePath, $archivePath);
    }
}

$zip->close();

$loProfile = sys_get_temp_dir() . '/airmius-libreoffice-' . getmypid();
if (! is_dir($loProfile) && ! mkdir($loProfile, 0775, true) && ! is_dir($loProfile)) {
    throw new RuntimeException("Could not create LibreOffice profile directory: {$loProfile}");
}
$loConfig = sys_get_temp_dir() . '/airmius-lo-config-' . getmypid();
$loCache = sys_get_temp_dir() . '/airmius-lo-cache-' . getmypid();
$loData = sys_get_temp_dir() . '/airmius-lo-data-' . getmypid();
foreach ([$loConfig, $loCache, $loData] as $loDirectory) {
    if (! is_dir($loDirectory) && ! mkdir($loDirectory, 0775, true) && ! is_dir($loDirectory)) {
        throw new RuntimeException("Could not create LibreOffice runtime directory: {$loDirectory}");
    }
}
$convertCommand = sprintf(
    'env XDG_CONFIG_HOME=%s XDG_CACHE_HOME=%s XDG_DATA_HOME=%s libreoffice --headless -env:UserInstallation=%s --convert-to pptx --outdir %s %s 2>&1',
    escapeshellarg($loConfig),
    escapeshellarg($loCache),
    escapeshellarg($loData),
    escapeshellarg('file://' . $loProfile),
    escapeshellarg($outDir),
    escapeshellarg($odpPath),
);
exec($convertCommand, $convertOutput, $convertExitCode);
if ($convertExitCode !== 0 || ! is_file($pptxPath)) {
    throw new RuntimeException("LibreOffice conversion failed: " . implode("\n", $convertOutput));
}

echo "ODP: {$odpPath}\n";
echo "PPTX: {$pptxPath}\n";
echo 'Use cases: ' . count($cases) . "\n";
echo 'Slides: ' . count($slides) . "\n";

function parseCases(string $markdown): array
{
    $cases = [];
    preg_match_all('/^##\s+(UC-\d+):\s+(.+?)\R(.*?)(?=^##\s+|\z)/ms', $markdown, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $sections = [];
        preg_match_all('/^###\s+(.+?)\R(.*?)(?=^###\s+|\z)/ms', $match[3], $sectionMatches, PREG_SET_ORDER);
        foreach ($sectionMatches as $sectionMatch) {
            $heading = trim($sectionMatch[1]);
            $body = trim($sectionMatch[2]);
            $sections[$heading] = $body;
        }

        $steps = [];
        foreach ($sections as $heading => $body) {
            if (! preg_match('/Schritte|Vorbereitung|Verbindung|Geräte und Rollen|Abschluss/i', $heading)) {
                continue;
            }
            foreach (preg_split('/\R/u', $body) ?: [] as $line) {
                $line = trim($line);
                if (preg_match('/^\d+\.\s+(.+)$/u', $line, $stepMatch)) {
                    $steps[] = stripMarkdown($stepMatch[1]);
                }
            }
        }

        $cases[] = [
            'id' => $match[1],
            'title' => stripMarkdown($match[2]),
            'goal' => firstSentences($sections['Ziel'] ?? 'Ziel im Markdown nicht gefunden.', 280),
            'prereq' => firstSentences($sections['Voraussetzungen'] ?? 'Testkonto und passendes Gerät vorbereiten.', 220),
            'expected' => firstSentences($sections['Erwartetes Ergebnis'] ?? 'Ergebnis und Status dokumentieren.', 260),
            'steps' => $steps,
            'status' => stripMarkdown($sections['Status'] ?? 'Bestanden / Fehler / Nachtest'),
        ];
    }
    return $cases;
}

function sectionDefinitions(): array
{
    return [
        ['number' => '01', 'title' => 'Zugang, Profil und Geräte', 'color' => '#2563EB', 'image' => 'feature', 'body' => 'Registrierung, Login, Passwort, 2FA, Profil, Datenschutz, Push, Deep Links, Entwicklungsmodus und die Vier-Geräte-Abnahme.', 'cases' => 'UC-01–04 · UC-63–66 · UC-83–85 · UC-91 · UC-93'],
        ['number' => '02', 'title' => 'Sportler, soziale Funktionen und Gesundheit', 'color' => '#0D9488', 'image' => 'flow', 'body' => 'Sportprofil, Vereinssuche, Matching, Freunde, Feed, Stories, Ernährung, Sportkarte, Sport-Apps und Reifegrad.', 'cases' => 'UC-05 · UC-11 · UC-14–17 · UC-28–30 · UC-36–40 · UC-52–53 · UC-68 · UC-73 · UC-78'],
        ['number' => '03', 'title' => 'Trainer, Teams, Training und Events', 'color' => '#7C3AED', 'image' => 'flow', 'body' => 'Teams, Einladungen, Kader, Trainingsplanung, Logs, Übungen, Feedback, Anwesenheit, Events und Eventchat.', 'cases' => 'UC-07–10 · UC-31 · UC-50–51 · UC-71–72 · UC-89'],
        ['number' => '04', 'title' => 'Verein, Mitglieder und Finanzen', 'color' => '#EA580C', 'image' => 'feature', 'body' => 'Vereinsprofil, Mitgliedsanträge, Formulare, Anforderungen, Rollen, Kommunikation, Mitgliedskarte, Beiträge, Rechnungen und Bankabgleich.', 'cases' => 'UC-06 · UC-20–27 · UC-31 · UC-49 · UC-74–77 · UC-80 · UC-88'],
        ['number' => '05', 'title' => 'Feed, Posts, Chat, Dateien und Moderation', 'color' => '#DB2777', 'image' => 'flow', 'body' => 'Posts, Kommentare, Stories, Nachrichten, Gruppen, Dateien, Meldungen, Einsprüche, Richtlinien und Support.', 'cases' => 'UC-11–13 · UC-28 · UC-37–39 · UC-58 · UC-62 · UC-67 · UC-69–70 · UC-79 · UC-81 · UC-91'],
        ['number' => '06', 'title' => 'Kurse, Lernstudio und Zertifikate', 'color' => '#0891B2', 'image' => 'feature', 'body' => 'Öffentliche Kurse, Einschreibung, Lektionen, Fortschritt, Notizen, Quiz, Aufgaben, Bewertungen, Zertifikate und Studio-Reports.', 'cases' => 'UC-17 · UC-36–38 · UC-46–48 · UC-60'],
        ['number' => '07', 'title' => 'Marketplace, Commerce und Verkäufer', 'color' => '#CA8A04', 'image' => 'flow', 'body' => 'Suche, Filter, Wunschliste, Warenkorb, Checkout, Überweisung, Bestellung, Rückgabe, Erstattung, Produkte, Bestand, Steuern, Versand, Kampagnen und Payouts.', 'cases' => 'UC-19 · UC-41–45 · UC-86–87'],
        ['number' => '08', 'title' => 'Abos, Outfit und Sponsoren', 'color' => '#9333EA', 'image' => 'feature', 'body' => 'Persönliche und Vereinsabos, Rechnungen, Outfit-Style, Lieferungen, Sponsorprofile, Kampagnen, Leads, Ads und Website-Anfragen.', 'cases' => 'UC-18 · UC-32 · UC-54–57 · UC-61 · UC-80 · UC-92'],
        ['number' => '09', 'title' => 'Admin, Plattform und Governance', 'color' => '#334155', 'image' => 'feature', 'body' => 'Nutzer, Rollen, Sportarten, Badges, Gamification, Moderation, Vereinsprüfung, Mail-Zentrale, Providerkosten, Systemstatus, Support und Rechte.', 'cases' => 'UC-33 · UC-55–63 · UC-78 · UC-82–84 · UC-90'],
        ['number' => '10', 'title' => 'Öffentliche Bereiche und End-to-End', 'color' => '#16A34A', 'image' => 'cover', 'body' => 'Öffentliche Vereine, Events, Jobs, Blog, Marketplace, Kurse, Zertifikatsprüfung, Kontakt, vier Geräte und Abschlussbereinigung.', 'cases' => 'UC-34–36 · UC-67 · UC-80 · UC-85'],
    ];
}

function coverSlide(): array
{
    return slide('cover', '#06142F', [
        imageElement('cover', 16.0, 0.0, 17.867, 19.05),
        textElement('Airmius', 1.5, 1.0, 10.0, 0.6, 'Peyebrow'),
        textElement('Vollständige\nUser-Case-Testanleitung', 1.5, 3.0, 13.5, 3.0, 'PWhiteTitle'),
        textElement('Web + App · Sportler · Trainer · Verein · Admin', 1.55, 6.6, 11.5, 0.8, 'PWhiteSub'),
        textElement('Schritt für Schritt prüfen, dokumentieren und auf vier Geräten abgleichen.', 1.55, 8.1, 10.8, 1.0, 'PWhiteBody'),
        pillElement('93 Use Cases', 1.55, 11.0, 3.2, '#2563EB'),
        pillElement('1.884 Checkpunkte', 5.0, 11.0, 4.0, '#0D9488'),
        pillElement('PPT-Testplan', 9.2, 11.0, 3.2, '#F59E0B'),
        textElement('Stand: ' . date('d.m.Y'), 1.55, 17.4, 5.0, 0.4, 'PWhiteSmall'),
    ]);
}

function deviceSlide(): array
{
    $elements = [
        textElement('So testest du mit 2 Handys und 2 Laptops', 1.1, 0.7, 22.0, 0.8, 'PTitle'),
        textElement('Jedes Gerät bleibt während eines Use Cases bei seiner Rolle.', 1.1, 1.6, 22.0, 0.5, 'PSub'),
        cardElement('Handy 1', 'App · Sportler\nTraining · Feed · Ernährung\nProfil · Vereinssuche', 1.1, 3.2, 6.7, 4.2, '#E8F1FF', '#2563EB'),
        cardElement('Handy 2', 'App · Trainer\nCockpit · Teams · Feedback\nPlan · Anwesenheit', 8.5, 3.2, 6.7, 4.2, '#F2EAFE', '#7C3AED'),
        cardElement('Laptop 1', 'Web · Verein\nMitglieder · Finanzen · Dokumente\nEvents · Sponsoren', 15.9, 3.2, 6.7, 4.2, '#FFF2E8', '#EA580C'),
        cardElement('Laptop 2', 'Web · Gegenkonto / Admin\nKontrolle · Moderation · Commerce\nöffentliche Gegenprüfung', 23.3, 3.2, 7.0, 4.2, '#E8F8F1', '#16A34A'),
        textElement('Ablauf: Aktion auslösen → anderes Gerät prüfen → Erwartung dokumentieren → Testobjekt bereinigen.', 1.1, 9.1, 29.0, 0.8, 'PCallout'),
        textElement('Wichtig: Auf echtem Handy niemals localhost verwenden. Emulator: 10.0.2.2 · echtes Handy: erreichbare LAN-IP.', 1.1, 11.0, 29.2, 0.9, 'PWarning'),
        textElement('Die genaue Entwicklungsmodus- und USB-Debugging-Anleitung steht in UC-93.', 1.1, 13.0, 24.0, 0.6, 'PBody'),
    ];
    return slide('devices', '#F8FAFC', $elements);
}

function howToSlide(): array
{
    $elements = [
        textElement('So benutzt du jede Use-Case-Folie', 1.1, 0.7, 22.0, 0.8, 'PTitle'),
        textElement('Nicht nur klicken: immer Ergebnis, Gegenkonto und Nachweis kontrollieren.', 1.1, 1.6, 24.0, 0.5, 'PSub'),
        cardElement('1 · Ziel', 'Verstehen, welche Nutzeraufgabe geprüft wird.', 1.1, 3.0, 8.8, 2.4, '#E8F1FF', '#2563EB'),
        cardElement('2 · Voraussetzungen', 'Konto, Rolle, Testdaten und Geräte vorbereiten.', 10.5, 3.0, 8.8, 2.4, '#F2EAFE', '#7C3AED'),
        cardElement('3 · Klicken', 'Schritt für Schritt die angezeigten Aktionen durchführen.', 19.9, 3.0, 8.8, 2.4, '#FFF2E8', '#EA580C'),
        cardElement('4 · Gegenprüfung', 'Auf dem zweiten Konto oder Gerät Ergebnis prüfen.', 1.1, 6.2, 8.8, 2.4, '#E8F8F1', '#16A34A'),
        cardElement('5 · Nachweis', 'Status, Version, Screenshot und Fehlertext eintragen.', 10.5, 6.2, 8.8, 2.4, '#FFF8E1', '#CA8A04'),
        cardElement('6 · Bereinigen', 'Testposts, Dateien, Zahlungen und Konten wieder entfernen.', 19.9, 6.2, 8.8, 2.4, '#FDECEC', '#DC2626'),
        textElement('Statuswerte: Offen · Bestanden · Fehler · Nachtest', 1.1, 10.5, 20.0, 0.6, 'PCallout'),
        textElement('UI-only beachten: Wenn ein Screen „UI bereit“ oder „API folgt“ anzeigt, als Integrationslücke markieren und nicht als echte erfolgreiche Serveraktion werten.', 1.1, 12.0, 29.8, 1.0, 'PWarning'),
    ];
    return slide('howto', '#F8FAFC', $elements);
}

function visualFlowSlide(): array
{
    return slide('flow', '#FFFFFF', [
        textElement('Die komplette Produktkette auf einen Blick', 1.1, 0.7, 22.0, 0.8, 'PTitle'),
        textElement('Vom ersten Login bis zu Verein, Commerce, Lernen und Admin.', 1.1, 1.6, 24.0, 0.5, 'PSub'),
        imageElement('flow', 1.1, 2.7, 22.0, 12.5),
        cardElement('Prüfprinzip', 'Jede Aktion braucht ein erwartetes Ergebnis und eine Gegenprüfung.', 24.0, 3.2, 8.5, 3.1, '#E8F1FF', '#2563EB'),
        cardElement('Datenfluss', 'Web und App greifen auf denselben Teststand zu. Änderungen müssen synchron erscheinen.', 24.0, 7.0, 8.5, 3.1, '#E8F8F1', '#0D9488'),
        cardElement('Sicherheit', 'Rollen, Sichtbarkeit, Altersfreigabe und Löschung in jedem Bereich mitprüfen.', 24.0, 10.8, 8.5, 3.1, '#FFF2E8', '#EA580C'),
    ]);
}

function roleMatrixSlide(): array
{
    $elements = [
        textElement('Rollen- und Funktionsmatrix', 1.1, 0.7, 22.0, 0.8, 'PTitle'),
        textElement('Diese Matrix zeigt, wer welchen Bereich primär prüft.', 1.1, 1.6, 22.0, 0.5, 'PSub'),
    ];
    $headers = ['Bereich', 'Sportler', 'Trainer', 'Verein', 'Admin'];
    $x = [1.1, 7.1, 12.5, 17.9, 24.0];
    $w = [5.6, 5.0, 5.0, 5.6, 6.1];
    foreach ($headers as $i => $header) {
        $elements[] = cardElement($header, '', $x[$i], 2.8, $w[$i], 0.9, '#0F2B52', '#0F2B52', 'PWhiteSmall');
    }
    $rows = [
        ['Profil & Sport', 'prüft', 'prüft', 'sieht', 'verwaltet'],
        ['Training & Events', 'nimmt teil', 'plant', 'koordiniert', 'kontrolliert'],
        ['Mitglieder & Finanzen', 'eigene Daten', 'Teambezug', 'verwaltet', 'auditieren'],
        ['Feed, Chat & Dateien', 'erstellt', 'moderiert Team', 'teilt', 'moderiert'],
        ['Kurse & Marketplace', 'lernt/kauft', 'erstellt', 'verkauft', 'prüft'],
        ['Sicherheit & Rechte', 'privat', 'Teamrechte', 'Vereinsrechte', 'Plattformrechte'],
    ];
    foreach ($rows as $r => $row) {
        $y = 3.9 + ($r * 1.55);
        foreach ($row as $i => $cell) {
            $elements[] = cardElement('', $cell, $x[$i], $y, $w[$i], 1.2, $i === 0 ? '#F1F5F9' : '#FFFFFF', $i === 0 ? '#CBD5E1' : '#E2E8F0', $i === 0 ? 'PLabel' : 'PSmall');
        }
    }
    $elements[] = textElement('Für jeden Use Case steht das Testkonto in der Überschrift bzw. in den Voraussetzungen.', 1.1, 14.1, 26.0, 0.6, 'PCallout');
    return slide('roles', '#F8FAFC', $elements);
}

function sectionSlide(array $section): array
{
    $elements = [
        textElement('Kapitel ' . $section['number'], 1.1, 0.7, 8.0, 0.5, 'Peyebrow'),
        textElement($section['title'], 1.1, 1.25, 18.0, 1.0, 'PTitle'),
        textElement($section['body'], 1.1, 2.55, 14.0, 2.0, 'PBody'),
        pillElement($section['cases'], 1.1, 5.2, 13.0, $section['color']),
        cardElement('Klickfokus', 'Menü öffnen → Testaktion ausführen → anderes Gerät prüfen → Status dokumentieren', 1.1, 7.4, 14.2, 2.5, '#FFFFFF', $section['color']),
        cardElement('Sicherheitsfokus', 'Sichtbarkeit, Rolle, Berechtigungen, Fehlerfall und Bereinigung nicht überspringen.', 1.1, 10.5, 14.2, 2.5, '#FFFFFF', '#CBD5E1'),
        imageElement($section['image'], 17.2, 2.0, 15.5, 12.2),
    ];
    return slide('section-' . $section['number'], '#F8FAFC', $elements);
}

function useCaseSlide(array $case, array $steps, int $chunkIndex, int $chunkCount): array
{
    $start = $chunkIndex * 10 + 1;
    $end = $start + count($steps) - 1;
    $title = $case['id'] . ': ' . $case['title'];
    if ($chunkCount > 1) {
        $title .= ' · Teil ' . ($chunkIndex + 1) . '/' . $chunkCount;
    }
    $elements = [
        textElement($title, 1.0, 0.55, 30.0, 0.95, 'PTitle'),
        textElement($chunkIndex === 0 ? 'Ziel' : 'Fortsetzung · Klickschritte ' . $start . '–' . $end, 1.0, 1.55, 5.0, 0.4, 'Peyebrow'),
    ];
    if ($chunkIndex === 0) {
        $elements[] = cardElement('Ziel', $case['goal'], 1.0, 2.0, 15.1, 2.0, '#E8F1FF', '#2563EB');
        $elements[] = cardElement('Voraussetzungen', $case['prereq'], 16.7, 2.0, 15.1, 2.0, '#F8FAFC', '#CBD5E1');
    }
    $top = $chunkIndex === 0 ? 4.5 : 2.1;
    $elements[] = textElement('Klickschritte ' . $start . '–' . $end, 1.0, $top, 13.0, 0.45, 'PLabel');
    $left = [];
    $right = [];
    foreach ($steps as $i => $step) {
        if ($i < 5) {
            $left[] = ($start + $i) . '. ' . $step;
        } else {
            $right[] = ($start + $i) . '. ' . $step;
        }
    }
    $elements[] = listCard($left, 1.0, $top + 0.55, 15.1, 6.4, '#FFFFFF', '#E2E8F0');
    if ($right !== []) {
        $elements[] = listCard($right, 16.7, $top + 0.55, 15.1, 6.4, '#FFFFFF', '#E2E8F0');
    }
    $bottom = $chunkIndex === 0 ? 11.7 : 9.1;
    $elements[] = cardElement('Erwartetes Ergebnis', $case['expected'], 1.0, $bottom, 20.8, 1.65, '#E8F8F1', '#16A34A');
    $elements[] = cardElement('Status / Nachweis', $case['status'] . '\nStatus: ________\nScreenshot/Fehlertext: ____________________', 22.2, $bottom, 9.6, 1.65, '#FFF8E1', '#CA8A04');
    return slide($case['id'] . '-' . ($chunkIndex + 1), '#FFFFFF', $elements);
}

function routeCatalogSlide(int $page): array
{
    $catalogs = [
        ['title' => 'Funktionskatalog · Kern- und Kommunikationsbereiche', 'color' => '#2563EB', 'items' => [
            'Zugang: Registrierung, Login, Passwort, E-Mail, 2FA, Sessions, Profilfoto',
            'Profile: Status, Sprache, Sportprofil, Skills, Follow, Block, Empfehlungen',
            'Feed: Posts, Kommentare, Likes, hilfreich, Stories, Meldungen, Einsprüche',
            'Chat: Gespräche, Gruppen, Einladungen, Teilnehmer, Mute, Read, Reaktionen',
            'Dateien: Upload, Vorschau, Download, Ordner, Freigabe, Shared-Link, Löschen',
            'Sport: Aktivitäten, Integrationen, Matching, Orte, Routen, Tracks, Ernährung',
            'Training: Übungen, Pläne, Vorlagen, AI, Logs, Drafts, Feedback, Analytics',
        ]],
        ['title' => 'Funktionskatalog · Verein, Team und Lernen', 'color' => '#0D9488', 'items' => [
            'Verein: Profil, Bilder, Sichtbarkeit, öffentliche Vorschau, Sponsoren, Jobs',
            'Mitglieder: Anträge, externe Mitglieder, Import, Einladung, Rollen, Nummern',
            'Beiträge: Mitgliedstypen, Regeln, Rechnungen, Zahlungen, Mahnungen, Bankabgleich',
            'Export: SEPA, DATEV, Kassenbuch, Spenden, Vorauszahlungen, Audit',
            'Team: Erstellen, Bilder, Kader, Rollen, Einladungen, Beitrittsanfragen, Insights',
            'Events: Filter, Erstellen, Bearbeiten, RSVP, Kommentare, Chat, Anwesenheit, Absage',
            'Learning: Kurse, Lektionen, Fortschritt, Notizen, Quiz, Aufgaben, Reviews, Zertifikate',
        ]],
        ['title' => 'Funktionskatalog · Commerce, Admin und Plattformbetrieb', 'color' => '#EA580C', 'items' => [
            'Marketplace: Produkte, Anbieter, Suche, Filter, Wunschliste, Warenkorb, Checkout',
            'Orders: Rechnung, Gutschrift, Überweisung, Versand, Rückgabe, Erstattung, Status',
            'Seller: Antrag, Produkte, Import, Bestand, Steuern, Versand, Providerstandorte',
            'Marketing: Coupons, Kampagnen, Gruppen, Creatives, Leads, Ads, Website-Anfragen',
            'Subscriptions: Pläne, Checkout, Banktransfer, Rechnung, Cancel, Renew, Limits',
            'Outfit: Styleprofil, Abo, Pause, Resume, Lieferstatus, Issue, Admin-Pläne',
            'Admin: Nutzer, Rollen, Rechte, Badges, Gamification, Moderation, Mail, System, Kosten',
        ]],
    ];
    $catalog = $catalogs[$page - 1];
    $elements = [
        textElement($catalog['title'], 1.1, 0.7, 27.0, 0.8, 'PTitle'),
        textElement('Diese Folie ist der Funktionsabgleich hinter den Use Cases.', 1.1, 1.6, 25.0, 0.5, 'PSub'),
    ];
    foreach ($catalog['items'] as $i => $item) {
        $col = $i % 2;
        $row = intdiv($i, 2);
        $elements[] = cardElement('✓', $item, 1.1 + ($col * 15.8), 2.7 + ($row * 2.4), 14.8, 1.8, '#FFFFFF', $catalog['color']);
    }
    return slide('catalog-' . $page, '#F8FAFC', $elements);
}

function evidenceSlide(): array
{
    return slide('evidence', '#06142F', [
        textElement('Nachweis und Fehlerdokumentation', 1.1, 0.8, 26.0, 0.9, 'PWhiteTitle'),
        textElement('Damit aus „getestet“ ein verwertbarer Befund wird.', 1.1, 1.9, 24.0, 0.5, 'PWhiteSub'),
        cardElement('Immer notieren', 'Use Case · Konto · Gerät · Browser/App-Version · Uhrzeit · Status', 1.1, 3.2, 14.8, 2.2, '#0F2B52', '#2563EB', 'PWhiteBody'),
        cardElement('Bei Fehlern', 'Exakter Klickweg · Eingabedaten · erwartetes Ergebnis · tatsächliches Ergebnis · Screenshot · Fehlermeldung', 16.6, 3.2, 15.0, 2.2, '#0F2B52', '#F59E0B', 'PWhiteBody'),
        cardElement('Bei Berechtigungen', 'Mit Gegenkonto und direktem Link prüfen. Zugriff verweigert, Umleitung und Datenleck getrennt dokumentieren.', 1.1, 6.2, 14.8, 2.5, '#0F2B52', '#0D9488', 'PWhiteBody'),
        cardElement('Bei Zahlungen', 'Nur Testmodus, Überweisung oder Mock verwenden. Keine echte SEPA-Datei und keine echte Auszahlung auslösen.', 16.6, 6.2, 15.0, 2.5, '#0F2B52', '#EA580C', 'PWhiteBody'),
        textElement('Abschluss: Testdaten löschen, alle Geräte abmelden, USB-Debugging deaktivieren.', 1.1, 11.0, 27.0, 0.7, 'PWhiteSub'),
    ]);
}

function finalSlide(): array
{
    return slide('final', '#F8FAFC', [
        textElement('Startpunkt für die Live-Begleitung', 1.1, 0.8, 25.0, 0.8, 'PTitle'),
        textElement('Beginne mit UC-01. Danach gehen wir die Use Cases in der Reihenfolge der Kapitel gemeinsam durch.', 1.1, 1.8, 27.0, 0.8, 'PSub'),
        cardElement('Als Nächstes', 'Du öffnest die Präsentation und sagst mir nur: „Ich bin bei UC-01, Schritt 1.“ Dann begleite ich dich Klick für Klick.', 1.1, 3.4, 20.0, 3.1, '#E8F1FF', '#2563EB'),
        cardElement('Bereit zur Prüfung', 'Web · App · zwei Handys · zwei Laptops · Testkonten · Testdaten', 22.0, 3.4, 10.0, 3.1, '#E8F8F1', '#16A34A'),
        textElement('Die vollständige Detailquelle bleibt zusätzlich als Word- und Markdown-Checkliste erhalten.', 1.1, 8.0, 25.0, 0.7, 'PCallout'),
        textElement('Nicht vergessen: UI-only oder „API folgt“ als offenes Integrationsthema markieren.', 1.1, 10.0, 26.0, 0.7, 'PWarning'),
    ]);
}

function slide(string $name, string $background, array $elements): array
{
    return ['name' => $name, 'background' => $background, 'elements' => $elements];
}

function textElement(string $text, float $x, float $y, float $w, float $h, string $style): array
{
    return ['type' => 'text', 'text' => $text, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'style' => $style];
}

function cardElement(string $title, string $body, float $x, float $y, float $w, float $h, string $fill, string $stroke, string $bodyStyle = 'PBody'): array
{
    return ['type' => 'card', 'title' => $title, 'body' => $body, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'fill' => $fill, 'stroke' => $stroke, 'bodyStyle' => $bodyStyle];
}

function listCard(array $items, float $x, float $y, float $w, float $h, string $fill, string $stroke): array
{
    return ['type' => 'listCard', 'items' => $items, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'fill' => $fill, 'stroke' => $stroke];
}

function pillElement(string $text, float $x, float $y, float $w, string $fill): array
{
    return ['type' => 'pill', 'text' => $text, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => 0.8, 'fill' => $fill];
}

function imageElement(string $image, float $x, float $y, float $w, float $h): array
{
    return ['type' => 'image', 'image' => $image, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
}

function contentXml(array $slides): string
{
    $automatic = automaticStyles($slides);
    $pages = '';
    foreach ($slides as $index => $slide) {
        $pages .= '<draw:page draw:name="' . xmlEscape($slide['name']) . '" draw:style-name="dp1" draw:master-page-name="Default">';
        $backgroundStyle = 'slide-bg-' . substr(md5($slide['background']), 0, 8);
        $pages .= '<draw:rect draw:style-name="' . $backgroundStyle . '" svg:x="0cm" svg:y="0cm" svg:width="33.867cm" svg:height="19.05cm"/>';
        foreach ($slide['elements'] as $element) {
            $pages .= elementXml($element);
        }
        $pages .= '<draw:frame draw:style-name="transparent" svg:x="31.4cm" svg:y="18.15cm" svg:width="1.1cm" svg:height="0.35cm"><draw:text-box><text:p text:style-name="PPage">' . ($index + 1) . '</text:p></draw:text-box></draw:frame>';
        $pages .= '</draw:page>';
    }

    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:svg="http://www.w3.org/2000/svg" xmlns:presentation="urn:oasis:names:tc:opendocument:xmlns:presentation:1.0" office:version="1.2">'
        . '<office:automatic-styles>' . $automatic . '</office:automatic-styles>'
        . '<office:body><office:presentation>' . $pages . '</office:presentation></office:body></office:document-content>';
}

function automaticStyles(array $slides): string
{
    $styles = '';
    $backgrounds = ['#F8FAFC', '#FFFFFF', '#F8FAFC', '#06142F', '#FFFFFF', '#F1F5F9'];
    foreach ($backgrounds as $i => $color) {
        $styles .= '<style:style style:name="bg-' . $i . '" style:family="graphic"><style:graphic-properties draw:fill="solid" draw:fill-color="' . $color . '" draw:stroke="none"/></style:style>';
    }
    foreach (['transparent', 'img'] as $name) {
        $styles .= '<style:style style:name="' . $name . '" style:family="graphic"><style:graphic-properties draw:fill="none" draw:stroke="none"/></style:style>';
    }
    $slideBackgrounds = [];
    foreach ($slides as $slide) {
        $slideBackgrounds[substr(md5($slide['background']), 0, 8)] = $slide['background'];
    }
    foreach ($slideBackgrounds as $hash => $color) {
        $styles .= '<style:style style:name="slide-bg-' . $hash . '" style:family="graphic"><style:graphic-properties draw:fill="solid" draw:fill-color="' . $color . '" draw:stroke="none"/></style:style>';
    }
    $cardPairs = [];
    $pillFills = [];
    foreach ($slides as $slide) {
        foreach ($slide['elements'] as $element) {
            if ($element['type'] === 'card' || $element['type'] === 'listCard') {
                $cardPairs[substr(md5($element['fill'] . $element['stroke']), 0, 8)] = [$element['fill'], $element['stroke']];
            }
            if ($element['type'] === 'pill') {
                $pillFills[substr(md5($element['fill']), 0, 8)] = $element['fill'];
            }
        }
    }
    foreach ($cardPairs as $hash => [$fill, $stroke]) {
        $styles .= '<style:style style:name="card-' . $hash . '" style:family="graphic"><style:graphic-properties draw:fill="solid" draw:fill-color="' . $fill . '" draw:stroke="solid" svg:stroke-color="' . $stroke . '" svg:stroke-width="0.035cm"/></style:style>';
    }
    foreach ($pillFills as $hash => $fill) {
        $styles .= '<style:style style:name="pill-' . $hash . '" style:family="graphic"><style:graphic-properties draw:fill="solid" draw:fill-color="' . $fill . '" draw:stroke="none"/></style:style>';
    }
    foreach (['PTitle' => ['24pt', '#0F2B52', 'bold'], 'PSub' => ['13pt', '#475569', 'normal'], 'PBody' => ['11pt', '#1E293B', 'normal'], 'PSmall' => ['9pt', '#334155', 'normal'], 'PLabel' => ['11pt', '#0F2B52', 'bold'], 'Peyebrow' => ['10pt', '#2563EB', 'bold'], 'PCallout' => ['13pt', '#0F2B52', 'bold'], 'PWarning' => ['11pt', '#9A3412', 'bold'], 'PWhiteTitle' => ['25pt', '#FFFFFF', 'bold'], 'PWhiteSub' => ['14pt', '#D8E7FF', 'normal'], 'PWhiteBody' => ['11pt', '#E8F1FF', 'normal'], 'PWhiteSmall' => ['9pt', '#A9C1E8', 'normal'], 'PPage' => ['8pt', '#64748B', 'normal']] as $name => $values) {
        $styles .= '<style:style style:name="' . $name . '" style:family="paragraph"><style:paragraph-properties fo:margin-left="0cm" fo:margin-right="0cm" fo:margin-top="0cm" fo:margin-bottom="0.04cm"/><style:text-properties fo:font-family="Liberation Sans" fo:font-size="' . $values[0] . '" fo:color="' . $values[1] . '" fo:font-weight="' . $values[2] . '"/></style:style>';
    }
    return '<style:style style:name="dp1" style:family="drawing-page"><style:drawing-page-properties/></style:style>' . $styles;
}

function stylesXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" office:version="1.2"><office:styles><style:style style:name="dp1" style:family="drawing-page"><style:drawing-page-properties/></style:style></office:styles><office:automatic-styles><style:page-layout style:name="PM1"><style:page-layout-properties fo:page-width="33.867cm" fo:page-height="19.05cm" style:print-orientation="landscape"/></style:page-layout></office:automatic-styles><office:master-styles><style:master-page style:name="Default" style:page-layout-name="PM1"/></office:master-styles></office:document-styles>';
}

function settingsXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><office:document-settings xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.2"><office:settings/></office:document-settings>';
}

function manifestXml(array $slides): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.2"><manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.presentation"/><manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/><manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/><manifest:file-entry manifest:full-path="settings.xml" manifest:media-type="text/xml"/><manifest:file-entry manifest:full-path="Pictures/cover.png" manifest:media-type="image/png"/><manifest:file-entry manifest:full-path="Pictures/flow.png" manifest:media-type="image/png"/><manifest:file-entry manifest:full-path="Pictures/feature.png" manifest:media-type="image/png"/><manifest:file-entry manifest:full-path="Pictures/icon.png" manifest:media-type="image/png"/></manifest:manifest>';
}

function elementXml(array $element): string
{
    $x = number_format($element['x'], 3, '.', '') . 'cm';
    $y = number_format($element['y'], 3, '.', '') . 'cm';
    $w = number_format($element['w'], 3, '.', '') . 'cm';
    $h = number_format($element['h'], 3, '.', '') . 'cm';
    if ($element['type'] === 'image') {
        $file = match ($element['image']) {
            'cover' => 'cover.png',
            'flow' => 'flow.png',
            'feature' => 'feature.png',
            default => 'icon.png',
        };
        return '<draw:frame draw:style-name="img" svg:x="' . $x . '" svg:y="' . $y . '" svg:width="' . $w . '" svg:height="' . $h . '"><draw:image xlink:href="Pictures/' . $file . '" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad"/></draw:frame>';
    }
    if ($element['type'] === 'pill') {
        $styleName = 'pill-' . substr(md5($element['fill']), 0, 8);
        return '<draw:rect draw:style-name="' . xmlEscape($styleName) . '" svg:x="' . $x . '" svg:y="' . $y . '" svg:width="' . $w . '" svg:height="0.8cm" rx="0.18cm" ry="0.18cm"/><draw:frame draw:style-name="transparent" svg:x="' . $x . '" svg:y="' . $y . '" svg:width="' . $w . '" svg:height="0.8cm"><draw:text-box><text:p text:style-name="PWhiteSmall">' . xmlEscape($element['text']) . '</text:p></draw:text-box></draw:frame>';
    }
    if ($element['type'] === 'card' || $element['type'] === 'listCard') {
        $styleName = 'card-' . substr(md5($element['fill'] . $element['stroke']), 0, 8);
        $bodyStyle = $element['type'] === 'listCard' ? 'PSmall' : ($element['bodyStyle'] ?? 'PBody');
        $bodyLines = $element['type'] === 'listCard'
            ? array_map(static fn (string $item): string => '☐ ' . $item, $element['items'])
            : preg_split('/\R/u', str_replace('\\n', "\n", (string) $element['body']));
        $title = $element['type'] === 'listCard' ? '' : $element['title'];
        $paragraphs = '';
        if ($title !== '') {
            $paragraphs .= '<text:p text:style-name="PLabel">' . xmlEscape($title) . '</text:p>';
        }
        foreach ($bodyLines as $bodyLine) {
            if (trim((string) $bodyLine) !== '') {
                $paragraphs .= '<text:p text:style-name="' . xmlEscape($bodyStyle) . '">' . xmlEscape(trim((string) $bodyLine)) . '</text:p>';
            }
        }
        return '<draw:rect draw:style-name="' . xmlEscape($styleName) . '" svg:x="' . $x . '" svg:y="' . $y . '" svg:width="' . $w . '" svg:height="' . $h . '" rx="0.16cm" ry="0.16cm"/><draw:frame draw:style-name="transparent" svg:x="' . $x . '" svg:y="' . $y . '" svg:width="' . $w . '" svg:height="' . $h . '"><draw:text-box>' . $paragraphs . '</draw:text-box></draw:frame>';
    }
    if ($element['type'] === 'text') {
        return '<draw:frame draw:style-name="transparent" svg:x="' . $x . '" svg:y="' . $y . '" svg:width="' . $w . '" svg:height="' . $h . '"><draw:text-box><text:p text:style-name="' . xmlEscape($element['style']) . '">' . inlineTextXml($element['text']) . '</text:p></draw:text-box></draw:frame>';
    }
    return '';
}

function stripMarkdown(string $text): string
{
    $text = preg_replace('/\*\*(.+?)\*\*/u', '$1', $text) ?? $text;
    $text = preg_replace('/`(.+?)`/u', '$1', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function inlineTextXml(string $text): string
{
    $text = str_replace('\\n', "\n", $text);
    $parts = preg_split('/\R/u', $text) ?: [$text];
    return implode('<text:line-break/>', array_map(static fn (string $part): string => xmlEscape($part), $parts));
}

function firstSentences(string $text, int $limit): string
{
    $text = stripMarkdown($text);
    if (mb_strlen($text) <= $limit) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $limit - 1)) . '…';
}

function xmlEscape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}
