<?php $purchaseEnabled = ($_POST['record_purchase'] ?? '') === '1'; ?>
<div class="estimate-form-section">
    <h3>Purchase &amp; Payment</h3>
    <label><input type="checkbox" id="stock-record-purchase" name="record_purchase" value="1" <?= $purchaseEnabled ? 'checked' : '' ?>> Record this new stock as a purchase</label>
    <p class="text-muted small">The quantity above is received once and added to Purchases. For an existing item, use Restock.</p>
    <fieldset id="stock-purchase-fields" <?= $purchaseEnabled ? '' : 'hidden disabled' ?>><div class="row g-3">
        <div class="col-6"><label class="form-label">Purchase Date *</label><input class="form-control" type="date" name="purchase_date" value="<?= e((string)($_POST['purchase_date'] ?? date('Y-m-d'))) ?>" required></div>
        <div class="col-6"><label class="form-label">Supplier Invoice No.</label><input class="form-control" name="purchase_invoice_no" maxlength="80" value="<?= e((string)($_POST['purchase_invoice_no'] ?? '')) ?>"></div>
        <div class="col-6"><label class="form-label">Payment Status</label><select class="form-select" name="payment_status" id="stock-purchase-status"><?php foreach (['paid'=>'Fully Paid','partial'=>'Partially Paid','due'=>'Unpaid / Due'] as $value=>$label): ?><option value="<?= $value ?>" <?= ($_POST['payment_status'] ?? 'paid') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div class="col-6"><label class="form-label">Payment Method</label><select class="form-select" name="payment_method"><?php foreach (['cash'=>'Cash','card'=>'Card','bank_transfer'=>'Online Transfer / Bank Transfer','cheque'=>'Cheque'] as $value=>$label): ?><option value="<?= $value ?>" <?= ($_POST['payment_method'] ?? 'cash') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div class="col-12" id="stock-purchase-paid-row"><label class="form-label">Paid Amount (Rs.)</label><input class="form-control" type="number" id="stock-purchase-paid" name="purchase_paid_amount" min="0.01" step="0.01" value="<?= e((string)($_POST['purchase_paid_amount'] ?? '0')) ?>"></div>
        <div class="col-12">Purchase Total: <strong id="stock-purchase-total">Rs. 0.00</strong><br>Balance Due: <strong id="stock-purchase-balance">Rs. 0.00</strong><p class="small text-muted">Save to view and print the purchase receipt.</p></div>
    </div></fieldset>
</div>
<script>
(() => {
    const get = id => document.getElementById(id);
    const enabled = get('stock-record-purchase'), fields = get('stock-purchase-fields');
    const qty = get('stock-qty'), cost = get('stock-buying-price'), status = get('stock-purchase-status'), paid = get('stock-purchase-paid');
    const money = n => 'Rs. ' + n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
    const update = () => {
        fields.hidden = fields.disabled = !enabled.checked;
        const partial = status.value === 'partial';
        paid.disabled = !partial; paid.required = enabled.checked && partial;
        get('stock-purchase-paid-row').hidden = !partial;
        qty.labels[0].textContent = enabled.checked ? 'Purchase Quantity' : 'Opening Stock Qty';
        const total = Math.round(Math.max(0,Number(qty.value)||0)*Math.max(0,Number(cost.value)||0)*100)/100;
        const amount = status.value === 'paid' ? total : (partial ? Number(paid.value)||0 : 0);
        get('stock-purchase-total').textContent = money(total);
        get('stock-purchase-balance').textContent = money(Math.max(0,total-amount));
    };
    [enabled,qty,cost,status,paid].forEach(field=>field.addEventListener('input',update));
    ['stock-part-code','stock-part-name'].forEach(id=>get(id).addEventListener('change',()=>setTimeout(update,0)));
    update();
})();
</script>
