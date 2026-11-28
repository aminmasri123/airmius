<?php

namespace Tests\Unit;

use App\Support\SepaData;
use PHPUnit\Framework\TestCase;

class SepaDataTest extends TestCase
{
    public function test_iban_length_country_and_checksum(): void
    {
        foreach (['de89 3704 0044 0532 0130 00', 'FR1420041010050500013M02606', 'GB82WEST12345698765432'] as $iban) {
            $this->assertTrue(SepaData::validIban($iban), $iban);
        }
        foreach (['', 'DE', 'DE89370400440532013001', 'DE893704004405320130000', 'ZZ89370400440532013000'] as $iban) {
            $this->assertFalse(SepaData::validIban($iban), $iban);
        }
    }

    public function test_creditor_business_code_does_not_affect_checksum(): void
    {
        $this->assertTrue(SepaData::validCreditorId('de98 zzz09999999999'));
        $this->assertTrue(SepaData::validCreditorId('DE98ABC09999999999'));
        $this->assertFalse(SepaData::validCreditorId('DE97ZZZ09999999999'));
        $this->assertFalse(SepaData::validCreditorId('DE98ZZZ9999999999'));
    }

    public function test_bic_format(): void
    {
        $this->assertTrue(SepaData::validBic('cobadeff'));
        $this->assertTrue(SepaData::validBic('COBADEFFXXX'));
        $this->assertFalse(SepaData::validBic('INVALID'));
        $this->assertFalse(SepaData::validBic('1234DEFF'));
    }

    public function test_malformed_json_types_are_rejected_without_conversion_errors(): void
    {
        foreach ([null, [], ['DE89370400440532013000'], 123, true] as $value) {
            $this->assertFalse(SepaData::validIban($value));
            $this->assertFalse(SepaData::validBic($value));
            $this->assertFalse(SepaData::validCreditorId($value));
        }
    }
}
