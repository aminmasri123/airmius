<?php
require __DIR__.'/../../app/Services/AirmiusPdfDocument.php';
use App\Services\AirmiusPdfDocument;
$labels = (require __DIR__.'/../../lang/de/learning.php')['certificate'];
$label = function ($key, $replace = []) use ($labels) { $text = $labels[$key]; foreach ($replace as $k => $v) $text = str_replace(':'.$k, $v, $text); return $text; };
$certificate = (object) ['code'=>'AIR-LEARN-DEMO-001','issued_at'=>new DateTimeImmutable('2026-11-19'),'user'=>(object)['name'=>'Max Mustermann'],'enrollment'=>(object)['progress_percent'=>100], 'course'=>(object)['title'=>'Einführung in Canva','subtitle'=>'Wie gestalte ich eine Visitenkarte?','certificate_signature_name'=>'Amin Masri','certificate_footer_text'=>null]];
        $pdf = new AirmiusPdfDocument;
        $pdf->certificateHeader($label('title'), 'Airmius Sportschule');
        $logo = null;
        if ($logo !== null) {
            $pdf->image($logo, 447, 732, 90, 50);
        }
        $pdf->text($label('participant'), 58, 589, 10, false, AirmiusPdfDocument::SLATE);
        $pdf->fitText($certificate->user?->name ?: $label('default_participant'), 58, 552, 479, 29, true);
        $pdf->fitText($label('completion_statement'), 58, 518, 479, 12, false, AirmiusPdfDocument::SLATE);
        $pdf->fillColor(...AirmiusPdfDocument::SURFACE)->rect(58, 384, 479, 110, true);
        $pdf->fillColor(111, 189, 55)->rect(58, 384, 3, 110, true);
        $pdf->text($label('course'), 76, 471, 10, false, AirmiusPdfDocument::SLATE);
        $pdf->fitText($certificate->course?->title ?: $label('default_course'), 76, 436, 443, 25, true);
        $pdf->fitText($certificate->course?->subtitle ?: ($certificate->course?->description ?: $label('default_description')), 76, 408, 443, 11, false, AirmiusPdfDocument::SLATE);
        $pdf->certificateNote($certificate->course?->certificate_footer_text ?: $label('default_footer'));
        $pdf->fitText($label('status').': '.$label('completed').'  |  '.$label('progress').': '.(string) ($certificate->enrollment?->progress_percent ?: 100).'%', 58, 299, 479, 10, true, AirmiusPdfDocument::SLATE);
        $pdf->fitText($certificate->course?->certificate_signature_name ?: ($certificate->course?->tutor?->name ?: $label('default_tutor')), 58, 225, 275, 14, true);
        $pdf->strokeColor(...AirmiusPdfDocument::BORDER)->line(58, 214, 333, 214);
        $pdf->text($label('course_lead'), 58, 198, 9, false, AirmiusPdfDocument::SLATE);
        $pdf->text($certificate->issued_at->format('d.m.Y'), 375, 225, 14, true);
        $pdf->strokeColor(...AirmiusPdfDocument::BORDER)->line(375, 214, 537, 214);
        $pdf->text($label('issued'), 375, 198, 9, false, AirmiusPdfDocument::SLATE);
        $pdf->fitText($label('certificate_id', ['code' => $certificate->code]), 58, 157, 479, 9, false, AirmiusPdfDocument::MUTED);
$bytes = $pdf->certificateFooter($label('congratulations'))->render();
foreach (['RECHNUNG','Rechnungsnummer','Diese Rechnung','Steuernr.'] as $forbidden) { if (str_contains($bytes, $forbidden)) throw new RuntimeException($forbidden); }
file_put_contents(__DIR__.'/certificate-preview.pdf', $bytes);
echo 'PDF generated; invoice text checks passed.\n';
