import { test } from 'node:test'
import assert from 'node:assert/strict'
import { parseFeeCents } from '../../resources/js/Components/ClubMemberships/feeAmount.js'

test('bank fee amounts preserve exact cents for comma and dot input', () => {
    for (const [input, cents] of [['4,10', 410], ['4.10', 410], ['0,29', 29],
        ['2.55', 255], [' 3,5 ', 350], ['999999.99', 99999999], ['0', 0]]) {
        assert.equal(parseFeeCents(input), cents, input)
    }
})

test('invalid amounts are rejected without rounding or partial parsing', () => {
    for (const input of ['', null, '-1', '1e2', '1.234', '1000000', '1,2,3', 'NaN', 'Infinity', '3 EUR']) {
        assert.equal(parseFeeCents(input), null, String(input))
    }
})
