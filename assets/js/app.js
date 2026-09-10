document.addEventListener('DOMContentLoaded', () => {
    const vehicleRemoveDialog = document.querySelector('[data-vehicle-remove-confirm]');
    if (vehicleRemoveDialog) {
        let pendingRemoveForm = null;
        const yesButton = vehicleRemoveDialog.querySelector('[data-vehicle-remove-yes]');
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!form.matches('[data-vehicle-remove-form]')) return;
            event.preventDefault();
            if (vehicleRemoveDialog.open) return;
            pendingRemoveForm = form;
            yesButton.disabled = false;
            vehicleRemoveDialog.querySelector('[data-vehicle-remove-message]').textContent =
                'Remove ' + form.dataset.vehicleNumber + ' from the active list?';
            vehicleRemoveDialog.showModal();
        });
        vehicleRemoveDialog.querySelector('[data-vehicle-remove-no]').addEventListener('click', () => vehicleRemoveDialog.close());
        vehicleRemoveDialog.addEventListener('close', () => { pendingRemoveForm = null; });
        yesButton.addEventListener('click', () => {
            if (!pendingRemoveForm || yesButton.disabled) return;
            yesButton.disabled = true;
            const confirmation = document.createElement('input');
            confirmation.type = 'hidden';
            confirmation.name = 'confirm_remove';
            confirmation.value = 'yes';
            pendingRemoveForm.appendChild(confirmation);
            HTMLFormElement.prototype.submit.call(pendingRemoveForm);
        });
    }
    const customerStatusDialog = document.querySelector('[data-customer-status-confirm]');
    if (customerStatusDialog) {
        let pendingStatusForm = null;
        const yesButton = customerStatusDialog.querySelector('[data-customer-status-yes]');
        document.querySelectorAll('[data-customer-status-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                if (customerStatusDialog.open) return;
                pendingStatusForm = form;
                yesButton.disabled = false;
                const action = form.dataset.customerStatus === 'active' ? 'deactivate' : 'activate';
                customerStatusDialog.querySelector('[data-customer-status-message]').textContent =
                    'Are you sure you want to ' + action + ' ' + form.dataset.customerName + '?';
                customerStatusDialog.showModal();
            });
        });
        customerStatusDialog.querySelector('[data-customer-status-no]').addEventListener('click', () => customerStatusDialog.close());
        customerStatusDialog.addEventListener('close', () => { pendingStatusForm = null; });
        yesButton.addEventListener('click', () => {
            if (!pendingStatusForm || yesButton.disabled) return;
            yesButton.disabled = true;
            const confirmation = document.createElement('input');
            confirmation.type = 'hidden';
            confirmation.name = 'confirm_status';
            confirmation.value = 'yes';
            pendingStatusForm.appendChild(confirmation);
            HTMLFormElement.prototype.submit.call(pendingStatusForm);
        });
    }
    const vehicleUpdateForm = document.querySelector('[data-vehicle-update-form]');
    const vehicleUpdateDialog = document.querySelector('[data-vehicle-update-confirm]');
    if (vehicleUpdateForm && vehicleUpdateDialog) {
        vehicleUpdateForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!vehicleUpdateDialog.open) vehicleUpdateDialog.showModal();
        });
        vehicleUpdateDialog.querySelector('[data-vehicle-update-no]').addEventListener('click', () => vehicleUpdateDialog.close());
        vehicleUpdateDialog.querySelector('[data-vehicle-update-yes]').addEventListener('click', (event) => {
            if (!vehicleUpdateForm.reportValidity()) {
                vehicleUpdateDialog.close();
                return;
            }
            event.currentTarget.disabled = true;
            const confirmation = document.createElement('input');
            confirmation.type = 'hidden';
            confirmation.name = 'confirm_update';
            confirmation.value = 'yes';
            vehicleUpdateForm.appendChild(confirmation);
            HTMLFormElement.prototype.submit.call(vehicleUpdateForm);
        });
    }
    const customerUpdateForm = document.querySelector('[data-customer-update-form]');
    const customerUpdateDialog = document.querySelector('[data-customer-update-confirm]');
    if (customerUpdateForm && customerUpdateDialog) {
        customerUpdateForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!customerUpdateDialog.open) customerUpdateDialog.showModal();
        });
        customerUpdateDialog.querySelector('[data-customer-update-no]').addEventListener('click', () => customerUpdateDialog.close());
        customerUpdateDialog.querySelector('[data-customer-update-yes]').addEventListener('click', (event) => {
            if (!customerUpdateForm.reportValidity()) {
                customerUpdateDialog.close();
                return;
            }
            event.currentTarget.disabled = true;
            const confirmation = document.createElement('input');
            confirmation.type = 'hidden';
            confirmation.name = 'confirm_update';
            confirmation.value = 'yes';
            customerUpdateForm.appendChild(confirmation);
            HTMLFormElement.prototype.submit.call(customerUpdateForm);
        });
    }
    const jobcardUpdateForm = document.querySelector('[data-jobcard-update-form]');
    const jobcardUpdateDialog = document.querySelector('[data-jobcard-update-confirm]');
    if (jobcardUpdateForm && jobcardUpdateDialog) {
        jobcardUpdateForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!jobcardUpdateDialog.open) jobcardUpdateDialog.showModal();
        });
        jobcardUpdateDialog.querySelector('[data-jobcard-update-no]').addEventListener('click', () => jobcardUpdateDialog.close());
        jobcardUpdateDialog.querySelector('[data-jobcard-update-yes]').addEventListener('click', (event) => {
            if (event.currentTarget.disabled) return;
            if (!jobcardUpdateForm.reportValidity()) {
                jobcardUpdateDialog.close();
                return;
            }
            event.currentTarget.disabled = true;
            const confirmation = document.createElement('input');
            confirmation.type = 'hidden';
            confirmation.name = 'confirm_update';
            confirmation.value = 'yes';
            jobcardUpdateForm.appendChild(confirmation);
            HTMLFormElement.prototype.submit.call(jobcardUpdateForm);
        });
    }
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
                const moreDetails = bay.closest('details');
                if (moreDetails) moreDetails.open = true;
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
            serviceRow.innerHTML = '<div class="job-service-choice"><label class="form-label" for="primary-service-select">Service<select class="form-select" id="primary-service-select"><option value="">Select a service...</option><option value="custom">Other - type a service</option></select></label><label class="form-label" data-custom-service hidden>Service name<input class="form-control" id="primary-service-input" placeholder="Type the service you need" maxlength="190"></label></div><label class="form-label service-custom-price">Price (Rs.) <input class="form-control" type="number" name="primary_service_price" value="0" min="0" step="0.01"></label><small data-service-help>Select a saved service to fill its price. If it is not listed, choose Other.</small><input type="hidden" name="primary_service_id" value=""><input type="hidden" name="primary_service_name" value="">';
            complaintField.closest('.col-md-5')?.prepend(serviceRow);
            const serviceSelect = serviceRow.querySelector('#primary-service-select');
            const serviceInput = serviceRow.querySelector('#primary-service-input');
            const customField = serviceRow.querySelector('[data-custom-service]');
            const servicePrice = serviceRow.querySelector('[name="primary_service_price"]');
            const hiddenId = serviceRow.querySelector('[name="primary_service_id"]');
            const hiddenName = serviceRow.querySelector('[name="primary_service_name"]');
            let services = [];
            const fillWork = (name, description) => {
                if (!complaintField.value.trim() || complaintField.dataset.serviceAutofill === 'true') {
                    complaintField.value = description || name;
                    complaintField.dataset.serviceAutofill = 'true';
                }
                if (workField && (!workField.value.trim() || workField.dataset.serviceAutofill === 'true')) {
                    workField.value = name;
                    workField.dataset.serviceAutofill = 'true';
                }
            };
            serviceSelect.addEventListener('change', () => {
                const custom = serviceSelect.value === 'custom';
                customField.hidden = !custom;
                serviceInput.required = custom;
                hiddenId.value = '';
                hiddenName.value = custom ? serviceInput.value.trim() : '';
                servicePrice.value = '0';
                const service = services.find((item) => String(item.id) === serviceSelect.value);
                if (service) {
                    hiddenId.value = service.id;
                    servicePrice.value = Number(service.price || 0).toFixed(2);
                    fillWork(service.service_name, service.description);
                } else {
                    fillWork(custom ? serviceInput.value.trim() : '', '');
                }
                if (custom) serviceInput.focus();
            });
            serviceInput.addEventListener('input', () => {
                hiddenName.value = serviceInput.value.trim();
                fillWork(hiddenName.value, '');
            });
            fetch('index.php?page=admin&section=jobcards-services').then((response) => {
                if (!response.ok) throw new Error('Services unavailable');
                return response.json();
            }).then((rows) => {
                if (!Array.isArray(rows)) throw new Error('Invalid services');
                services = rows;
                services.forEach((service) => {
                    const option = document.createElement('option');
                    option.value = String(service.id);
                    option.textContent = service.service_name + ' - Rs. ' + Number(service.price || 0).toFixed(2);
                    serviceSelect.insertBefore(option, serviceSelect.querySelector('[value="custom"]'));
                });
                if (!services.length) serviceRow.querySelector('[data-service-help]').textContent = 'No saved services yet. Choose Other to type a service and enter its price.';
            }).catch(() => {
                serviceRow.querySelector('[data-service-help]').textContent = 'Saved services could not be loaded. Choose Other to type a service and enter its price.';
            });
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
    document.querySelectorAll('.pending-jobs-friendly .job-row-actions').forEach((actions) => {
        const view = actions.querySelector('a[href*="section=jobcards-view"]');
        const edit = actions.querySelector('a[href*="section=jobcards-edit"]');
        const remove = actions.querySelector('form[action*="section=jobcards-delete"]');
        const cancel = actions.querySelector('.job-cancel-button')?.closest('form');
        const start = actions.querySelector('input[name="action"][value="start"]')?.closest('form');
        const ongoing = Boolean(actions.closest('.ongoing-jobs-friendly'));
        if (ongoing && view) {
            view.textContent = 'View';
            if (edit) edit.textContent = 'Edit';
            if (remove) remove.querySelector('button').textContent = 'Delete';
            actions.classList.add('ongoing-actions-inline');
            return;
        }
        if (!view || (!start && !ongoing) || actions.querySelector('.pending-action-menu')) return;
        const menu = document.createElement('details');
        menu.className = 'pending-action-menu';
        const summary = document.createElement('summary');
        summary.textContent = 'More actions';
        menu.appendChild(summary);
        if (edit) { edit.textContent = 'Edit job'; menu.appendChild(edit); }
        if (cancel) { cancel.querySelector('button').textContent = 'Cancel job'; menu.appendChild(cancel); }
        if (remove) { remove.querySelector('button').textContent = 'Delete job'; menu.appendChild(remove); }
        view.textContent = ongoing ? 'Open job' : 'View';
        if (start) {
            start.querySelector('button').textContent = 'Start job';
            actions.appendChild(start);
        }
        actions.append(view, menu);
        actions.classList.add('pending-actions-compact');
        menu.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') { menu.open = false; summary.focus(); }
        });
    });
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
    if (sidebar && toggle) {
        let backdrop = document.querySelector('[data-sidebar-backdrop]');
        if (!backdrop) { backdrop = document.createElement('button'); backdrop.type = 'button'; backdrop.className = 'sidebar-backdrop'; backdrop.dataset.sidebarBackdrop = 'true'; backdrop.setAttribute('aria-label', 'Close navigation'); document.body.appendChild(backdrop); }
        const closeSidebar = () => { sidebar.classList.remove('open'); backdrop.classList.remove('visible'); document.body.classList.remove('sidebar-open'); };
        toggle.addEventListener('click', () => { const open = sidebar.classList.toggle('open'); backdrop.classList.toggle('visible', open); document.body.classList.toggle('sidebar-open', open); });
        backdrop.addEventListener('click', closeSidebar);
        sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeSidebar));
        const currentSection = new URLSearchParams(window.location.search).get('section') || 'dashboard';
        sidebar.querySelectorAll('[data-sidebar-item]').forEach((link) => { if (new URL(link.href, window.location.href).searchParams.get('section') === currentSection) link.classList.add('active'); });
        const search = sidebar.querySelector('[data-sidebar-search]');
        if (search) search.addEventListener('input', () => {
            const query = search.value.trim().toLowerCase(); let visible = 0;
            sidebar.querySelectorAll('[data-sidebar-item]').forEach((item) => { const match = !query || item.textContent.toLowerCase().includes(query); item.hidden = !match; if (match) visible += 1; });
            sidebar.querySelectorAll('[data-sidebar-group]').forEach((group) => { const hasMatch = !query || group.querySelector('[data-sidebar-item]:not([hidden])'); group.hidden = !hasMatch; if (query && hasMatch) group.open = true; });
            const noResults = sidebar.querySelector('[data-sidebar-no-results]'); if (noResults) noResults.hidden = visible > 0;
        });
    }

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
            removeForm.setAttribute('data-vehicle-remove-form', '');
            removeForm.dataset.vehicleNumber = row.querySelector('td:nth-child(2)')?.textContent.trim() || 'this vehicle';
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

    document.querySelectorAll('[data-modal-open]').forEach((button) => {
        const modal = document.getElementById(button.dataset.modalOpen);
        if (!modal) return;
        const close = () => { modal.hidden = true; document.body.classList.remove('modal-open'); };
        button.addEventListener('click', () => { modal.hidden = false; document.body.classList.add('modal-open'); });
        modal.querySelectorAll('[data-modal-close]').forEach((closeButton) => closeButton.addEventListener('click', close));
        modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
    });

    const stockNote = document.querySelector('.preview-note p');
    if (stockNote && stockNote.textContent.includes('deducted from stock')) {
        stockNote.textContent = 'Parts are reserved from stock when added. Removing a part returns it to stock.';
    }

    const invoiceForm = document.querySelector('[data-invoice-form]');
    const invoiceLookups = document.querySelector('#invoice-lookups');
    if (invoiceForm && invoiceLookups) {
        const lookups = JSON.parse(invoiceLookups.textContent || '{}');
        const itemsBody = invoiceForm.querySelector('[data-invoice-items]');
        itemsBody.querySelectorAll('tr:not(.invoice-empty-row)').forEach((row) => row.remove());
        const itemsTableHeader = itemsBody.closest('table')?.querySelector('thead tr');
        if (itemsTableHeader && itemsTableHeader.children.length !== 7) {
            itemsTableHeader.innerHTML = '<th>#</th><th>Type</th><th>Description</th><th>Qty</th><th>Unit Price</th><th>Amount</th><th></th>';
        }
        const vehicleSelect = invoiceForm.querySelector('[data-invoice-vehicle]');
        if (vehicleSelect && !invoiceForm.querySelector('[data-invoice-vehicle-search]')) {
            const vehicleSearch = document.createElement('input');
            const vehicleList = document.createElement('datalist');
            const vehicleNumber = document.createElement('input');
            vehicleSearch.className = 'form-control';
            vehicleSearch.placeholder = 'Search registered vehicle or type a number...';
            vehicleSearch.setAttribute('list', 'invoice-vehicle-options');
            vehicleSearch.dataset.invoiceVehicleSearch = 'true';
            vehicleList.id = 'invoice-vehicle-options';
            vehicleNumber.type = 'hidden';
            vehicleNumber.name = 'vehicle_number';
            vehicleNumber.dataset.invoiceVehicleNumber = 'true';
            Array.from(vehicleSelect.options).slice(1).forEach((option) => {
                const entry = document.createElement('option');
                entry.value = option.textContent.split(' - ')[0];
                entry.label = option.textContent;
                vehicleList.appendChild(entry);
            });
            vehicleSelect.parentElement.insertBefore(vehicleSearch, vehicleSelect);
            vehicleSelect.parentElement.appendChild(vehicleList);
            invoiceForm.appendChild(vehicleNumber);
            vehicleSelect.classList.add('visually-hidden');
            vehicleSearch.addEventListener('input', () => {
                const value = vehicleSearch.value.trim();
                vehicleNumber.value = value;
                const match = Array.from(vehicleSelect.options).slice(1).find((option) => option.textContent.split(' - ')[0].trim().toLowerCase() === value.toLowerCase());
                vehicleSelect.value = match ? match.value : '0';
            });
        }
        let itemIndex = 0;
        const money = (value) => (Number(value) || 0).toFixed(2);
        const recalculateInvoice = () => {
            let subtotal = 0;
            itemsBody.querySelectorAll('.invoice-item-row').forEach((row) => {
                const quantityField = row.querySelector('.invoice-line-quantity');
                const quantity = Number(quantityField?.value) || 0;
                if (row.dataset.itemType !== 'custom' && quantity <= 0 && quantityField) quantityField.value = '1';
                const actualQuantity = Number(quantityField?.value) || 0;
                const amount = actualQuantity * (Number(row.querySelector('.invoice-line-price')?.value) || 0);
                row.querySelector('.invoice-line-amount').textContent = money(amount);
                subtotal += amount;
            });
            const charge = Number(invoiceForm.querySelector('[data-charge]')?.value) || 0;
            const discountValue = Number(invoiceForm.querySelector('[data-discount-value]')?.value) || 0;
            const discountType = invoiceForm.querySelector('[data-discount-type]')?.value || '';
            const base = subtotal + charge;
            const discount = Math.min(base, discountType === 'percentage' ? base * discountValue / 100 : discountValue);
            const total = Math.max(0, base - discount);
            const received = Number(invoiceForm.querySelector('[data-payment-received]')?.value) || 0;
            const paid = Math.min(received, total);
            const due = Math.max(0, total - paid);
            invoiceForm.querySelector('[data-preview-subtotal]')?.replaceChildren(document.createTextNode('Rs. ' + money(subtotal)));
            invoiceForm.querySelector('[data-preview-charge]')?.replaceChildren(document.createTextNode('Rs. ' + money(charge)));
            invoiceForm.querySelector('[data-preview-discount]')?.replaceChildren(document.createTextNode('Rs. ' + money(discount)));
            invoiceForm.querySelector('[data-preview-total]')?.replaceChildren(document.createTextNode('Rs. ' + money(total)));
            invoiceForm.querySelector('[data-preview-paid]')?.replaceChildren(document.createTextNode('Rs. ' + money(paid)));
            invoiceForm.querySelector('[data-preview-due]')?.replaceChildren(document.createTextNode('Rs. ' + money(due)));
            const paymentPreview = invoiceForm.querySelector('[data-payment-preview]');
            if (paymentPreview) paymentPreview.innerHTML = 'Amount applied: Rs. ' + money(paid) + ' <span>' + (received > total ? 'Change: Rs. ' + money(received - total) : 'Balance: Rs. ' + money(due)) + '</span>';
        };
        const addRow = (type) => {
            itemsBody.querySelectorAll('tr').forEach((existingRow) => {
                const selector = existingRow.querySelector('.invoice-line-selector, select');
                if (selector && (!selector.value || selector.value === '0')) existingRow.remove();
            });
            const index = itemIndex++;
            const options = type === 'service' ? (lookups.services || []).map((item) => '<option value="' + item.id + '" data-price="' + item.price + '">' + escapeHtml(item.service_name) + '</option>').join('') : type === 'stock_part' ? (lookups.parts || []).map((item) => '<option value="' + item.id + '" data-price="' + item.selling_price + '">' + escapeHtml(item.part_code + ' - ' + item.part_name) + '</option>').join('') : '';
            const selector = type === 'custom' ? '<input class="form-control invoice-line-description" name="items[' + index + '][description]" placeholder="Description" required>' : '<select class="form-select invoice-line-selector" name="items[' + index + '][' + (type === 'service' ? 'service_id' : 'stock_item_id') + ']" required><option value="">Select ' + (type === 'service' ? 'service' : 'stock part') + '</option>' + options + '</select>';
            const row = document.createElement('tr');
            row.className = 'invoice-item-row';
            row.dataset.itemType = type;
            row.innerHTML = '<td>' + (index + 1) + '<input type="hidden" name="items[' + index + '][type]" value="' + type + '"></td><td>' + (type === 'custom' ? 'Custom' : type === 'service' ? 'Service' : 'Stock Part') + '</td><td>' + selector + '</td><td><input class="form-control invoice-line-quantity" type="number" name="items[' + index + '][quantity]" value="1" min="0.01" step="0.01" required></td><td><input class="form-control invoice-line-price" type="number" name="items[' + index + '][unit_price]" value="0" min="0" step="0.01" ' + (type !== 'custom' ? 'readonly' : '') + ' required></td><td>Rs. <span class="invoice-line-amount">0.00</span></td><td><button class="btn btn-light btn-sm invoice-remove-line" type="button">&times;</button></td>';
            itemsBody.querySelector('.invoice-empty-row')?.remove();
            itemsBody.appendChild(row);
            row.querySelector('.invoice-line-selector')?.addEventListener('change', (event) => { const price = event.target.selectedOptions[0]?.dataset.price || 0; row.querySelector('.invoice-line-price').value = price; itemsBody.querySelectorAll('tr').forEach((existingRow) => { const selector = existingRow.querySelector('.invoice-line-selector, select'); if (existingRow !== row && selector && (!selector.value || selector.value === '0')) existingRow.remove(); }); recalculateInvoice(); });
            row.querySelector('.invoice-remove-line').onclick = () => { row.remove(); recalculateInvoice(); };
            row.querySelectorAll('input').forEach((input) => input.addEventListener('input', recalculateInvoice));
            recalculateInvoice();
        };
        invoiceForm.querySelector('[data-add-service]').onclick = () => addRow('service');
        invoiceForm.querySelector('[data-add-stock]').onclick = () => addRow('stock_part');
        invoiceForm.querySelector('[data-add-custom]').onclick = () => addRow('custom');
        invoiceForm.querySelectorAll('[data-charge], [data-discount-type], [data-discount-value], [data-payment-received]').forEach((field) => field.addEventListener('input', recalculateInvoice));
        invoiceForm.querySelectorAll('[data-discount-type]').forEach((field) => field.addEventListener('change', recalculateInvoice));
        recalculateInvoice();
    }

    const invoiceTable = document.querySelector('.invoice-table');
    if (invoiceTable) {
        invoiceTable.querySelectorAll('tbody tr').forEach((row) => {
            const cells = row.querySelectorAll('td');
            if (cells.length < 12 || row.querySelector('.empty-table')) return;

            const vehicleCell = cells[5];
            const vehicleNumber = vehicleCell.textContent.trim();
            if (vehicleNumber && vehicleNumber !== '-') {
                const link = document.createElement('a');
                link.href = 'index.php?page=admin&section=invoices&search=' + encodeURIComponent(vehicleNumber);
                link.className = 'invoice-vehicle-link';
                link.textContent = vehicleNumber;
                vehicleCell.replaceChildren(link);
            }

            const status = cells[10].textContent.trim().toLowerCase();
            const viewLink = cells[11].querySelector('a[href*="invoices-view"]');
            if ((status === 'due' || status === 'partial') && viewLink) {
                const payLink = document.createElement('a');
                payLink.href = viewLink.href;
                payLink.className = 'btn btn-primary btn-sm invoice-pay-link';
                payLink.textContent = 'Pay';
                cells[11].querySelector('.invoice-actions')?.appendChild(payLink);
            }
        });
    }

    const invoicePaymentAmount = document.querySelector('.invoice-payment-card input[name="amount_received"]');
    if (invoicePaymentAmount && !invoicePaymentAmount.value) {
        invoicePaymentAmount.value = invoicePaymentAmount.max;
    }
});
