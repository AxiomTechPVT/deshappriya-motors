<div class="customer-modal-backdrop" role="presentation">
    <section class="customer-modal employee-edit-modal" role="dialog" aria-modal="true" aria-labelledby="salary-modal-title">
        <div class="customer-modal-header">
            <div>
                <span class="modal-eyebrow">Salary calculation</span>
                <h2 id="salary-modal-title">Calculate Salary</h2>
                <span class="modal-code">Attendance and paid leave are included</span>
            </div>
            <a class="customer-modal-close" href="index.php?page=admin&amp;section=employees-salary" aria-label="Close">&times;</a>
        </div>
        <div class="customer-modal-body">
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="alert alert-info">Formula: Basic salary / (month days - paid leave days) x no-pay leave days. Then advance, loan and other deductions are applied. Paid leave days are not deducted.</div>
            <form method="post" action="index.php?page=admin&amp;section=employees-salary">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label">Employee *</label>
                        <select class="form-select" name="employee_id" id="salary-employee" required>
                            <option value="">Select employee</option>
                            <?php foreach ($options as $option): $financial = $salaryFinancials[(int)$option['id']] ?? ['advance_total'=>0,'loan_total'=>0]; ?>
                                <option value="<?= (int)$option['id'] ?>"
                                    data-basic="<?= e((string)$option['salary']) ?>"
                                    data-advance="<?= e((string)$financial['advance_total']) ?>"
                                    data-loan="<?= e((string)$financial['loan_total']) ?>"
                                    data-unpaid-days="<?= e((string)(($salaryAttendancePreview[(int)$option['id']]['unpaid_days'] ?? 0))) ?>"
                                    data-attendance-deduction="<?= e((string)(($salaryAttendancePreview[(int)$option['id']]['deduction'] ?? 0))) ?>"
                                    <?= (int)($_POST['employee_id'] ?? 0) === (int)$option['id'] ? 'selected' : '' ?>>
                                    <?= e($option['name'].' ('.$option['employee_code'].')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Salary Month *</label>
                        <input class="form-control" name="salary_month" type="month" value="<?= e($_POST['salary_month'] ?? date('Y-m')) ?>" required>
                    </div>
                </div>

                <div class="salary-outstanding-summary mt-3" id="salary-outstanding-summary">
                    <div><span>Outstanding Advance</span><strong>0.00</strong></div>
                    <div><span>Outstanding Loan</span><strong>0.00</strong></div>
                </div>
                <div class="salary-outstanding-summary salary-no-pay-summary mt-2" id="salary-no-pay-summary">
                    <div><span>No-pay Leave Days</span><strong>0.00</strong></div>
                    <div><span>No-pay Leave Deduction</span><strong>0.00</strong></div>
                </div>

                <div class="row g-3 mt-1 salary-deduction-fields">
                    <div class="col-md-6" id="salary-advance-deduction-wrap" hidden>
                        <label class="form-label">Advance Deduction</label>
                        <input class="form-control" name="advance_deduction" id="salary-advance-deduction" type="number" min="0" step="0.01" value="<?= e($_POST['advance_deduction'] ?? '0') ?>">
                        <small class="text-muted">Maximum: outstanding advance balance</small>
                    </div>
                    <div class="col-md-6" id="salary-loan-deduction-wrap" hidden>
                        <label class="form-label">Loan Deduction</label>
                        <input class="form-control" name="loan_deduction" id="salary-loan-deduction" type="number" min="0" step="0.01" value="<?= e($_POST['loan_deduction'] ?? '0') ?>">
                        <small class="text-muted">Maximum: outstanding loan balance</small>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <label class="form-label">Basic Salary *</label>
                        <input class="form-control" name="basic_salary" id="salary-basic" type="number" min="0" step="0.01" value="<?= e($_POST['basic_salary'] ?? '') ?>" required>
                        <small class="text-muted">Loaded from employee profile</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Allowances</label>
                        <input class="form-control" name="allowances" type="number" min="0" step="0.01" value="<?= e($_POST['allowances'] ?? '0') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Other Deductions</label>
                        <input class="form-control" name="deductions" type="number" min="0" step="0.01" value="<?= e($_POST['deductions'] ?? '0') ?>">
                    </div>
                </div>
                <div class="customer-modal-footer px-0 pb-0">
                    <a class="btn btn-light" href="index.php?page=admin&amp;section=employees-salary">Cancel</a>
                    <button class="btn btn-primary" type="submit">Calculate Salary</button>
                </div>
            </form>
        </div>
    </section>
</div>
