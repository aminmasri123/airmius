import assert from 'node:assert/strict'
import test from 'node:test'

import {
    decimalToMinorUnits,
    normalizeLocalizedDecimal,
} from '../../resources/js/utils/localizedNumber.js'

test('accepts German and English decimal notation without changing the value', () => {
    assert.equal(normalizeLocalizedDecimal('12,50'), 12.5)
    assert.equal(normalizeLocalizedDecimal('12.50'), 12.5)
    assert.equal(normalizeLocalizedDecimal(' 1\u00a0234,50 '), 1234.5)
    assert.equal(decimalToMinorUnits('12,50'), 1250)
    assert.equal(decimalToMinorUnits('12.50'), 1250)
})

test('rejects ambiguous, empty and non-finite input', () => {
    for (const value of ['', ' ', '12,3.4', '1.234,56', 'NaN', 'Infinity', '--2']) {
        assert.equal(normalizeLocalizedDecimal(value), null)
        assert.equal(decimalToMinorUnits(value), null)
    }
})

test('rounds monetary values to integer minor units', () => {
    assert.equal(decimalToMinorUnits('0,005'), 1)
    assert.equal(decimalToMinorUnits('-2.50'), -250)
})
