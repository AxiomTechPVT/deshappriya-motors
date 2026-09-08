document.addEventListener('DOMContentLoaded', () => {
    const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const jobVehicle = document.querySelector('#job-vehicle');
    if (jobVehicle) {
        jobVehicle.required = false;
        jobVehicle.classList.add('visually-hidden');
        jobVehicle.parentElement.classList.add('job-search-field');
        const search = document.createElement('input');
        search.className = 'form-control job-live-search';
        search.placeholder = 'Search vehicle number or customer name...';
        search.autocomplete = 'off';
        search.required = true;
        const results = document.createElement('div');
        results.className = 'job-live-results';
        jobVehicle.parentElement.insertBefore(search, jobVehicle);
        jobVehicle.parentElement.appendChild(results);
        let timer;
        const render = (rows) => {
            results.innerHTML = rows.map((row) => '<button type="button" class="job-live-result" data-vehicle-id="' + row.id + '"><strong>' + row.vehicle_number + '</strong><span>' + row.customer_name + ' · ' + (row.make || '') + ' ' + (row.model || '') + '</span></button>').join('');
            results.hidden = rows.length === 0;
        };
        search.addEventListener('input', () => {
            clearTimeout(timer);
            const query = search.value.trim();
            if (query.length < 2) { results.hidden = true; return; }
            timer = setTimeout(() => fetch('index.php?page=admin&section=jobcards-search&q=' + encodeURIComponent(query)).then((response) => response.json()).then(render).catch(() => { results.hidden = true; }), 180);
        });
        results.addEventListener('click', (event) => {
            const result = event.target.closest('[data-vehicle-id]');
            if (!result) return;
            jobVehicle.value = result.dataset.vehicleId;
            const option = jobVehicle.querySelector('option[value="' + result.dataset.vehicleId + '"]');
            search.value = option ? option.textContent.trim() : result.querySelector('strong').textContent;
            results.hidden = true;
            jobVehicle.dispatchEvent(new Event('change', { bubbles: true }));
        });
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.job-search-field')) results.hidden = true;
        });
    }
    const jobForm = document.querySelector('#jobcard-form');
    if (jobForm) {
        const complaint = jobForm.querySelector('[name="complaint"]');
        const bay = jobForm.querySelector('[name="bay_name"]');
        const mechanic = jobForm.querySelector('[name="mechanic_id"]');
        if (complaint) complaint.required = true;
        if (bay) bay.required = false;
        jobForm.addEventListener('submit', (event) => {
            if (event.submitter && event.submitter.value === 'start' && (!bay.value || !mechanic.value || mechanic.value === '0')) {
                event.preventDefault();
                alert('Select both a working bay and a mechanic before starting the job.');
                if (!bay.value) bay.focus(); else mechanic.focus();
            }
        });
        const complaintField = jobForm.querySelector('[name="complaint"]');
        const workField = jobForm.querySelector('[name="requested_work"]');
        const isNewJobCard = new URLSearchParams(window.location.search).get('section') === 'jobcards-new';
        if (isNewJobCard && complaintField && !jobForm.querySelector('[name="primary_service_id"]')) {
            const serviceRow = document.createElement('div');
            serviceRow.className = 'job-service-picker';
            serviceRow.innerHTML = '<label class="form-label">Service / Complaint <input class="form-control" id="primary-service-input" list="saved-job-services" placeholder="Select a saved service or type a custom service"></label><datalist id="saved-job-services"></datalist><label class="form-label service-custom-price">Price (Rs.) <input class="form-control" type="number" name="primary_service_price" value="0" min="0" step="0.01"></label><small>Choose a saved service or type your own service in the same field. Saved prices fill automatically.</small><input type="hidden" name="primary_service_id" value=""><input type="hidden" name="primary_service_name" value="">';
            complaintField.closest('.col-md-5')?.prepend(serviceRow);
            const serviceInput = serviceRow.querySelector('#primary-service-input');
            const serviceList = serviceRow.querySelector('datalist');
            const servicePrice = serviceRow.querySelector('[name="primary_service_price"]');
            fetch('index.php?page=admin&section=jobcards-services').then((response) => response.json()).then((services) => {
                services.forEach((service) => {
                    const option = document.createElement('option');
                    option.value = service.service_name;
                    option.label = service.service_code + ' - Rs. ' + Number(service.price || 0).toFixed(2);
                    serviceList.appendChild(option);
                });
                serviceInput.addEventListener('input', () => {
                    const value = serviceInput.value.trim().toLowerCase();
                    const service = services.find((item) => item.service_name.toLowerCase() === value || item.service_code.toLowerCase() === value);
                    const hiddenId = serviceRow.querySelector('[name="primary_service_id"]');
                    const hiddenName = serviceRow.querySelector('[name="primary_service_name"]');
                    if (service) {
                        hiddenId.value = service.id;
                        hiddenName.value = '';
                        servicePrice.value = Number(service.price || 0).toFixed(2);
                        if (!complaintField.value.trim() || complaintField.dataset.serviceAutofill === 'true') { complaintField.value = service.description || service.service_name; complaintField.dataset.serviceAutofill = 'true'; }
                        if (workField && (!workField.value.trim() || workField.dataset.serviceAutofill === 'true')) { workField.value = service.service_name; workField.dataset.serviceAutofill = 'true'; }
                    } else {
                        hiddenId.value = '';
                        hiddenName.value = serviceInput.value.trim();
                        if (serviceInput.value.trim() && (!complaintField.value.trim() || complaintField.dataset.serviceAutofill === 'true')) { complaintField.value = serviceInput.value.trim(); complaintField.dataset.serviceAutofill = 'true'; }
                    }
                });
            }).catch(() => {});
            complaintField.addEventListener('input', () => { complaintField.dataset.serviceAutofill = 'false'; });
            workField?.addEventListener('input', () => { workField.dataset.serviceAutofill = 'false'; });
        }
    }
    document.querySelectorAll('form[action*="section=jobcards-action"]').forEach((form) => {
        if (form.closest('.job-start-screen')) return;
        const action = form.querySelector('input[name="action"]');
        if (!action || action.value !== 'start') return;
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const cardId = form.querySelector('[name="job_card_id"]');
            if (cardId && cardId.value) {
                window.location.href = 'index.php?page=admin&section=jobcards-start&id=' + encodeURIComponent(cardId.value);
                return;
            }
            fetch('index.php?page=admin&section=jobcards-start-options').then((response) => response.json()).then((options) => {
                const escape = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
                const backdrop = document.createElement('div');
                backdrop.className = 'job-start-modal-backdrop';
                backdrop.innerHTML = '<section class="job-start-modal"><h2>Start Job Card</h2><p>Select the working bay and mechanic before starting this job.</p><form method="post" action="' + form.action + '"><input type="hidden" name="csrf_token" value="' + escape(form.querySelector('[name="csrf_token"]').value) + '"><input type="hidden" name="job_card_id" value="' + escape(form.querySelector('[name="job_card_id"]').value) + '"><input type="hidden" name="action" value="start"><label>Bay / Working Bay *<select class="form-select" name="bay_name" required><option value="">Select bay</option>' + options.bays.map((bay) => '<option>' + escape(bay.bay_name) + '</option>').join('') + '</select></label><label>Mechanic *<select class="form-select" name="mechanic_id" required><option value="">Select mechanic</option>' + options.mechanics.map((mechanic) => '<option value="' + escape(mechanic.id) + '">' + escape(mechanic.name) + '</option>').join('') + '</select></label><div class="job-start-modal-actions"><button type="button" class="btn btn-light" data-start-close>Cancel</button><button class="btn btn-primary">Start Job</button></div></form></section>';
                document.body.appendChild(backdrop);
                backdrop.querySelector('[data-start-close]').addEventListener('click', () => backdrop.remove());
                backdrop.addEventListener('click', (click) => { if (click.target === backdrop) backdrop.remove(); });
            });
        });
    });
    if (new URLSearchParams(window.location.search).get('section') === 'jobcards-pending') {
        const csrf = document.querySelector('input[name="csrf_token"]');
        document.querySelectorAll('.jobcard-list-table .job-row-actions').forEach((actions) => {
            const view = actions.querySelector('a[href*="section=jobcards-view"]');
            if (!view || !csrf || actions.querySelector('.job-cancel-button')) return;
            const id = new URL(view.href).searchParams.get('id');
            if (!id) return;
            const cancel = document.createElement('form');
            cancel.method = 'post';
            cancel.action = 'index.php?page=admin&section=jobcards-action';
            cancel.innerHTML = '<input type="hidden" name="csrf_token" value="' + csrf.value + '"><input type="hidden" name="job_card_id" value="' + id + '"><input type="hidden" name="action" value="cancel"><button class="btn btn-light btn-sm job-cancel-button" type="submit">Cancel</button>';
            cancel.addEventListener('submit', (event) => {
                if (!window.confirm('Cancel this pending job card?')) event.preventDefault();
            });
            actions.append(cancel);
        });
    }
    const manualPartButton = document.querySelector('[data-add-manual]');
    if (manualPartButton) {
        manualPartButton.addEventListener('click', () => {
            if (document.querySelector('[data-manual-part-modal]')) return;
            const cardId = document.querySelector('input[name="job_card_id"]')?.value || '';
            const csrf = document.querySelector('input[name="csrf_token"]')?.value || '';
            const backdrop = document.createElement('div');
            backdrop.className = 'job-item-modal-backdrop';
            backdrop.dataset.manualPartModal = 'true';
            backdrop.innerHTML = '<section class="job-start-modal manual-part-modal"><div class="manual-part-modal-heading"><div><span class="modal-eyebrow">EXTERNAL PART</span><h2>Add Manual Part</h2><p>Buying price will be recorded as an expense. Selling price will be charged on this job card.</p></div><button type="button" class="customer-modal-close" data-manual-close>&times;</button></div><form method="post" action="index.php?page=admin&section=jobcards-item-add"><input type="hidden" name="csrf_token" value="' + escapeHtml(csrf) + '"><input type="hidden" name="job_card_id" value="' + escapeHtml(cardId) + '"><input type="hidden" name="item_type" value="manual"><div class="manual-part-fields"><label>Part Name *<input class="form-control" name="item_name" placeholder="e.g. Brake pad set" required autofocus></label><label>Quantity *<input class="form-control manual-part-quantity" type="number" name="quantity" value="1" min="0.01" step="0.01" required></label><label>Buying Price / Unit (Rs.) *<input class="form-control manual-part-buying" type="number" name="buying_price" value="0" min="0" step="0.01" required></label><label>Selling Price / Unit (Rs.) *<input class="form-control manual-part-price" type="number" name="unit_price" value="0" min="0" step="0.01" required></label><div class="manual-part-total"><span>Customer Amount</span><strong>Rs. <b data-manual-total>0.00</b></strong></div></div><div class="job-start-modal-actions"><button type="button" class="btn btn-light" data-manual-close>Cancel</button><button class="btn btn-primary" type="submit">Add Part &amp; Expense</button></div></form></section>';
            document.body.appendChild(backdrop);
            const quantity = backdrop.querySelector('.manual-part-quantity');
            const price = backdrop.querySelector('.manual-part-price');
            const total = backdrop.querySelector('[data-manual-total]');
            const updateTotal = () => { total.textContent = ((parseFloat(quantity.value) || 0) * (parseFloat(price.value) || 0)).toFixed(2); };
            quantity.addEventListener('input', updateTotal);
            price.addEventListener('input', updateTotal);
            backdrop.querySelectorAll('[data-manual-close]').forEach((button) => button.addEventListener('click', () => backdrop.remove()));
            backdrop.addEventListener('click', (event) => { if (event.target === backdrop) backdrop.remove(); });
        });
    }
    if (new URLSearchParams(window.location.search).get('section') === 'jobcards-view') {
        const cardId = new URLSearchParams(window.location.search).get('id');
        const amountBox = document.querySelector('.ongoing-amount');
        if (cardId && amountBox) {
            fetch('index.php?page=admin&section=jobcards-payment-summary&id=' + encodeURIComponent(cardId)).then((response) => response.json()).then((summary) => {
                if (summary.error) return;
                const csrf = document.querySelector('input[name="csrf_token"]')?.value || '';
                const charge = document.createElement('form');
                charge.className = 'job-service-charge-form';
                charge.method = 'post';
                charge.action = 'index.php?page=admin&section=jobcards-service-charge&id=' + encodeURIComponent(cardId);
                charge.innerHTML = '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrf) + '"><input type="hidden" name="job_card_id" value="' + escapeHtml(cardId) + '"><label>Service Charge (Rs.)<div class="job-inline-input"><input class="form-control" type="number" name="service_charge" min="0" step="0.01" value="' + Number(summary.service_charge || 0).toFixed(2) + '"><button class="btn btn-light" type="submit">Save</button></div></label>';
                amountBox.prepend(charge);
                const paymentButton = amountBox.querySelector('.btn-primary');
                if (paymentButton) paymentButton.addEventListener('click', () => {
                    const backdrop = document.createElement('div');
                    backdrop.className = 'job-item-modal-backdrop';
                    const due = Number(summary.balance || 0);
                    const fullTotal = Number(summary.total || 0);
                    const payable = due > 0.009 ? due : fullTotal;
                    if (payable <= 0.009) {
                        window.alert('Add at least one spare part or service charge before collecting payment. The current job total is Rs. 0.00.');
                        return;
                    }
                    const startingAmount = payable;
                    const currentDateTime = () => { const date = new Date(); const pad = (value) => String(value).padStart(2, '0'); return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes()); };
                    const savedStart = summary.performance?.start_time ? summary.performance.start_time.replace(' ', 'T').slice(0, 16) : (summary.started_at ? summary.started_at.replace(' ', 'T').slice(0, 16) : currentDateTime());
                    const savedEnd = summary.performance?.end_time ? summary.performance.end_time.replace(' ', 'T').slice(0, 16) : currentDateTime();
                    backdrop.innerHTML = '<section class="job-start-modal payment-modal"><div class="manual-part-modal-heading"><div><span class="modal-eyebrow">PAYMENT</span><h2>Collect Payment</h2><p>Enter work times and the amount received before generating the bill.</p></div><button type="button" class="customer-modal-close" data-payment-close>&times;</button></div><form method="post" action="index.php?page=admin&section=jobcards-payment"><input type="hidden" name="csrf_token" value="' + escapeHtml(csrf) + '"><input type="hidden" name="job_card_id" value="' + escapeHtml(cardId) + '"><div class="payment-due-card"><span>Full Job Amount</span><strong>Rs. ' + fullTotal.toFixed(2) + '</strong><small>Outstanding: Rs. ' + due.toFixed(2) + '</small></div><div class="performance-time-grid"><label>Start Time *<input class="form-control" type="datetime-local" name="performance_start" value="' + escapeHtml(savedStart) + '" required></label><label>End Time *<input class="form-control" type="datetime-local" name="performance_end" value="' + escapeHtml(savedEnd) + '" required></label></div><label>Amount Received (Rs.) *<input class="form-control payment-received" type="number" name="amount" value="' + startingAmount.toFixed(2) + '" min="0.01" step="0.01" required></label><label>Payment Method<select class="form-select payment-method" name="payment_method"><option value="cash">Cash</option><option value="card">Card</option><option value="bank">Bank Transfer</option><option value="other">Other</option></select></label><div class="payment-calculation"><div><span>Applied Amount</span><strong data-payment-applied>Rs. ' + payable.toFixed(2) + '</strong></div><div><span>Change / Balance</span><strong data-payment-change>Rs. 0.00</strong></div></div><div class="job-start-modal-actions"><button type="button" class="btn btn-light" data-payment-close>Close</button><button class="btn btn-primary" type="submit">Pay &amp; Generate Receipt</button></div></form></section>';
                    document.body.appendChild(backdrop);
                    const received = backdrop.querySelector('.payment-received');
                    const method = backdrop.querySelector('.payment-method');
                    const applied = backdrop.querySelector('[data-payment-applied]');
                    const change = backdrop.querySelector('[data-payment-change]');
                    const updatePayment = () => { const value = Math.max(0, parseFloat(received.value) || 0); const appliedValue = Math.min(value, payable); const difference = value - payable; applied.textContent = 'Rs. ' + appliedValue.toFixed(2); change.textContent = difference >= 0 ? 'Change: Rs. ' + difference.toFixed(2) : 'Balance: Rs. ' + Math.abs(difference).toFixed(2); change.className = difference >= 0 ? 'payment-change-positive' : 'payment-change-due'; };
                    received.addEventListener('input', updatePayment);
                    method.addEventListener('change', () => { received.max = method.value === 'cash' ? '' : payable.toFixed(2); updatePayment(); });
                    updatePayment();
                    backdrop.querySelectorAll('[data-payment-close]').forEach((button) => button.addEventListener('click', () => backdrop.remove()));
                });
            });
        }
    }
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

    const employeeForm = document.querySelector('.employee-form-panel form');
    if (employeeForm && !employeeForm.querySelector('[name="paid_leave_days"]')) {
        const salaryField = employeeForm.querySelector('[name="salary"]');
        if (salaryField) {
            const leaveField = document.createElement('div');
            leaveField.className = 'col-md-6';
            leaveField.innerHTML = '<label class="form-label">Paid Leave Days / Month</label><input class="form-control" name="paid_leave_days" type="number" min="0" step="0.5" value="0"><small class="text-muted">These leave days will not be deducted from salary.</small>';
            salaryField.closest('.col-md-6')?.parentNode.insertBefore(leaveField, salaryField.closest('.col-md-6').nextSibling);
        }
    }

    const salaryEmployee = document.querySelector('#salary-employee');
    const salaryOutstanding = document.querySelector('#salary-outstanding-summary');
    const salaryNoPaySummary = document.querySelector('#salary-no-pay-summary');
    const salaryBasic = document.querySelector('#salary-basic');
    const advanceWrap = document.querySelector('#salary-advance-deduction-wrap');
    const loanWrap = document.querySelector('#salary-loan-deduction-wrap');
    const advanceInput = document.querySelector('#salary-advance-deduction');
    const loanInput = document.querySelector('#salary-loan-deduction');
    if (salaryEmployee && salaryOutstanding) {
        const updateSalaryOutstanding = () => {
            const option = salaryEmployee.options[salaryEmployee.selectedIndex];
            const advance = Number(option?.dataset.advance || 0);
            const loan = Number(option?.dataset.loan || 0);
            const unpaidDays = Number(option?.dataset.unpaidDays || 0);
            const attendanceDeduction = Number(option?.dataset.attendanceDeduction || 0);
            salaryOutstanding.innerHTML = '<div><span>Outstanding Advance</span><strong>' + advance.toFixed(2) + '</strong></div><div><span>Outstanding Loan</span><strong>' + loan.toFixed(2) + '</strong></div>';
            if (salaryNoPaySummary) salaryNoPaySummary.innerHTML = '<div><span>No-pay Leave Days</span><strong>' + unpaidDays.toFixed(2) + '</strong></div><div><span>No-pay Leave Deduction</span><strong>' + attendanceDeduction.toFixed(2) + '</strong></div>';
            if (salaryBasic && option?.dataset.basic !== undefined) salaryBasic.value = Number(option.dataset.basic || 0).toFixed(2);
            if (advanceWrap) advanceWrap.hidden = advance <= 0;
            if (loanWrap) loanWrap.hidden = loan <= 0;
            if (advanceInput && advance <= 0) advanceInput.value = '0';
            if (loanInput && loan <= 0) loanInput.value = '0';
            if (advanceInput && advance > 0 && Number(advanceInput.value) > advance) advanceInput.value = advance.toFixed(2);
            if (loanInput && loan > 0 && Number(loanInput.value) > loan) loanInput.value = loan.toFixed(2);
        };
        salaryEmployee.addEventListener('change', updateSalaryOutstanding);
        updateSalaryOutstanding();
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

    const serviceModal = document.querySelector('[data-edit-service-modal]');
    const openServiceModal = document.querySelector('[data-open-edit-service]');
    if (serviceModal && openServiceModal) {
        const closeServiceModal = () => {
            serviceModal.hidden = true;
            document.body.classList.remove('modal-open');
        };
        openServiceModal.addEventListener('click', () => {
            serviceModal.hidden = false;
            document.body.classList.add('modal-open');
        });
        serviceModal.querySelectorAll('[data-close-edit-service]').forEach((button) => button.addEventListener('click', closeServiceModal));
        serviceModal.addEventListener('click', (event) => {
            if (event.target === serviceModal) closeServiceModal();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !serviceModal.hidden) closeServiceModal();
        });
    }

    const stockNote = document.querySelector('.preview-note p');
    if (stockNote && stockNote.textContent.includes('deducted from stock')) {
        stockNote.textContent = 'Parts are reserved from stock when added. Removing a part returns it to stock.';
    }
});
