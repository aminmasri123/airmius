<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sourcePath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'USER_CASES.md';
$csvPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'Airmius_Use_Cases_Testmatrix.csv';
$xlsxPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'Airmius_Use_Cases_Testmatrix.xlsx';
$xlsxPartsPath = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . 'use-case-testmatrix-xlsx';

if (! is_file($sourcePath)) {
    fwrite(STDERR, "Source file not found: {$sourcePath}\n");
    exit(1);
}

$markdown = file_get_contents($sourcePath);

if ($markdown === false) {
    fwrite(STDERR, "Could not read source file: {$sourcePath}\n");
    exit(1);
}

$sections = parseSections($markdown);
$rows = buildRows($sections);

writeCsv($csvPath, $rows);
writeXlsxParts($xlsxPartsPath, $rows);

echo "CSV: {$csvPath}\n";
echo "XLSX parts: {$xlsxPartsPath}\n";
echo "XLSX target: {$xlsxPath}\n";
echo 'Rows: ' . count($rows) . "\n";

function parseSections(string $markdown): array
{
    $sections = [];
    $current = null;
    $captureUseCases = false;
    $captureRoutes = false;

    foreach (preg_split('/\R/u', $markdown) as $line) {
        if (preg_match('/^###\s+(.+)$/u', $line, $matches)) {
            if ($current !== null) {
                $sections[] = $current;
            }

            $current = [
                'title' => trim($matches[1]),
                'items' => [],
                'routes' => [],
            ];
            $captureUseCases = false;
            $captureRoutes = false;
            continue;
        }

        if ($current === null) {
            continue;
        }

        $trimmed = trim($line);

        if (preg_match('/^##\s+/u', $line)) {
            $captureUseCases = false;
            $captureRoutes = false;
            continue;
        }

        if ($trimmed === 'Use Cases:') {
            $captureUseCases = true;
            $captureRoutes = false;
            continue;
        }

        if ($trimmed === 'Relevante Routen:') {
            $captureUseCases = false;
            $captureRoutes = true;
            continue;
        }

        if ($captureUseCases && preg_match('/^\s*-\s+(.+)$/u', $line, $matches)) {
            $item = cleanText($matches[1]);

            if (! isChecklistItem($item)) {
                continue;
            }

            $current['items'][] = $item;
            continue;
        }

        if ($captureRoutes && preg_match('/^\s*-\s+(.+)$/u', $line, $matches)) {
            $route = trim($matches[1]);
            $route = trim($route, "` \t\n\r\0\x0B");
            $current['routes'][] = $route;
        }
    }

    if ($current !== null) {
        $sections[] = $current;
    }

    return $sections;
}

function buildRows(array $sections): array
{
    $rows = [];
    $counter = 1;

    foreach ($sections as $section) {
        if (count($section['items']) === 0) {
            continue;
        }

        $area = sectionArea($section['title']);
        $actor = sectionActor($section['title']);
        $routes = implode("\n", array_unique($section['routes']));

        foreach ($section['items'] as $item) {
            $phase = testPhaseFor($section['title'], $item);
            $account = testAccountFor($section['title'], $item, $phase);

            $rows[] = [
                'ID' => 'UC-' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
                'Testphase' => $phase,
                'Testkonto / Plan' => $account,
                'Bereich' => $area,
                'Akteur' => $actor,
                'Priorität' => priorityFor($section['title'], $item),
                'Funktion' => $item,
                'Voraussetzung' => prerequisiteFor($section['title'], $item, $phase),
                'Testdaten' => testDataFor($section['title'], $item, $phase),
                'Testschritte' => testStepsFor($section['title'], $item, $phase),
                'Erwartetes Ergebnis' => expectedFor($item),
                'Free-Test' => freeExpectationFor($section['title'], $item),
                'Premium-Test' => premiumExpectationFor($section['title'], $item),
                'Relevante Routen' => $routes !== '' ? $routes : '-',
                'Status' => 'Offen',
                'Tester' => '',
                'Testdatum' => '',
                'Fehler/Notiz' => '',
            ];
            $counter++;
        }
    }

    usort($rows, static function (array $a, array $b): int {
        return phaseOrder($a['Testphase']) <=> phaseOrder($b['Testphase'])
            ?: strcmp($a['Bereich'], $b['Bereich'])
            ?: strcmp($a['ID'], $b['ID']);
    });

    return $rows;
}

function phaseOrder(string $phase): int
{
    return [
        '1 - Free: Gast & Registrierung' => 10,
        '2 - Free: Nutzerkonto testen' => 20,
        '3 - Free: Verein/Team testen' => 30,
        '4 - Premium: Nutzerfunktionen testen' => 40,
        '5 - Premium: Verein/Team testen' => 50,
        '6 - Admin & System testen' => 60,
    ][$phase] ?? 99;
}

function testPhaseFor(string $sectionTitle, string $item): string
{
    $text = mb_strtolower($sectionTitle . ' ' . $item);

    if (str_contains($text, 'admin') || str_contains($text, 'moderation') || str_contains($text, 'webhook') || str_contains($text, 'scheduler') || str_contains($text, 'cron') || str_contains($text, 'wartungsmodus')) {
        return '6 - Admin & System testen';
    }

    if (str_contains($text, 'blog und cms blogbeitrag') || str_contains($text, 'blog und cms status setzen')) {
        return '6 - Admin & System testen';
    }

    if (isPremiumFeature($text)) {
        if (str_contains($text, 'verein') || str_contains($text, 'club') || str_contains($text, 'team') || str_contains($text, 'mitglied') || str_contains($text, 'sepa') || str_contains($text, 'datev')) {
            return '5 - Premium: Verein/Team testen';
        }

        return '4 - Premium: Nutzerfunktionen testen';
    }

    if (str_contains($text, 'oeffentlicher') || str_contains($text, 'öffentlich') || str_contains($text, 'preise') || str_contains($text, 'blog') || str_contains($text, 'jobs') || str_contains($text, 'registr') || str_contains($text, 'login')) {
        return '1 - Free: Gast & Registrierung';
    }

    if (str_contains($text, 'verein') || str_contains($text, 'club') || str_contains($text, 'team')) {
        return '3 - Free: Verein/Team testen';
    }

    return '2 - Free: Nutzerkonto testen';
}

function isPremiumFeature(string $text): bool
{
    $premiumNeedles = [
        'abo',
        'premium',
        'subscription',
        'checkout',
        'zahlung',
        'rechnung',
        'sepa',
        'datev',
        'bank',
        'import',
        'export',
        'sponsor',
        'marketplace-anbieter',
        'provider',
        'outfit',
        'werbeagentur',
        'add-on',
        'limit',
        'feature-gate',
    ];

    foreach ($premiumNeedles as $needle) {
        if (str_contains($text, $needle)) {
            return true;
        }
    }

    return false;
}

function testAccountFor(string $sectionTitle, string $item, string $phase): string
{
    $text = mb_strtolower($sectionTitle . ' ' . $item);

    if (str_starts_with($phase, '1 - Free: Gast')) {
        return str_contains($text, 'registr') || str_contains($text, 'login')
            ? 'Gast + neues Free-Nutzerkonto'
            : 'Gast ohne Login';
    }

    if (str_starts_with($phase, '2 - Free')) {
        return 'Free-Nutzerkonto';
    }

    if (str_starts_with($phase, '3 - Free')) {
        return 'Free-Nutzerkonto + Free-Verein/Team';
    }

    if (str_starts_with($phase, '4 - Premium')) {
        return 'Premium-Nutzerkonto';
    }

    if (str_starts_with($phase, '5 - Premium')) {
        return 'Vereinsadmin mit Premium-Vereinsplan';
    }

    return 'Admin-Konto oder Systemprozess';
}

function prerequisiteFor(string $sectionTitle, string $item, string $phase): string
{
    $text = mb_strtolower($sectionTitle . ' ' . $item);

    if (str_starts_with($phase, '1 - Free: Gast')) {
        return 'Browser im privaten Fenster oeffnen; fuer Registrierung eine neue Test-E-Mail verwenden.';
    }

    if (str_contains($text, 'mitglied') || str_contains($text, 'team') || str_contains($text, 'verein')) {
        return str_contains($phase, 'Premium')
            ? 'Testverein mit Premium-Plan, mindestens 2 Mitglieder, 1 Team und 1 offene Einladung vorbereiten.'
            : 'Free-Konto mit Testverein/Team vorbereiten; Plan-Limits bewusst nicht umgehen.';
    }

    if (str_contains($text, 'rechnung') || str_contains($text, 'zahlung') || str_contains($text, 'checkout')) {
        return 'Testplan, Testrechnung und Test-Zahlungsmethode oder Ueberweisung vorbereiten.';
    }

    if (str_contains($text, 'benachrichtigung') || str_contains($text, 'e-mail') || str_contains($text, 'email')) {
        return 'Zwei Testkonten nutzen und Mail-Log oder Mailbox offen halten.';
    }

    if (str_contains($text, 'admin') || str_starts_with($phase, '6 - Admin')) {
        return 'Admin-Konto nutzen; vorher pruefen, dass Testdaten wieder loeschbar oder eindeutig markiert sind.';
    }

    return 'Passendes Testkonto anmelden und Ausgangszustand notieren.';
}

function testDataFor(string $sectionTitle, string $item, string $phase): string
{
    $text = mb_strtolower($sectionTitle . ' ' . $item);

    if (str_contains($text, 'profil')) {
        return 'Name, Bio, Sportart, Sichtbarkeit, optional Profilbild.';
    }

    if (str_contains($text, 'adresse')) {
        return 'Land als Pflichtfeld; Strasse, PLZ, Ort als optionale Testwerte.';
    }

    if (str_contains($text, 'mitglied')) {
        return 'Mitgliedsnummer, Beitrag, Status aktiv/passiv, optional Lizenznummer.';
    }

    if (str_contains($text, 'rechnung') || str_contains($text, 'zahlung')) {
        return 'Betrag 10,00 EUR; Status offen/bezahlt; eindeutige Rechnungsnummer.';
    }

    if (str_contains($text, 'import')) {
        return 'Gueltige Beispiel-Datei und zweite Datei mit bewusst fehlenden Pflichtfeldern.';
    }

    if (str_contains($text, 'nachricht') || str_contains($text, 'chat')) {
        return 'Absender, Empfaenger, kurzer Text, optional Anhang.';
    }

    if (str_starts_with($phase, '1 - Free')) {
        return 'Keine bestehenden Daten voraussetzen; nur oeffentliche Seiten und neue Registrierung.';
    }

    return 'Normale Eingabe + ein negativer Fall mit fehlenden Pflichtdaten.';
}

function cleanText(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    if (preg_match('/[.!?]$/u', $value)) {
        $value = mb_substr($value, 0, mb_strlen($value) - 1);
    }

    return $value;
}

function isChecklistItem(string $item): bool
{
    $lower = mb_strtolower(trim($item, " \t\n\r\0\x0B:"));

    if ($lower === '') {
        return false;
    }

    $metadataValues = [
        'draft',
        'review',
        'published',
        'archived',
        'new',
        'contacted',
        'quoted',
        'in_progress',
        'done',
        'cancelled',
        'low',
        'medium',
        'high',
    ];

    return ! in_array($lower, $metadataValues, true);
}

function sectionArea(string $title): string
{
    $title = preg_replace('/^\d+(?:\.\d+)*\s+/u', '', $title) ?? $title;
    return trim($title);
}

function sectionActor(string $title): string
{
    $lower = mb_strtolower($title);

    $actors = [
        'Öffentlicher Besucher' => ['öffentlicher besucher', 'oeffentlicher besucher', 'öffentlich'],
        'Registrierter Nutzer' => ['registrierter nutzer', 'konto'],
        'Sportler' => ['sportler', 'profil und sportprofil'],
        'Minderjähriger Nutzer' => ['minderjähriger', 'minderjaehriger', 'jugendschutz'],
        'Eltern' => ['eltern', 'erziehungsberechtigte'],
        'Trainer' => ['trainer'],
        'Verein' => ['verein', 'vereine', 'mitgliederverwaltung', 'sepa', 'datev', 'bank'],
        'Team' => ['team', 'teams'],
        'Sponsor' => ['sponsor', 'ads', 'sponsoring'],
        'Marketplace-Anbieter' => ['marketplace-anbieter', 'anbieter', 'auszahlungen'],
        'Käufer' => ['käufer', 'kaeufer', 'commerce', 'marketplace', 'zahlungen'],
        'System Admin' => ['system admin', 'admin'],
        'System' => ['scheduler', 'cron', 'webhooks', 'broadcast', 'seo'],
    ];

    foreach ($actors as $actor => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                return $actor;
            }
        }
    }

    return 'Alle / je nach Berechtigung';
}

function priorityFor(string $sectionTitle, string $item): string
{
    $text = mb_strtolower($sectionTitle . ' ' . $item);

    $criticalNeedles = [
        'login',
        'registr',
        'eltern',
        'zahlung',
        'rechnung',
        'webhook',
        'moderation',
        'sperr',
        'datenschutz',
        'agb',
        'widerruf',
        'wartungsmodus',
        'berechtigung',
        'rolle',
        'mitglied',
        'abo',
        'checkout',
    ];

    foreach ($criticalNeedles as $needle) {
        if (str_contains($text, $needle)) {
            return 'Hoch';
        }
    }

    $mediumNeedles = [
        'profil',
        'team',
        'verein',
        'feed',
        'chat',
        'event',
        'datei',
        'import',
        'export',
        'benachrichtigung',
        'marketplace',
    ];

    foreach ($mediumNeedles as $needle) {
        if (str_contains($text, $needle)) {
            return 'Mittel';
        }
    }

    return 'Normal';
}

function testStepsFor(string $sectionTitle, string $item, string $phase): string
{
    $action = lcfirst($item);
    $text = mb_strtolower($item);

    if (str_starts_with($phase, '1 - Free: Gast')) {
        return "1. Privates Browserfenster oeffnen.\n2. Zielseite ohne Login aufrufen.\n3. Navigation, Texte, Ladezustand und Mobilansicht pruefen.\n4. Falls Registrierung/Login betroffen ist: neues Free-Konto anlegen und Weiterleitung pruefen.";
    }

    if (str_contains($text, 'löschen') || str_contains($text, 'loeschen')) {
        return "1. Passenden Datensatz anlegen oder auswählen.\n2. Löschaktion ausführen.\n3. Sicherheitsabfrage bestätigen.\n4. Liste und Detailseite neu laden.";
    }

    if (str_contains($text, 'bearbeiten') || str_contains($text, 'aktualisieren') || str_contains($text, 'ändern') || str_contains($text, 'pflegen')) {
        return "1. Bestehenden Datensatz öffnen.\n2. Feldwerte ändern und speichern.\n3. Seite neu laden.\n4. Prüfen, ob Änderungen sichtbar und dauerhaft gespeichert sind.";
    }

    if (str_contains($text, 'erstellen') || str_contains($text, 'anlegen') || str_contains($text, 'einreichen')) {
        return "1. Formular öffnen.\n2. Pflichtfelder ausfüllen.\n3. Speichern oder absenden.\n4. Prüfen, ob der neue Datensatz korrekt erscheint.";
    }

    if (str_contains($text, 'anzeigen') || str_contains($text, 'ansehen') || str_contains($text, 'öffnen') || str_contains($text, 'oeffnen') || str_contains($text, 'lesen')) {
        return "1. Mit passender Rolle anmelden.\n2. Zielseite öffnen.\n3. Inhalte, Ladezustand und Berechtigungen prüfen.\n4. Mobilansicht kurz gegenprüfen.";
    }

    if (str_contains($text, 'senden') || str_contains($text, 'verschicken') || str_contains($text, 'einladen')) {
        return "1. Zielperson oder Empfänger auswählen.\n2. Aktion auslösen.\n3. Erfolgsmeldung prüfen.\n4. Prüfen, ob Benachrichtigung oder E-Mail erzeugt wurde.";
    }

    if (str_contains($text, 'import')) {
        return "1. Vorlage herunterladen oder Testdatei vorbereiten.\n2. Datei hochladen.\n3. Validierung und Import starten.\n4. Importierte Datensätze und Fehlerprotokoll prüfen.";
    }

    if (str_contains($text, 'export')) {
        return "1. Exportbereich öffnen.\n2. Zeitraum oder Datensätze auswählen.\n3. Export starten.\n4. Datei öffnen und Inhalte prüfen.";
    }

    return "1. Mit passender Rolle anmelden.\n2. Funktion '{$action}' ausführen.\n3. Erfolgsmeldung, Datenstand und Berechtigungen prüfen.\n4. Fehlerfall mit fehlenden oder ungültigen Daten testen.";
}

function expectedFor(string $item): string
{
    $text = mb_strtolower($item);

    if (str_contains($text, 'löschen') || str_contains($text, 'loeschen')) {
        return 'Der Datensatz wird nur nach Berechtigung und Bestätigung entfernt oder korrekt als nicht löschbar abgewiesen.';
    }

    if (str_contains($text, 'zahlung') || str_contains($text, 'rechnung') || str_contains($text, 'checkout')) {
        return 'Zahlungs- oder Rechnungsstatus ist korrekt, nachvollziehbar und wird dem Nutzer passend angezeigt.';
    }

    if (str_contains($text, 'eltern') || str_contains($text, 'minderjähr') || str_contains($text, 'minderjaehr')) {
        return 'Jugendschutzregeln greifen: Zugriff, Zustimmung und Widerruf verhalten sich nachvollziehbar.';
    }

    if (str_contains($text, 'moderation') || str_contains($text, 'sperr') || str_contains($text, 'melden')) {
        return 'Moderation, Meldung oder Sperrlogik erstellt den richtigen Status und schützt andere Nutzer.';
    }

    if (str_contains($text, 'import') || str_contains($text, 'export')) {
        return 'Datei wird im erwarteten Format verarbeitet und Fehler werden klar angezeigt.';
    }

    return 'Die Funktion ist sichtbar, ausführbar, speichert korrekt und respektiert Rollen, Limits und Datenschutz.';
}

function freeExpectationFor(string $sectionTitle, string $item): string
{
    $text = mb_strtolower($sectionTitle . ' ' . $item);

    if (isPremiumFeature($text)) {
        return 'Im Free-Plan sichtbar als Hinweis/Upgrade oder sauber gesperrt; keine kaputte Seite und keine unerlaubte Speicherung.';
    }

    if (str_contains($text, 'admin') || str_contains($text, 'moderation') || str_contains($text, 'webhook') || str_contains($text, 'scheduler') || str_contains($text, 'cron')) {
        return 'Fuer Free-Nutzer nicht sichtbar oder mit 403/Weiterleitung geschuetzt.';
    }

    return 'Mit Free-Konto bzw. ohne Login testbar und ohne Premium-Zwang nutzbar.';
}

function premiumExpectationFor(string $sectionTitle, string $item): string
{
    $text = mb_strtolower($sectionTitle . ' ' . $item);

    if (str_contains($text, 'admin') || str_contains($text, 'moderation') || str_contains($text, 'webhook') || str_contains($text, 'scheduler') || str_contains($text, 'cron')) {
        return 'Nur fuer Admin/System ausfuehrbar; Premium-Konto allein reicht nicht.';
    }

    if (isPremiumFeature($text)) {
        return 'Mit aktivem Premium-Plan voll nutzbar; Limits, Rechnungen, E-Mails und Berechtigungen stimmen.';
    }

    return 'Muss weiterhin funktionieren wie im Free-Test; keine Regression durch aktiven Premium-Plan.';
}

function writeCsv(string $path, array $rows): void
{
    $handle = fopen($path, 'wb');

    if ($handle === false) {
        throw new RuntimeException("Could not open CSV for writing: {$path}");
    }

    fwrite($handle, "\xEF\xBB\xBF");
    $headers = array_keys($rows[0] ?? []);
    fputcsv($handle, $headers, ';');

    foreach ($rows as $row) {
        fputcsv($handle, $row, ';');
    }

    fclose($handle);
}

function writeXlsxParts(string $path, array $rows): void
{
    if (! is_dir($path)) {
        if (! mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new RuntimeException("Could not create XLSX parts directory: {$path}");
        }
    }

    $headers = array_keys($rows[0] ?? []);
    $sheetRows = [];
    $sheetRows[] = $headers;

    foreach ($rows as $row) {
        $sheetRows[] = array_values($row);
    }

    $sheetXml = buildSheetXml($sheetRows);
    $files = [
        '[Content_Types].xml' => contentTypesXml(),
        '_rels/.rels' => relsXml(),
        'docProps/app.xml' => appXml(),
        'docProps/core.xml' => coreXml(),
        'xl/workbook.xml' => workbookXml(),
        'xl/_rels/workbook.xml.rels' => workbookRelsXml(),
        'xl/styles.xml' => stylesXml(),
        'xl/worksheets/sheet1.xml' => $sheetXml,
    ];

    foreach ($files as $name => $content) {
        $filePath = $path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
        $directory = dirname($filePath);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Could not create directory: {$directory}");
        }

        file_put_contents($filePath, $content);
    }
}

function buildSheetXml(array $rows): string
{
    $xmlRows = [];

    foreach ($rows as $rowIndex => $row) {
        $excelRow = $rowIndex + 1;
        $cells = [];

        foreach ($row as $columnIndex => $value) {
            $cellRef = columnName($columnIndex + 1) . $excelRow;
            $style = $rowIndex === 0 ? 1 : 0;
            $styleAttr = $style > 0 ? ' s="' . $style . '"' : '';
            $cells[] = '<c r="' . $cellRef . '" t="inlineStr"' . $styleAttr . '><is><t>' . xmlEscape((string) $value) . '</t></is></c>';
        }

        $xmlRows[] = '<row r="' . $excelRow . '">' . implode('', $cells) . '</row>';
    }

    $dimension = 'A1:' . columnName(count($rows[0] ?? [])) . count($rows);
    $columns = [
        [1, 1, 14],
        [2, 2, 30],
        [3, 3, 30],
        [4, 4, 28],
        [5, 5, 22],
        [6, 6, 12],
        [7, 7, 46],
        [8, 8, 48],
        [9, 9, 42],
        [10, 10, 62],
        [11, 13, 58],
        [14, 14, 42],
        [15, 18, 18],
    ];
    $colsXml = '';

    foreach ($columns as [$min, $max, $width]) {
        $colsXml .= '<col min="' . $min . '" max="' . $max . '" width="' . $width . '" customWidth="1"/>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<dimension ref="' . $dimension . '"/>'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<cols>' . $colsXml . '</cols>'
        . '<sheetData>' . implode('', $xmlRows) . '</sheetData>'
        . '<autoFilter ref="' . $dimension . '"/>'
        . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
        . '</worksheet>';
}

function columnName(int $index): string
{
    $name = '';

    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }

    return $name;
}

function xmlEscape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function contentTypesXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';
}

function relsXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>';
}

function workbookXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Testmatrix" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';
}

function workbookRelsXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
}

function stylesXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

function appXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
        . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
        . '<Application>Airmius</Application>'
        . '</Properties>';
}

function coreXml(): string
{
    $created = gmdate('Y-m-d\TH:i:s\Z');

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
        . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
        . 'xmlns:dcterms="http://purl.org/dc/terms/" '
        . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" '
        . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>Airmius Use Cases Testmatrix</dc:title>'
        . '<dc:creator>Airmius</dc:creator>'
        . '<cp:lastModifiedBy>Airmius</cp:lastModifiedBy>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created>'
        . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified>'
        . '</cp:coreProperties>';
}
