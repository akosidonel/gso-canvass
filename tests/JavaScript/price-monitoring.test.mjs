import assert from 'node:assert/strict';
import { test } from 'node:test';
globalThis.document = { addEventListener() {} };
const { parseExcelRows, priceNumber, excelCell } = await import('../../resources/js/script.js');

test('category imports preserve both existing Excel layouts and exported category columns', async () => {
    const { pricePasteColumns, pasteGridCells, isPriceHeader } = await import('../../resources/js/components/price-paste-grid.js');
    const fields = ['category', 'qty', 'unit', 'particulars', 'amount', 'department', 'control_number', 'brand_model', 'store', 'canvasser'];
    assert.deepEqual(pricePasteColumns(fields, 'system'), fields.slice(1));
    assert.deepEqual(pricePasteColumns(fields, 'system-category'), fields);
    assert.deepEqual(pricePasteColumns(fields, 'workbook'), ['qty', 'unit', 'particulars', 'amount', 'department', 'control_number', 'item', 'brand_model', 'canvasser', 'canvass', 'store']);
    const cells = parseExcelRows('Office Supplies\t1\tpcs\tPaper\t10.00\tGSO\t001-26\t\tShop\tStaff');
    const row = pasteGridCells([], cells, 0, 0, pricePasteColumns(fields, 'system-category'))[0];
    assert.equal(isPriceHeader(['Category', 'Qty', 'Unit'], fields), true);
    assert.equal(isPriceHeader(['Qty', 'Unit'], fields.slice(1)), true);
    assert.equal(isPriceHeader(cells[0], fields), false);
    assert.equal(row.category, 'Office Supplies');
    assert.equal(row.control_number, '001-26');
    assert.equal(row.canvasser, 'Staff');
});

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
