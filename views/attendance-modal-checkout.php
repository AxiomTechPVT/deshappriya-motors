<?php $canCheckout = $attendanceModalRecord['check_in'] && !$attendanceModalRecord['check_out'] && in_array($attendanceModalRecord['attendance_status'], ['present', 'half_day'], true); ?>
<div class="customer-modal-backdrop" role="presentation">
    <section class="customer-modal" role="dialog" aria-modal="true" aria-labelledby="attendance-checkout-title">
        <div class="customer-modal-header">
            <div><span class="modal-eyebrow">Attendance</span><h2 id="attendance-checkout-title">Check Out</h2><span class="modal-code"><?= e((employee_find((int) $attendanceModalRecord['employee_id'])['name'] ?? 'Employee')) ?> &middot; <?= e($attendanceModalRecord['attendance_date']) ?></span></div>
            <a class="customer-modal-close" href="index.php?page=admin&amp;section=employees-attendance" aria-label="Close">&times;</a>
        </div>
        <div class="customer-modal-body">
            <p>Check In: <strong><?= e($attendanceModalRecord['check_in'] ?: '-') ?></strong></p>
            <?php if ($canCheckout): ?>
            <p>Enter the time this employee leaves. Their saved attendance details will stay the same.</p>
            <form method="post" action="index.php?page=admin&amp;section=employees-attendance">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="attendance_action" value="checkout">
                <input type="hidden" name="checkout_id" value="<?= (int) $attendanceModalRecord['id'] ?>">
                <label class="form-label" for="attendance-checkout-time">Check Out time *</label>
                <input class="form-control" id="attendance-checkout-time" type="time" name="check_out" required value="<?= e($_POST['check_out'] ?? '') ?>">
                <button class="btn btn-light mt-2" type="button" id="attendance-checkout-now">Use current time</button>
                <div class="customer-modal-footer px-0 pb-0">
                    <a class="btn btn-light" href="index.php?page=admin&amp;section=employees-attendance">Cancel</a>
                    <button class="btn btn-primary" type="submit">Save Check Out</button>
                </div>
            </form>
            <?php else: ?>
            <p>This record is not waiting for check out.</p>
            <a class="btn btn-light" href="index.php?page=admin&amp;section=employees-attendance">Back to Attendance</a>
            <?php endif; ?>
        </div>
    </section>
</div>
<script>
document.getElementById('attendance-checkout-now')?.addEventListener('click', function () {
    const now = new Date();
    document.getElementById('attendance-checkout-time').value = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
});
</script>
