<?php
$input = $input ?? restock_input();
$stockOptions = $stockOptions ?? [];
?>
<div class="customer-modal-backdrop stock-form-backdrop" role="presentation">
    <section class="estimate-side-panel stock-side-panel restock-panel" role="dialog" aria-modal="true" aria-labelledby="restock-title">
        <div class="estimate-panel-header"><div><h2 id="restock-title">Restock Item</h2><span>Receive additional quantity without losing batch history</span></div><a href="index.php?page=admin&amp;section=stock" aria-label="Close">&times;</a></div>
        <div class="estimate-panel-body">
            <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
            <form method="post" action="index.php?page=admin&amp;section=stock-restock">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="estimate-form-section">
                    <h3>Existing Item</h3>
                    <label class="form-label" for="restock-item">Search by Part Code, Name or Brand *</label>
                    <input class="form-control" id="restock-item" list="restock-items" placeholder="Type to search..." autocomplete="off" required>
                    <datalist id="restock-items"><?php foreach ($stockOptions as $option): ?><option value="<?= e($option['part_code'].' - '.$option['part_name']) ?>"><?= e($option['brand'] ?: '') ?></option><?php endforeach; ?></datalist>
                    <input type="hidden" name="stock_item_id" id="restock-item-id" value="<?= (int)$input['stock_item_id'] ?>">
                    <div id="restock-item-error" class="stock-form-error"></div>
                    <div class="restock-item-summary" id="restock-summary" hidden><div><span>Part</span><strong id="summary-part">-</strong></div><div><span>Brand / Unit</span><strong id="summary-brand">-</strong></div><div><span>Current Stock</span><strong id="summary-qty">-</strong></div><div><span>Average Cost</span><strong id="summary-average">-</strong></div><div><span>Last Buying Price</span><strong id="summary-last">-</strong></div><div><span>Current Selling Price</span><strong id="summary-selling">-</strong></div><div><span>Reorder Level</span><strong id="summary-reorder">-</strong></div><div><span>Status</span><strong id="summary-status">-</strong></div></div>
                </div>
                <div class="estimate-form-section"><h3>Stock In Details</h3><div class="row g-3"><div class="col-6"><label class="form-label">Quantity Received *</label><input class="form-control" type="number" min="0.01" step="0.01" name="quantity" id="restock-quantity" value="<?= e($input['quantity']) ?>" required><small class="bay-field-hint">Total number of units received.</small></div><div class="col-6"><label class="form-label">New Buying Price (Per Unit) *</label><input class="form-control" type="number" min="0" step="0.01" name="buying_price" id="restock-buying-price" value="<?= e($input['buying_price']) ?>" required><small class="bay-field-hint">Your purchase cost for one unit.</small></div><div class="col-6"><label class="form-label">Selling Price (Per Unit)</label><input class="form-control" type="number" min="0" step="0.01" name="selling_price" id="restock-selling-price" value="<?= e($input['selling_price']) ?>"><small class="bay-field-hint">Customer sale price for one unit.</small></div><div class="col-6"><label class="form-label">Supplier</label><select class="form-select" name="supplier_id" id="restock-supplier"><option value="0">No supplier</option><?php foreach ($suppliers as $supplier): ?><option value="<?= (int)$supplier['id'] ?>" <?= (int)$input['supplier_id']===(int)$supplier['id']?'selected':'' ?>><?= e($supplier['name'].' ('.$supplier['supplier_code'].')') ?></option><?php endforeach; ?></select></div><div class="col-6"><label class="form-label">Purchase Date *</label><input class="form-control" type="date" name="purchase_date" value="<?= e($input['purchase_date']) ?>" required></div><div class="col-6"><label class="form-label">Payment Status</label><select class="form-select" name="payment_status" id="restock-payment-status"><option value="paid" <?= $input['payment_status']==='paid'?'selected':'' ?>>Paid</option><option value="due" <?= $input['payment_status']==='due'?'selected':'' ?>>Due</option><option value="partial" <?= $input['payment_status']==='partial'?'selected':'' ?>>Partial</option></select></div><div class="col-6 partial-payment-field" id="partial-payment-field" hidden><label class="form-label">Amount Paid (Rs.) *</label><input class="form-control" type="number" min="0" step="0.01" name="paid_amount" id="restock-paid-amount" value="<?= e($input['paid_amount']) ?>" placeholder="Enter amount paid"><small class="bay-field-hint">Enter the amount paid now. The remaining amount will stay due.</small></div></div></div>
                <div class="restock-preview" id="restock-preview" hidden><h3>Live Stock Preview</h3><div><span>Current Stock Cost</span><strong id="preview-current-cost">-</strong></div><div><span>New Stock Cost</span><strong id="preview-new-cost">-</strong></div><div><span>Total Stock Cost</span><strong id="preview-total-cost">-</strong></div><div><span>New Total Stock</span><strong id="preview-total">-</strong></div><div><span>Weighted Average Cost</span><strong id="preview-average">-</strong></div><small>Formula: (Current stock cost + New stock cost) / New total stock. Batch buying prices remain separately stored for FIFO costing.</small></div>
                <div class="estimate-form-section"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3" placeholder="Optional notes"><?= e($input['notes']) ?></textarea></div>
                <div class="estimate-form-section"><label class="form-label">Payment Method</label><select class="form-select" name="payment_method"><?php foreach (['cash' => 'Cash (cash drawer)', 'bank_transfer' => 'Bank transfer', 'card' => 'Card', 'cheque' => 'Cheque'] as $method => $label): ?><option value="<?= e($method) ?>" <?= ($input['payment_method'] ?? $_POST['payment_method'] ?? 'cash') === $method ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><small class="bay-field-hint">Cash payments reduce the paying cashier's expected closing cash.</small></div><div class="estimate-form-footer"><a class="btn btn-light" href="index.php?page=admin&amp;section=stock">Cancel</a><button class="btn btn-danger" type="submit">Save Restock</button></div>
            </form>
        </div>
    </section>
</div>
<script>
(() => {
    const items = <?= json_encode($stockOptions, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const search = document.getElementById('restock-item'), id = document.getElementById('restock-item-id'), summary = document.getElementById('restock-summary'), error = document.getElementById('restock-item-error');
    const qty = document.getElementById('restock-quantity'), paymentStatus = document.getElementById('restock-payment-status'), partialField = document.getElementById('partial-payment-field'), cost = document.getElementById('restock-buying-price'), selling = document.getElementById('restock-selling-price'), preview = document.getElementById('restock-preview');
    let current = null;
    const money = value => 'Rs. ' + Number(value || 0).toLocaleString('en-LK', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    function render() {
        if (!current) { summary.hidden = true; preview.hidden = true; return; }
        summary.hidden = false; preview.hidden = false;
        document.getElementById('summary-part').textContent = current.part_code + ' - ' + current.part_name;
        document.getElementById('summary-brand').textContent = (current.brand || '-') + ' / ' + current.unit;
        document.getElementById('summary-qty').textContent = Number(current.stock_qty).toLocaleString() + ' ' + current.unit;
        document.getElementById('summary-average').textContent = money(current.average_cost || current.buying_price);
        document.getElementById('summary-last').textContent = money(current.buying_price);
        document.getElementById('summary-selling').textContent = money(current.selling_price);
        document.getElementById('summary-reorder').textContent = Number(current.reorder_level).toLocaleString();
        document.getElementById('summary-status').textContent = Number(current.stock_qty) <= 0 ? 'Out of Stock' : (Number(current.stock_qty) <= Number(current.reorder_level) ? 'Low Stock' : 'In Stock');
        if (!selling.value) selling.value = current.selling_price;
        if (!cost.value) cost.value = current.buying_price;
        updatePreview();
    }
    function findItem(value) { const term = value.trim().toLowerCase(); return items.find(item => (item.part_code + ' - ' + item.part_name).toLowerCase() === term || item.part_code.toLowerCase() === term || item.part_name.toLowerCase() === term || (item.brand || '').toLowerCase() === term); }
    function updatePreview() { if (!current) return; const newQty = Number.parseFloat(qty.value) || 0, newCost = Number.parseFloat(cost.value) || 0, oldQty = Number.parseFloat(current.stock_qty) || 0, oldCost = Number.parseFloat(current.average_cost ?? current.buying_price) || 0, currentCost = oldQty * oldCost, newStockCost = newQty * newCost, totalCost = currentCost + newStockCost, total = oldQty + newQty; document.getElementById('preview-current-cost').textContent = money(currentCost); document.getElementById('preview-new-cost').textContent = money(newStockCost); document.getElementById('preview-total-cost').textContent = money(totalCost); document.getElementById('preview-total').textContent = total.toLocaleString() + ' ' + current.unit; document.getElementById('preview-average').textContent = money(total > 0 ? totalCost / total : newCost); }
    function selectItem(item) { current = item || null; id.value = item ? item.id : '0'; error.textContent = item ? '' : 'Select an existing stock item from the suggestions.'; if (item) { cost.value = item.buying_price; selling.value = item.selling_price; } render(); }
    search.addEventListener('change', () => selectItem(findItem(search.value))); search.addEventListener('input', () => { if (!findItem(search.value)) { current = null; id.value = '0'; render(); } }); [qty, cost].forEach(field => field.addEventListener('input', updatePreview)); paymentStatus.addEventListener('change', () => { partialField.hidden = paymentStatus.value !== 'partial'; if (paymentStatus.value !== 'partial') document.getElementById('restock-paid-amount').value = ''; }); partialField.hidden = paymentStatus.value !== 'partial';
    const selected = items.find(item => String(item.id) === String(id.value)); if (selected) { search.value = selected.part_code + ' - ' + selected.part_name; selectItem(selected); }
})();
</script>
