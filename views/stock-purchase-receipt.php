<?php
$receiptSettings = receipt_settings();
$methods = ['cash'=>'Cash','card'=>'Card','bank_transfer'=>'Online Transfer','cheque'=>'Cheque'];
$back = (current_user()['role'] ?? '') === 'administrator' ? 'stock-purchases' : 'stock';
?>
<div class="page-heading"><h1>Stock Purchase Receipt</h1><div><a class="btn btn-light" href="index.php?page=admin&amp;section=<?= $back ?>">Back</a> <button class="btn btn-primary" type="button" onclick="window.print()">Print Receipt</button></div></div>
<section class="thermal-receipt stock-purchase-print">
    <header><strong class="purchase-garage-name"><?= e($receiptSettings['garage_name']) ?></strong>
        <?php foreach (['address','contact_number','secondary_contact_number'] as $field): if (!empty($receiptSettings[$field])): ?><div><?= nl2br(e($receiptSettings[$field])) ?></div><?php endif; endforeach; ?>
        <h3>STOCK PURCHASE RECEIPT</h3>
    </header>
    <div class="purchase-meta">
    <div class="purchase-line"><span>Purchase No.</span><strong> <?= e($purchase['purchase_no']) ?></strong></div>
    <div class="purchase-line"><span>Date</span><strong><?= e($purchase['purchase_date']) ?></strong></div>
    <div class="purchase-line"><span>Supplier</span><strong><?= e($purchase['supplier_name'] ?: 'No supplier') ?></strong></div>
    <?php if (!empty($purchase['invoice_no'])): ?><div class="purchase-line"><span>Supplier Invoice</span><strong><?= e($purchase['invoice_no']) ?></strong></div><?php endif; ?>
    <div class="purchase-line"><span>Status</span><strong class="purchase-status"><?= e(strtoupper($purchase['status'] === 'cancelled' ? 'cancelled' : $purchase['payment_status'])) ?></strong></div>
    </div>
    <table><thead><tr><th>Item / Qty × Cost</th><th>Amount (Rs.)</th></tr></thead><tbody>
        <?php foreach ($purchaseItems as $item): ?><tr><td><strong class="purchase-item-name"><?= e($item['part_name']) ?></strong><br><small><?= e($item['part_code']) ?></small><br><?= number_format((float)$item['quantity'],2) ?> × <?= number_format((float)$item['unit_cost'],2) ?></td><td><?= number_format((float)$item['amount'],2) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <div class="purchase-totals"><div class="purchase-line purchase-total"><span>Total</span><strong>Rs. <?= number_format((float)$purchase['total_amount'],2) ?></strong></div>
    <div class="purchase-line"><span>Paid to Date</span><strong>Rs. <?= number_format((float)$purchase['paid_amount'],2) ?></strong></div>
    <div class="purchase-line purchase-balance"><span>Balance Due</span><strong>Rs. <?= number_format((float)$purchase['balance_amount'],2) ?></strong></div></div>
    <h4>Payment History</h4>
    <?php $hasPayment = false; foreach ($purchasePayments as $payment): if ((float)$payment['amount'] <= 0) continue; $hasPayment = true; ?>
        <div class="purchase-payment">
            <div class="purchase-line"><strong>Payment #<?= (int)$payment['id'] ?></strong><span><?= e($payment['paid_at']) ?></span></div>
            <div class="purchase-line"><span><?= e($methods[$payment['payment_method']] ?? $payment['payment_method']) ?></span><strong>Rs. <?= number_format((float)$payment['amount'],2) ?></strong></div>
            <div class="purchase-payer">Recorded by: <?= e($payment['payer_name'] ?: '-') ?></div>
        </div>
    <?php endforeach; if (!$hasPayment): ?><div>No payment recorded — purchase is due.</div><?php endif; ?>
    <footer><div>Internal purchase record</div><div><?= e(powered_by_text()) ?></div></footer>
</section>
<style>
.stock-purchase-print{width:80mm;max-width:100%;margin:0 auto 24px;padding:5mm;background:#fff;color:#111;font:12px/1.5 Arial,sans-serif;box-sizing:border-box;overflow-wrap:anywhere;border:1px solid #e1e5ea;box-shadow:0 8px 28px rgba(16,24,40,.08);font-variant-numeric:tabular-nums}
.stock-purchase-print header,.stock-purchase-print footer{text-align:center}
.stock-purchase-print header{font-size:10px;line-height:1.5}
.stock-purchase-print .purchase-garage-name{display:block;font-size:19px;line-height:1.2;margin-bottom:6px}
.stock-purchase-print h3{font-size:12px;letter-spacing:.06em;margin:10px 0 0;padding:8px 0;border-top:1px solid #111;border-bottom:1px solid #111;font-weight:700}
.stock-purchase-print h4{font-size:10px;text-transform:uppercase;letter-spacing:.08em;margin:12px 0 4px;font-weight:700}
.stock-purchase-print .purchase-meta{padding:8px 0;border-bottom:1px dashed #888;font-size:10px}
.stock-purchase-print .purchase-line{display:flex;justify-content:space-between;align-items:baseline;gap:10px;padding:3px 0}
.stock-purchase-print .purchase-line>:first-child{flex:0 0 auto;max-width:45%}
.stock-purchase-print .purchase-line>:last-child{text-align:right;min-width:0}
.stock-purchase-print .purchase-status{font-size:9px;letter-spacing:.06em}
.stock-purchase-print table{width:100%;border-collapse:collapse;table-layout:fixed;margin:8px 0;font-size:10px}
.stock-purchase-print th,.stock-purchase-print td{padding:7px 0;text-align:left;vertical-align:top;border-bottom:1px dashed #aaa}
.stock-purchase-print th{font-size:9px}.stock-purchase-print th:last-child,.stock-purchase-print td:last-child{text-align:right;width:30%;padding-left:6px}
.stock-purchase-print small{font-size:8px}.stock-purchase-print .purchase-item-name{font-size:11px}
.stock-purchase-print .purchase-totals{padding-top:2px;font-size:11px}
.stock-purchase-print .purchase-total{font-size:14px;font-weight:bold;padding:5px 0}
.stock-purchase-print .purchase-balance{font-weight:bold;border-top:1px solid #111;border-bottom:1px solid #111;margin-top:4px;padding:7px 0}
.stock-purchase-print .purchase-payment{padding:5px 0;border-bottom:1px dashed #aaa;font-size:10px;break-inside:avoid}
.stock-purchase-print .purchase-payment .purchase-line:first-child{font-size:9px}.stock-purchase-print .purchase-payer{font-size:9px;padding-top:2px}
.stock-purchase-print footer{margin-top:12px;font-size:9px;line-height:1.6}
@media print{.stock-purchase-print{border:0;box-shadow:none;margin:0}.stock-purchase-print .purchase-totals{break-inside:avoid}.stock-purchase-print h4{break-after:avoid}}
</style>
