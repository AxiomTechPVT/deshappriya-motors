<?php
$statusLabels=['pending'=>'Pending','accepted'=>'Accepted','rejected'=>'Rejected','expired'=>'Expired'];
$isEdit=$section==='estimates-edit';
$itemRows=$input['items'] ?: [['name'=>'','quantity'=>'1','unit_price'=>'0']];
if ($isEdit && empty($input['items']) && !empty($estimate['items'])) $itemRows=array_map(static fn($item)=>['name'=>$item['item_name'],'quantity'=>$item['quantity'],'unit_price'=>$item['unit_price']],$estimate['items']);
?>
<div class="estimate-list-background" inert aria-hidden="true">
<?php
(static function (array $customers): void {
    $filters = estimate_filters();
    $rows = estimate_rows($filters);
    $summary = estimate_summary();
    require __DIR__ . '/estimates.php';
})($customers);
?>
</div>
<div class="customer-modal-backdrop estimate-form-backdrop"><section class="estimate-side-panel" role="dialog" aria-modal="true"><div class="estimate-panel-header"><div><h2><?= $isEdit?'Edit Estimate':'Create Estimate' ?></h2><span>Customer, vehicle, spare parts and service charge</span></div><a href="index.php?page=admin&amp;section=estimates" aria-label="Close">&times;</a></div>
<div class="estimate-panel-body">
<?php if($errors): ?><div class="alert alert-danger"><?php foreach($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="post" id="estimate-form" action="index.php?page=admin&amp;section=<?= e($section) ?>&amp;id=<?= (int)$id ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<section class="estimate-form-section"><h3>Customer &amp; Vehicle</h3><div class="row g-3"><div class="col-7"><label class="form-label">Customer *</label><select class="form-select" id="estimate-customer" name="customer_id" required><option value="">Select customer</option><?php foreach($customers as $customer): ?><option value="<?= (int)$customer['id'] ?>" <?= $input['customer_id']==(int)$customer['id']?'selected':'' ?>><?= e($customer['name']) ?> (<?= e($customer['contact_number']) ?>)</option><?php endforeach; ?></select></div><div class="col-5"><label class="form-label">Vehicle</label><select class="form-select" id="estimate-vehicle" name="vehicle_id"><option value="0">Select vehicle</option><?php foreach($vehicles as $vehicle): ?><option value="<?= (int)$vehicle['id'] ?>" data-customer-id="<?= (int)$vehicle['customer_id'] ?>" data-mileage="<?= e((string)$vehicle['current_mileage']) ?>" data-engine="<?= e((string)$vehicle['engine_number']) ?>" data-chassis="<?= e((string)$vehicle['chassis_number']) ?>" <?= $input['vehicle_id']==(int)$vehicle['id']?'selected':'' ?>><?= e($vehicle['vehicle_number']) ?> - <?= e(trim(($vehicle['make']??'').' '.($vehicle['model']??''))) ?></option><?php endforeach; ?></select></div><div class="col-12"><button class="btn btn-light btn-sm" type="button" id="toggle-register-vehicle">+ Register New Vehicle</button></div><div class="col-12" id="register-vehicle-panel" <?= $register['enabled']?'':'hidden' ?>><div class="estimate-vehicle-register"><label class="form-label">Vehicle Number *<input class="form-control" name="register_vehicle[vehicle_number]" value="<?= e($register['vehicle_number']) ?>"></label><div class="row g-2"><div class="col-6"><label class="form-label">Vehicle Type<select class="form-select" name="register_vehicle[vehicle_type]"><?php foreach(['Car','Van','SUV','Motorcycle','Three Wheeler','Other'] as $type): ?><option <?= $register['vehicle_type']===$type?'selected':'' ?>><?= e($type) ?></option><?php endforeach; ?></select></label></div><div class="col-6"><label class="form-label">Model *<input class="form-control" name="register_vehicle[model]" value="<?= e($register['model']) ?>"></label></div></div><label class="form-label">Make / Brand<input class="form-control" name="register_vehicle[make]" value="<?= e($register['make']) ?>"></label><label class="form-check"><input class="form-check-input" type="checkbox" name="register_new_vehicle" value="1" <?= $register['enabled']?'checked':'' ?>> Save this vehicle to customer profile</label></div></div><div class="col-4"><label class="form-label">Mileage (KM)<input class="form-control" type="number" step="0.01" min="0" id="estimate-mileage" name="mileage" value="<?= e($input['mileage']) ?>"></label></div><div class="col-4"><label class="form-label">Engine No.<input class="form-control" id="estimate-engine" name="engine_number" value="<?= e($input['engine_number']) ?>"></label></div><div class="col-4"><label class="form-label">Chassis No.<input class="form-control" id="estimate-chassis" name="chassis_number" value="<?= e($input['chassis_number']) ?>"></label></div></div></section>
<section class="estimate-form-section"><h3>Estimate Details</h3><div class="row g-3"><div class="col-6"><label class="form-label">Estimate Date *<input class="form-control" type="date" name="estimate_date" value="<?= e($input['estimate_date']) ?>" required></label></div><div class="col-6"><label class="form-label">Valid Until *<input class="form-control" type="date" name="valid_until" value="<?= e($input['valid_until']) ?>" required></label></div><div class="col-8"><label class="form-label">Service Type / Issue *<input class="form-control" name="service_type" value="<?= e($input['service_type']) ?>" required></label></div><div class="col-4"><label class="form-label">Status<select class="form-select" name="status"><?php foreach($statusLabels as $value=>$label): ?><option value="<?= e($value) ?>" <?= $input['status']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label></div><div class="col-12"><label class="form-label">Notes<textarea class="form-control" name="notes" rows="2"><?= e($input['notes']) ?></textarea></label></div></div></section>
<section class="estimate-form-section"><div class="estimate-items-heading"><h3>Estimate Items</h3><button type="button" class="btn btn-outline-danger btn-sm" id="add-estimate-item">+ Add Item</button></div><p class="text-muted small">Search stock by item name or code. Select a result to fill the price, or type a new item and enter its price.</p><div id="estimate-items"><?php foreach($itemRows as $index=>$item): ?><div class="estimate-item-row"><input class="form-control item-name" list="estimate-catalog-options" name="items[<?= $index ?>][name]" value="<?= e((string)$item['name']) ?>" placeholder="Spare part or service" required><input class="form-control estimate-qty" type="number" min="0.01" step="0.01" name="items[<?= $index ?>][quantity]" value="<?= e((string)$item['quantity']) ?>"><input class="form-control estimate-price" type="number" min="0" step="0.01" name="items[<?= $index ?>][unit_price]" value="<?= e((string)$item['unit_price']) ?>"><output class="estimate-line-amount">0.00</output><button type="button" class="estimate-remove-item">&times;</button></div><?php endforeach; ?></div><datalist id="estimate-catalog-options"><?php foreach($catalog as $entry): ?><option value="<?= e($entry['name']) ?>" data-price="<?= e((string)$entry['price']) ?>" data-code="<?= e($entry['code'] ?? '') ?>"><?= e($entry['kind']) ?></option><?php endforeach; ?></datalist></section>
<div class="estimate-service-charge"><label class="form-label">Service Charge (Rs.)<input class="form-control" id="estimate-service-charge" type="number" min="0" step="0.01" name="service_charge" value="<?= e($input['service_charge']) ?>"></label><small>Garage labour/service charge added to subtotal.</small></div>
<section class="estimate-total-box"><div><span>Subtotal</span><strong id="estimate-subtotal">Rs. 0.00</strong></div><div><label>Discount <input class="form-control" id="estimate-discount" type="number" min="0" step="0.01" name="discount" value="<?= e($input['discount']) ?>"></label><strong id="estimate-discount-display">Rs. 0.00</strong></div><div><label>VAT (%) <input class="form-control" id="estimate-vat-rate" type="number" min="0" step="0.01" name="vat_rate" value="<?= e($input['vat_rate']) ?>"></label><strong id="estimate-vat-display">Rs. 0.00</strong></div><div class="estimate-grand-total"><span>Total Amount</span><strong id="estimate-total">Rs. 0.00</strong></div></section><div class="estimate-form-footer"><a class="btn btn-light" href="index.php?page=admin&amp;section=estimates">Cancel</a><button class="btn btn-danger" type="submit">Save Estimate</button></div>
</form></div></section></div>
<script>
document.addEventListener('DOMContentLoaded',function(){const form=document.getElementById('estimate-form'),items=document.getElementById('estimate-items'),customer=document.getElementById('estimate-customer'),vehicle=document.getElementById('estimate-vehicle'),catalog=Array.from(document.querySelectorAll('#estimate-catalog-options option')).map(o=>({name:o.value,code:o.dataset.code||'',price:parseFloat(o.dataset.price||0)||0}));let next=items.children.length;
function filterVehicles(){Array.from(vehicle.options).forEach(o=>{if(!o.dataset.customerId)return;o.hidden=!!customer.value&&o.dataset.customerId!==customer.value;if(o.hidden&&o.selected)vehicle.value='0';});}
function fillVehicle(){const o=vehicle.options[vehicle.selectedIndex];if(!o||!o.value)return;document.getElementById('estimate-mileage').value=o.dataset.mileage||'';document.getElementById('estimate-engine').value=o.dataset.engine||'';document.getElementById('estimate-chassis').value=o.dataset.chassis||'';}
function totals(){let sub=0;items.querySelectorAll('.estimate-item-row').forEach(r=>{const a=(parseFloat(r.querySelector('.estimate-qty').value)||0)*(parseFloat(r.querySelector('.estimate-price').value)||0);r.querySelector('.estimate-line-amount').textContent=a.toFixed(2);sub+=a;});sub+=parseFloat(document.getElementById('estimate-service-charge').value)||0;const d=Math.min(parseFloat(document.getElementById('estimate-discount').value)||0,sub),v=(sub-d)*(parseFloat(document.getElementById('estimate-vat-rate').value)||0)/100;document.getElementById('estimate-subtotal').textContent='Rs. '+sub.toFixed(2);document.getElementById('estimate-discount-display').textContent='Rs. '+d.toFixed(2);document.getElementById('estimate-vat-display').textContent='Rs. '+v.toFixed(2);document.getElementById('estimate-total').textContent='Rs. '+(sub-d+v).toFixed(2);}
function bind(r){
    const input=r.querySelector('.item-name'),price=r.querySelector('.estimate-price');
    input.removeAttribute('list');input.autocomplete='off';input.placeholder='Search stock or type a new item';
    input.setAttribute('aria-label','Item name or stock code');
    const picker=document.createElement('div');picker.className='estimate-item-picker';input.before(picker);picker.appendChild(input);
    const results=document.createElement('div');results.className='estimate-item-results';results.hidden=true;picker.appendChild(results);
    results.id='estimate-item-results-'+input.name.replace(/[^a-z0-9]/gi,'-');results.setAttribute('role','listbox');
    input.setAttribute('role','combobox');input.setAttribute('aria-autocomplete','list');input.setAttribute('aria-controls',results.id);input.setAttribute('aria-expanded','false');
    let choices=[],active=-1;
    const close=()=>{results.hidden=true;input.setAttribute('aria-expanded','false');input.removeAttribute('aria-activedescendant');active=-1;};
    const choose=(entry)=>{input.value=entry.name;price.value=entry.price.toFixed(2);input.dataset.selectedName=entry.name;input.focus();close();totals();};
    function show(){
        const query=input.value.trim().toLowerCase();choices=catalog.filter(x=>(x.name+' '+x.code).toLowerCase().includes(query)).slice(0,12);results.replaceChildren();active=-1;
        choices.forEach((entry,index)=>{const button=document.createElement('button');button.type='button';button.id=results.id+'-'+index;button.setAttribute('role','option');button.setAttribute('aria-selected','false');button.tabIndex=-1;
            const name=document.createElement('strong');name.textContent=entry.name;const detail=document.createElement('small');detail.textContent=(entry.code?entry.code+' · ':'')+'Rs. '+entry.price.toFixed(2);button.append(name,detail);button.addEventListener('click',()=>choose(entry));results.appendChild(button);});
        const hint=document.createElement('div');hint.className='estimate-item-search-help';hint.textContent=choices.length?'Not listed? Keep your typed item name and enter its price.':'No matching stock item. Keep this name and enter its price to add it to the estimate.';results.appendChild(hint);
        results.hidden=false;input.setAttribute('aria-expanded','true');input.removeAttribute('aria-activedescendant');
    }
    input.addEventListener('focus',show);
    input.addEventListener('input',()=>{if(input.dataset.selectedName&&input.value!==input.dataset.selectedName){delete input.dataset.selectedName;price.value='0';}show();totals();});
    input.addEventListener('keydown',event=>{
        if(event.key==='Escape'){event.preventDefault();event.stopPropagation();close();return;}
        if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();if(results.hidden)show();if(!choices.length)return;active=(active+(event.key==='ArrowDown'?1:-1)+choices.length)%choices.length;
            results.querySelectorAll('button').forEach((button,index)=>button.setAttribute('aria-selected',index===active?'true':'false'));const current=results.children[active];input.setAttribute('aria-activedescendant',current.id);current.scrollIntoView({block:'nearest'});
        }else if(event.key==='Enter'&&!results.hidden){event.preventDefault();if(active>=0)choose(choices[active]);else{close();price.focus();}}
    });
    picker.addEventListener('focusout',()=>setTimeout(()=>{if(!picker.contains(document.activeElement))close();},0));
    input.addEventListener('change',()=>{if(input.dataset.selectedName===input.value)return;const query=input.value.trim().toLowerCase(),hit=catalog.find(x=>x.name.toLowerCase()===query||(x.code&&x.code.toLowerCase()===query));if(hit){input.value=hit.name;price.value=hit.price.toFixed(2);input.dataset.selectedName=hit.name;totals();}});
    r.querySelectorAll('input').forEach(i=>i.addEventListener('input',totals));
    r.querySelector('.estimate-remove-item').addEventListener('click',function(){if(items.children.length>1){r.remove();totals();}});
}
document.getElementById('add-estimate-item').addEventListener('click',function(){const r=document.createElement('div');r.className='estimate-item-row';r.innerHTML='<input class="form-control item-name" list="estimate-catalog-options" name="items['+next+'][name]" placeholder="Spare part or service" required><input class="form-control estimate-qty" type="number" min="0.01" step="0.01" name="items['+next+'][quantity]" value="1"><input class="form-control estimate-price" type="number" min="0" step="0.01" name="items['+next+'][unit_price]" value="0"><output class="estimate-line-amount">0.00</output><button type="button" class="estimate-remove-item">&times;</button>';items.appendChild(r);bind(r);next++;});document.getElementById('toggle-register-vehicle').addEventListener('click',function(){const p=document.getElementById('register-vehicle-panel');p.toggleAttribute('hidden');});customer.addEventListener('change',function(){filterVehicles();});vehicle.addEventListener('change',fillVehicle);items.querySelectorAll('.estimate-item-row').forEach(bind);[document.getElementById('estimate-service-charge'),document.getElementById('estimate-discount'),document.getElementById('estimate-vat-rate')].forEach(i=>i.addEventListener('input',totals));filterVehicles();totals();});
</script>
<?php if ($isEdit): ?>
<dialog class="vehicle-update-confirm" data-estimate-update-confirm aria-labelledby="estimate-update-title">
    <h2 id="estimate-update-title">Update Estimate?</h2>
    <p>Are you sure you want to save these estimate changes?</p>
    <div class="customer-form-actions">
        <button type="button" class="btn btn-light" data-estimate-update-no autofocus>No</button>
        <button type="button" class="btn btn-danger" data-estimate-update-yes>Yes</button>
    </div>
</dialog>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('estimate-form');
    const dialog = document.querySelector('[data-estimate-update-confirm]');
    const yes = dialog.querySelector('[data-estimate-update-yes]');
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!dialog.open) dialog.showModal();
    });
    dialog.querySelector('[data-estimate-update-no]').addEventListener('click', () => dialog.close());
    yes.addEventListener('click', function () {
        if (yes.disabled) return;
        if (!form.reportValidity()) { dialog.close(); return; }
        yes.disabled = true;
        const confirmation = document.createElement('input');
        confirmation.type = 'hidden';
        confirmation.name = 'confirm_update';
        confirmation.value = 'yes';
        form.appendChild(confirmation);
        HTMLFormElement.prototype.submit.call(form);
    });
});
</script>
<?php endif; ?>
