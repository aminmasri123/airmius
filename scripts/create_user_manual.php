<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sourcePath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'USER_CASES.md';
$markdownPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'Airmius_Bedienhandbuch.md';
$pdfPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'Airmius_Bedienhandbuch.pdf';
$docxPartsPath = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . 'airmius-manual-docx';
$docxTargetPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'Airmius_Bedienhandbuch.docx';

if (! is_file($sourcePath)) {
    fwrite(STDERR, "Source file not found: {$sourcePath}\n");
    exit(1);
}

$sections = parseUseCaseSections((string) file_get_contents($sourcePath));
$blocks = buildManualBlocks($sections);

writeMarkdown($markdownPath, $blocks);
writeDocxParts($docxPartsPath, $blocks);
writePdf($pdfPath, $blocks);

echo "Markdown: {$markdownPath}\n";
echo "DOCX parts: {$docxPartsPath}\n";
echo "DOCX target: {$docxTargetPath}\n";
echo "PDF: {$pdfPath}\n";
echo 'Sections: ' . count($sections) . "\n";

function parseUseCaseSections(string $markdown): array
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
            $current['routes'][] = trim($route, "` \t\n\r\0\x0B");
        }
    }

    if ($current !== null) {
        $sections[] = $current;
    }

    return $sections;
}

function buildManualBlocks(array $sections): array
{
    $roleSections = array_values(array_filter($sections, fn ($section) => preg_match('/^1\./u', $section['title'])));
    $moduleSections = array_values(array_filter($sections, fn ($section) => preg_match('/^2\./u', $section['title'])));
    $workflowSections = array_values(array_filter($sections, fn ($section) => preg_match('/^3\./u', $section['title'])));
    $ruleSections = array_values(array_filter($sections, fn ($section) => preg_match('/^4\./u', $section['title'])));

    $blocks = [
        ['type' => 'h1', 'text' => 'Airmius Bedienhandbuch'],
        ['type' => 'p', 'text' => 'Stand: ' . date('d.m.Y')],
        ['type' => 'p', 'text' => 'Dieses Handbuch erklärt die wichtigsten Abläufe der Airmius-Plattform für alle Rollen: Sportler, Trainer, Vereine, Eltern, Sponsoren, Anbieter, Käufer und Administratoren. Es ist als praktische Einleitung gedacht, damit neue Nutzer die Plattform bedienen und Tester alle Funktionsbereiche nachvollziehen können.'],
        ['type' => 'h2', 'text' => 'Grundprinzip'],
        ['type' => 'p', 'text' => 'Airmius ist als Smartphone-first Plattform gedacht. Die wichtigsten Bereiche sind über die Sidebar erreichbar. Auf dem Handy öffnet sich die Navigation über das Menü-Symbol, auf Tablet und Laptop bleibt sie als feste Seitenleiste verfügbar. Aktionen werden rollen- und rechteabhängig angezeigt.'],
        ['type' => 'h2', 'text' => 'Erste Schritte für alle Nutzer'],
        ['type' => 'list', 'items' => [
            'Registrieren oder mit Google/Microsoft anmelden.',
            'Profil vervollständigen, besonders Name, Geburtsdatum und Pflichtdaten.',
            'Bei Minderjährigen unter 16 Jahren die Eltern-E-Mail angeben und die Zustimmung abwarten.',
            'Über die Sidebar zu Dashboard, Feed, Teams, Vereinen, Chat, Events, Dateien, Marketplace und Einstellungen wechseln.',
            'Benachrichtigungen regelmäßig prüfen, weil Einladungen, Zahlungen, Moderation und Mitgliedschaftshinweise dort erscheinen.',
        ]],
    ];

    $blocks[] = ['type' => 'h2', 'text' => 'Rollen und Bedienung'];

    foreach ($roleSections as $section) {
        appendSection($blocks, $section);
    }

    $blocks[] = ['type' => 'h2', 'text' => 'Funktionsbereiche'];

    foreach ($moduleSections as $section) {
        appendSection($blocks, $section);
    }

    $blocks[] = ['type' => 'h2', 'text' => 'Typische Abläufe'];

    foreach ($workflowSections as $section) {
        appendSection($blocks, $section);
    }

    $blocks[] = ['type' => 'h2', 'text' => 'Wichtige Systemregeln'];

    foreach ($ruleSections as $section) {
        appendSection($blocks, $section);
    }

    $blocks[] = ['type' => 'h2', 'text' => 'Prüfung und Qualitätssicherung'];
    $blocks[] = ['type' => 'p', 'text' => 'Für die Beta-Phase sollte jede Funktion mindestens auf Smartphone, Tablet und Laptop geprüft werden. Besonders wichtig sind Registrierung, Login, Profilvervollständigung, Elternzustimmung, Chat, Feed, Teams, Vereine, Mitgliederverwaltung, Zahlungen, Marketplace, Adminbereiche und Wartungsmodus.'];
    $blocks[] = ['type' => 'list', 'items' => [
        'Status in der Testmatrix markieren: Offen, Bestanden, Fehler, Nachtest.',
        'Fehler immer mit Rolle, Gerät, Browser, Route und Screenshot dokumentieren.',
        'Zahlungs-, Eltern- und Moderationsabläufe besonders sorgfältig testen.',
        'Bei neuen Funktionen dieses Handbuch und die Use-Case-Dokumentation aktualisieren.',
    ]];

    return $blocks;
}

function appendSection(array &$blocks, array $section): void
{
    $title = stripSectionNumber($section['title']);
    $blocks[] = ['type' => 'h3', 'text' => $title];
    $blocks[] = ['type' => 'p', 'text' => explanationFor($title)];

    if (count($section['items']) > 0) {
        $blocks[] = ['type' => 'p', 'text' => 'So wird dieser Bereich bedient:'];
        $blocks[] = ['type' => 'list', 'items' => array_values(array_unique($section['items']))];
    }

    if (count($section['routes']) > 0) {
        $blocks[] = ['type' => 'p', 'text' => 'Typische Seiten und technische Bereiche: ' . implode(', ', array_slice(array_unique($section['routes']), 0, 18)) . (count($section['routes']) > 18 ? ', ...' : '')];
    }
}

function explanationFor(string $title): string
{
    $lower = mb_strtolower($title);

    $texts = [
        'öffentlicher besucher' => 'Besucher können Airmius kennenlernen, Preise und öffentliche Inhalte ansehen und danach entscheiden, ob sie sich registrieren möchten.',
        'registrierter nutzer' => 'Registrierte Nutzer erhalten Zugriff auf die persönliche Plattformoberfläche und können Profil, Suche, Benachrichtigungen und Einstellungen nutzen.',
        'sportler' => 'Sportler pflegen ihr Profil, verbinden Sportarten und Fähigkeiten, nutzen Feed, Teams, Events, Chat und Marketplace.',
        'minderjähriger' => 'Minderjährige unter 16 Jahren dürfen soziale Funktionen erst nach Zustimmung eines Erziehungsberechtigten nutzen.',
        'eltern' => 'Eltern können Zustimmung erteilen, ablehnen oder später widerrufen und behalten dadurch Kontrolle über die Nutzung ihrer Kinder.',
        'trainer' => 'Trainer organisieren Teams, Trainings, Kommunikation und Inhalte für Sportler.',
        'verein' => 'Vereine verwalten Organisation, Teams, Mitglieder, Beiträge, Rechnungen, Zahlungen, Dateien und digitale Zusatzdienste.',
        'team' => 'Teams bündeln Mitglieder, Kommunikation, Termine und Rollen innerhalb eines Vereins oder einer Trainingsgruppe.',
        'sponsor' => 'Sponsoren und Werbepartner können Kampagnen vorbereiten und über Admin-Freigaben ausspielen lassen.',
        'marketplace-anbieter' => 'Anbieter verkaufen Kurse, Camps, Produkte oder Dienstleistungen über den Marketplace und erhalten Auszahlungen nach Prüfung.',
        'käufer' => 'Käufer können Produkte ansehen, bezahlen und bei Problemen Unterstützung anfordern.',
        'system admin' => 'System-Admins steuern Plattform, Nutzer, Rollen, Zahlungen, Inhalte, Abos, Moderation und Einstellungen.',
        'registrierung' => 'Dieser Bereich deckt Kontoanlage, Login, Social Login, Pflichtdaten, Minderjährigenschutz und Kontosicherheit ab.',
        'profil' => 'Das Profil ist die digitale Identität innerhalb von Airmius und verbindet persönliche Daten, Sportarten, Skills und Sichtbarkeit.',
        'feed' => 'Der Feed ist der soziale Bereich für Beiträge, Kommentare, Likes, hilfreiche Inhalte und Meldungen.',
        'chat' => 'Der Chat dient der direkten Kommunikation. Konversationen werden bewusst geöffnet, um Privatsphäre zu schützen.',
        'freunde' => 'Freunde und Follows steuern soziale Verbindungen und Sichtbarkeit zwischen Nutzern.',
        'mitgliederverwaltung' => 'Die Mitgliederverwaltung unterstützt Vereine bei Stammdaten, Mitgliedsnummern, Beiträgen, Import, Rechnungen und Zahlungen.',
        'abo' => 'Abo-Pläne steuern Preise, Limits und freigeschaltete Funktionen für Nutzer, Vereine und Partner.',
        'commerce' => 'Commerce bündelt Add-ons, Marketplace, Zahlungsarten, Bestellungen, Probleme und Auszahlungen.',
        'moderation' => 'Moderation schützt Nutzer durch automatische Prüfung, Meldungen, Verwarnungen und Sperren.',
        'scheduler' => 'Scheduler und Cron führen wiederkehrende Aufgaben aus, etwa Rechnungen, Erinnerungen und Statusprüfungen.',
    ];

    foreach ($texts as $needle => $text) {
        if (str_contains($lower, $needle)) {
            return $text;
        }
    }

    return 'Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.';
}

function writeMarkdown(string $path, array $blocks): void
{
    $lines = [];

    foreach ($blocks as $block) {
        if ($block['type'] === 'h1') {
            $lines[] = '# ' . $block['text'];
        } elseif ($block['type'] === 'h2') {
            $lines[] = '## ' . $block['text'];
        } elseif ($block['type'] === 'h3') {
            $lines[] = '### ' . $block['text'];
        } elseif ($block['type'] === 'p') {
            $lines[] = $block['text'];
        } elseif ($block['type'] === 'list') {
            foreach ($block['items'] as $item) {
                $lines[] = '- ' . $item;
            }
        }

        $lines[] = '';
    }

    file_put_contents($path, implode("\n", $lines));
}

function writeDocxParts(string $path, array $blocks): void
{
    if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
        throw new RuntimeException("Could not create DOCX parts directory: {$path}");
    }

    $files = [
        '[Content_Types].xml' => contentTypesXml(),
        '_rels/.rels' => relsXml(),
        'docProps/app.xml' => appXml(),
        'docProps/core.xml' => coreXml(),
        'word/document.xml' => documentXml($blocks),
        'word/styles.xml' => stylesXml(),
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

function documentXml(array $blocks): string
{
    $body = '';

    foreach ($blocks as $block) {
        if (in_array($block['type'], ['h1', 'h2', 'h3'], true)) {
            $body .= paragraphXml($block['text'], strtoupper($block['type']));
        } elseif ($block['type'] === 'p') {
            $body .= paragraphXml($block['text'], 'Normal');
        } elseif ($block['type'] === 'list') {
            foreach ($block['items'] as $item) {
                $body .= paragraphXml('• ' . $item, 'Normal');
            }
        }
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:body>'
        . $body
        . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>'
        . '</w:body></w:document>';
}

function paragraphXml(string $text, string $style): string
{
    $styleXml = $style !== 'Normal' ? '<w:pStyle w:val="' . $style . '"/>' : '';

    return '<w:p><w:pPr>' . $styleXml . '<w:spacing w:after="120"/></w:pPr><w:r><w:t xml:space="preserve">' . xmlEscape($text) . '</w:t></w:r></w:p>';
}

function writePdf(string $path, array $blocks): void
{
    $pages = paginatePdfLines($blocks);
    $objects = [];
    $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[] = '<< /Type /Pages /Kids [' . implode(' ', array_map(fn ($i) => (($i * 2) + 2) . ' 0 R', array_keys($pages))) . '] /Count ' . count($pages) . ' >>';
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

    foreach ($pages as $pageIndex => $lines) {
        $pageObjectNumber = count($objects) + 1;
        $contentObjectNumber = $pageObjectNumber + 1;
        $content = "q\n";

        foreach ($lines as $line) {
            $content .= 'BT /F1 ' . $line['size'] . ' Tf 50 ' . $line['y'] . ' Td (' . pdfEscape($line['text']) . ") Tj ET\n";
        }

        $content .= "Q\n";
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObjectNumber . ' 0 R >>';
        $objects[] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";
    }

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    file_put_contents($path, $pdf);
}

function paginatePdfLines(array $blocks): array
{
    $pages = [];
    $current = [];
    $y = 800;

    $addLine = function (string $text, int $size) use (&$pages, &$current, &$y): void {
        if ($y < 50) {
            $pages[] = $current;
            $current = [];
            $y = 800;
        }

        $current[] = ['text' => $text, 'size' => $size, 'y' => $y];
        $y -= $size + 6;
    };

    foreach ($blocks as $block) {
        if ($block['type'] === 'h1') {
            foreach (wrapText($block['text'], 42) as $line) {
                $addLine($line, 20);
            }
            $y -= 10;
        } elseif ($block['type'] === 'h2') {
            $y -= 6;
            foreach (wrapText($block['text'], 52) as $line) {
                $addLine($line, 15);
            }
        } elseif ($block['type'] === 'h3') {
            $y -= 4;
            foreach (wrapText($block['text'], 65) as $line) {
                $addLine($line, 12);
            }
        } elseif ($block['type'] === 'p') {
            foreach (wrapText($block['text'], 88) as $line) {
                $addLine($line, 10);
            }
            $y -= 4;
        } elseif ($block['type'] === 'list') {
            foreach ($block['items'] as $item) {
                foreach (wrapText('- ' . $item, 90) as $line) {
                    $addLine($line, 9);
                }
            }
            $y -= 4;
        }
    }

    if ($current !== []) {
        $pages[] = $current;
    }

    return $pages;
}

function wrapText(string $text, int $width): array
{
    $words = preg_split('/\s+/u', $text) ?: [];
    $lines = [];
    $line = '';

    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;

        if (mb_strlen($candidate) > $width && $line !== '') {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }

    if ($line !== '') {
        $lines[] = $line;
    }

    return $lines;
}

function pdfEscape(string $text): string
{
    $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);

    if ($encoded === false) {
        $encoded = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
    }

    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
}

function contentTypesXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
        . '</Types>';
}

function relsXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>';
}

function stylesXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:rPr><w:sz w:val="22"/></w:rPr></w:style>'
        . '<w:style w:type="paragraph" w:styleId="H1"><w:name w:val="Heading 1"/><w:rPr><w:b/><w:sz w:val="34"/></w:rPr></w:style>'
        . '<w:style w:type="paragraph" w:styleId="H2"><w:name w:val="Heading 2"/><w:rPr><w:b/><w:sz w:val="28"/></w:rPr></w:style>'
        . '<w:style w:type="paragraph" w:styleId="H3"><w:name w:val="Heading 3"/><w:rPr><w:b/><w:sz w:val="24"/></w:rPr></w:style>'
        . '</w:styles>';
}

function appXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
        . '<Application>Airmius</Application></Properties>';
}

function coreXml(): string
{
    $created = gmdate('Y-m-d\TH:i:s\Z');

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
        . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>Airmius Bedienhandbuch</dc:title><dc:creator>Airmius</dc:creator>'
        . '<cp:lastModifiedBy>Airmius</cp:lastModifiedBy>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created>'
        . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified>'
        . '</cp:coreProperties>';
}

function stripSectionNumber(string $title): string
{
    return trim(preg_replace('/^\d+(?:\.\d+)*\s+/u', '', $title) ?? $title);
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

function xmlEscape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}
