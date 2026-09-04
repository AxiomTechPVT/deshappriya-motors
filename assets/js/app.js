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
});
