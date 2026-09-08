<?php
$vehicleName = trim(($vehicle['make'] ? $vehicle['make'] . ' ' : '') . $vehicle['model']);
$statusLabel = ucfirst((string) ($vehicle['status'] ?? 'active'));
?>
<div class="vehicle-history-heading">
    <div>
        <div class="breadcrumb-line"><a href="index.php?page=admin&amp;section=vehicles">Vehicles</a> <span>›</span> Vehicle History <span>›</span> <b><?= e($vehicle['vehicle_number']) ?></b></div>
        <h1>Vehicle History</h1>
        <p>Review every service, job card, invoice, and part used for this vehicle.</p>
    </div>
    <div class="vehicle-history-actions">
        <a class="btn btn-light" href="index.php?page=admin&amp;section=vehicles">Back to Vehicles</a>
        <a class="btn btn-primary" href="index.php?page=admin&amp;section=vehicles-edit&amp;id=<?= (int) $vehicle['id'] ?>">Edit Vehicle</a>
    </div>
</div>

<div class="vehicle-history-layout">
    <section class="vehicle-history-main">
        <article class="admin-panel vehicle-profile-card">
            <div class="vehicle-profile-top">
                <div class="vehicle-profile-mark">▣</div>
                <div>
                    <span class="vehicle-history-eyebrow">Vehicle profile</span>
                    <h2><?= e($vehicle['vehicle_number']) ?></h2>
                    <p><?= e($vehicleName ?: $vehicle['vehicle_type']) ?> · <?= e($vehicle['vehicle_type']) ?></p>
                </div>
                <span class="status-badge status-<?= e($vehicle['status']) ?>"><?= e($statusLabel) ?></span>
            </div>
            <div class="vehicle-profile-grid">
                <div><span>Owner</span><strong><?= e($vehicle['customer_name']) ?></strong></div>
                <div><span>Contact</span><strong><?= e($vehicle['customer_contact']) ?></strong></div>
                <div><span>Year</span><strong><?= e((string) ($vehicle['year'] ?: '-')) ?></strong></div>
                <div><span>Colour</span><strong><?= e($vehicle['colour'] ?: '-') ?></strong></div>
                <div><span>Engine No.</span><strong><?= e($vehicle['engine_number'] ?: '-') ?></strong></div>
                <div><span>Chassis No.</span><strong><?= e($vehicle['chassis_number'] ?: '-') ?></strong></div>
                <div><span>Current Mileage</span><strong><?= e($vehicle['current_mileage'] !== null ? number_format((float) $vehicle['current_mileage'], 0) . ' km' : '-') ?></strong></div>
                <div><span>Next Service</span><strong><?= e($vehicle['next_service_mileage'] !== null ? number_format((float) $vehicle['next_service_mileage'], 0) . ' km' : '-') ?></strong></div>
            </div>
        </article>

        <div class="vehicle-history-stats">
            <div class="admin-panel"><span>Job Cards</span><strong><?= (int) $vehicle['job_count'] ?></strong></div>
            <div class="admin-panel"><span>Invoices</span><strong><?= (int) $vehicle['invoice_count'] ?></strong></div>
            <div class="admin-panel"><span>Parts / Services</span><strong><?= (int) $vehicle['item_count'] ?></strong></div>
            <div class="admin-panel"><span>Total Billed</span><strong>Rs. <?= number_format((float) $vehicle['total_billed'], 2) ?></strong></div>
        </div>

        <article class="admin-panel vehicle-history-table-panel">
            <div class="vehicle-history-panel-heading"><div><h2>Job Card History</h2><span><?= (int) $vehicle['job_count'] ?> records</span></div><form class="vehicle-history-search" method="get"><input type="hidden" name="page" value="admin"><input type="hidden" name="section" value="vehicles-view"><input type="hidden" name="id" value="<?= (int) $vehicle['id'] ?>"><input type="hidden" name="item_search" value="<?= e($vehicle['item_search']) ?>"><input class="form-control" type="search" name="job_search" value="<?= e($vehicle['job_search']) ?>" placeholder="Search job no, work or mechanic"><button class="btn btn-primary" type="submit">Search</button><?php if ($vehicle['job_search'] !== ''): ?><a class="btn btn-light" href="index.php?page=admin&amp;section=vehicles-view&amp;id=<?= (int) $vehicle['id'] ?>&amp;item_search=<?= urlencode($vehicle['item_search']) ?>">Clear</a><?php endif; ?></form></div>
            <?php if (!$vehicle['jobs']): ?>
                <p class="vehicle-history-empty">No job cards have been recorded for this vehicle.</p>
            <?php else: ?>
                <div class="table-responsive"><table class="table admin-table vehicle-history-table"><thead><tr><th>Job Card</th><th>Date</th><th>Work / Complaint</th><th>Mechanic</th><th>Status</th><th>Total</th></tr></thead><tbody>
                    <?php foreach ($vehicle['jobs'] as $job): ?><tr><td><a href="index.php?page=admin&amp;section=jobcards-view&amp;id=<?= (int) $job['id'] ?>"><strong><?= e($job['job_card_no']) ?></strong></a></td><td><?= e(date('d M Y', strtotime($job['created_at']))) ?></td><td><?= e($job['complaint'] ?: ($job['requested_work'] ?: '-')) ?></td><td><?= e($job['mechanic_name'] ?: '-') ?></td><td><span class="service-status service-status-<?= e($job['status']) ?>"><?= e(ucfirst($job['status'])) ?></span></td><td>Rs. <?= number_format((float) $job['total_amount'], 2) ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </article>

        <article class="admin-panel vehicle-history-table-panel">
            <div class="vehicle-history-panel-heading"><div><h2>Parts &amp; Services Used</h2><span><?= (int) $vehicle['item_count'] ?> items</span></div><form class="vehicle-history-search" method="get"><input type="hidden" name="page" value="admin"><input type="hidden" name="section" value="vehicles-view"><input type="hidden" name="id" value="<?= (int) $vehicle['id'] ?>"><input type="hidden" name="job_search" value="<?= e($vehicle['job_search']) ?>"><input class="form-control" type="search" name="item_search" value="<?= e($vehicle['item_search']) ?>" placeholder="Search part, service or code"><button class="btn btn-primary" type="submit">Search</button><?php if ($vehicle['item_search'] !== ''): ?><a class="btn btn-light" href="index.php?page=admin&amp;section=vehicles-view&amp;id=<?= (int) $vehicle['id'] ?>&amp;job_search=<?= urlencode($vehicle['job_search']) ?>">Clear</a><?php endif; ?></form></div>
            <?php if (!$vehicle['items'] && !$vehicle['external_parts']): ?>
                <p class="vehicle-history-empty">No spare parts or services have been recorded for this vehicle.</p>
            <?php else: ?>
                <div class="table-responsive"><table class="table admin-table vehicle-history-table"><thead><tr><th>Date</th><th>Job / Expense</th><th>Type</th><th>Item</th><th>Qty</th><th>Unit Price</th><th>Amount</th></tr></thead><tbody>
                    <?php foreach ($vehicle['items'] as $item): ?><tr><td><?= e(date('d M Y', strtotime($item['job_date']))) ?></td><td><?= e($item['job_card_no']) ?></td><td><span class="history-item-type history-item-<?= e($item['item_type']) ?>"><?= e(ucfirst($item['item_type'])) ?></span></td><td><strong><?= e($item['item_name']) ?></strong><?= $item['item_code'] ? '<small>' . e($item['item_code']) . '</small>' : '' ?></td><td><?= e((string) $item['quantity']) ?></td><td>Rs. <?= number_format((float) $item['unit_price'], 2) ?></td><td>Rs. <?= number_format((float) $item['amount'], 2) ?></td></tr><?php endforeach; ?>
                    <?php foreach ($vehicle['external_parts'] as $part): ?><tr><td><?= e(date('d M Y', strtotime($part['expense_date']))) ?></td><td><?= e($part['expense_no']) ?></td><td><span class="history-item-type history-item-external">External Part</span></td><td><strong><?= e($part['part_name']) ?></strong><?= $part['part_code'] ? '<small>' . e($part['part_code']) . '</small>' : '' ?></td><td><?= e((string) $part['quantity']) ?></td><td>Rs. <?= number_format((float) $part['unit_cost'], 2) ?></td><td>Rs. <?= number_format((float) $part['total_cost'], 2) ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </article>
    </section>

    <aside class="vehicle-history-sidebar">
        <div class="vehicle-history-sidebar-head"><span class="vehicle-history-eyebrow">Quick history</span><h2><?= e($vehicle['vehicle_number']) ?></h2><p><?= e($vehicle['customer_name']) ?></p></div>
        <div class="vehicle-history-timeline">
            <?php if (!$vehicle['jobs'] && !$vehicle['items'] && !$vehicle['external_parts']): ?><p class="vehicle-history-empty">History will appear here after the first job is created.</p><?php endif; ?>
            <?php foreach (array_slice($vehicle['jobs'], 0, 6) as $job): ?><a class="vehicle-history-event" href="index.php?page=admin&amp;section=jobcards-view&amp;id=<?= (int) $job['id'] ?>"><span class="vehicle-history-event-dot"></span><span><strong><?= e($job['job_card_no']) ?></strong><small><?= e(date('d M Y', strtotime($job['created_at']))) ?> · <?= e(ucfirst($job['status'])) ?></small><em><?= e($job['complaint'] ?: ($job['requested_work'] ?: 'Job card service')) ?></em></span></a><?php endforeach; ?>
        </div>
        <div class="vehicle-history-sidebar-footer"><a class="btn btn-primary w-100" href="index.php?page=admin&amp;section=jobcards-new">Create Job Card</a></div>
    </aside>
</div>
