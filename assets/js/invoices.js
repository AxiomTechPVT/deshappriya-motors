(function () {
    const form = document.querySelector('[data-invoice-form]');
    const lookupNode = document.getElementById('invoice-lookups');
    if (!form || !lookupNode) return;
    const data = JSON.parse(lookupNode.textContent || '{}');
    const body = form.querySelector('[data-invoice-items]');
    const compact = form.classList.contains('invoice-side-form');
    let index = 0;
    const money = value => (Math.max(0, Number(value) || 0)).toFixed(2);
    const optionRows = (items, label) => items.map(item => `<option value="${item.id}" data-price="${item.price || item.selling_price || 0}" data-name="${String(item.service_name || item.part_name).replace(/"/g, '&quot;')}" data-stock="${item.stock_qty || 0}">${String(item.part_code || '').replace(/</g, '&lt;')} ${String(item.service_name || item.part_name).replace(/</g, '&lt;')} ${label ? '(' + label(item) + ')' : ''}</option>`).join('');
    const addRow = type => {
        body.querySelector('.invoice-empty-row')?.remove();
        const i = index++;
        let selector = '';
        if (type === 'service') selector = `<select class="form-select item-selector" name="items[${i}][service_id]" required><option value="">Select service</option>${optionRows(data.services || [], item => 'Rs. ' + money(item.price))}<option value="__custom__">Other - type a service</option></select><input class="form-control mt-2 service-description" name="items[${i}][description]" placeholder="Type service name" aria-label="Service name" maxlength="190" hidden disabled>`;
        if (type === 'stock_part') selector = `<select class="form-select item-selector" name="items[${i}][stock_item_id]" required><option value="">Select stock part</option>${optionRows(data.parts || [], item => (item.stock_qty || 0) + ' available')}</select>`;
        if (type === 'custom') selector = `<input class="form-control item-description" name="items[${i}][description]" placeholder="Description" required>`;
        const row = document.createElement('tr');
        row.dataset.type = type;
        row.innerHTML = `${compact ? '' : `<td>${i + 1}</td>`}<td><input type="hidden" name="items[${i}][type]" value="${type}"><span class="invoice-type-pill">${type === 'stock_part' ? 'Stock Part' : type[0].toUpperCase() + type.slice(1)}</span></td><td>${selector}</td><td><input class="form-control item-quantity" name="items[${i}][quantity]" type="number" min="0.01" step="0.01" value="1" required></td><td><input class="form-control item-price" name="items[${i}][unit_price]" type="number" min="0" step="0.01" value="0" ${type === 'custom' ? '' : 'readonly'} required></td><td class="item-amount">Rs. 0.00</td><td><button type="button" class="btn btn-light btn-sm remove-item" aria-label="Remove item">×</button></td>`;
        body.appendChild(row);
        const selectorNode = row.querySelector('.item-selector');
        const update = () => {
            if (selectorNode) {
                const selected = selectorNode.options[selectorNode.selectedIndex];
                const price = row.querySelector('.item-price');
                const customService = type === 'service' && selectorNode.value === '__custom__';
                price.readOnly = !customService;
                price.value = selected?.dataset.price || '0';
                const description = row.querySelector('.service-description');
                if (description) {
                    description.hidden = !customService;
                    description.disabled = !customService;
                    description.required = customService;
                    if (customService) description.focus();
                }
                if (type === 'stock_part' && selected && Number(row.querySelector('.item-quantity').value) > Number(selected.dataset.stock || 0)) row.querySelector('.item-quantity').value = selected.dataset.stock || '0';
            }
            recalculate();
        };
        selectorNode?.addEventListener('change', update); row.querySelector('.item-quantity').addEventListener('input', recalculate); row.querySelector('.item-price').addEventListener('input', recalculate); row.querySelector('.remove-item').addEventListener('click', () => { row.remove(); if (!body.children.length) body.innerHTML = '<tr class="invoice-empty-row"><td colspan="7">No items added yet. Use one of the buttons above.</td></tr>'; recalculate(); }); update();
    };
    const recalculate = () => { let subtotal = 0; body.querySelectorAll('tr:not(.invoice-empty-row)').forEach(row => { const amount = (Number(row.querySelector('.item-quantity')?.value) || 0) * (Number(row.querySelector('.item-price')?.value) || 0); subtotal += amount; row.querySelector('.item-amount').textContent = 'Rs. ' + money(amount); }); const charge = Number(form.querySelector('[data-charge]')?.value) || 0; const discountType = form.querySelector('[data-discount-type]')?.value; const discountValue = Number(form.querySelector('[data-discount-value]')?.value) || 0; const base = subtotal + charge; const discount = discountType === 'percentage' ? Math.min(base, base * discountValue / 100) : Math.min(base, discountValue); const total = Math.max(0, base - discount); const received = Number(form.querySelector('[data-payment-received]')?.value) || 0; const applied = Math.min(total, received); const due = Math.max(0, total - applied); const set = (name, value) => { const node = form.querySelector(`[data-preview-${name}]`); if (node) node.textContent = 'Rs. ' + money(value); }; set('subtotal', subtotal); set('charge', charge); set('discount', discount); set('total', total); set('paid', applied); set('due', due); const preview = form.querySelector('[data-payment-preview]'); if (preview) preview.innerHTML = `Amount applied: Rs. ${money(applied)} <span>${received > applied ? 'Change: Rs. ' + money(received - applied) : 'Balance: Rs. ' + money(due)}</span>`; };
    form.querySelector('[data-add-service]')?.addEventListener('click', () => addRow('service')); form.querySelector('[data-add-stock]')?.addEventListener('click', () => addRow('stock_part')); form.querySelector('[data-add-custom]')?.addEventListener('click', () => addRow('custom')); form.querySelectorAll('[data-charge],[data-discount-type],[data-discount-value],[data-payment-received]').forEach(node => node.addEventListener('input', recalculate));
    const customer = form.querySelector('[data-invoice-customer]'); const vehicle = form.querySelector('[data-invoice-vehicle]'); customer?.addEventListener('change', () => { if (!vehicle) return; Array.from(vehicle.options).forEach(option => { option.hidden = option.value !== '0' && option.dataset.customer !== customer.value; }); if (vehicle.selectedOptions[0]?.hidden) vehicle.value = '0'; }); customer?.dispatchEvent(new Event('change')); recalculate();
})();
