document.addEventListener('DOMContentLoaded', function () {
    const customerSelect = document.getElementById('appointment-customer');
    const vehicleSelect = document.getElementById('appointment-vehicle');
    if (!customerSelect || !vehicleSelect) return;
    const customers = Array.from(customerSelect.options).filter(o => o.value);
    const vehicles = Array.from(vehicleSelect.options).filter(o => o.value !== '0');
    let customerPicker, vehiclePicker;

    function picker(select, options, placeholder, required, onSelect, onClear) {
        const wrapper = document.createElement('div');
        wrapper.className = 'appointment-search';
        const input = document.createElement('input');
        input.type = 'text'; input.className = 'form-control'; input.placeholder = placeholder;
        input.autocomplete = 'off'; input.required = required;
        input.id = select.id + '-search';
        input.setAttribute('aria-label', required ? 'Search customer' : 'Search vehicle');
        input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        const results = document.createElement('div');
        results.className = 'appointment-search-results'; results.id = select.id + '-results';
        results.setAttribute('role', 'listbox'); results.hidden = true;
        input.setAttribute('aria-controls', results.id);
        const details = document.createElement('small');
        details.className = 'appointment-search-details'; details.setAttribute('aria-live', 'polite');
        wrapper.append(input, results, details); select.after(wrapper);
        select.hidden = true; select.required = false;
        let matches = [], active = -1;
        function close() { results.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; }
        function sync() {
            const selected = options.find(o => o.value === select.value);
            input.value = selected ? selected.textContent.trim() : '';
            details.textContent = selected ? 'Selected: ' + selected.textContent.trim() : '';
            input.setCustomValidity(''); close();
        }
        function choose(option) { select.value = option.value; sync(); onSelect(option); }
        function show() {
            const term = input.value.trim().toLowerCase();
            matches = options.filter(o => !o.hidden && (!term || (o.textContent + ' ' + (o.dataset.search || '')).toLowerCase().includes(term))).slice(0, 30);
            results.replaceChildren(); active = -1; input.removeAttribute('aria-activedescendant');
            matches.forEach((option, index) => {
                const button = document.createElement('button'); button.type = 'button';
                button.textContent = option.textContent.trim(); button.id = results.id + '-' + index;
                button.setAttribute('role', 'option'); button.setAttribute('aria-selected', 'false');
                button.addEventListener('mousedown', e => e.preventDefault());
                button.addEventListener('click', () => choose(option)); results.append(button);
            });
            if (!matches.length) { const empty = document.createElement('div'); empty.textContent = 'No matching records found.'; results.append(empty); }
            results.hidden = false; input.setAttribute('aria-expanded', 'true');
        }
        input.addEventListener('focus', show);
        input.addEventListener('input', function () {
            select.value = required ? '' : '0'; details.textContent = '';
            input.setCustomValidity(input.value.trim() ? 'Select a matching result from the list.' : '');
            onClear(); show();
        });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { close(); return; }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault(); if (results.hidden) show();
                if (!matches.length) return;
                active = (active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length;
                Array.from(results.children).forEach((el, i) => el.setAttribute('aria-selected', String(i === active)));
                input.setAttribute('aria-activedescendant', results.children[active].id);
                results.children[active].scrollIntoView({block: 'nearest'});
            } else if (event.key === 'Enter' && !results.hidden) {
                event.preventDefault(); if (matches.length) choose(matches[active < 0 ? 0 : active]);
            }
        });
        wrapper.addEventListener('focusout', () => setTimeout(() => { if (!wrapper.contains(document.activeElement)) close(); }, 0));
        document.addEventListener('click', e => { if (!wrapper.contains(e.target)) close(); });
        sync(); return { sync };
    }
    function filterVehicles() {
        vehicles.forEach(option => { option.hidden = !!customerSelect.value && option.dataset.customerId !== customerSelect.value; });
        const selected = vehicles.find(o => o.value === vehicleSelect.value);
        if (selected && selected.hidden) { vehicleSelect.value = '0'; vehiclePicker.sync(); }
    }
    customerPicker = picker(customerSelect, customers, 'Type customer name or phone...', true, filterVehicles, function () {
        vehicleSelect.value = '0'; vehiclePicker.sync(); filterVehicles();
    });
    vehiclePicker = picker(vehicleSelect, vehicles, 'Type vehicle number, make or model...', false, function (option) {
        customerSelect.value = option.dataset.customerId; customerPicker.sync(); filterVehicles();
    }, function () {});
    filterVehicles();
});
