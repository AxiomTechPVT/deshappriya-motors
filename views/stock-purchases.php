<?php
$paidRows = array_values(array_filter($rows, static fn(array $row): bool => ($row['payment_status'] ?? 'paid') === 'paid'));
$partialRows = array_values(array_filter($rows, static fn(array $row): bool => ($row['payment_status'] ?? '') === 'partial'));
$dueRows = array_values(array_filter($rows, static fn(array $row): bool => ($row['payment_status'] ?? '') === 'due'));
$paymentRows = static function (array $paymentRows): void {
    if (!$paymentRows) {
        echo '<tr><td colspan="11" class="empty-table">No records in this payment group.</td></tr>';
        return;
    }
    foreach ($paymentRows as $row):
?>
<tr>
    <td><strong><?= e($row['purchase_no']) ?></strong></td>
    <td><?= e($row['supplier_name'] ?: 'No supplier') ?></td>
    <td><?= e($row['purchase_date']) ?></td>
    <td><?= (int)$row['item_count'] ?></td>
    <td><?= number_format((float)$row['total_amount'], 2) ?></td>
    <td><?= number_format((float)($row['paid_amount'] ?? 0), 2) ?></td>
    <td><?= number_format((float)($row['balance_amount'] ?? 0), 2) ?></td>
    <td><?= e($row['created_by_name'] ?: '-') ?></td>
    <td><?= e($row['notes'] ?: '-') ?></td>
    <td><span class="stock-status stock-status-<?= ($row['payment_status'] ?? '') === 'paid' ? 'in' : 'low' ?>"><?= e(ucfirst($row['payment_status'] ?? 'paid')) ?></span></td>
    <td><div class="action-links employee-actions"><a href="index.php?page=admin&amp;section=stock-purchase-view&amp;id=<?= (int)$row['id'] ?>" title="View purchase" aria-label="View purchase">&#128065;</a><a href="index.php?page=admin&amp;section=stock-purchase-edit&amp;id=<?= (int)$row['id'] ?>" title="Edit purchase" aria-label="Edit purchase">&#9998;</a><?php if((float)($row['balance_amount']??0)>0 && ($row['status']??'received')!=='cancelled'): ?><a href="index.php?page=admin&amp;section=stock-purchase-pay&amp;id=<?= (int)$row['id'] ?>" title="Record payment" aria-label="Record payment">&#8377;</a><?php endif; ?><form method="post" action="index.php?page=admin&amp;section=stock-purchase-delete" onsubmit="return confirm('Mark this purchase as cancelled?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="purchase_id" value="<?= (int)$row['id'] ?>"><button type="submit" title="Delete purchase" aria-label="Delete purchase">&#128465;</button></form></div></td>
</tr>
<?php endforeach; }; ?>

<div class="page-heading">
    <div><div class="breadcrumb-line">Home <span>&rsaquo;</span> Stock / Parts <span>&rsaquo;</span> <b>Stock Purchases</b></div><h1>Stock Purchases</h1><p>Review restock purchases and follow up outstanding supplier payments.</p></div>
    <a href="index.php?page=admin&amp;section=stock-restock" class="btn btn-danger">+ Restock Item</a>
</div>
<div class="admin-panel stock-filters purchase-filters"><form class="stock-filter-bar purchase-filter-bar" method="get"><input type="hidden" name="page" value="admin"><input type="hidden" name="section" value="stock-purchases"><label>From Date<input class="form-control" type="date" name="date_from" value="<?= e($filters['date_from']) ?>"></label><label>To Date<input class="form-control" type="date" name="date_to" value="<?= e($filters['date_to']) ?>"></label><label>Payment Status<select class="form-select" name="payment_status"><option value="">All Payments</option><option value="paid" <?= $filters['payment_status']==='paid'?'selected':'' ?>>Paid</option><option value="partial" <?= $filters['payment_status']==='partial'?'selected':'' ?>>Partial</option><option value="due" <?= $filters['payment_status']==='due'?'selected':'' ?>>Due</option></select></label><div class="stock-filter-actions"><a class="btn btn-light" href="index.php?page=admin&amp;section=stock-purchases">Reset</a><button class="btn btn-danger" type="submit">Apply Filter</button></div></form></div>

<div class="row g-3 stock-summary purchase-payment-summary">
    <div class="col-sm-4"><div class="stat-card stock-card-green"><small>Paid Purchases</small><strong><?= count($paidRows) ?></strong><span>Fully settled</span></div></div>
    <div class="col-sm-4"><div class="stat-card stock-card-orange"><small>Partial Payments</small><strong><?= count($partialRows) ?></strong><span>Balance remaining: Rs. <?= number_format(array_sum(array_map(static fn(array $row): float => (float)($row['balance_amount'] ?? 0), $partialRows)), 2) ?></span></div></div>
    <div class="col-sm-4"><div class="stat-card stock-card-red"><small>Due Purchases</small><strong><?= count($dueRows) ?></strong><span>Balance remaining: Rs. <?= number_format(array_sum(array_map(static fn(array $row): float => (float)($row['balance_amount'] ?? 0), $dueRows)), 2) ?></span></div></div>
</div>

<?php foreach ([['Paid Purchases', $paidRows], ['Partial Payments', $partialRows], ['Due Purchases', $dueRows]] as [$heading, $group]): ?>
<div class="admin-panel stock-list purchase-list purchase-payment-group">
    <div class="panel-heading"><h2><?= e($heading) ?></h2><span class="text-muted"><?= count($group) ?> records</span></div>
    <div class="table-responsive"><table class="table admin-table"><thead><tr><th>Purchase No.</th><th>Supplier</th><th>Date</th><th>Items</th><th>Total (Rs.)</th><th>Paid (Rs.)</th><th>Balance (Rs.)</th><th>Created By</th><th>Notes</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php $paymentRows($group); ?></tbody></table></div>
</div>
<?php endforeach; ?>
