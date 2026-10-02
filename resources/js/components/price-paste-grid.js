// Apply a clipboard rectangle atomically, so an oversized paste never loses cells.
export function pasteGridCells(rows, cells, rowIndex, columnIndex, columns) {
    if (rowIndex + cells.length > 500) throw new Error('rows');
    if (cells.some(row => columnIndex + row.length > columns.length)) throw new Error('columns');
    const result = rows.map(row => ({ ...row }));
    cells.forEach((row, offset) => {
        result[rowIndex + offset] ??= {};
        row.forEach((value, index) => { result[rowIndex + offset][columns[columnIndex + index]] = value; });
    });
    return result;
}

export function pricePasteColumns(fields, layout) {
    if (layout === 'workbook') return ['qty', 'unit', 'particulars', 'amount', 'department', 'control_number', 'item', 'brand_model', 'canvasser', 'canvass', 'store'];
    return layout === 'system-category' ? fields : fields.filter(field => field !== 'category');
}

export function isPriceHeader(cells, columns) {
    const quantity = columns.indexOf('qty');
    const unit = columns.indexOf('unit');
    return quantity >= 0 && unit >= 0
        && /^(qty(?:\s*1)?|quantity)$/i.test(cells?.[quantity]?.trim() || '')
        && /^(units?|qty\s*2)$/i.test(cells?.[unit]?.trim() || '');
}

export function createPricePasteGrid(editor, fields, parseRows, onChange, onError) {
    const root = editor.querySelector('[data-paste-grid]');
    if (!root) return null;
    const labels = JSON.parse(root.dataset.labels);
    const selector = editor.querySelector('[data-paste-layout]');
    const body = root.querySelector('[data-paste-rows]');
    const head = root.querySelector('[data-paste-head]');
    const template = root.querySelector('[data-paste-cell-template]');
    let rows = Array.from({ length: 5 }, () => ({}));
    const columns = () => pricePasteColumns(fields, selector.value);
    const focus = (row, column) => body.children[row]?.querySelectorAll('textarea')[column]?.focus();
    const appendRow = (values, rowIndex) => {
        const tr = document.createElement('tr');
        const number = document.createElement('th');
        number.scope = 'row';
        number.className = 'border border-gray-200 bg-gray-50 px-3 text-center font-normal text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400';
        number.textContent = rowIndex + 1;
        tr.append(number);
        columns().forEach((key, columnIndex) => {
            const td = template.content.firstElementChild.cloneNode(true);
            const input = td.querySelector('textarea');
            input.value = values[key] ?? '';
            input.setAttribute('aria-label', `${labels[key]} ${rowIndex + 1}`);
            if (['particulars', 'store'].includes(key)) input.classList.replace('w-40', 'w-72');
            input.addEventListener('input', () => {
                rows[rowIndex][key] = input.value;
                onChange();
                if (rowIndex === rows.length - 1 && rows.length < 500) {
                    rows.push({}); appendRow({}, rows.length - 1);
                }
            });
            input.addEventListener('paste', event => {
                if (!event.clipboardData) return;
                event.preventDefault();
                const cells = parseRows(event.clipboardData.getData('text/plain'), true);
                if (isPriceHeader(cells[0], columns().slice(columnIndex))) cells.shift();
                if (!cells.length) return;
                try {
                    rows = pasteGridCells(rows, cells, rowIndex, columnIndex, columns());
                    if (rows.length < 500 && Object.values(rows.at(-1)).some(value => value.trim())) rows.push({});
                    onChange(); render(); focus(rowIndex, columnIndex);
                } catch (error) { onError(error.message); }
            });
            input.addEventListener('keydown', event => {
                if (event.isComposing || event.altKey || event.ctrlKey || event.metaKey) return;
                let nextRow = rowIndex, nextColumn = columnIndex;
                if (event.key === 'Enter' && !event.shiftKey) nextRow++;
                else if (event.key === 'Tab') {
                    nextColumn += event.shiftKey ? -1 : 1;
                    if (nextColumn === columns().length) { nextColumn = 0; nextRow++; }
                    if (nextColumn < 0) { nextColumn = columns().length - 1; nextRow--; }
                } else return;
                if (nextRow < 0 || nextRow >= 500) return;
                event.preventDefault();
                if (nextRow >= rows.length) { rows.push({}); appendRow({}, rows.length - 1); }
                focus(nextRow, nextColumn);
            });
            tr.append(td);
        });
        body.append(tr);
    };
    const render = () => {
        head.replaceChildren(); body.replaceChildren();
        const tr = document.createElement('tr');
        ['#', ...columns().map((key, index) => `${String.fromCharCode(65 + index)} · ${labels[key]}`)].forEach(label => {
            const th = document.createElement('th');
            th.scope = 'col';
            th.className = 'whitespace-nowrap border border-gray-200 px-2 py-2 text-start font-medium dark:border-gray-700';
            th.textContent = label;
            tr.append(th);
        });
        head.append(tr);
        rows.forEach(appendRow);
    };
    const clear = () => { rows = Array.from({ length: 5 }, () => ({})); render(); };
    selector.addEventListener('change', render);
    root.querySelector('[data-clear-paste]').addEventListener('click', () => { clear(); onChange(); focus(0, 0); });
    render();
    return {
        getRows: () => rows.map(row => columns().map(key => row[key] ?? '')).filter(row => row.some(value => value.trim())),
        hasContent: () => rows.some(row => Object.values(row).some(value => value.trim())),
        clear,
    };
}
