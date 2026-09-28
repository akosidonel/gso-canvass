import assert from 'node:assert/strict';
import { test } from 'node:test';
globalThis.document = { addEventListener() {} };
const { parseExcelRows, priceNumber, excelCell } = await import('../../resources/js/script.js');

test('Excel rows preserve multiline cells, escaped quotes, empty cells, and leading zeros', () => {
    assert.deepEqual(parseExcelRows('2\tpcs\t"Paper\nA4 ""white"""\t001-26\t\r\n'), [['2', 'pcs', 'Paper\nA4 "white"', '001-26', '']]);
    assert.deepEqual(parseExcelRows('\r\n\t\r\n'), []);
});
test('number normalization accepts thousands groups but leaves malformed values invalid', () => {
    assert.equal(priceNumber(' 1,250.50 '), '1250.50');
    assert.equal(priceNumber('1,2'), '1,2');
    assert.equal(priceNumber('=2*3'), '=2*3');
});

test('copying quotes multiline cells and protects formula-like text', () => {
    assert.equal(excelCell('=HYPERLINK("bad")'), '"\'=HYPERLINK(""bad"")"');
    assert.equal(excelCell('001-26'), '001-26');
    assert.deepEqual(parseExcelRows([['2', 'Paper\nA4', '"brand"']].map(row => row.map(excelCell).join('\t')).join('\n')), [['2', 'Paper\nA4', '"brand"']]);
});

test('grid paste preserves offsets, blank cells, multiline values, and untouched data', async () => {
    const { pasteGridCells } = await import('../../resources/js/components/price-paste-grid.js');
    const original = [{ qty: '003', unit: 'box', particulars: 'Old' }];
    const cells = parseExcelRows('pcs\t"Paper\nA4"\n\t\nset\tPens\n', true);
    const rows = pasteGridCells(original, cells, 0, 1, ['qty', 'unit', 'particulars']);
    assert.deepEqual(rows, [
        { qty: '003', unit: 'pcs', particulars: 'Paper\nA4' },
        { unit: '', particulars: '' },
        { unit: 'set', particulars: 'Pens' },
    ]);
    assert.equal(original[0].particulars, 'Old');
});

test('grid paste rejects overflow without modifying existing cells', async () => {
    const { pasteGridCells } = await import('../../resources/js/components/price-paste-grid.js');
    const original = [{ qty: '1' }];
    assert.throws(() => pasteGridCells(original, [['2', 'pcs']], 0, 1, ['qty', 'unit']), /columns/);
    assert.throws(() => pasteGridCells(original, [['2'], ['3']], 499, 0, ['qty']), /rows/);
    assert.deepEqual(original, [{ qty: '1' }]);
    assert.equal(pasteGridCells([], [['2']], 499, 0, ['qty'])[499].qty, '2');
});
