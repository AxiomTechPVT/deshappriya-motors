<div class="page-heading jobcard-heading edit-job-heading compact-jobcard-page">
    <div>
        <div class="edit-job-kicker"><span class="edit-job-pulse"></span> <?= $job['status'] === 'ongoing' ? 'Ongoing job workspace' : 'Pending job workspace' ?></div>
        <h1>Edit Job Card <span><?= e($job['job_card_no']) ?></span></h1>
        <nav class="job-breadcrumb"><a href="index.php?page=admin">Dashboard</a><span>›</span><a href="index.php?page=admin&amp;section=jobcards-<?= e($job['status']) ?>"><?= e(ucfirst($job['status'])) ?> Job Cards</a><span>›</span><strong>Edit Details</strong></nav>
    </div>
    <a class="btn btn-light edit-back-button" href="index.php?page=admin&amp;section=jobcards-<?= e($job['status']) ?>">← Back to <?= e(ucfirst($job['status'])) ?> Jobs</a>
</div>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="post" data-jobcard-update-form class="admin-panel job-edit-panel compact-edit-panel" action="index.php?page=admin&amp;section=jobcards-edit&amp;id=<?= (int)$job['id'] ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="customer_id" value="<?= (int)$input['customer_id'] ?>">
    <input type="hidden" name="vehicle_id" value="<?= (int)$input['vehicle_id'] ?>">
    <section class="job-edit-identity">
        <div class="edit-identity-icon">JC</div>
        <div><span class="edit-section-label">Job card</span><strong><?= e($job['job_card_no']) ?></strong><small>Created <?= e(date('d M Y, h:i A', strtotime($job['created_at']))) ?></small></div>
        <div class="edit-identity-divider"></div>
        <div><span class="edit-section-label">Customer</span><strong><?= e($job['customer_name']) ?></strong><small><?= e($job['contact_number'] ?: 'No contact number') ?></small></div>
        <div class="edit-identity-divider"></div>
        <div><span class="edit-section-label">Vehicle</span><strong><?= e($job['vehicle_number'] ?: 'No vehicle') ?></strong><small><?= e(trim(($job['make'] ?: '') . ' ' . ($job['model'] ?: ''))) ?></small></div>
        <span class="edit-status-pill"><?= e(strtoupper($job['status'])) ?></span>
    </section>
    <div class="job-edit-grid">
        <section class="edit-form-section edit-main-section">
            <div class="edit-section-heading"><span class="edit-section-number">01</span><div><h2>Customer request</h2><p>Capture the issue and work requested by the customer.</p></div></div>
            <div class="edit-field-grid">
                <label class="form-label edit-field-wide">Customer Complaint *<textarea class="form-control" name="complaint" rows="6" required placeholder="Describe the issue reported by the customer..."><?= e($input['complaint']) ?></textarea></label>
                <label class="form-label edit-field-wide">Requested Services / Work Details<textarea class="form-control" name="requested_work" rows="6" placeholder="Add services or work to be done..."><?= e($input['requested_work']) ?></textarea></label>
            </div>
        </section>
        <section class="edit-form-section edit-items-section">
            <div class="edit-section-heading"><span class="edit-section-number">02</span><div><h2>Added services &amp; items</h2><p>Services already added to this job card.</p></div></div>
            <?php $serviceItems = array_values(array_filter($job['items'] ?? [], static fn(array $item): bool => ($item['item_type'] ?? '') === 'service')); ?>
            <?php if ($serviceItems): ?><div class="edit-service-list"><?php foreach ($serviceItems as $item): ?><div class="edit-service-row"><div><strong><?= e($item['item_name']) ?></strong><small><?= e($item['item_code'] ?: 'Saved service') ?> · Qty <?= e((string)$item['quantity']) ?></small></div><strong>Rs. <?= number_format((float)$item['amount'], 2) ?></strong></div><?php endforeach; ?></div><div class="edit-service-total"><span>Service charge</span><strong>Rs. <?= number_format((float)($job['service_charge'] ?? 0), 2) ?></strong></div><?php else: ?><div class="edit-empty-items">No saved services have been added to this job card yet.</div><?php endif; ?>
            <button type="button" class="btn btn-primary edit-service-open" data-open-edit-service>+ Add Service</button>
        </section>
        <section class="edit-form-section edit-details-section">
            <div class="edit-section-heading"><span class="edit-section-number">03</span><div><h2>Job details</h2><p>Plan the workshop visit.</p></div></div>
            <div class="edit-field-grid edit-field-grid-two">
                <label class="form-label">Working Bay<select class="form-select" name="bay_name"><option value="">Select bay</option><?php foreach ($lookups['bays'] as $bay): ?><option value="<?= e($bay['bay_name']) ?>" <?= $input['bay_name'] === $bay['bay_name'] ? 'selected' : '' ?>><?= e($bay['bay_name']) ?></option><?php endforeach; ?></select></label>
                <label class="form-label">Mechanic<select class="form-select" name="mechanic_id"><option value="0">Select mechanic</option><?php foreach ($lookups['employees'] as $employee): ?><option value="<?= (int)$employee['id'] ?>" <?= $input['mechanic_id'] === (int)$employee['id'] ? 'selected' : '' ?>><?= e($employee['name']) ?></option><?php endforeach; ?></select></label>
                <label class="form-label">Expected Delivery Date<input class="form-control" type="date" name="expected_delivery_date" value="<?= e($input['expected_delivery_date']) ?>"></label>
                <label class="form-label">Priority<select class="form-select" name="priority"><?php foreach (['low','normal','high','urgent'] as $priority): ?><option value="<?= $priority ?>" <?= $input['priority'] === $priority ? 'selected' : '' ?>><?= ucfirst($priority) ?></option><?php endforeach; ?></select></label>
            </div>
        </section>
        <section class="edit-form-section edit-notes-section">
            <div class="edit-section-heading"><span class="edit-section-number">04</span><div><h2>Internal notes</h2><p>Keep useful details for the workshop team.</p></div></div>
            <label class="form-label">Notes<textarea class="form-control" name="notes" rows="4" placeholder="Add inspection notes, customer preferences or reminders..."><?= e($input['notes']) ?></textarea></label>
        </section>
    </div>
    <div class="jobcard-form-actions edit-form-actions"><div><span class="edit-save-hint">Changes will update this <?= e($job['status']) ?> job card.</span></div><div><a class="btn btn-light" href="index.php?page=admin&amp;section=jobcards-<?= e($job['status']) ?>">Discard</a><button class="btn btn-primary" type="submit">Save Changes <span>→</span></button></div></div>
</form>

            <div class="edit-service-modal" data-edit-service-modal hidden>
                <div class="edit-service-modal-card" role="dialog" aria-modal="true" aria-labelledby="edit-service-modal-title">
                    <div class="edit-service-modal-heading">
                        <div><span class="edit-service-modal-eyebrow">JOB CARD SERVICE</span><h3 id="edit-service-modal-title">Add service</h3><p>Select a saved service or enter a custom service.</p></div>
                        <button type="button" class="edit-service-modal-close" data-close-edit-service aria-label="Close">&times;</button>
                    </div>
                    <div class="edit-service-modal-body">
                        <form method="post" action="index.php?page=admin&amp;section=jobcards-item-add" class="edit-service-add-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="job_card_id" value="<?= (int)$job['id'] ?>"><input type="hidden" name="item_type" value="service"><input type="hidden" name="return_section" value="jobcards-edit">
                            <label class="form-label">Saved service<select class="form-select" name="service_id"><option value="0">Select a saved service</option><?php foreach ($lookups['services'] as $service): ?><option value="<?= (int)$service['id'] ?>"><?= e($service['service_code'].' - '.$service['service_name'].' (Rs. '.number_format((float)$service['price'], 2).')') ?></option><?php endforeach; ?></select></label>
                            <label class="form-label">Quantity<input class="form-control" type="number" name="quantity" value="1" min="0.01" step="0.01"></label>
                            <button class="btn btn-primary btn-sm" type="submit" formaction="index.php?page=admin&amp;section=jobcards-item-add" formmethod="post" formnovalidate>Add Saved Service</button>
                        </form>
                        <div class="edit-service-modal-divider"><span>OR</span></div>
                        <form method="post" action="index.php?page=admin&amp;section=jobcards-item-add" class="edit-service-add-form edit-custom-service-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="job_card_id" value="<?= (int)$job['id'] ?>"><input type="hidden" name="item_type" value="service"><input type="hidden" name="return_section" value="jobcards-edit">
                            <label class="form-label">Custom service name<input class="form-control" name="item_name" placeholder="e.g. Engine repair" required></label>
                            <label class="form-label">Price (Rs.)<input class="form-control" type="number" name="unit_price" value="0" min="0" step="0.01" required></label>
                            <label class="form-label">Quantity<input class="form-control" type="number" name="quantity" value="1" min="0.01" step="0.01"></label>
                            <button class="btn btn-light btn-sm" type="submit" name="custom_service" value="1" formaction="index.php?page=admin&amp;section=jobcards-item-add" formmethod="post">Add Custom Service</button>
                        </form>
                    </div>
                </div>
            </div>

<dialog class="vehicle-update-confirm" data-jobcard-update-confirm aria-labelledby="jobcard-update-confirm-title">
    <h2 id="jobcard-update-confirm-title">Update Job Card?</h2>
    <p>Save changes to job card <?= e($job['job_card_no']) ?>?</p>
    <div class="customer-form-actions">
        <button class="btn btn-light" type="button" data-jobcard-update-no autofocus>No</button>
        <button class="btn btn-primary" type="button" data-jobcard-update-yes>Yes</button>
    </div>
</dialog>