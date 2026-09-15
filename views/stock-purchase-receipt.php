<?php
$receiptSettings = receipt_settings();
$methods = ['cash'=>'Cash','card'=>'Card','bank_transfer'=>'Online Transfer','cheque'=>'Cheque'];
$back = (current_user()['role'] ?? '') === 'administrator' ? 'stock-purchases' : 'stock';
?>
<div class="page-heading"><h1>Stock Purchase Receipt</h1><div><a class="btn btn-light" href="index.php?page=admin&amp;section=<?= $back ?>">Back</a> <button class="btn btn-primary" type="button" onclick="window.print()">Print Receipt</button></div></div>
<section class="thermal-receipt stock-purchase-print">
    <header><strong><?= e($receiptSettings['garage_name']) ?></strong>
        <?php foreach (['address','contact_number','secondary_contact_number'] as $field): if (!empty($receiptSettings[$field])): ?><div><?= nl2br(e($receiptSettings[$field])) ?></div><?php endif; endforeach; ?>
        <h3>STOCK PURCHASE RECEIPT</h3>
    </header>
    <div>Purchase No: <?= e($purchase['purchase_no']) ?></div>
    <div>Date: <?= e($purchase['purchase_date']) ?></div>
    <div>Supplier: <?= e($purchase['supplier_name'] ?: 'No supplier') ?></div>
    <?php if (!empty($purchase['invoice_no'])): ?><div>Supplier Invoice: <?= e($purchase['invoice_no']) ?></div><?php endif; ?>
    <div>Status: <strong><?= e(strtoupper($purchase['status'] === 'cancelled' ? 'cancelled' : $purchase['payment_status'])) ?></strong></div>
    <table><thead><tr><th>Item / Qty × Cost</th><th>Amount</th></tr></thead><tbody>
        <?php foreach ($purchaseItems as $item): ?><tr><td><?= e($item['part_name']) ?><br><small><?= e($item['part_code']) ?></small><br><?= number_format((float)$item['quantity'],2) ?> × <?= number_format((float)$item['unit_cost'],2) ?></td><td><?= number_format((float)$item['amount'],2) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <div class="purchase-total">Total: Rs. <?= number_format((float)$purchase['total_amount'],2) ?></div>
    <div>Paid to Date: Rs. <?= number_format((float)$purchase['paid_amount'],2) ?></div>
    <div class="purchase-total">Balance Due: Rs. <?= number_format((float)$purchase['balance_amount'],2) ?></div>
    <h4>Payment History</h4>
    <?php $hasPayment = false; foreach ($purchasePayments as $payment): if ((float)$payment['amount'] <= 0) continue; $hasPayment = true; ?>
        <div class="purchase-payment">Payment #<?= (int)$payment['id'] ?> · <?= e($payment['paid_at']) ?><br><?= e($methods[$payment['payment_method']] ?? $payment['payment_method']) ?>: Rs. <?= number_format((float)$payment['amount'],2) ?><br>Recorded by: <?= e($payment['payer_name'] ?: '-') ?></div>
    <?php endforeach; if (!$hasPayment): ?><div>No payment recorded — purchase is due.</div><?php endif; ?>
    <footer><div>Internal purchase record</div><div><?= e(powered_by_text()) ?></div></footer>
</section>
<style>
.stock-purchase-print{width:80mm;max-width:100%;margin:0 auto;padding:4mm;background:white;color:black;font:12px/1.45 Arial,sans-serif;box-sizing:border-box;overflow-wrap:anywhere}
.stock-purchase-print header,.stock-purchase-print footer{text-align:center}
.stock-purchase-print h3{font-size:14px;margin:10px 0}.stock-purchase-print h4{font-size:12px;margin:10px 0 4px}
.stock-purchase-print table{width:100%;border-collapse:collapse;margin:10px 0}.stock-purchase-print th,.stock-purchase-print td{padding:5px 0;text-align:left;vertical-align:top;border-bottom:1px dashed #888}
.stock-purchase-print th:last-child,.stock-purchase-print td:last-child{text-align:right}
.stock-purchase-print .purchase-total{font-weight:bold}.stock-purchase-print .purchase-payment{padding:4px 0;border-bottom:1px dashed #888}.stock-purchase-print footer{margin-top:12px;font-size:10px}
</style>
