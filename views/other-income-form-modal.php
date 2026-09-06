<?php
$paymentLabels = ['cash' => 'Cash', 'card' => 'Card', 'bank' => 'Bank Transfer', 'other' => 'Other'];
$isEdit = $section === 'other-income-edit';
$knownIncomeType = in_array($input['title'], $types, true);
?>
<div class="customer-modal-backdrop" role="presentation">
    <section class="customer-modal other-income-modal" role="dialog" aria-modal="true" aria-labelledby="other-income-form-title">
        <div class="customer-modal-header">
            <div><span class="modal-eyebrow">Finance record</span><h2 id="other-income-form-title"><?= $isEdit ? 'Edit Other Income' : 'Add Other Income' ?></h2><span class="modal-code"><?= $isEdit ? 'Update this income entry' : 'Record a new income entry' ?></span></div>
            <a class="customer-modal-close" href="index.php?page=admin&amp;section=other-income" aria-label="Close">&times;</a>
        </div>
        <div class="customer-modal-body">
            <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
            <form method="post" action="index.php?page=admin&amp;section=<?= e($section) ?>&amp;id=<?= (int) $id ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Income Date *</label><input class="form-control" type="date" name="income_date" value="<?= e($input['income_date']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Amount (Rs.) *</label><input class="form-control" type="number" name="amount" min="0.01" step="0.01" value="<?= e($input['amount']) ?>" required></div>
                    <div class="col-md-8"><label class="form-label">Income Type *</label><select class="form-select" name="income_type" id="income-type-select" required><option value="">Select income type</option><?php foreach ($types as $type): ?><option value="<?= e($type) ?>" <?= $knownIncomeType && $input['title'] === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?><option value="__custom__" <?= !$knownIncomeType && $input['title'] !== '' ? 'selected' : '' ?>>Type New Income Type</option></select><input class="form-control mt-2" name="custom_income_type" id="custom-income-type" value="<?= !$knownIncomeType ? e($input['title']) : '' ?>" placeholder="Enter a new income type"><small class="other-income-field-hint">Select an existing type, or choose Type New Income Type.</small></div>
                    <div class="col-md-4"><label class="form-label">New Income Category</label><input class="form-control" name="category" value="<?= e($input['category']) ?>" list="other-income-categories" placeholder="Select or type a new category"><datalist id="other-income-categories"><?php foreach ($categories as $category): ?><option value="<?= e((string) $category) ?>"></option><?php endforeach; ?></datalist><small class="other-income-field-hint">Select an existing category or type a new category.</small></div>
                    <div class="col-md-6"><label class="form-label">Payment Method *</label><select class="form-select" name="payment_method" required><?php foreach ($paymentLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $input['payment_method'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Reference Number</label><input class="form-control" name="reference_no" value="<?= e($input['reference_no']) ?>" placeholder="Optional receipt or reference"></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3" placeholder="Optional notes"><?= e($input['notes']) ?></textarea></div>
                </div>
                <div class="customer-modal-footer px-0 pb-0"><a class="btn btn-light" href="index.php?page=admin&amp;section=other-income">Cancel</a><button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Income' : 'Save Income' ?></button></div>
            </form>
        </div>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('income-type-select');
    const custom = document.getElementById('custom-income-type');
    if (!select || !custom) return;
    const syncCustomType = function () {
        const isCustom = select.value === '__custom__';
        custom.required = isCustom;
        custom.disabled = !isCustom;
        if (!isCustom) custom.value = '';
    };
    select.addEventListener('change', syncCustomType);
    syncCustomType();
});
</script>
