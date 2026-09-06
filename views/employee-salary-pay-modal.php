<div class="customer-modal-backdrop" role="presentation">
    <section class="customer-modal employee-edit-modal salary-pay-modal" role="dialog" aria-modal="true" aria-labelledby="salary-pay-title">
        <div class="customer-modal-header">
            <div>
                <span class="modal-eyebrow">Salary payment</span>
                <h2 id="salary-pay-title">Pay Salary?</h2>
                <span class="modal-code"><?= e($salaryPay['employee_name'].' ('.$salaryPay['employee_code'].')') ?></span>
            </div>
            <a class="customer-modal-close" href="index.php?page=admin&amp;section=employees-salary" aria-label="Close">&times;</a>
        </div>
        <div class="customer-modal-body">
            <p class="mb-3">Confirm that this salary has been paid. The system will record the payment time and create a receipt.</p>
            <div class="salary-receipt-card">
                <div class="salary-receipt-row"><span>Salary Month</span><strong><?= e(date('F Y', strtotime($salaryPay['salary_month']))) ?></strong></div>
                <div class="salary-receipt-row"><span>Net Salary</span><strong><?= number_format((float)$salaryPay['net_salary'], 2) ?></strong></div>
            </div>
            <form method="post" action="index.php?page=admin&amp;section=employees-salary">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="salary_action" value="pay">
                <input type="hidden" name="salary_id" value="<?= (int)$salaryPay['id'] ?>">
                <div class="customer-modal-footer px-0 pb-0">
                    <a class="btn btn-light" href="index.php?page=admin&amp;section=employees-salary">Cancel</a>
                    <button class="btn btn-primary" type="submit">Pay &amp; Create Receipt</button>
                </div>
            </form>
        </div>
    </section>
</div>
