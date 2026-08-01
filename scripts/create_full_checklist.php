<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sourcePath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'Airmius_Klickanleitung_Web_App_Rollen.md';
$targetPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'Airmius_Vollstaendige_Checkliste_Web_und_App.docx';

if (! is_file($sourcePath)) {
    fwrite(STDERR, "Source file not found: {$sourcePath}\n");
    exit(1);
}

$blocks = [
    ['type' => 'h1', 'text' => 'Airmius vollständige Prüf-Checkliste'],
    ['type' => 'p', 'text' => 'Stand: 01.08.2026. Diese Datei wird Schritt für Schritt abgearbeitet. Einen Punkt erst abhaken, wenn das Ergebnis auf dem angegebenen Gerät wirklich sichtbar und gespeichert ist. Bei fehlenden Menüs immer Rolle, Berechtigung, Verein, Team und Abo-Plan notieren.'],
    ['type' => 'h2', 'text' => 'Testergebnis eintragen'],
    ['type' => 'list', 'text' => '☐ Testkonto, Rolle, Verein und Team notiert'],
    ['type' => 'list', 'text' => '☐ Gerät und Browser/App-Version notiert'],
    ['type' => 'list', 'text' => '☐ Erwartetes und tatsächliches Ergebnis dokumentiert'],
    ['type' => 'list', 'text' => '☐ Screenshot oder Bildschirmaufnahme bei Fehlern gespeichert'],
];

$source = (string) file_get_contents($sourcePath);
foreach (parseChecklistMarkdown($source) as $block) {
    $blocks[] = $block;
}

$docx = new ZipArchive();
if ($docx->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException("Could not create DOCX: {$targetPath}");
}

$docx->addFromString('[Content_Types].xml', contentTypesXml());
$docx->addFromString('_rels/.rels', relsXml());
$docx->addFromString('docProps/app.xml', appXml());
$docx->addFromString('docProps/core.xml', coreXml());
$docx->addFromString('word/document.xml', documentXml($blocks));
$docx->addFromString('word/styles.xml', stylesXml());
$docx->close();

echo "DOCX: {$targetPath}\n";
echo 'Blocks: ' . count($blocks) . "\n";

function parseChecklistMarkdown(string $markdown): array
{
    $blocks = [];

    foreach (preg_split('/\R/u', $markdown) ?: [] as $rawLine) {
        $line = trim($rawLine);
        if ($line === '') continue;
        if (preg_match('/^#\s+(.+)$/u', $line, $match)) {
            $blocks[] = ['type' => 'h1', 'text' => cleanInline($match[1])];
            continue;
        }
        if (preg_match('/^##\s+(.+)$/u', $line, $match)) {
            $blocks[] = ['type' => 'h2', 'text' => cleanInline($match[1])];
            continue;
        }
        if (preg_match('/^###\s+(.+)$/u', $line, $match)) {
            $blocks[] = ['type' => 'h3', 'text' => cleanInline($match[1])];
            continue;
        }
        if (preg_match('/^[-*]\s+\[\s*\]\s*(.+)$/u', $line, $match)) {
            $blocks[] = ['type' => 'list', 'text' => '☐ ' . cleanInline($match[1])];
            continue;
        }
        if (preg_match('/^[-*]\s+(.+)$/u', $line, $match)) {
            $blocks[] = ['type' => 'list', 'text' => '☐ ' . cleanInline($match[1])];
            continue;
        }
        if (preg_match('/^\d+\.\s+(.+)$/u', $line, $match)) {
            $blocks[] = ['type' => 'list', 'text' => '☐ ' . cleanInline($match[1])];
            continue;
        }
        $blocks[] = ['type' => 'p', 'text' => cleanInline($line)];
    }

    return $blocks;
}

function cleanInline(string $text): string
{
    $text = preg_replace('/\*\*(.+?)\*\*/u', '$1', $text) ?? $text;
    $text = preg_replace('/`(.+?)`/u', '$1', $text) ?? $text;
    return trim($text);
}

function documentXml(array $blocks): string
{
    $body = '';
    foreach ($blocks as $block) {
        $style = match ($block['type']) {
            'h1' => 'Title',
            'h2' => 'Heading1',
            'h3' => 'Heading2',
            'list' => 'ListParagraph',
            default => 'Normal',
        };
        $body .= paragraphXml($block['text'], $style);
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
        . $body
        . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>'
        . '</w:body></w:document>';
}

function paragraphXml(string $text, string $style): string
{
    return '<w:p><w:pPr><w:pStyle w:val="' . $style . '"/><w:spacing w:after="100"/></w:pPr>'
        . '<w:r><w:t xml:space="preserve">' . xmlEscape($text) . '</w:t></w:r></w:p>';
}

function contentTypesXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>';
}

function relsXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/package/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
}

function stylesXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:rPr><w:sz w:val="21"/><w:color w:val="202B3C"/></w:rPr></w:style>'
        . '<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:pPr><w:spacing w:after="260"/></w:pPr><w:rPr><w:b/><w:sz w:val="38"/><w:color w:val="0B6E99"/></w:rPr></w:style>'
        . '<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="Heading 1"/><w:pPr><w:keepNext/><w:spacing w:before="260" w:after="130"/></w:pPr><w:rPr><w:b/><w:sz w:val="29"/><w:color w:val="0B6E99"/></w:rPr></w:style>'
        . '<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="Heading 2"/><w:pPr><w:keepNext/><w:spacing w:before="180" w:after="100"/></w:pPr><w:rPr><w:b/><w:sz w:val="24"/><w:color w:val="174A63"/></w:rPr></w:style>'
        . '<w:style w:type="paragraph" w:styleId="ListParagraph"><w:name w:val="List Paragraph"/><w:pPr><w:ind w:left="420" w:hanging="210"/><w:spacing w:after="65"/></w:pPr><w:rPr><w:sz w:val="21"/></w:rPr></w:style></w:styles>';
}

function appXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Airmius</Application><AppVersion>1.0</AppVersion></Properties>';
}

function coreXml(): string
{
    $timestamp = gmdate('Y-m-d\\TH:i:s\\Z');
    return '<?xml version="1.0" encoding="UTF-8"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>Airmius vollständige Prüf-Checkliste Web und App</dc:title><dc:creator>Airmius</dc:creator><cp:lastModifiedBy>Airmius</cp:lastModifiedBy>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:modified></cp:coreProperties>';
}

function xmlEscape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}
