<div class="service-list-background" inert aria-hidden="true">
<?php
(static function (): void {
    $filters = ['search' => '', 'category_id' => 0, 'status' => '', 'page' => 1, 'per_page' => 10];
    [$rows, $total] = service_rows($filters);
    $summary = service_summary();
    $categories = service_categories();
    $pages = max(1, (int) ceil($total / $filters['per_page']));
    require __DIR__ . '/services.php';
})();
?>
</div>
<div class="customer-modal-backdrop service-view-backdrop"><section class="customer-modal" role="dialog" aria-modal="true" aria-labelledby="service-view-title"><div class="customer-modal-header"><div><span class="modal-eyebrow"><?= e($service['service_code']) ?></span><h2 id="service-view-title"><?= e($service['service_name']) ?></h2><span class="modal-code"><?= e($service['category_name']?:'-') ?></span></div><a class="customer-modal-close" href="index.php?page=admin&amp;section=services">&times;</a></div><div class="customer-modal-body"><span class="service-status service-status-<?= e($service['status']) ?>"><?= e(ucfirst($service['status'])) ?></span><div class="modal-detail-grid"><div><span>Service Code</span><strong><?= e($service['service_code']) ?></strong></div><div><span>Category</span><strong><?= e($service['category_name']?:'-') ?></strong></div><div><span>Price</span><strong>Rs. <?= number_format((float)$service['price'],2) ?></strong></div><div><span>Duration</span><strong><?= $service['duration_minutes']!==null?(int)$service['duration_minutes'].' min':'-' ?></strong></div><div><span>Description</span><strong><?= e($service['description']?:'-') ?></strong></div><div><span>Created</span><strong><?= e($service['created_at']) ?></strong></div></div></div><div class="customer-modal-footer"><a class="btn btn-light" href="index.php?page=admin&amp;section=services">Close</a><?php if ((current_user()['role'] ?? '') !== 'cashier'): ?><form method="post" action="index.php?page=admin&amp;section=services-toggle"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>"><button class="btn btn-light"><?= $service['status']==='active'?'Deactivate':'Activate' ?></button></form><a class="btn btn-danger" href="index.php?page=admin&amp;section=services-edit&amp;id=<?= (int)$service['id'] ?>">Edit Service</a><?php endif; ?></div></section></div>
