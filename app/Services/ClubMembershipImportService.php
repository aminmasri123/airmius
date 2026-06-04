<?php

namespace App\Services;

use App\Support\ClubMembershipInput;

class ClubMembershipImportService
{
    public function readRows(string $path, ?string $extension): array
    {
        return strtolower((string) $extension) === 'xlsx'
            ? $this->readXlsxRows($path)
            : $this->readCsvRows($path);
    }

    public function memberDataFromRow(array $row): ?array
    {
        $email = strtolower(trim((string) ($row['email'] ?? '')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return [
            'name' => trim((string) ($row['name'] ?? '')) ?: null,
            'email' => $email,
            'membership_status' => ClubMembershipInput::normalizeMembershipStatus($row['mitgliedschaft'] ?? $row['membership_status'] ?? 'active'),
            'member_number' => trim((string) ($row['mitgliedsnummer'] ?? $row['member_number'] ?? '')) ?: null,
            'athlete_license_number' => trim((string) ($row['lizenznummer'] ?? $row['athlete_license_number'] ?? '')) ?: null,
            'contribution_amount' => ClubMembershipInput::normalizeMoney($row['beitrag'] ?? $row['contribution_amount'] ?? null),
            'contribution_interval' => ClubMembershipInput::normalizeContributionInterval($row['intervall'] ?? $row['contribution_interval'] ?? 'none'),
            'contribution_next_invoice_on' => ClubMembershipInput::normalizeDate($row['nächsten_rechnung'] ?? $row['nächste_rechnung'] ?? $row['contribution_next_invoice_on'] ?? null),
            'sepa_iban' => ClubMembershipInput::normalizeIban($row['iban'] ?? $row['sepa_iban'] ?? null),
            'sepa_bic' => ClubMembershipInput::normalizeBic($row['bic'] ?? $row['sepa_bic'] ?? null),
            'sepa_mandate_reference' => trim((string) ($row['mandatsreferenz'] ?? $row['sepa_mandate_reference'] ?? '')) ?: null,
            'sepa_mandate_signed_on' => ClubMembershipInput::normalizeDate($row['mandatsdatum'] ?? $row['sepa_mandate_signed_on'] ?? null),
            'sepa_mandate_active' => ClubMembershipInput::normalizeBoolean($row['sepa_aktiv'] ?? $row['sepa_mandate_active'] ?? null),
            'joined_on' => ClubMembershipInput::normalizeDate($row['eintritt'] ?? $row['joined_on'] ?? null),
            'membership_ends_on' => ClubMembershipInput::normalizeDate($row['ende'] ?? $row['membership_ends_on'] ?? null),
            'membership_notes' => trim((string) ($row['notiz'] ?? $row['membership_notes'] ?? '')) ?: null,
        ];
    }

    public function buildTemplate(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'airmius-members-').'.xlsx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>
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
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Airmius Mitgliederimport</dc:title></cp:coreProperties>');
        $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Airmius</Application></Properties>');
        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Mitgliederimport" sheetId="1" r:id="rId1"/></sheets>
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
  <fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="16"/><color rgb="FF000000"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>
  <fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF111827"/><bgColor indexed="64"/></patternFill></fill></fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>
</styleSheet>
XML);

        $sheetRows = [
            ['Airmius Mitgliederimport'],
            ['Fuellen Sie ab Zeile 5 die Mitglieder aus. Pflichtfeld ist E-Mail. Mitgliedschaft: active, non_member, pending, paused, former. Intervall: none, monthly, quarterly, yearly, once. SEPA aktiv: ja/nein.'],
            [],
            ['Name', 'E-Mail', 'Mitgliedschaft', 'Mitgliedsnummer', 'Lizenznummer', 'Beitrag', 'Intervall', 'Naechste_Rechnung', 'IBAN', 'BIC', 'Mandatsreferenz', 'Mandatsdatum', 'SEPA_Aktiv', 'Eintritt', 'Ende', 'Notiz'],
            ['Max Mustermann', 'max@example.org', 'active', 'MV-1001', 'LIC-2026-001', '12,50', 'monthly', '2026-06-01', 'DE02120300000000202051', '', 'MANDAT-1001', '2026-05-02', 'ja', '2026-05-02', '2027-05-01', 'Beispielzeile entfernen'],
        ];

        $zip->addFromString('xl/worksheets/sheet1.xml', $this->buildSheetXml($sheetRows));
        $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/drawings/drawing1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <xdr:oneCellAnchor>
    <xdr:from><xdr:col>7</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>
    <xdr:ext cx="1600000" cy="520000"/>
    <xdr:pic>
      <xdr:nvPicPr><xdr:cNvPr id="2" name="Airmius Logo"/><xdr:cNvPicPr/></xdr:nvPicPr>
      <xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>
      <xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>
    </xdr:pic>
    <xdr:clientData/>
  </xdr:oneCellAnchor>
</xdr:wsDr>
XML);
        $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/airmius-logo.png"/>
</Relationships>
XML);

        $logoPath = public_path('img/logo/Logo-Airmius-Quervormat.png');
        if (is_file($logoPath)) {
            $zip->addFile($logoPath, 'xl/media/airmius-logo.png');
        } else {
            $zip->addFromString('xl/media/airmius-logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        }

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
                if (isset($string->t)) {
                    $sharedStrings[] = (string) $string->t;
                    continue;
                }

                $text = '';
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
            $candidate = array_map(fn ($value) => ClubMembershipInput::normalizeKey($value), $values);

            if (in_array('email', $candidate, true) || in_array('e_mail', $candidate, true)) {
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
                    $row[$header === 'e_mail' ? 'email' : $header] = $values[$index] ?? null;
                }
            }

            if (array_filter($row, fn ($value) => filled($value))) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function buildSheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="2" width="30" customWidth="1"/><col min="3" max="16" width="18" customWidth="1"/></cols>';
        $xml .= '<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $height = $number === 1 ? ' ht="54" customHeight="1"' : '';
            $xml .= '<row r="'.$number.'"'.$height.'>';

            foreach ($row as $columnIndex => $value) {
                $cell = $this->excelColumnName($columnIndex).$number;
                $style = $number === 1 ? 1 : ($number === 4 ? 2 : 0);
                $xml .= '<c r="'.$cell.'" t="inlineStr" s="'.$style.'"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData><mergeCells count="2"><mergeCell ref="A1:P1"/><mergeCell ref="A2:P2"/></mergeCells><dataValidations count="2"><dataValidation type="list" allowBlank="1" showDropDown="0" sqref="G5:G1000"><formula1>"none,monthly,quarterly,yearly,once"</formula1></dataValidation><dataValidation type="list" allowBlank="1" showDropDown="0" sqref="M5:M1000"><formula1>"ja,nein"</formula1></dataValidation></dataValidations><drawing r:id="rId1"/></worksheet>';

        return $xml;
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

