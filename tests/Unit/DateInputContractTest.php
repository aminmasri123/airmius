<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DateInputContractTest extends TestCase
{
    public function test_date_input_accepts_localized_digits_and_iso_autofill(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/Components/DateInput.vue');

        self::assertStringContainsString('const isoMatch = /^(\\d{4})-(\\d{2})-(\\d{2})$/', $source);
        self::assertStringContainsString('displayValue.value = `${isoMatch[3]}.${isoMatch[2]}.${isoMatch[1]}`', $source);
        self::assertStringContainsString("emit('update:modelValue', toIso(displayValue.value))", $source);
        self::assertStringContainsString("rawValue.replace(/\\D/g, '').slice(0, 8)", $source);
    }
}
