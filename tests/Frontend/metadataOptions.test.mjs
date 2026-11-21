import { test } from 'node:test'
import assert from 'node:assert/strict'
import { validateMetadataOptions } from '../../resources/js/support/metadataOptions.js'

test('selection options reject blank, duplicate, oversized and too many entries', () => {
    assert.deepEqual(validateMetadataOptions(['XS', 'S', 'M']).rows, [null, null, null])
    assert.equal(validateMetadataOptions(['']).rows[0], 'optionEmpty')
    assert.equal(validateMetadataOptions(['XS', '  ']).rows[1], 'optionEmpty')
    assert.deepEqual(validateMetadataOptions(['XS', ' xs ']).rows, ['optionDuplicate', 'optionDuplicate'])
    assert.equal(validateMetadataOptions(['x'.repeat(161)]).rows[0], 'optionTooLong')
    assert.equal(validateMetadataOptions([]).list, 'optionEmpty')
    assert.equal(validateMetadataOptions(Array.from({ length: 51 }, (_, i) => `${i}`)).list, 'optionLimit')
})
