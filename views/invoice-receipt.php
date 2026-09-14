<div class="page-heading invoice-heading no-print">
    <div><h1>Payment Receipt <span><?= e($payment['payment_no']) ?></span></h1><nav class="job-breadcrumb"><a href="index.php?page=admin">Dashboard</a><span>&rsaquo;</span><a href="index.php?page=admin&amp;section=invoices">Invoices</a><span>&rsaquo;</span><strong>Receipt</strong></nav></div>
    <div class="invoice-heading-actions"><?php if ((current_user()['role'] ?? '') === 'administrator'): ?><a class="btn btn-light" href="index.php?page=admin&amp;section=payments&amp;customer_id=<?= (int)$payment['customer_id'] ?>">Back to Payments</a><?php endif; ?><a class="btn btn-light" href="index.php?page=admin&amp;section=invoices-view&amp;id=<?= (int)$payment['invoice_id'] ?>">Back to Invoice</a><button class="btn btn-primary" type="button" onclick="window.print()">Print Receipt</button></div>
</div>
<main class="invoice-receipt thermal-receipt">
    <header class="receipt-brand">
        <?php if ($logo = receipt_logo_path($receiptSettings)): ?><img class="receipt-brand-logo" src="<?= e($logo) ?>" alt="<?= e($receiptSettings['garage_name'] ?? 'Garage') ?> logo"><?php endif; ?>
        <strong><?= e($receiptSettings['garage_name'] ?? 'DESHAPRIYA MOTORS') ?></strong>
        <small><?= e($receiptSettings['tagline'] ?? 'Garage Management System') ?></small>
        <small><?= e($receiptSettings['address'] ?? '') ?></small>
        <?php if (!empty($receiptSettings['contact_number'])): ?><small><?= e($receiptSettings['contact_number']) ?></small><?php endif; ?>
        <?php if (!empty($receiptSettings['email'])): ?><small><?= e($receiptSettings['email']) ?></small><?php endif; ?>
    </header>
    <div class="receipt-title"><span>PAYMENT RECEIPT</span><strong><?= e($payment['payment_no']) ?></strong></div>
    <div class="receipt-meta"><div><span>Invoice No.</span><strong><?= e($payment['invoice_no']) ?></strong></div><div><span>Date</span><strong><?= e(date('d M Y h:i A', strtotime($payment['payment_date']))) ?></strong></div><div><span>Customer</span><strong><?= e($payment['customer_name'] ?: 'Walk-in Customer') ?></strong></div><div><span>Vehicle</span><strong><?= e($payment['vehicle_number'] ?: '-') ?></strong></div></div>
    <?php if ($receiptItems): ?><table class="thermal-items"><thead><tr><th>#</th><th>Description</th><th>Qty</th><th>Amount</th></tr></thead><tbody><?php foreach ($receiptItems as $index => $item): ?><tr><td><?= $index + 1 ?></td><td><?= e($item['description']) ?></td><td><?= number_format((float)$item['quantity'], 2) ?></td><td>Rs. <?= number_format((float)$item['line_total'], 2) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
    <section class="receipt-payment-highlight"><span>Amount Received</span><strong>Rs. <?= number_format((float)$payment['amount_applied'], 2) ?></strong><small><?= e(payment_method_label($payment['payment_method'])) ?><?php if ((float)$payment['change_given'] > 0): ?> · Change Rs. <?= number_format((float)$payment['change_given'], 2) ?><?php endif; ?></small></section>
    <div class="receipt-total-lines"><div><span>Invoice Total</span><strong>Rs. <?= number_format((float)$payment['total_amount'], 2) ?></strong></div><div><span>Total Paid</span><strong>Rs. <?= number_format((float)$payment['paid_amount'], 2) ?></strong></div><div class="receipt-grand-total"><span>Remaining Due</span><strong>Rs. <?= number_format((float)$payment['balance_amount'], 2) ?></strong></div></div>
    <?php if (!empty($receiptSettings['receipt_footer'])): ?><div class="receipt-custom-text receipt-footer-text"><?= nl2br(e($receiptSettings['receipt_footer'])) ?></div><?php endif; ?><p class="receipt-thanks">Thank you for your payment.</p><small class="powered-by-print"><?= e(powered_by_text()) ?></small>
</main>
