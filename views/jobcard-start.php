<div class="customer-modal-backdrop jobcard-modal-backdrop">
    <section class="customer-modal job-start-screen">
        <div class="customer-modal-header">
            <div>
                <span class="modal-eyebrow"><?= e($job['job_card_no']) ?></span>
                <h2>Start Job Card</h2>
                <span class="modal-code"><?= e($job['customer_name']) ?> - <?= e($job['vehicle_number'] ?: 'No vehicle') ?></span>
            </div>
            <a class="customer-modal-close" href="index.php?page=admin&amp;section=jobcards-pending">&times;</a>
        </div>
        <div class="customer-modal-body">
            <div class="start-job-message">Select the working bay and mechanic before starting this job.</div>
            <form method="post" action="index.php?page=admin&amp;section=jobcards-action">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="job_card_id" value="<?= (int) $job['id'] ?>">
                <input type="hidden" name="action" value="start">
                <div class="job-start-fields">
                    <label class="form-label">
                        Bay / Working Bay *
                        <select class="form-select" name="bay_name" required>
                            <?php foreach ($jobLookups['bays'] as $index => $bay): ?>
                                <option value="<?= e($bay['bay_name']) ?>" <?= $index === 0 ? 'selected' : '' ?>><?= e($bay['bay_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-label">
                        Mechanic *
                        <select class="form-select" name="mechanic_id" required>
                            <?php foreach ($jobLookups['employees'] as $index => $employee): ?>
                                <option value="<?= (int) $employee['id'] ?>" <?= $index === 0 ? 'selected' : '' ?>><?= e($employee['name'] . ' - ' . $employee['role_position']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="customer-modal-footer px-0 pb-0">
                    <a class="btn btn-light" href="index.php?page=admin&amp;section=jobcards-pending">Cancel</a>
                    <button class="btn btn-primary" type="submit">Start Job</button>
                </div>
            </form>
        </div>
    </section>
</div>
