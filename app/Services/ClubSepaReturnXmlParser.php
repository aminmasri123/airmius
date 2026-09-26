<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final class ClubSepaReturnXmlParser
{
    private DOMXPath $xpath;

    public function parse(string $contents): array
    {
        abort_if(preg_match('/<!DOCTYPE|<!ENTITY/i', $contents), 422, __('sepa.import_file'));

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $loaded = $document->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_COMPACT);
            abort_unless($loaded && $document->documentElement?->localName === 'Document', 422, __('sepa.import_file'));
            abort_if($document->getElementsByTagName('*')->length > 20000, 422, __('sepa.import_file'));

            $namespace = $document->documentElement->namespaceURI ?? '';
            abort_unless(preg_match('/^urn:iso:std:iso:20022:tech:xsd:(pain\.002|camt\.053|camt\.054)\.\d{3}\.\d{2}$/D', $namespace, $match), 422, __('sepa.import_file'));
            $this->xpath = new DOMXPath($document);
            $this->xpath->registerNamespace('iso', $namespace);
            $rows = match ($match[1]) {
                'pain.002' => $this->pain002($document),
                'camt.053', 'camt.054' => $this->camt($document),
            };
            abort_unless($rows !== [] && count($rows) <= 200, 422, __('sepa.import_file'));

            return [$match[1], $rows];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function pain002(DOMDocument $document): array
    {
        abort_unless($this->nodes($document, '/iso:Document/iso:CstmrPmtStsRpt')->length === 1, 422, __('sepa.import_file'));
        $messageId = $this->text($document, '/iso:Document/iso:CstmrPmtStsRpt/iso:GrpHdr/iso:MsgId');
        $created = $this->date($this->text($document, '/iso:Document/iso:CstmrPmtStsRpt/iso:GrpHdr/iso:CreDtTm'));
        abort_unless($messageId !== '' && $created !== null, 422, __('sepa.import_file'));

        $rows = [];
        foreach ($this->nodes($document, '//iso:TxInfAndSts') as $transaction) {
            if (! $transaction instanceof DOMElement || $this->text($transaction, './iso:TxSts') !== 'RJCT') {
                continue;
            }
            $endToEndId = $this->text($transaction, './iso:OrgnlEndToEndId');
            $amountNode = $this->first($transaction, './iso:OrgnlTxRef/iso:Amt/iso:InstdAmt | ./iso:OrgnlTxRef/iso:Amt/iso:ReqdInstdAmt');
            $amount = $amountNode ? trim($amountNode->textContent) : '';
            $currency = $amountNode instanceof DOMElement ? trim($amountNode->getAttribute('Ccy')) : '';
            $reason = $this->text($transaction, './iso:StsRsnInf/iso:Rsn/iso:Cd | ./iso:StsRsnInf/iso:Rsn/iso:Prtry');
            $instructionId = $this->text($transaction, './iso:OrgnlInstrId');
            $reference = $instructionId !== '' ? $instructionId : $messageId.':'.$endToEndId;
            $iban = $this->text($transaction, './iso:OrgnlTxRef/iso:DbtrAcct/iso:Id/iso:IBAN');
            $statusDate = $this->date($this->text($transaction, './iso:OrgnlTxRef/iso:ReqdColltnDt'))
                ?? $this->date($this->text($transaction, './iso:AccptncDtTm')) ?? $created;
            $rows[] = [$endToEndId, $statusDate, $this->negative($amount), $currency, $reference, $reason, $iban];
        }

        return $rows;
    }

    private function camt(DOMDocument $document): array
    {
        $reports = $this->nodes($document, '/iso:Document/iso:BkToCstmrStmt | /iso:Document/iso:BkToCstmrDbtCdtNtfctn');
        abort_unless($reports->length === 1, 422, __('sepa.import_file'));
        $rows = [];
        foreach ($this->nodes($document, '//iso:Ntry') as $entry) {
            if (! $entry instanceof DOMElement || $this->text($entry, './iso:CdtDbtInd') !== 'DBIT') {
                continue;
            }
            $bookingDate = $this->date($this->text($entry, './iso:BookgDt/iso:Dt | ./iso:BookgDt/iso:DtTm'));
            $entryReference = $this->text($entry, './iso:AcctSvcrRef | ./iso:NtryRef');
            $transactions = $this->nodes($entry, './/iso:TxDtls[.//iso:RtrInf]');
            foreach ($transactions as $transaction) {
                if (! $transaction instanceof DOMElement) {
                    continue;
                }
                $endToEndId = $this->text($transaction, './iso:Refs/iso:EndToEndId');
                $amountNode = $this->first($transaction, './iso:AmtDtls/iso:TxAmt/iso:Amt | ./iso:AmtDtls/iso:InstdAmt/iso:Amt');
                if (! $amountNode && $transactions->length === 1) {
                    $amountNode = $this->first($entry, './iso:Amt');
                }
                $amount = $amountNode ? trim($amountNode->textContent) : '';
                $currency = $amountNode instanceof DOMElement ? trim($amountNode->getAttribute('Ccy')) : '';
                $reference = $this->text($transaction, './iso:Refs/iso:AcctSvcrRef | ./iso:Refs/iso:TxId | ./iso:Refs/iso:InstrId');
                $reference = $reference !== '' ? $reference : $entryReference;
                $reason = $this->text($transaction, './/iso:RtrInf/iso:Rsn/iso:Cd | .//iso:RtrInf/iso:Rsn/iso:Prtry');
                $iban = $this->text($transaction, './/iso:RltdPties/iso:DbtrAcct/iso:Id/iso:IBAN');
                $rows[] = [$endToEndId, $bookingDate ?? '', $this->negative($amount), $currency, $reference, $reason, $iban];
            }
        }

        return $rows;
    }

    private function nodes(DOMNode $context, string $query): \DOMNodeList
    {
        $nodes = $this->xpath->query($query, $context);
        abort_unless($nodes !== false, 422, __('sepa.import_file'));

        return $nodes;
    }

    private function first(DOMNode $context, string $query): ?DOMNode
    {
        return $this->nodes($context, $query)->item(0);
    }

    private function text(DOMNode $context, string $query): string
    {
        return trim($this->first($context, $query)?->textContent ?? '');
    }

    private function date(string $value): ?string
    {
        $date = substr($value, 0, 10);
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed && $parsed->format('Y-m-d') === $date ? $date : null;
    }

    private function negative(string $amount): string
    {
        $amount = trim($amount);

        return $amount === '' ? '' : '-'.ltrim($amount, '+-');
    }
}
