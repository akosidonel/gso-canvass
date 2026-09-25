document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-digits-only]').forEach((input) => {
        const sanitize = (value) => value.replace(/[^0-9]/g, '').slice(0, input.maxLength);

        input.addEventListener('beforeinput', (event) => {
            if (event.data && /[^0-9]/.test(event.data)) event.preventDefault();
        });

        input.addEventListener('paste', (event) => {
            event.preventDefault();
            const digits = event.clipboardData.getData('text').replace(/[^0-9]/g, '');
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const available = input.maxLength - (input.value.length - (end - start));
            input.setRangeText(digits.slice(0, available), start, end, 'end');
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });

        input.addEventListener('input', () => {
            const start = input.selectionStart;
            const cursor = sanitize(input.value.slice(0, start)).length;
            const value = sanitize(input.value);
            if (input.value !== value) {
                input.value = value;
                input.setSelectionRange(cursor, cursor);
            }
        });
    });

    document.querySelectorAll('[data-confirm-delete]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirmDelete)) event.preventDefault();
        });
    });

    const presence = document.querySelector('meta[name="presence-url"]');
    if (presence) {
        const ping = async () => {
            if (document.hidden) return;
            try {
                const response = await fetch(presence.content, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                if ([401, 419].includes(response.status) || response.redirected) {
                    window.location.assign('/signin');
                }
            } catch {
                // A later heartbeat retries after a temporary network failure.
            }
        };
        setInterval(ping, 60000);
        document.addEventListener('visibilitychange', ping);
    }

    const toggle = document.querySelector('[data-toggle-password]');
    const password = document.getElementById('password');

    if (!toggle || !password) return;

    toggle.addEventListener('click', () => {
        const show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        toggle.textContent = show ? toggle.dataset.hide : toggle.dataset.show;
        toggle.setAttribute('aria-pressed', String(show));
    });
});

// Excel quotes cells containing tabs, line breaks, or quotation marks.
export function parseExcelRows(text) {
    const rows = [];
    let row = [], cell = '', quoted = false;
    const source = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    for (let i = 0; i < source.length; i++) {
        const character = source[i];
        if (character === '"' && (quoted || cell === '')) {
            if (quoted && source[i + 1] === '"') { cell += '"'; i++; }
            else quoted = !quoted;
        } else if (!quoted && (character === '\t' || character === '\n')) {
            row.push(cell); cell = '';
            if (character === '\n') { rows.push(row); row = []; }
        } else cell += character;
    }
    row.push(cell); rows.push(row);
    return rows.filter(row => row.some(value => value.trim() !== ''));
}

export function priceNumber(value) {
    const text = String(value ?? '').trim();
    // Strip valid thousands groups only; malformed separators remain validation errors.
    return /^\d{1,3}(,\d{3})+(\.\d+)?$/.test(text) ? text.replaceAll(',', '') : text;
}

export function priceTotal(qty, amount) {
    if (!/^\d+(\.\d{1,3})?$/.test(qty) || !/^\d+(\.\d{1,2})?$/.test(amount)) return '';
    const scaled = (value, places) => {
        const [whole, fraction = ''] = value.split('.');
        return BigInt(whole) * (10n ** BigInt(places)) + BigInt(fraction.padEnd(places, '0'));
    };
    const cents = (scaled(qty, 3) * scaled(amount, 2) + 500n) / 1000n;
    return `${cents / 100n}.${String(cents % 100n).padStart(2, '0')}`;
}

export function excelCell(value) {
    let text = String(value ?? '');
    if (/^\s*[=+@-]/.test(text)) text = "'" + text;
    return /[\t\n\r"]/.test(text) ? '"' + text.replaceAll('"', '""') + '"' : text;
}

document.addEventListener('DOMContentLoaded', () => {
    const list = document.querySelector('[data-price-list]');
    if (list) {
        const status = list.querySelector('[data-copy-status]');
        list.querySelectorAll('[data-copy-row]').forEach(button => {
            const label = button.getAttribute('aria-label');
            const title = button.title;
            const copyIcon = button.querySelector('[data-copy-icon]');
            const copiedIcon = button.querySelector('[data-copied-icon]');
            let resetTimer;
            const reset = () => {
                copyIcon.classList.remove('hidden');
                copiedIcon.classList.add('hidden');
                button.setAttribute('aria-label', label);
                button.title = title;
            };
            button.addEventListener('click', async () => {
                clearTimeout(resetTimer);
                reset();
                status.textContent = '';
                button.disabled = true;
                try {
                    const row = JSON.parse(button.dataset.copyRow).map(excelCell).join('\t');
                    await navigator.clipboard.writeText(row);
                    copyIcon.classList.add('hidden');
                    copiedIcon.classList.remove('hidden');
                    button.setAttribute('aria-label', list.dataset.copiedLabel);
                    button.title = list.dataset.copiedLabel;
                    status.textContent = list.dataset.copySuccess;
                    resetTimer = setTimeout(reset, 2000);
                } catch {
                    status.textContent = list.dataset.copyError;
                } finally {
                    button.disabled = false;
                }
            });
        });
    }

    const editor = document.querySelector('[data-price-editor]');
    if (!editor) return;
    const messages = JSON.parse(editor.dataset.messages);
    const translate = key => messages[key] || key;
    const body = editor.querySelector('[data-preview-rows]');
    const template = editor.querySelector('[data-row-template]');
    const fields = [...template.content.querySelectorAll('[data-field]')].map(input => input.dataset.field);
    const status = editor.querySelector('[data-editor-status]');
    const source = editor.querySelector('[data-paste-source]');
    const save = editor.querySelector('[data-save-rows]');
    let dirty = false, saving = false;
    const renumber = () => [...body.children].forEach((row, index) => { row.querySelector('[data-row-number]').textContent = index + 1; });
    const total = row => {
        const input = key => row.querySelector(`[data-field="${key}"]`);
        input('total').value = priceTotal(priceNumber(input('qty').value), priceNumber(input('amount').value));
    };
    const addRow = (values = {}) => {
        const row = template.content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[data-field]').forEach(input => {
            input.value = values[input.dataset.field] ?? '';
            input.addEventListener('input', () => {
                dirty = true; input.removeAttribute('aria-invalid');
                input.classList.remove('ring-2', 'ring-error-500');
                total(row);
            });
        });
        row.querySelector('[data-remove-row]')?.addEventListener('click', () => { row.remove(); dirty = true; renumber(); });
        body.append(row); total(row);
    };
    const initial = JSON.parse(editor.dataset.record);
    if (initial) { addRow(initial); renumber(); }
    source?.addEventListener('input', () => { dirty = true; });
    editor.querySelector('[data-add-row]')?.addEventListener('click', () => {
        if (body.children.length >= 500) { status.textContent = translate('Use at most 500 rows per batch.'); return; }
        addRow(); dirty = true; renumber();
    });
    editor.querySelector('[data-preview-paste]')?.addEventListener('click', () => {
        const rows = parseExcelRows(source.value);
        if (!rows.length) { status.textContent = translate('Paste copied Excel rows first.'); return; }
        const workbook = editor.querySelector('[data-paste-layout]').value === 'workbook';
        const layout = workbook
            ? ['qty', 'unit', 'particulars', 'amount', 'total', 'department', 'control_number', null, 'brand_model', 'canvasser', null, 'store']
            : fields;
        if (/^(qty(?:\s*1)?|quantity)$/i.test(rows[0][0].trim()) && /^(unit(?:s)?|qty\s*2)$/i.test(rows[0][1]?.trim() || '')) rows.shift();
        if (!rows.length) { status.textContent = translate('Paste copied Excel rows first.'); return; }
        if (rows.length + body.children.length > 500) { status.textContent = translate('Use at most 500 rows per batch.'); return; }
        if (rows.some(row => row.length !== layout.length)) {
            status.textContent = translate('Each row must have :count columns. Check the selected column order.').replace(':count', layout.length); return;
        }
        rows.forEach(cells => {
            const values = {};
            layout.forEach((key, index) => { if (key) values[key] = cells[index].trim(); });
            values.qty = priceNumber(values.qty); values.amount = priceNumber(values.amount);
            addRow(values);
        });
        dirty = true; renumber(); source.value = '';
        status.textContent = translate('Preview ready. Review the rows before saving.');
    });
    save.addEventListener('click', async () => {
        if (saving) return;
        const rows = [...body.children].map(row => Object.fromEntries([...row.querySelectorAll('[data-field]')].map(input => [input.dataset.field, ['qty', 'amount'].includes(input.dataset.field) ? priceNumber(input.value) : input.value.trim()])));
        if (!rows.length) { status.textContent = translate('No rows to save.'); return; }
        // Pending pasted content must be previewed before saving.
        if (source?.value.trim()) { status.textContent = translate('Add the pasted rows to the preview before saving.'); return; }
        editor.querySelectorAll('[aria-invalid]').forEach(input => { input.removeAttribute('aria-invalid'); input.classList.remove('ring-2', 'ring-error-500'); });
        saving = true;
        const controls = [...editor.querySelectorAll('button, textarea, select')];
        controls.forEach(input => { input.disabled = true; });
        status.textContent = translate('Saving records…');
        try {
            const response = await fetch(editor.dataset.url, {
                method: editor.dataset.method,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ rows }),
            });
            if ([401, 419].includes(response.status) || response.redirected) {
                status.textContent = translate('Your session expired. Sign in again in another tab, then retry.'); return;
            }
            const result = await response.json();
            if (!response.ok) {
                const errors = result.errors || {};
                status.textContent = Object.values(errors).flat().join('\n') || result.message || translate('Review the highlighted fields.');
                Object.keys(errors).forEach(key => {
                    const [, index, field] = key.split('.');
                    const input = [...(body.children[index]?.querySelectorAll('[data-field]') || [])].find(input => input.dataset.field === field);
                    if (input) { input.setAttribute('aria-invalid', 'true'); input.classList.add('ring-2', 'ring-error-500'); }
                });
                return;
            }
            dirty = false; window.location.assign(result.redirect);
        } catch { status.textContent = translate('Unable to save. Your rows are still here; check your connection and try again.'); }
        finally { saving = false; controls.forEach(input => { input.disabled = false; }); }
    });
    window.addEventListener('beforeunload', event => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
});
