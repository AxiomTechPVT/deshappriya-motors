<div class="page-heading jobcard-heading edit-job-heading">
    <div>
        <div class="edit-job-kicker"><span class="edit-job-pulse"></span> Pending job workspace</div>
        <h1>Edit Job Card <span><?= e($job['job_card_no']) ?></span></h1>
        <nav class="job-breadcrumb"><a href="index.php?page=admin">Dashboard</a><span>›</span><a href="index.php?page=admin&amp;section=jobcards-pending">Pending Job Cards</a><span>›</span><strong>Edit Details</strong></nav>
    </div>
    <a class="btn btn-light edit-back-button" href="index.php?page=admin&amp;section=jobcards-pending">← Back to Pending Jobs</a>
</div>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="post" class="admin-panel job-edit-panel" action="index.php?page=admin&amp;section=jobcards-edit&amp;id=<?= (int)$job['id'] ?>">
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
        <span class="edit-status-pill">PENDING</span>
    </section>
    <div class="job-edit-grid">
        <section class="edit-form-section edit-main-section">
            <div class="edit-section-heading"><span class="edit-section-number">01</span><div><h2>Customer request</h2><p>Capture the issue and work requested by the customer.</p></div></div>
            <div class="edit-field-grid">
                <label class="form-label edit-field-wide">Customer Complaint *<textarea class="form-control" name="complaint" rows="6" required placeholder="Describe the issue reported by the customer..."><?= e($input['complaint']) ?></textarea></label>
                <label class="form-label edit-field-wide">Requested Services / Work Details<textarea class="form-control" name="requested_work" rows="6" placeholder="Add services or work to be done..."><?= e($input['requested_work']) ?></textarea></label>
            </div>
        </section>
        <section class="edit-form-section edit-details-section">
            <div class="edit-section-heading"><span class="edit-section-number">02</span><div><h2>Job details</h2><p>Plan the workshop visit.</p></div></div>
            <div class="edit-field-grid edit-field-grid-two">
                <label class="form-label">Working Bay<select class="form-select" name="bay_name"><option value="">Select bay</option><?php foreach ($lookups['bays'] as $bay): ?><option value="<?= e($bay['bay_name']) ?>" <?= $input['bay_name'] === $bay['bay_name'] ? 'selected' : '' ?>><?= e($bay['bay_name']) ?></option><?php endforeach; ?></select></label>
                <label class="form-label">Mechanic<select class="form-select" name="mechanic_id"><option value="0">Select mechanic</option><?php foreach ($lookups['employees'] as $employee): ?><option value="<?= (int)$employee['id'] ?>" <?= $input['mechanic_id'] === (int)$employee['id'] ? 'selected' : '' ?>><?= e($employee['name']) ?></option><?php endforeach; ?></select></label>
                <label class="form-label">Expected Delivery Date<input class="form-control" type="date" name="expected_delivery_date" value="<?= e($input['expected_delivery_date']) ?>"></label>
                <label class="form-label">Priority<select class="form-select" name="priority"><?php foreach (['low','normal','high','urgent'] as $priority): ?><option value="<?= $priority ?>" <?= $input['priority'] === $priority ? 'selected' : '' ?>><?= ucfirst($priority) ?></option><?php endforeach; ?></select></label>
            </div>
        </section>
        <section class="edit-form-section edit-notes-section">
            <div class="edit-section-heading"><span class="edit-section-number">03</span><div><h2>Internal notes</h2><p>Keep useful details for the workshop team.</p></div></div>
            <label class="form-label">Notes<textarea class="form-control" name="notes" rows="4" placeholder="Add inspection notes, customer preferences or reminders..."><?= e($input['notes']) ?></textarea></label>
        </section>
    </div>
    <div class="jobcard-form-actions edit-form-actions"><div><span class="edit-save-hint">Changes will update this pending job card.</span></div><div><a class="btn btn-light" href="index.php?page=admin&amp;section=jobcards-pending">Discard</a><button class="btn btn-primary" type="submit">Save Changes <span>→</span></button></div></div>
</form>
