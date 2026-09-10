<div class="start-pending-background" inert aria-hidden="true">
<?php
// Reuse the existing list view and read-only query without changing start handling.
(static function (array $lookups): void {
    $status = 'pending';
    $section = 'jobcards-pending';
    $filters = ['search'=>'', 'mechanic_id'=>0, 'bay_name'=>'', 'priority'=>'', 'date_from'=>'', 'date_to'=>''];
    $rows = job_card_rows($status, $filters);
    require __DIR__ . '/jobcards.php';
})($jobLookups);
?>
</div>
<dialog class="customer-modal job-start-screen friendly-start-dialog" aria-labelledby="start-job-title" aria-describedby="start-job-description">
        <div class="customer-modal-header">
            <div>
                <span class="modal-eyebrow"><?= e($job['job_card_no']) ?></span>
                <h2 id="start-job-title">Ready to start this job?</h2>
                <span class="modal-code"><?= e($job['customer_name']) ?> - <?= e($job['vehicle_number'] ?: 'No vehicle') ?></span>
            </div>
            <a class="customer-modal-close" aria-label="Close and return to pending jobs" autofocus href="index.php?page=admin&amp;section=jobcards-pending">&times;</a>
        </div>
        <div class="customer-modal-body">
            <p class="start-job-message" id="start-job-description">Choose where the work will happen and who will do it.</p>
            <form method="post" action="index.php?page=admin&amp;section=jobcards-action">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="job_card_id" value="<?= (int) $job['id'] ?>">
                <input type="hidden" name="action" value="start">
                <div class="job-start-fields">
                    <label class="form-label">
                        <span class="start-field-title"><b>1</b> Working bay *</span><small>Where will the vehicle be worked on?</small>
                        <select class="form-select" name="bay_name" required>
                            <?php foreach ($jobLookups['bays'] as $index => $bay): ?>
                                <option value="<?= e($bay['bay_name']) ?>" <?= $index === 0 ? 'selected' : '' ?>><?= e($bay['bay_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-label">
                        <span class="start-field-title"><b>2</b> Mechanic *</span><small>Who will be responsible for the work?</small>
                        <select class="form-select" name="mechanic_id" required>
                            <?php foreach ($jobLookups['employees'] as $index => $employee): ?>
                                <option value="<?= (int) $employee['id'] ?>" <?= $index === 0 ? 'selected' : '' ?>><?= e($employee['name'] . ' - ' . $employee['role_position']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <p class="start-job-note">Starting will move this job to the Ongoing Job Cards list.</p><div class="customer-modal-footer px-0 pb-0">
                    <a class="btn btn-light" href="index.php?page=admin&amp;section=jobcards-pending">Cancel</a>
                    <button class="btn btn-primary" type="submit">Start Job</button>
                </div>
            </form>
        </div>
</dialog>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dialog = document.querySelector('.friendly-start-dialog');
    dialog.showModal();
    dialog.addEventListener('cancel', function (event) {
        event.preventDefault();
        window.location.href = 'index.php?page=admin&section=jobcards-pending';
    });
});
</script>