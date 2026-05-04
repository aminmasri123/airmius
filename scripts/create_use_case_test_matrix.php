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
            $current['items'][] = cleanText($matches[1]);
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
            $rows[] = [
                'ID' => 'UC-' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
                'Bereich' => $area,
                'Akteur' => $actor,
                'Priorität' => priorityFor($section['title'], $item),
                'Funktion' => $item,
                'Testschritte' => testStepsFor($item),
                'Erwartetes Ergebnis' => expectedFor($item),
                'Relevante Routen' => $routes !== '' ? $routes : '-',
                'Status' => 'Offen',
                'Tester' => '',
                'Testdatum' => '',
                'Fehler/Notiz' => '',
            ];
            $counter++;
        }
    }

    return $rows;
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

function testStepsFor(string $item): string
{
    $action = lcfirst($item);
    $text = mb_strtolower($item);

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
        [2, 2, 28],
        [3, 3, 22],
        [4, 4, 12],
        [5, 5, 46],
        [6, 6, 62],
        [7, 7, 58],
        [8, 8, 42],
        [9, 9, 14],
        [10, 12, 18],
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
