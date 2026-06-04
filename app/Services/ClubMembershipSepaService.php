<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Invoice;
use App\Support\ClubMembershipInput;

class ClubMembershipSepaService
{
    public function buildDebitXml(Club $club, $invoices, $memberships): string
    {
        $messageId = 'AIRMIUS-'.$club->id.'-'.now()->format('YmdHis');
        $paymentId = $messageId.'-PMT';
        $controlSum = $invoices->sum(fn (Invoice $invoice) => (float) $invoice->amount);
        $collectionDate = now()->addDays(3)->toDateString();
        $creditorName = $club->sepa_account_holder ?: $club->name;

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('Document');
        $xml->writeAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.02');
        $xml->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $xml->startElement('CstmrDrctDbtInitn');

        $xml->startElement('GrpHdr');
        $xml->writeElement('MsgId', $messageId);
        $xml->writeElement('CreDtTm', now()->toIso8601String());
        $xml->writeElement('NbOfTxs', (string) $invoices->count());
        $xml->writeElement('CtrlSum', number_format($controlSum, 2, '.', ''));
        $xml->startElement('InitgPty');
        $xml->writeElement('Nm', $creditorName);
        $xml->endElement();
        $xml->endElement();

        $xml->startElement('PmtInf');
        $xml->writeElement('PmtInfId', $paymentId);
        $xml->writeElement('PmtMtd', 'DD');
        $xml->writeElement('BtchBookg', 'true');
        $xml->writeElement('NbOfTxs', (string) $invoices->count());
        $xml->writeElement('CtrlSum', number_format($controlSum, 2, '.', ''));
        $xml->startElement('PmtTpInf');
        $xml->startElement('SvcLvl');
        $xml->writeElement('Cd', 'SEPA');
        $xml->endElement();
        $xml->startElement('LclInstrm');
        $xml->writeElement('Cd', 'CORE');
        $xml->endElement();
        $xml->writeElement('SeqTp', 'RCUR');
        $xml->endElement();
        $xml->writeElement('ReqdColltnDt', $collectionDate);
        $xml->startElement('Cdtr');
        $xml->writeElement('Nm', $creditorName);
        $xml->endElement();
        $xml->startElement('CdtrAcct');
        $xml->startElement('Id');
        $xml->writeElement('IBAN', ClubMembershipInput::normalizeIban($club->sepa_iban));
        $xml->endElement();
        $xml->endElement();
        $xml->startElement('CdtrAgt');
        $xml->startElement('FinInstnId');
        $this->writeFinancialInstitution($xml, $club->sepa_bic);
        $xml->endElement();
        $xml->endElement();
        $xml->writeElement('ChrgBr', 'SLEV');
        $xml->startElement('CdtrSchmeId');
        $xml->startElement('Id');
        $xml->startElement('PrvtId');
        $xml->startElement('Othr');
        $xml->writeElement('Id', $club->sepa_creditor_id);
        $xml->startElement('SchmeNm');
        $xml->writeElement('Prtry', 'SEPA');
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();

        foreach ($invoices as $invoice) {
            $membership = $memberships->get($invoice->user_id);
            $debtorName = $invoice->user?->name ?: $invoice->user?->email ?: 'Mitglied '.$invoice->user_id;

            $xml->startElement('DrctDbtTxInf');
            $xml->startElement('PmtId');
            $xml->writeElement('EndToEndId', $invoice->number);
            $xml->endElement();
            $xml->startElement('InstdAmt');
            $xml->writeAttribute('Ccy', 'EUR');
            $xml->text(number_format((float) $invoice->amount, 2, '.', ''));
            $xml->endElement();
            $xml->startElement('DrctDbtTx');
            $xml->startElement('MndtRltdInf');
            $xml->writeElement('MndtId', $membership->sepa_mandate_reference);
            $xml->writeElement('DtOfSgntr', $membership->sepa_mandate_signed_on);
            $xml->endElement();
            $xml->endElement();
            $xml->startElement('DbtrAgt');
            $xml->startElement('FinInstnId');
            $this->writeFinancialInstitution($xml, $membership->sepa_bic);
            $xml->endElement();
            $xml->endElement();
            $xml->startElement('Dbtr');
            $xml->writeElement('Nm', $debtorName);
            $xml->endElement();
            $xml->startElement('DbtrAcct');
            $xml->startElement('Id');
            $xml->writeElement('IBAN', ClubMembershipInput::normalizeIban($membership->sepa_iban));
            $xml->endElement();
            $xml->endElement();
            $xml->startElement('RmtInf');
            $xml->writeElement('Ustrd', trim($invoice->number.' '.$invoice->title));
            $xml->endElement();
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function writeFinancialInstitution(\XMLWriter $xml, mixed $bic): void
    {
        $bic = ClubMembershipInput::normalizeBic($bic);

        if ($bic) {
            $xml->writeElement('BIC', $bic);

            return;
        }

        $xml->startElement('Othr');
        $xml->writeElement('Id', 'NOTPROVIDED');
        $xml->endElement();
    }
}
