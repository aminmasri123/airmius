<?php

namespace App\Services;

use App\Models\MarketplaceProduct;
use App\Models\User;
use App\Support\MarketplaceProductInput;

class MarketplaceProductImportService
{
    public function __construct(private MarketplacePricingService $pricing) {}

    public function readRows(string $path, ?string $extension): array
    {
        return strtolower((string) $extension) === 'xlsx'
            ? $this->readXlsxRows($path)
            : $this->readCsvRows($path);
    }

    public function productDataFromRow(array $row, User $user, callable $clubResolver, int $line, array &$errors): ?array
    {
        $title = trim((string) ($row['titel'] ?? $row['title'] ?? ''));
        $priceCents = $this->moneyToCents($row['preis_eur'] ?? $row['preis'] ?? $row['price_eur'] ?? null);
        $offerType = $this->normalizeOfferType($row['angebotstyp'] ?? $row['offer_type'] ?? 'physical_product');
        $productType = $this->normalizeProductType($row['produkt_typ'] ?? $row['product_type'] ?? 'single');
        $category = $this->categoryForOffer($offerType, $row['kategorie'] ?? $row['category'] ?? null);
        $stockQuantity = trim((string) ($row['lagerbestand'] ?? $row['bestand'] ?? $row['stock_quantity'] ?? ''));
        $imageUrl = trim((string) ($row['hauptbild_url'] ?? $row['image_url'] ?? ''));

        if ($title === '') {
            $errors[] = "Zeile {$line}: Titel fehlt.";
        }

        if ($priceCents === null) {
            $errors[] = "Zeile {$line}: Preis fehlt oder ist ungültig.";
        }

        if ($imageUrl !== '' && ! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $errors[] = "Zeile {$line}: Hauptbild-URL ist ungültig.";
        }

        if ($offerType === 'physical_product' && $productType !== 'digital' && ((int) $stockQuantity) < 1) {
            $errors[] = "Zeile {$line}: Lagerbestand muss mindestens 1 sein.";
        }

        if ($offerType === 'service') {
            $errors[] = "Zeile {$line}: Dienstleistungen werden aktuell nur intern von Airmius angelegt.";
        }

        if ($title === '' || $priceCents === null || $offerType === 'service' || ($imageUrl !== '' && ! filter_var($imageUrl, FILTER_VALIDATE_URL))) {
            return null;
        }

        $club = $clubResolver($row['verein_id'] ?? $row['club_id'] ?? null);
        $galleryImages = collect(preg_split('/\r\n|\r|\n|;|\|/', (string) ($row['galerie_bild_urls'] ?? $row['gallery_image_urls'] ?? '')))
            ->map(fn (string $url) => trim($url))
            ->filter(fn (string $url) => $url !== '' && filter_var($url, FILTER_VALIDATE_URL))
            ->prepend($imageUrl ?: null)
            ->filter()
            ->unique()
            ->take(12)
            ->values()
            ->all();

        $isDigital = $productType === 'digital' || in_array($offerType, ['online_course', 'training_plan', 'service'], true);
        $managesStock = ! $isDigital && $offerType === 'physical_product';
        $taxClass = trim((string) ($row['steuerklasse'] ?? $row['tax_class'] ?? 'standard')) ?: 'standard';

        if (! in_array($taxClass, ['standard', 'reduced', 'zero'], true)) {
            $taxClass = 'standard';
        }

        return [
            'user_id' => $user->id,
            'club_id' => $club?->id,
            'title' => $title,
            'description' => trim((string) ($row['beschreibung'] ?? $row['description'] ?? '')),
            'product_attributes' => MarketplaceProductInput::attributesFromText((string) ($row['merkmale'] ?? $row['attributes_text'] ?? '')),
            'attribute_options' => [],
            'variants' => [],
            'image_url' => $imageUrl ?: ($galleryImages[0] ?? null),
            'gallery_images' => $galleryImages,
            'category' => $category,
            'offer_type' => $offerType,
            'product_type' => $isDigital ? 'digital' : $productType,
            'sku' => trim((string) ($row['artikelnummer'] ?? $row['sku'] ?? '')) ?: null,
            'is_shippable' => $isDigital ? false : $this->boolFromImport($row['versandpflichtig'] ?? $row['is_shippable'] ?? true),
            'manages_stock' => $managesStock,
            'stock_quantity' => $managesStock ? max(1, (int) $stockQuantity) : null,
            'tax_class' => $taxClass,
            'return_policy_type' => $isDigital ? 'digital' : 'standard',
            'return_window_days' => $isDigital ? 0 : 14,
            'digital_delivery_note' => trim((string) ($row['lieferinfo_digital'] ?? $row['digital_delivery_note'] ?? '')) ?: null,
            'course_outline' => MarketplaceProductInput::linesFromText((string) ($row['kursinhalt'] ?? $row['course_outline_text'] ?? ''), 20),
            'learning_goals' => MarketplaceProductInput::linesFromText((string) ($row['lernziele'] ?? $row['learning_goals_text'] ?? ''), 12),
            'coaching_enabled' => $offerType === 'training_plan' ? $this->boolFromImport($row['feedback_aktiv'] ?? true) : $this->boolFromImport($row['feedback_aktiv'] ?? false),
            'coach_feedback_instructions' => trim((string) ($row['feedback_hinweise'] ?? $row['coach_feedback_instructions'] ?? '')) ?: null,
            'price_cents' => $priceCents,
            'currency' => 'EUR',
            'status' => 'published',
            'moderation_status' => 'approved',
            'commission_percent' => $this->pricing->commissionPercentFor((new MarketplaceProduct)->forceFill(['category' => $category])),
            'payout_status' => 'pending_sales',
        ];
    }

    public function buildTemplate(array $categoryCommissions): string
    {
        $path = tempnam(sys_get_temp_dir(), 'airmius-products-').'.xlsx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML);
        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML);
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Airmius Produktimport</dc:title></cp:coreProperties>');
        $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Airmius</Application></Properties>');
        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Produkte" sheetId="1" r:id="rId1"/></sheets>
  <calcPr calcId="191029" fullCalcOnLoad="1"/>
</workbook>
XML);
        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/styles.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="16"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>
  <fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF111827"/><bgColor indexed="64"/></patternFill></fill></fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>
</styleSheet>
XML);

        $zip->addFromString('xl/worksheets/sheet1.xml', $this->buildSheetXml($categoryCommissions));
        $zip->close();

        return $path;
    }

    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }

        $firstLine = fgets($handle) ?: '';
        rewind($handle);
        $delimiter = str_contains($firstLine, ';') ? ';' : (str_contains($firstLine, "\t") ? "\t" : ',');
        $tableRows = [];

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($values !== [null] && $values !== false) {
                $tableRows[] = $values;
            }
        }

        fclose($handle);

        return $this->normalizeTableRows($tableRows);
    }

    private function readXlsxRows(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $xml = simplexml_load_string($sharedStringsXml);
            foreach ($xml->si ?? [] as $string) {
                $text = isset($string->t) ? (string) $string->t : '';
                foreach ($string->r ?? [] as $run) {
                    $text .= (string) $run->t;
                }
                $sharedStrings[] = $text;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $xml = simplexml_load_string($sheetXml);
        $tableRows = [];

        foreach ($xml->sheetData->row ?? [] as $row) {
            $values = [];
            foreach ($row->c ?? [] as $cell) {
                $reference = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $reference);
                $index = $this->excelColumnIndex($column);
                $type = (string) $cell['t'];
                $value = (string) ($cell->v ?? '');

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                }

                $values[$index] = $value;
            }

            if ($values !== []) {
                ksort($values);
                $tableRows[] = $values;
            }
        }

        return $this->normalizeTableRows($tableRows);
    }

    private function normalizeTableRows(array $tableRows): array
    {
        $headerIndex = null;
        $headers = [];

        foreach ($tableRows as $index => $values) {
            $candidate = array_map(fn ($value) => $this->normalizeKey($value), $values);
            if (in_array('titel', $candidate, true) || in_array('title', $candidate, true)) {
                $headerIndex = $index;
                $headers = $candidate;
                break;
            }
        }

        if ($headerIndex === null) {
            return [];
        }

        $rows = [];
        foreach (array_slice($tableRows, $headerIndex + 1) as $values) {
            $row = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = $values[$index] ?? null;
                }
            }

            if (array_filter($row, fn ($value) => filled($value))) {
                $rows[] = $row;
            }
        }

        return array_slice($rows, 0, 100);
    }

    private function buildSheetXml(array $categoryCommissions): string
    {
        $headers = [
            'Titel', 'Angebotstyp', 'Kategorie', 'Produkttyp', 'Preis_EUR',
            'Airmius_Provision_%', 'Airmius_Provision_EUR', 'Auszahlung_EUR',
            'Lagerbestand', 'Artikelnummer', 'Hauptbild_URL', 'Galerie_Bild_URLs',
            'Steuerklasse', 'Versandpflichtig', 'Beschreibung', 'Merkmale',
            'Kursinhalt', 'Lernziele', 'Feedback_Aktiv', 'Feedback_Hinweise',
        ];
        $rows = [
            ['Airmius Marketplace Produktimport'],
            ['Pflichtfelder: Titel, Angebotstyp, Preis_EUR und bei physischen Produkten Lagerbestand min. 1. Bilder bitte als öffentliche URLs eintragen.'],
            ['Provision: Die Spalten F-H sind Formeln. Wenn Preis oder Kategorie geändert werden, aktualisieren sich Provision und Auszahlung in Excel. Beim Import rechnet Airmius die Provision serverseitig erneut mit den aktuellen Admin-Einstellungen.'],
            $headers,
            [
                'Beispiel Trainingsball', 'physical_product', 'product', 'single', '29,99',
                ['formula' => $this->commissionFormula(5, $categoryCommissions)],
                ['formula' => 'IF(E5="","",E5*F5/100)'],
                ['formula' => 'IF(E5="","",E5-G5)'],
                '10', 'BALL-1001', 'https://example.com/ball.jpg', 'https://example.com/ball-side.jpg; https://example.com/ball-box.jpg',
                'standard', 'ja', 'Robuster Trainingsball für Vereinstraining.', 'Farbe: Weiß | Größe: 5',
                '', '', 'nein', '',
            ],
        ];

        for ($row = 6; $row <= 104; $row++) {
            $rows[] = array_replace(array_fill(0, count($headers), ''), [
                5 => ['formula' => $this->commissionFormula($row, $categoryCommissions)],
                6 => ['formula' => 'IF(E'.$row.'="","",E'.$row.'*F'.$row.'/100)'],
                7 => ['formula' => 'IF(E'.$row.'="","",E'.$row.'-G'.$row.')'],
            ]);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<cols>';
        foreach ([28, 20, 16, 16, 14, 18, 22, 18, 16, 18, 34, 42, 16, 18, 42, 32, 36, 36, 16, 34] as $index => $width) {
            $col = $index + 1;
            $xml .= '<col min="'.$col.'" max="'.$col.'" width="'.$width.'" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $height = $number === 1 ? ' ht="28" customHeight="1"' : '';
            $xml .= '<row r="'.$number.'"'.$height.'>';

            foreach ($row as $columnIndex => $value) {
                $style = $number === 1 ? 1 : ($number === 4 ? 2 : 0);
                $xml .= $this->cellXml($columnIndex, $number, $value, $style);
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData>';
        $xml .= '<mergeCells count="3"><mergeCell ref="A1:T1"/><mergeCell ref="A2:T2"/><mergeCell ref="A3:T3"/></mergeCells>';
        $xml .= '<dataValidations count="6">';
        $xml .= '<dataValidation type="list" allowBlank="1" showDropDown="0" sqref="B5:B104"><formula1>"physical_product,online_course,training_plan,camp,service"</formula1></dataValidation>';
        $xml .= '<dataValidation type="list" allowBlank="1" showDropDown="0" sqref="C5:C104"><formula1>"product,course,camp,service"</formula1></dataValidation>';
        $xml .= '<dataValidation type="list" allowBlank="1" showDropDown="0" sqref="D5:D104"><formula1>"single,variable,digital"</formula1></dataValidation>';
        $xml .= '<dataValidation type="list" allowBlank="1" showDropDown="0" sqref="M5:M104"><formula1>"standard,reduced,zero"</formula1></dataValidation>';
        $xml .= '<dataValidation type="list" allowBlank="1" showDropDown="0" sqref="N5:N104"><formula1>"ja,nein"</formula1></dataValidation>';
        $xml .= '<dataValidation type="list" allowBlank="1" showDropDown="0" sqref="S5:S104"><formula1>"ja,nein"</formula1></dataValidation>';
        $xml .= '</dataValidations></worksheet>';

        return $xml;
    }

    private function commissionFormula(int $row, array $categoryCommissions): string
    {
        $commissions = collect($categoryCommissions)
            ->mapWithKeys(fn (array $commission) => [$commission['category'] => (int) $commission['commission_percent']])
            ->all();
        $default = (int) ($commissions['product'] ?? 10);
        $formula = (string) $default;

        foreach (array_reverse($commissions) as $category => $percent) {
            $formula = 'IF(C'.$row.'="'.$category.'",'.$percent.','.$formula.')';
        }

        return $formula;
    }

    private function cellXml(int $columnIndex, int $rowNumber, mixed $value, int $style = 0): string
    {
        $cell = $this->excelColumnName($columnIndex).$rowNumber;

        if (is_array($value) && isset($value['formula'])) {
            return '<c r="'.$cell.'" s="'.$style.'"><f>'.htmlspecialchars((string) $value['formula'], ENT_XML1).'</f><v></v></c>';
        }

        return '<c r="'.$cell.'" t="inlineStr" s="'.$style.'"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
    }

    private function normalizeKey(mixed $value): string
    {
        $key = strtolower(trim((string) $value));
        $key = strtr($key, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue',
            ' ' => '_', '-' => '_', '/' => '_',
        ]);

        return preg_replace('/[^a-z0-9_]/', '', $key) ?: '';
    }

    private function moneyToCents(mixed $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^0-9,.\-]/', '', $value);
        if ($value === '' || $value === '-') {
            return null;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');
        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (int) round(((float) $value) * 100) : null;
    }

    private function normalizeOfferType(mixed $value): string
    {
        $value = $this->normalizeKey($value);
        $aliases = [
            'produkt' => 'physical_product',
            'product' => 'physical_product',
            'equipment' => 'physical_product',
            'kurs' => 'online_course',
            'course' => 'online_course',
            'elearning' => 'online_course',
            'onlinekurs' => 'online_course',
            'trainingsplan' => 'training_plan',
            'trainingplan' => 'training_plan',
            'workshop' => 'camp',
            'beratung' => 'service',
            'analyse' => 'service',
        ];

        return in_array($value, ['physical_product', 'online_course', 'training_plan', 'camp', 'service'], true)
            ? $value
            : ($aliases[$value] ?? 'physical_product');
    }

    private function normalizeProductType(mixed $value): string
    {
        $value = $this->normalizeKey($value);
        $aliases = [
            'einfach' => 'single',
            'einfaches_produkt' => 'single',
            'variabel' => 'variable',
            'variables_produkt' => 'variable',
            'digitales_produkt' => 'digital',
        ];

        return in_array($value, ['single', 'variable', 'digital'], true) ? $value : ($aliases[$value] ?? 'single');
    }

    private function categoryForOffer(string $offerType, mixed $category): string
    {
        $category = $this->normalizeKey($category);
        $aliases = [
            'produkt' => 'product',
            'produkte' => 'product',
            'kurs' => 'course',
            'kurse' => 'course',
            'elearning' => 'course',
            'camp' => 'camp',
            'camps' => 'camp',
            'service' => 'service',
            'services' => 'service',
        ];
        $category = in_array($category, ['product', 'course', 'camp', 'service'], true) ? $category : ($aliases[$category] ?? '');

        if ($category !== '') {
            return $category;
        }

        return match ($offerType) {
            'online_course', 'training_plan' => 'course',
            'camp' => 'camp',
            'service' => 'service',
            default => 'product',
        };
    }

    private function boolFromImport(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'ja', 'yes', 'true', 'aktiv', 'active'], true);
    }

    private function excelColumnIndex(string $column): int
    {
        $index = 0;
        foreach (str_split($column) as $char) {
            $index = ($index * 26) + (ord(strtoupper($char)) - 64);
        }

        return max(0, $index - 1);
    }

    private function excelColumnName(int $index): string
    {
        $name = '';
        $index++;

        while ($index > 0) {
            $modulo = ($index - 1) % 26;
            $name = chr(65 + $modulo).$name;
            $index = intdiv($index - $modulo, 26);
        }

        return $name;
    }
}
