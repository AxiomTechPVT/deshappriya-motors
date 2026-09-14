<div class="page-heading jobcard-heading no-print">
    <div>
        <h1>Payment Receipt <span class="job-status-pill"><?= (float)$payment['balance_amount'] > 0.009 ? 'DUE' : 'PAID' ?></span></h1>
        <nav class="job-breadcrumb"><a href="index.php?page=admin">Dashboard</a><span>›</span><a href="index.php?page=admin&amp;section=jobcards-ongoing">Ongoing Job Cards</a><span>›</span><strong><?= e($payment['receipt_no']) ?></strong></nav>
    </div>
    <button class="btn btn-primary" type="button" data-print-complete>Print &amp; Complete</button>
</div>
<section class="admin-panel job-receipt-card thermal-receipt">
    <div class="receipt-brand">
        <?php if ($logo = receipt_logo_path($receiptSettings)): ?><img class="receipt-brand-logo" src="<?= e($logo) ?>" alt="<?= e($receiptSettings['garage_name'] ?? 'Garage') ?> logo"><?php endif; ?>
        <strong><?= e($receiptSettings['garage_name'] ?? 'DESHAPRIYA MOTORS') ?></strong>
        <small><?= e($receiptSettings['tagline'] ?? 'Garage Management System') ?></small>
        <small><?= e($receiptSettings['address'] ?? '') ?></small>
        <?php if (!empty($receiptSettings['contact_number'])): ?><small><?= e($receiptSettings['contact_number']) ?></small><?php endif; ?>
        <?php if (!empty($receiptSettings['secondary_contact_number'])): ?><small><?= e($receiptSettings['secondary_contact_number']) ?></small><?php endif; ?>
        <?php if (!empty($receiptSettings['email'])): ?><small><?= e($receiptSettings['email']) ?></small><?php endif; ?>
    </div>
    <?php if (!empty($receiptSettings['receipt_header'])): ?><div class="receipt-custom-text"><?= nl2br(e($receiptSettings['receipt_header'])) ?></div><?php endif; ?>
    <div class="receipt-title"><span>PAYMENT RECEIPT</span><strong><?= e($payment['receipt_no']) ?></strong></div>
    <div class="thermal-meta">
        <div><span>Date / Time</span><strong><?= e(date('d M Y, h:i A', strtotime($payment['paid_at']))) ?></strong></div>
        <div><span>Job Card</span><strong><?= e($payment['job_card_no']) ?></strong></div>
        <div><span>Customer</span><strong><?= e($payment['customer_name']) ?></strong></div>
        <div><span>Vehicle</span><strong><?= e($payment['vehicle_number'] ?: 'No vehicle') ?></strong></div>
        <?php if (!empty($payment['performance_start'])): ?><div><span>Work Time</span><strong><?= e(date('d M Y H:i', strtotime($payment['performance_start']))) ?> - <?= e(date('H:i', strtotime($payment['performance_end']))) ?></strong><small><?= number_format((float)$payment['performance_duration'], 0) ?> minutes</small></div><?php endif; ?>
    </div>
    <table class="thermal-items"><thead><tr><th>#</th><th>Description</th><th>Qty</th><th>Price</th><th>Amount</th></tr></thead><tbody>
        <?php $line = 1; foreach ($receiptItems as $item): ?><tr><td><?= $line++ ?></td><td><?= e($item['item_name']) ?><small><?= e($item['item_type'] === 'manual' ? 'External Part' : ($item['item_type'] === 'service' ? 'Service' : 'Spare Part')) ?></small></td><td><?= e((string)$item['quantity']) ?></td><td><?= number_format((float)$item['unit_price'], 2) ?></td><td><?= number_format((float)$item['amount'], 2) ?></td></tr><?php endforeach; ?>
        <?php if ((float)$payment['service_charge'] > 0): ?><tr><td><?= $line++ ?></td><td>Mechanic / Service Charge<small>Labour charge</small></td><td>1</td><td><?= number_format((float)$payment['service_charge'], 2) ?></td><td><?= number_format((float)$payment['service_charge'], 2) ?></td></tr><?php endif; ?>
    </tbody></table>
    <div class="thermal-total-lines"><div><span>Subtotal</span><strong>Rs. <?= number_format((float)$payment['total_amount'], 2) ?></strong></div><div class="thermal-grand-total"><span>FULL TOTAL</span><strong>Rs. <?= number_format((float)$payment['total_amount'], 2) ?></strong></div></div>
    <div class="thermal-payment-lines"><div><span>Payment Method</span><strong><?= e(payment_method_label($payment['payment_method'])) ?></strong></div><div><span>Amount Received</span><strong>Rs. <?= number_format((float)($payment['amount_received'] ?: $payment['amount']), 2) ?></strong></div><div><span>Change Given</span><strong>Rs. <?= number_format((float)($payment['change_amount'] ?? 0), 2) ?></strong></div><div><span>Balance Due</span><strong>Rs. <?= number_format((float)$payment['balance_amount'], 2) ?></strong></div></div>
    <?php if (!empty($receiptSettings['receipt_footer'])): ?><div class="receipt-custom-text receipt-footer-text"><?= nl2br(e($receiptSettings['receipt_footer'])) ?></div><?php endif; ?><p class="receipt-thanks">Thank you for choosing <?= e($receiptSettings['garage_name'] ?? 'Deshappriya Motors') ?>.</p><small class="powered-by-print"><?= e(powered_by_text()) ?></small>
</section>
<div class="receipt-actions no-print"><a class="btn btn-light" href="index.php?page=admin&amp;section=jobcards-view&amp;id=<?= (int)$payment['job_card_id'] ?>">Back to Job Card</a><a class="btn btn-primary" href="index.php?page=admin&amp;section=jobcards-completed">Completed Job Cards</a></div>
<script>
document.querySelector('[data-print-complete]')?.addEventListener('click', function () {
    var completedUrl = 'index.php?page=admin&section=jobcards-completed';
    var goToCompleted = function () { window.location.href = completedUrl; };
    window.addEventListener('afterprint', goToCompleted, { once: true });
    window.print();
});
</script>
