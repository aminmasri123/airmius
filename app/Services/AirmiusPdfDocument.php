<?php

namespace App\Services;

class AirmiusPdfDocument
{
    public const PAGE_WIDTH = 595;
    public const PAGE_HEIGHT = 842;

    public const NAVY = [15, 23, 42];
    public const BLUE = [37, 99, 235];
    public const ORANGE = [249, 115, 22];
    public const SKY = [239, 246, 255];
    public const SLATE = [71, 85, 105];
    public const MUTED = [100, 116, 139];
    public const BORDER = [226, 232, 240];
    public const SURFACE = [248, 250, 252];
    public const SURFACE_MUTED = [242, 247, 253];

    private string $content = '';

    public function header(
        string $title,
        string $reference,
        string $subtitle = 'Sport. Vereine. Wachstum.',
        ?string $brandName = null,
        string $documentLabel = 'Rechnungsnummer',
        string $documentType = 'invoice'
    ): self
    {
        $brand = trim((string) ($brandName ?: 'Airmius'));
        $brandBadge = $this->brandBadge($brand);
        $subtitleText = trim($subtitle) === '' ? ' ' : $subtitle;
        $docAccent = $documentType === 'credit_note' ? self::ORANGE : self::BLUE;
        $docTypeLabel = $documentType === 'credit_note' ? 'GUTSCHRIFT' : 'RECHNUNG';
        $subtitleColor = $documentType === 'credit_note' ? self::ORANGE : self::SLATE;

        $this->fillColor(...self::NAVY)->rect(0, 700, self::PAGE_WIDTH, 142, true);
        $this->fillColor(...self::BLUE)->rect(0, 824, self::PAGE_WIDTH, 18, true);
        $this->fillColor(...self::ORANGE)->rect(0, 824, 190, 6, true);
        $this->fillColor(...self::SURFACE)->rect(48, 748, 46, 50, true);
        $this->strokeColor(...self::BLUE)->rect(48, 748, 46, 50);
        $this->text($brandBadge, 57, 778, 20, true, self::NAVY);

        $this->text($brand, 108, 782, 28, true, [255, 255, 255], 52);
        $this->text($subtitleText, 108, 764, 8, false, $subtitleColor, 58);
        $this->strokeColor(...self::BORDER)
            ->line(108, 756, 356, 756);
        $this->fillColor(...$docAccent)->rect(396, 772, 138, 40, true);
        $this->fillColor(...self::SURFACE)->rect(397, 773, 136, 38, true);
        $this->strokeColor(...self::BORDER)->rect(396, 772, 138, 40);
        $this->text($title, 401, 798, 22, true, self::NAVY);
        $this->text($docTypeLabel, 401, 810, 6, true, $docAccent);
        $this->strokeColor(...self::BORDER)
            ->line(401, 770, 529, 770);
        $this->text($documentLabel, 401, 782, 6, true, self::MUTED);
        $this->text($reference, 476, 782, 8, true, $docAccent, 49);

        return $this;
    }

    public function card(float $x, float $y, float $w, float $h, string $label, string $value, bool $accent = false): self
    {
        $cardBackground = $accent ? self::SKY : [255, 255, 255];
        $labelColor = $accent ? self::BLUE : self::MUTED;
        $valueColor = $accent ? self::NAVY : self::NAVY;
        $stripeColor = $accent ? self::BLUE : self::BORDER;
        $this->fillColor(...$cardBackground)
            ->rect($x, $y, $w, $h, true)
            ->strokeColor(...self::BORDER)
            ->rect($x, $y, $w, $h)
            ->fillColor(...$stripeColor)
            ->rect($x + 6, $y + $h - 10, $w - 12, 3, true)
            ->text($label, $x + 14, $y + 32, 8, true, $labelColor)
            ->text($value, $x + 14, $y + 14, 12, true, $valueColor);

        return $this;
    }

    public function sectionTitle(string $title, float $x, float $y, float $lineWidth = 150): self
    {
        $this->text($title, $x, $y, 11, true, self::BLUE)
            ->strokeColor(...self::BORDER)
            ->line($x, $y - 8, $x + $lineWidth, $y - 8)
            ->fillColor(...self::SURFACE_MUTED)
            ->rect($x - 2, $y - 9, 4, 2, true);

        return $this;
    }

    public function labelValue(string $label, string $value, float $x, float $y, float $labelWidth = 88, ?int $maxChars = 230): self
    {
        $this->strokeColor(...self::BORDER)
            ->line($x, $y - 3, $x + 258, $y - 3)
            ->text($label, $x, $y, 8, true, self::MUTED)
            ->text($value, $x + $labelWidth, $y, 9, false, self::NAVY, $maxChars);

        return $this;
    }

    public function statusPill(string $label, string $status, float $x, float $y, float $width = 116): self
    {
        $color = match ($status) {
            'paid' => [22, 163, 74],
            'open', 'awaiting_transfer' => self::ORANGE,
            'overdue' => [220, 38, 38],
            default => self::MUTED,
        };

        $bg = match ($status) {
            'paid' => [236, 253, 245],
            'open', 'awaiting_transfer' => [255, 247, 237],
            'overdue' => [254, 226, 226],
            default => [241, 245, 249],
        };

        $this->fillColor(...$color)
            ->rect($x + 1, $y + 2, $width - 2, 18, true)
            ->fillColor(...self::NAVY)
            ->rect($x, $y, $width, 22, false)
            ->fillColor(...$bg)
            ->rect($x + 2, $y + 2, $width - 4, 18, true)
            ->strokeColor(...self::BORDER)
            ->rect($x, $y, $width, 22, false)
            ->text($label, $x + 12, $y + 7, 9, true, $color, 32);

        return $this;
    }

    public function invoiceTableHeader(float $x, float $y, float $width): self
    {
        $this->fillColor(...self::SURFACE_MUTED)
            ->rect($x, $y, $width, 32, true)
            ->text('Beschreibung', $x + 16, $y + 11, 10, true, [51, 65, 85])
            ->text('Zeitraum', $x + 282, $y + 11, 10, true, [51, 65, 85])
            ->text('Summe', $x + 440, $y + 11, 10, true, [51, 65, 85])
            ->strokeColor(...self::BORDER)
            ->line($x + 270, $y + 2, $x + 270, $y + 28)
            ->line($x + 426, $y + 2, $x + 426, $y + 28)
            ->line($x, $y, $x + $width, $y);

        return $this;
    }

    public function legalFooter(array $profile, string $message = 'Danke, dass du Airmius nutzt.'): self
    {
        $this->fillColor(...self::SURFACE_MUTED)->rect(0, 0, self::PAGE_WIDTH, 96, true);
        $this->fillColor(...self::NAVY)->rect(0, 84, self::PAGE_WIDTH, 12, true);
        $this->fillColor(...self::BLUE)->rect(48, 82, 120, 3, true);
        $this->fillColor(...self::MUTED)->rect(0, 0, self::PAGE_WIDTH, 1, true);
        $this->text($message, 48, 64, 9, true, self::NAVY, 80);
        $this->text('Diese Rechnung wurde automatisch erstellt und ist ohne Unterschrift gueltig.', 48, 50, 7, false, self::MUTED, 100);

        $issuer = $this->issuerLine($profile);
        $tax = $this->taxLine($profile);
        $registry = $this->registryLine($profile);

        $this->text($issuer, 48, 31, 7, false, self::MUTED, 125);
        $this->text($tax, 48, 19, 7, false, self::MUTED, 125);
        $this->text($registry, 48, 7, 7, false, self::MUTED, 125);
        $this->text($profile['website'] ?? 'airmius.com', 468, 64, 9, true, self::BLUE, 24);

        return $this;
    }

    public function issuerBlock(array $profile, float $x, float $y): self
    {
        $this->sectionTitle('Rechnungsaussteller', $x, $y);
        $this->text($profile['company_name'] ?? 'Airmius', $x, $y - 23, 11, true, self::NAVY, 55);
        $this->text($profile['legal_name'] ?? '', $x, $y - 39, 8, false, self::SLATE, 65);
        $this->text(($profile['street'] ?? '').', '.($profile['postal_code'] ?? '').' '.($profile['city'] ?? ''), $x, $y - 54, 8, false, self::SLATE, 75);
        $this->text($profile['country'] ?? '', $x, $y - 69, 8, false, self::SLATE, 75);
        $this->text(($profile['email'] ?? '').' | '.($profile['website'] ?? ''), $x, $y - 84, 8, false, self::SLATE, 75);

        return $this;
    }

    public function text(string $text, float $x, float $y, int $size = 10, bool $bold = false, array $rgb = self::NAVY, ?int $maxChars = null): self
    {
        if ($maxChars !== null && strlen($text) > $maxChars) {
            $text = rtrim(substr($text, 0, max(0, $maxChars - 3))).'...';
        }

        $this->content .= sprintf(
            "BT\n%s %s %s rg\n/%s %d Tf\n%s %s Td\n(%s) Tj\nET\n",
            $this->num($rgb[0] / 255),
            $this->num($rgb[1] / 255),
            $this->num($rgb[2] / 255),
            $bold ? 'F2' : 'F1',
            $size,
            $this->num($x),
            $this->num($y),
            $this->escape($text),
        );

        return $this;
    }

    public function rect(float $x, float $y, float $w, float $h, bool $fill = false): self
    {
        $this->content .= sprintf("%s %s %s %s re %s\n", $this->num($x), $this->num($y), $this->num($w), $this->num($h), $fill ? 'f' : 'S');

        return $this;
    }

    public function line(float $x1, float $y1, float $x2, float $y2): self
    {
        $this->content .= sprintf("%s %s m %s %s l S\n", $this->num($x1), $this->num($y1), $this->num($x2), $this->num($y2));

        return $this;
    }

    public function fillColor(int $r, int $g, int $b): self
    {
        $this->content .= $this->num($r / 255).' '.$this->num($g / 255).' '.$this->num($b / 255)." rg\n";

        return $this;
    }

    public function strokeColor(int $r, int $g, int $b): self
    {
        $this->content .= $this->num($r / 255).' '.$this->num($g / 255).' '.$this->num($b / 255)." RG\n";

        return $this;
    }

    public function money(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2, ',', '.').' '.$currency;
    }

    private function issuerLine(array $profile): string
    {
        return trim(($profile['company_name'] ?? 'Airmius').' | '.($profile['street'] ?? '').' | '.($profile['postal_code'] ?? '').' '.($profile['city'] ?? '').' | '.($profile['country'] ?? ''));
    }

    private function taxLine(array $profile): string
    {
        $parts = [
            'Steuernr.: '.($profile['tax_number'] ?? ''),
            'USt-IdNr.: '.($profile['vat_id'] ?? ''),
        ];

        return implode(' | ', $parts);
    }

    private function registryLine(array $profile): string
    {
        $parts = [
            'Registergericht: '.($profile['court'] ?? ''),
            'Registernr.: '.($profile['registration_number'] ?? ''),
            'Vertreten durch: '.($profile['managing_director'] ?? ''),
        ];

        return implode(' | ', $parts);
    }

    private function brandBadge(string $brandName): string
    {
        $words = preg_split('/\s+/', trim($brandName));
        $words = array_values(array_filter($words, static fn ($word) => $word !== ''));

        if ($words === []) {
            return 'AI';
        }

        if (count($words) === 1) {
            return strtoupper(substr($words[0], 0, 2));
        }

        return strtoupper(substr($words[0], 0, 1).substr($words[1], 0, 1));
    }

    public function render(): string
    {
        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ".self::PAGE_WIDTH.' '.self::PAGE_HEIGHT."] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n",
            "6 0 obj\n<< /Length ".strlen($this->content)." >>\nstream\n".$this->content."\nendstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function escape(?string $text): string
    {
        $text = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) $text);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function num(float $number): string
    {
        return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
    }
}
