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

    const supplierForm = document.querySelector('.supplier-form-card form');
    if (supplierForm) {
        const actions = supplierForm.querySelector('.customer-form-actions');
        if (actions) {
            const productSection = document.createElement('section');
            productSection.className = 'supplier-products-section';
            productSection.innerHTML = '<div class="supplier-products-heading"><div><h2>Products from this Supplier</h2><p>Add the products/items you purchase from this supplier.</p></div><button type="button" class="btn btn-outline-primary" data-add-supplier-product>+ Add Product</button></div><div class="supplier-product-header"><span>Product Code</span><span>Product Name *</span><span>Category</span><span>Unit</span><span>Buying Price</span><span>Selling Price</span><span>Opening Qty</span><span>Reorder Level</span><span></span></div><div data-supplier-products></div>';
            actions.parentNode.insertBefore(productSection, actions);
            const productList = productSection.querySelector('[data-supplier-products]');
            const addProduct = productSection.querySelector('[data-add-supplier-product]');
            const addProductRow = () => {
                const index = productList.children.length;
                productList.insertAdjacentHTML('beforeend', '<div class="supplier-product-row"><input class="form-control" name="products[' + index + '][code]" placeholder="PRD-001"><input class="form-control" name="products[' + index + '][name]" placeholder="Product name"><input class="form-control" name="products[' + index + '][category]" placeholder="Category"><input class="form-control" name="products[' + index + '][unit]" value="piece"><input class="form-control" name="products[' + index + '][buying_price]" type="number" min="0" step="0.01" value="0"><input class="form-control" name="products[' + index + '][selling_price]" type="number" min="0" step="0.01" value="0"><input class="form-control" name="products[' + index + '][quantity]" type="number" min="0" step="0.01" value="0"><input class="form-control" name="products[' + index + '][reorder_level]" type="number" min="0" step="0.01" value="0"><button type="button" class="btn btn-light supplier-product-remove" aria-label="Remove product">x</button></div>');
            };
            addProduct.addEventListener('click', addProductRow);
            productList.addEventListener('click', (event) => {
                const button = event.target.closest('.supplier-product-remove');
                if (button) button.closest('.supplier-product-row').remove();
            });
            addProductRow();
        }
    }

    let supplierModal = document.querySelector('[data-supplier-modal]');
    const supplierButtons = document.querySelectorAll('.supplier-view-products');
    if (!supplierModal && supplierButtons.length) {
        document.body.insertAdjacentHTML('beforeend', '<div class="supplier-products-modal" data-supplier-modal hidden><div class="supplier-products-dialog"><button type="button" class="supplier-modal-close" data-close-supplier-modal aria-label="Close">x</button><span class="modal-eyebrow">Supplier Details</span><h2 data-supplier-modal-title>Supplier</h2><div class="supplier-modal-details" data-supplier-modal-details></div><h3 class="supplier-modal-products-heading">Products</h3><div data-supplier-modal-list></div></div></div>');
        supplierModal = document.querySelector('[data-supplier-modal]');
    }
    if (supplierModal) {
        const modalTitle = supplierModal.querySelector('[data-supplier-modal-title]');
        const modalList = supplierModal.querySelector('[data-supplier-modal-list]');
        const modalDetails = supplierModal.querySelector('[data-supplier-modal-details]');
        const closeSupplierModal = () => { supplierModal.hidden = true; };
        document.querySelectorAll('.supplier-view-products').forEach((button) => {
            button.addEventListener('click', () => {
                const products = JSON.parse(button.dataset.supplierProducts || '[]');
                modalTitle.textContent = button.dataset.supplierName || 'Supplier Products';
                modalDetails.innerHTML = '<div><span>Contact Person</span><strong>' + button.dataset.supplierContact + '</strong></div><div><span>Phone</span><strong>' + button.dataset.supplierPhone + '</strong></div><div><span>Email</span><strong>' + button.dataset.supplierEmail + '</strong></div><div><span>Status</span><strong>' + button.dataset.supplierStatus + '</strong></div>';
                modalList.innerHTML = products.length ? products.map((product) => '<div class="supplier-modal-product"><div><strong>' + product.product_name + '</strong><small>' + (product.product_code || 'No code') + '</small></div><span>' + Number(product.buying_price).toFixed(2) + '</span></div>').join('') : '<p class="empty-note">No products have been added for this supplier.</p>';
                supplierModal.hidden = false;
            });
        });
        supplierModal.querySelector('[data-close-supplier-modal]')?.addEventListener('click', closeSupplierModal);
        supplierModal.addEventListener('click', (event) => { if (event.target === supplierModal) closeSupplierModal(); });
    }

    const loanButtons = document.querySelectorAll('.loan-view-button');
    if (loanButtons.length) {
        document.body.insertAdjacentHTML('beforeend', '<div class="supplier-products-modal" data-loan-modal hidden><div class="supplier-products-dialog"><button type="button" class="supplier-modal-close" data-close-loan-modal aria-label="Close">x</button><span class="modal-eyebrow">Staff Loan</span><h2 data-loan-title>Loan Details</h2><div class="supplier-modal-details" data-loan-details></div></div></div>');
        const loanModal = document.querySelector('[data-loan-modal]');
        const loanDetails = loanModal.querySelector('[data-loan-details]');
        const closeLoanModal = () => { loanModal.hidden = true; };
        loanButtons.forEach((button) => button.addEventListener('click', () => {
            loanModal.querySelector('[data-loan-title]').textContent = button.dataset.loanEmployee || 'Loan Details';
            loanDetails.innerHTML = '<div><span>Employee ID</span><strong>' + button.dataset.loanCode + '</strong></div><div><span>Loan Date</span><strong>' + button.dataset.loanDate + '</strong></div><div><span>Amount</span><strong>' + button.dataset.loanAmount + '</strong></div><div><span>Balance</span><strong>' + button.dataset.loanBalance + '</strong></div><div><span>Status</span><strong>' + button.dataset.loanStatus + '</strong></div><div><span>Reason</span><strong>' + button.dataset.loanReason + '</strong></div>';
            loanModal.hidden = false;
        }));
        loanModal.querySelector('[data-close-loan-modal]')?.addEventListener('click', closeLoanModal);
        loanModal.addEventListener('click', (event) => { if (event.target === loanModal) closeLoanModal(); });
    }

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
