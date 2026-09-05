document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('[data-sidebar]');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    if (sidebar && toggle) toggle.addEventListener('click', () => sidebar.classList.toggle('open'));

    const vehicleList = document.querySelector('[data-vehicle-list]');
    const addVehicle = document.querySelector('[data-add-vehicle]');
    if (vehicleList && addVehicle) {
        addVehicle.addEventListener('click', () => {
            const entries = vehicleList.querySelectorAll('[data-vehicle-entry]');
            const nextIndex = entries.length;
            const clone = entries[0].cloneNode(true);
            clone.querySelectorAll('input, textarea, select').forEach((field) => {
                field.value = '';
                field.name = field.name.replace(/vehicles\[\d+\]/, `vehicles[${nextIndex}]`);
            });
            clone.querySelector('[data-vehicle-number]').textContent = nextIndex + 1;
            const removeButton = clone.querySelector('[data-remove-vehicle]');
            if (!removeButton) {
                const heading = clone.querySelector('.vehicle-entry-heading');
                heading.insertAdjacentHTML('beforeend', '<button type="button" class="remove-vehicle" data-remove-vehicle>Remove</button>');
            }
            vehicleList.appendChild(clone);
        });
        vehicleList.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-remove-vehicle]');
            if (!removeButton) return;
            removeButton.closest('[data-vehicle-entry]').remove();
            vehicleList.querySelectorAll('[data-vehicle-entry]').forEach((entry, index) => {
                entry.querySelector('[data-vehicle-number]').textContent = index + 1;
                entry.querySelectorAll('input, textarea, select').forEach((field) => {
                    field.name = field.name.replace(/vehicles\[\d+\]/, `vehicles[${index}]`);
                });
            });
        });
    }
    const addVehicleLink = document.querySelector('.vehicle-heading .customer-add-button[href="#vehicle-form"]');
    if (addVehicleLink) addVehicleLink.href = 'index.php?page=admin&section=vehicles-add';

    const jobVehicleSelect = document.querySelector('.jobcard-vehicle-select');
    if (jobVehicleSelect) {
        const jobVehicles = JSON.parse(document.querySelector('#jobcard-vehicle-data')?.textContent || '[]');
        const lookup = document.querySelector('.jobcard-vehicle-lookup');
        const search = document.createElement('input');
        const datalist = document.createElement('datalist');
        const searchId = 'jobcard-vehicle-search-options';
        search.type = 'search';
        search.className = 'form-control jobcard-vehicle-search';
        search.setAttribute('list', searchId);
        search.setAttribute('autocomplete', 'off');
        search.placeholder = 'Search vehicle number or customer name...';
        search.required = true;
        datalist.id = searchId;
        jobVehicles.forEach((vehicle) => {
            [
                vehicle.vehicle_number + ' - ' + vehicle.customer_name,
                vehicle.customer_name + ' - ' + vehicle.vehicle_number,
            ].forEach((label) => {
                const option = document.createElement('option');
                option.value = label;
                datalist.appendChild(option);
            });
        });
        if (lookup) {
            lookup.insertBefore(search, jobVehicleSelect);
            lookup.insertBefore(datalist, jobVehicleSelect);
        }
        jobVehicleSelect.style.display = 'none';
        const info = document.querySelector('[data-jobcard-vehicle-info]');
        const setField = (selector, value) => { const field = document.querySelector(selector); if (!field) return; if ('value' in field) field.value = value || ''; else field.textContent = value || 'Select vehicle'; };
        const findVehicle = (value) => {
            const query = value.trim().toLowerCase();
            if (!query) return null;
            const exactLabel = jobVehicles.find((vehicle) => [
                vehicle.vehicle_number + ' - ' + vehicle.customer_name,
                vehicle.customer_name + ' - ' + vehicle.vehicle_number,
            ].some((label) => label.toLowerCase() === query));
            if (exactLabel) return exactLabel;
            return jobVehicles.find((vehicle) => [
                vehicle.vehicle_number,
                vehicle.customer_name,
            ].some((field) => String(field || '').toLowerCase() === query)) || null;
        };
        const updateJobVehicle = () => {
            const selected = findVehicle(search.value);
            jobVehicleSelect.value = selected ? selected.id : '';
            if (info) info.textContent = selected ? 'Vehicle & customer details loaded successfully.' : 'Select a vehicle to load customer and vehicle details.';
            setField('[data-jobcard-customer]', selected?.customer_name);
            setField('[data-jobcard-email]', selected?.email);
            setField('[data-jobcard-address]', selected?.address);
            setField('[data-jobcard-number]', selected?.vehicle_number);
            setField('[data-jobcard-type]', selected?.vehicle_type);
            setField('[data-jobcard-model]', [selected?.make, selected?.model].filter(Boolean).join(' '));
            setField('[data-jobcard-colour]', selected?.colour);
            setField('[data-jobcard-mileage]', [selected?.year, selected?.current_mileage ? `${selected.current_mileage} KM` : ''].filter(Boolean).join('  |  '));
            setField('[data-preview-customer]', selected?.customer_name);
            setField('[data-preview-contact]', selected?.contact_number);
            setField('[data-preview-vehicle]', selected?.vehicle_number);
            setField('[data-preview-vehicle-detail]', [selected?.vehicle_type, selected?.make, selected?.model, selected?.year].filter(Boolean).join('  |  '));
        };
        search.addEventListener('input', updateJobVehicle);
        search.addEventListener('change', updateJobVehicle);
        search.addEventListener('blur', updateJobVehicle);
        const initialVehicle = jobVehicles.find((vehicle) => String(vehicle.id) === jobVehicleSelect.value);
        if (initialVehicle) search.value = initialVehicle.vehicle_number + ' - ' + initialVehicle.customer_name;
        updateJobVehicle();
    }

    const addRepeatRow = (containerSelector, buttonSelector, template, removeSelector) => {
        const container = document.querySelector(containerSelector);
        const button = document.querySelector(buttonSelector);
        if (!container || !button) return;
        button.addEventListener('click', () => {
            const index = container.children.length;
            container.insertAdjacentHTML('beforeend', template.replaceAll('__INDEX__', index));
        });
        container.addEventListener('click', (event) => {
            const remove = event.target.closest(removeSelector);
            if (remove && container.children.length > 1) remove.closest('.jobcard-repeat-row, .jobcard-item-row').remove();
        });
    };
    addRepeatRow('[data-job-services]', '[data-add-service]', '<div class="jobcard-repeat-row"><input class="form-control" name="services[__INDEX__][name]" placeholder="Add service or work to be done..."><input class="form-control" name="services[__INDEX__][notes]" placeholder="Service notes (optional)"><button type="button" class="btn btn-light repeat-remove" data-remove-service>×</button></div>', '[data-remove-service]');
    addRepeatRow('[data-job-items]', '[data-add-job-item]', '<div class="jobcard-item-row"><select class="form-select" name="items[__INDEX__][type]"><option value="part">Part</option><option value="service">Labour / Service</option></select><input class="form-control" name="items[__INDEX__][name]" placeholder="Item / service name"><input class="form-control" name="items[__INDEX__][quantity]" type="number" min="0.01" step="0.01" value="1" placeholder="Qty"><input class="form-control" name="items[__INDEX__][unit_price]" type="number" min="0" step="0.01" value="0" placeholder="Unit price"><input class="form-control" name="items[__INDEX__][discount]" type="number" min="0" step="0.01" value="0" placeholder="Discount"><button type="button" class="btn btn-light repeat-remove" data-remove-item>×</button></div>', '[data-remove-item]');

    const vehicleActionData = document.querySelector('#vehicle-action-data');
    const vehicleRows = document.querySelectorAll('.vehicle-table tbody tr');
    if (vehicleActionData && vehicleRows.length) {
        const actionData = JSON.parse(vehicleActionData.textContent || '{}');
        vehicleRows.forEach((row, index) => {
            const vehicleId = actionData.ids?.[index];
            const actionCell = row.querySelector('td:last-child');
            const viewLink = row.querySelector('.vehicle-action');
            if (!vehicleId || !actionCell || !viewLink) return;
            viewLink.href = `index.php?page=admin&section=vehicles-view&id=${vehicleId}`;
            viewLink.title = 'View vehicle';
            const editLink = document.createElement('a');
            editLink.className = 'vehicle-action';
            editLink.href = `index.php?page=admin&section=vehicles-edit&id=${vehicleId}`;
            editLink.title = 'Edit vehicle';
            editLink.textContent = '✎';
            const removeForm = document.createElement('form');
            removeForm.method = 'post';
            removeForm.action = `index.php?page=admin&section=vehicles-remove&id=${vehicleId}`;
            removeForm.className = 'vehicle-remove-form';
            removeForm.innerHTML = `<input type="hidden" name="csrf_token" value="${actionData.csrf}"><button class="vehicle-action vehicle-remove" type="submit" title="Remove vehicle">×</button>`;
            removeForm.addEventListener('submit', (event) => {
                if (!window.confirm('Remove this vehicle from the active list?')) event.preventDefault();
            });
            actionCell.append(editLink, removeForm);
        });
    }

    const makeOptions = ['Toyota', 'Honda', 'Nissan', 'Suzuki', 'Mitsubishi', 'Mazda', 'BMW', 'Mercedes-Benz', 'Kia', 'Hyundai', 'Tata', 'Bajaj', 'TVS', 'Yamaha', 'Hero', 'KTM', 'Royal Enfield', 'Other'];
    const modelOptions = ['Axio', 'Aqua', 'Prius', 'Corolla', 'Vitz', 'Wagon R', 'Alto', 'Swift', 'Celerio', 'Civic', 'Fit', 'CR-V', 'March', 'Sunny', 'Leaf', 'Pulsar', 'Platina', 'CT 100', 'Apache', 'FZ', 'Dio', 'Gixxer', 'Three Wheeler', 'Other'];
    const addEditableOptions = (selector, id, options) => {
        let list = document.getElementById(id);
        if (!list) {
            list = document.createElement('datalist');
            list.id = id;
            options.forEach((option) => list.insertAdjacentHTML('beforeend', `<option value="${option}"></option>`));
            document.body.appendChild(list);
        }
        document.querySelectorAll(selector).forEach((field) => { field.setAttribute('list', id); });
    };
    addEditableOptions('input[name="make"], input[name*="[make]"]', 'vehicle-make-options', makeOptions);
    addEditableOptions('input[name="model"], input[name*="[model]"]', 'vehicle-model-options', modelOptions);

    const customerField = document.querySelector('select[name="customer_id"]');
    if (customerField && customerField.closest('.vehicle-add-card')) {
        const addCustomerButton = document.createElement('a');
        addCustomerButton.className = 'add-customer-inline-button';
        addCustomerButton.href = 'index.php?page=admin&section=customers-add';
        addCustomerButton.textContent = '+ Add New Customer';
        customerField.parentElement.appendChild(addCustomerButton);
    }
});
