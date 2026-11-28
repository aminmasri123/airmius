<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Invoice;
use App\Support\ClubMembershipInput;

class ClubMembershipSepaService
{
    public function buildDebitXml(Club $club, $invoices, $memberships, ?string $collectionDate = null, ?string $messageId = null): string
    {
        abort_if($invoices->isEmpty(), 422, __('organization.club.sepa_no_open_invoices'));
        foreach ($invoices as $invoice) {
            abort_if(($invoice->club_id !== null && (int) $invoice->club_id !== (int) $club->id)
                || $invoice->outstandingCents() <= 0 || ! $memberships->has($invoice->user_id),
                422, __('sepa.invoices'));
        }

        \App\Support\SepaData::assertCreditor([
            'sepa_creditor_id' => $club->sepa_creditor_id,
            'sepa_iban' => $club->sepa_iban, 'sepa_bic' => $club->sepa_bic,
            'sepa_account_holder' => $club->sepa_account_holder ?: $club->name,
        ]);
        foreach ($invoices as $invoice) {
            $mandate = (array) $memberships->get($invoice->user_id);
            // Batch snapshots are checked for active status before export.
            $mandate['sepa_mandate_active'] ??= true;
            abort_unless(\App\Support\SepaData::mandateReady($mandate) && filled($invoice->user?->name), 422, __('sepa.mandate'));
        }

        $messageId ??= 'AIRMIUS-'.$club->id.'-'.now()->format('YmdHis');
        $paymentId = $messageId.'-PMT';
        $controlSum = $invoices->sum(fn (Invoice $invoice) => $invoice->outstandingCents()) / 100;
        $collectionDate ??= now()->addDays(3)->toDateString();
        $creditorName = $club->sepa_account_holder ?: $club->name;
        foreach ($invoices as $invoice) {
            $membership = $memberships->get($invoice->user_id);
            abort_unless($this->validIban($membership->sepa_iban ?? null)
                && filled($membership->sepa_mandate_reference ?? null)
                && filled($membership->sepa_mandate_signed_on ?? null), 422, __('sepa.mandate'));
        }
        abort_unless($this->validIban($club->sepa_iban) && filled($club->sepa_creditor_id), 422, __('sepa.credentials'));

        $xml = new \XMLWriter;
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
        $xml->writeElement('Id', ClubMembershipInput::normalizeIban($club->sepa_creditor_id));
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
            $xml->text(number_format(($invoice->outstandingCents() / 100), 2, '.', ''));
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

    private function validIban(mixed $value): bool
    {
        $iban = ClubMembershipInput::normalizeIban($value) ?? '';
        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/D', $iban)) {
            return false;
        }
        $remainder = 0;
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $character) {
            $digits = ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
            foreach (str_split($digits) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
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
