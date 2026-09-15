<?php
$isEdit = $section === 'stock-edit';
$stockOptions = $stockOptions ?? [];
$categories = stock_categories();
$submissionToken = (string)($_POST['stock_submission_token'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/D', $submissionToken)) $submissionToken = bin2hex(random_bytes(32));
?>
<div class="customer-modal-backdrop stock-form-backdrop" role="presentation">
    <section class="estimate-side-panel stock-side-panel" role="dialog" aria-modal="true" aria-labelledby="stock-form-title">
        <div class="estimate-panel-header">
            <div>
                <h2 id="stock-form-title"><?= $isEdit ? 'Edit Stock Item' : 'Add New Stock Item' ?></h2>
                <span><?= $isEdit ? 'Update stock item details' : 'Create a new spare part record' ?></span>
            </div>
            <a href="index.php?page=admin&amp;section=stock" aria-label="Close">&times;</a>
        </div>
        <div class="estimate-panel-body">
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form data-stock-save-form method="post" action="index.php?page=admin&amp;section=<?= e($section) ?>&amp;id=<?= (int)$id ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="stock_submission_token" value="<?= e($submissionToken) ?>">
                <input type="hidden" name="existing_stock_id" id="existing-stock-id" value="<?= (int)($input['existing_stock_id'] ?? 0) ?>">
                <div class="estimate-form-section">
                    <h3>Item Information</h3>
                    <div class="stock-search-help">Search an existing part number or name to load its saved details. For a new item, enter its name and leave the part number blank to generate it automatically when saved.</div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" for="stock-part-code">Part No.<?= $isEdit ? ' *' : '' ?></label>
                            <input class="form-control" id="stock-part-code" name="part_code" list="stock-part-codes" value="<?= e($input['part_code']) ?>" placeholder="<?= $isEdit ? 'Part number' : 'Auto-generated if left blank' ?>" maxlength="60" autocomplete="off" <?= $isEdit ? 'required' : '' ?>>
                            <datalist id="stock-part-codes">
                                <?php foreach ($stockOptions as $option): ?>
                                    <option value="<?= e($option['part_code']) ?>"><?= e($option['part_name']) ?></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="stock-part-name">Part Name *</label>
                            <input class="form-control" id="stock-part-name" name="part_name" list="stock-part-names" value="<?= e($input['part_name']) ?>" placeholder="Search part name..." autocomplete="off" required>
                            <datalist id="stock-part-names">
                                <?php foreach ($stockOptions as $option): ?>
                                    <option value="<?= e($option['part_name']) ?>"><?= e($option['part_code']) ?></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="stock-category">Category</label>
                            <input class="form-control" name="category" id="stock-category" list="stock-categories" value="<?= e($input['category']) ?>" placeholder="Select or type a category" maxlength="100" autocomplete="off" aria-describedby="stock-category-help">
                            <datalist id="stock-categories">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= e($category) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <small id="stock-category-help" class="text-muted">New categories are added to the list when you save the item.</small>
                        </div>
                        <div class="col-6"><label class="form-label">Brand</label><input class="form-control" name="brand" id="stock-brand" value="<?= e($input['brand']) ?>" placeholder="Toyota"></div>
                        <div class="col-6"><label class="form-label">Unit</label><input class="form-control" name="unit" id="stock-unit" value="<?= e($input['unit']) ?>" placeholder="PCS"></div>
                        <div class="col-6">
                            <label class="form-label">Supplier</label>
                            <select class="form-select" name="supplier_id" id="stock-supplier">
                                <option value="0">No supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= (int)$supplier['id'] ?>" <?= (int)$input['supplier_id'] === (int)$supplier['id'] ? 'selected' : '' ?>><?= e($supplier['name'].' ('.$supplier['supplier_code'].')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="estimate-form-section">
                    <h3>Pricing &amp; Stock</h3>
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label">Buying Price (Rs.)</label><input class="form-control" type="number" min="0" step="0.01" name="buying_price" id="stock-buying-price" value="<?= e($input['buying_price']) ?>"></div>
                        <div class="col-6"><label class="form-label">Selling Price (Rs.)</label><input class="form-control" type="number" min="0" step="0.01" name="selling_price" id="stock-selling-price" value="<?= e($input['selling_price']) ?>"></div>
                        <div class="col-6"><label class="form-label">Opening Stock Qty</label><input class="form-control" type="number" min="0" step="0.01" name="stock_qty" id="stock-qty" value="<?= e($input['stock_qty']) ?>"></div>
                        <div class="col-6"><label class="form-label">Low Stock Level</label><input class="form-control" type="number" min="0" step="0.01" name="reorder_level" id="stock-reorder-level" value="<?= e($input['reorder_level']) ?>"></div>
                        <div class="col-12"><label class="form-label">Status</label><select class="form-select" name="status" id="stock-status"><option value="active" <?= $input['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $input['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
                    </div>
                </div>
                <div class="estimate-form-section"><label class="form-label">Notes</label><textarea class="form-control" name="notes" id="stock-notes" rows="3" placeholder="Optional notes"><?= e($input['notes']) ?></textarea></div>
                <div class="estimate-form-footer"><a class="btn btn-light" href="index.php?page=admin&amp;section=stock">Cancel</a><button class="btn btn-danger" type="submit"><?= $isEdit ? 'Update Item' : 'Save Item' ?></button></div>
            </form>
        </div>
    </section>
</div>
<script>
(() => {
    const items = <?= json_encode($stockOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const fields = {
        id: document.getElementById('existing-stock-id'),
        code: document.getElementById('stock-part-code'),
        name: document.getElementById('stock-part-name'),
        category: document.getElementById('stock-category'),
        brand: document.getElementById('stock-brand'),
        unit: document.getElementById('stock-unit'),
        supplier: document.getElementById('stock-supplier'),
        buying: document.getElementById('stock-buying-price'),
        selling: document.getElementById('stock-selling-price'),
        quantity: document.getElementById('stock-qty'),
        reorder: document.getElementById('stock-reorder-level'),
        status: document.getElementById('stock-status'),
        notes: document.getElementById('stock-notes')
    };
    function loadItem(value, key) {
        const search = value.trim().toLowerCase();
        const item = items.find(row => String(row[key] || '').toLowerCase() === search);
        if (!item) {
            fields.id.value = '0';
            return;
        }
        fields.id.value = item.id;
        fields.code.value = item.part_code || '';
        fields.name.value = item.part_name || '';
        fields.category.value = item.category || '';
        fields.brand.value = item.brand || '';
        fields.unit.value = item.unit || 'PCS';
        fields.supplier.value = item.supplier_id || '0';
        fields.buying.value = item.buying_price ?? '0';
        fields.selling.value = item.selling_price ?? '0';
        fields.quantity.value = item.stock_qty ?? '0';
        fields.reorder.value = item.reorder_level ?? '10';
        fields.status.value = item.status || 'active';
        fields.notes.value = item.notes || '';
    }
    fields.code.addEventListener('change', () => loadItem(fields.code.value, 'part_code'));
    fields.name.addEventListener('change', () => loadItem(fields.name.value, 'part_name'));
    fields.code.addEventListener('input', () => { if (!items.some(row => String(row.part_code).toLowerCase() === fields.code.value.trim().toLowerCase())) fields.id.value = '0'; });
    fields.name.addEventListener('input', () => { if (!items.some(row => String(row.part_name).toLowerCase() === fields.name.value.trim().toLowerCase())) fields.id.value = '0'; });
})();
</script>
<div class="page-heading"><div><div class="breadcrumb-line">Home <span>&rsaquo;</span> Stock / Parts <span>&rsaquo;</span> <b>Add Stock</b></div><h1>Add New Stock Item</h1></div></div><div class="admin-panel form-card"><?php if($errors): ?><div class="alert alert-danger"><?php foreach($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?><form data-stock-save-form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="stock_submission_token" value="<?= e($submissionToken) ?>"><div class="row g-3"><div class="col-md-6"><label class="form-label">Part No.<?= $isEdit ? ' *' : '' ?></label><input class="form-control" name="part_code" value="<?= e($input['part_code']) ?>" placeholder="Auto-generated if left blank" maxlength="60" <?= $isEdit ? 'required' : '' ?>></div><div class="col-md-6"><label class="form-label">Part Name *</label><input class="form-control" name="part_name" value="<?= e($input['part_name']) ?>" required></div><div class="col-md-4"><label class="form-label">Category</label><input class="form-control" name="category" list="stock-categories" value="<?= e($input['category']) ?>" placeholder="Select or type a category" maxlength="100"></div><div class="col-md-4"><label class="form-label">Brand</label><input class="form-control" name="brand" value="<?= e($input['brand']) ?>"></div><div class="col-md-4"><label class="form-label">Unit</label><input class="form-control" name="unit" value="<?= e($input['unit']) ?>"></div><div class="col-md-4"><label class="form-label">Buying Price</label><input class="form-control" type="number" step="0.01" name="buying_price" value="<?= e((string)$input['buying_price']) ?>"></div><div class="col-md-4"><label class="form-label">Selling Price</label><input class="form-control" type="number" step="0.01" name="selling_price" value="<?= e((string)$input['selling_price']) ?>"></div><div class="col-md-4"><label class="form-label">Opening Quantity</label><input class="form-control" type="number" step="0.01" name="stock_qty" value="<?= e((string)$input['stock_qty']) ?>"></div><div class="col-md-4"><label class="form-label">Reorder Level</label><input class="form-control" type="number" step="0.01" name="reorder_level" value="<?= e((string)$input['reorder_level']) ?>"></div><div class="col-md-8"><label class="form-label">Supplier</label><select class="form-select" name="supplier_id"><option value="0">No supplier</option><?php foreach($suppliers as $supplier): ?><option value="<?= (int)$supplier['id'] ?>"><?= e($supplier['name'].' ('.$supplier['supplier_code'].')') ?></option><?php endforeach; ?></select></div><div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3"><?= e($input['notes']) ?></textarea></div></div><div class="estimate-form-footer px-0"><a class="btn btn-light" href="index.php?page=admin&amp;section=stock">Cancel</a><button class="btn btn-danger">Save Stock Item</button></div></form></div>

<script>
(() => {
    const forms = document.querySelectorAll('[data-stock-save-form]');
    let submitting = false;
    const reset = () => { submitting = false; forms.forEach(form => {
        form.querySelectorAll('[data-stock-submit-label]').forEach(button => {
            button.disabled = false; button.textContent = button.dataset.stockSubmitLabel;
        });
    }); };
    forms.forEach(form => form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        submitting = true;
        forms.forEach(candidate => candidate.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => {
            button.dataset.stockSubmitLabel = button.textContent;
            button.disabled = true; button.textContent = 'Saving...';
        }));
    }));
    window.addEventListener('pageshow', reset);
})();
</script>
