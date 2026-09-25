import assert from 'node:assert/strict';
import { test } from 'node:test';
globalThis.document = { addEventListener() {} };
const { parseExcelRows, priceNumber, priceTotal, excelCell } = await import('../../resources/js/script.js');

test('Excel rows preserve multiline cells, escaped quotes, empty cells, and leading zeros', () => {
    assert.deepEqual(parseExcelRows('2\tpcs\t"Paper\nA4 ""white"""\t001-26\t\r\n'), [['2', 'pcs', 'Paper\nA4 "white"', '001-26', '']]);
    assert.deepEqual(parseExcelRows('\r\n\t\r\n'), []);
});
test('number normalization accepts thousands groups but leaves malformed values invalid', () => {
    assert.equal(priceNumber(' 1,250.50 '), '1250.50');
    assert.equal(priceNumber('1,2'), '1,2');
    assert.equal(priceNumber('=2*3'), '=2*3');
});
test('totals use exact decimal rounding, including zero and fractional quantities', () => {
    assert.equal(priceTotal('2.125', '10.20'), '21.68');
    assert.equal(priceTotal('1', '0'), '0.00');
    assert.equal(priceTotal('0.1', '0.2'), '0.02');
    assert.equal(priceTotal('99999.999', '9999999.99'), '999999989000.00');
    assert.equal(priceTotal('=2*3', '10'), '');
});
test('copying quotes multiline cells and protects formula-like text', () => {
    assert.equal(excelCell('=HYPERLINK("bad")'), '"\'=HYPERLINK(""bad"")"');
    assert.equal(excelCell('001-26'), '001-26');
    assert.deepEqual(parseExcelRows([['2', 'Paper\nA4', '"brand"']].map(row => row.map(excelCell).join('\t')).join('\n')), [['2', 'Paper\nA4', '"brand"']]);
});
