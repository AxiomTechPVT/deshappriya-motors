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
