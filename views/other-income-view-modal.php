<?php $paymentLabels = ['cash' => 'Cash', 'card' => 'Card', 'bank' => 'Bank Transfer', 'other' => 'Other']; ?>
<div class="other-income-list-background" inert aria-hidden="true">
<?php
(static function (): void {
    $filters = other_income_filters();
    if ((current_user()['role'] ?? '') === 'cashier') {
        $filters['created_by'] = (int) (current_user()['id'] ?? 0);
    }
    $rows = other_income_rows($filters);
    $summary = other_income_summary($filters);
    $categories = other_income_categories();
    require __DIR__ . '/other-income.php';
})();
?>
</div>
<div class="customer-modal-backdrop other-income-view-backdrop" role="presentation"><section class="customer-modal other-income-modal" role="dialog" aria-modal="true" aria-labelledby="other-income-view-title"><div class="customer-modal-header"><div><span class="modal-eyebrow">Income record</span><h2 id="other-income-view-title"><?= e($income['title']) ?></h2><span class="modal-code">Recorded on <?= e($income['income_date']) ?></span></div><a class="customer-modal-close" href="index.php?page=admin&amp;section=other-income" aria-label="Close">&times;</a></div><div class="customer-modal-body"><div class="modal-detail-grid"><div><span>Income Date</span><strong><?= e($income['income_date']) ?></strong></div><div><span>Amount</span><strong>Rs. <?= number_format((float) $income['amount'], 2) ?></strong></div><div><span>Category</span><strong><?= e($income['category'] ?: '-') ?></strong></div><div><span>Payment Method</span><strong><?= e($paymentLabels[$income['payment_method']] ?? ucfirst($income['payment_method'])) ?></strong></div><div><span>Reference Number</span><strong><?= e($income['reference_no'] ?: '-') ?></strong></div><div><span>Added By</span><strong><?= e($income['created_by_name'] ?: '-') ?></strong></div><div class="modal-detail-wide"><span>Notes</span><strong><?= nl2br(e($income['notes'] ?: '-')) ?></strong></div></div></div><div class="customer-modal-footer"><a class="btn btn-light" href="index.php?page=admin&amp;section=other-income">Close</a><?php if ((current_user()['role'] ?? '') !== 'cashier'): ?><a class="btn btn-primary" href="index.php?page=admin&amp;section=other-income-edit&amp;id=<?= (int) $income['id'] ?>">Edit Income</a><?php endif; ?></div></section></div>
