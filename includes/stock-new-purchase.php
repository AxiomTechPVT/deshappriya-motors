<?php
declare(strict_types=1);

function stock_new_purchase_input(array $source, float $qty, float $cost): ?array
{
    if (($source['record_purchase'] ?? '') !== '1') return null;
    $date = (string)($source['purchase_date'] ?? '');
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Select a valid purchase date.');
    if (!is_finite($qty) || !is_finite($cost) || $qty <= 0 || $cost < 0) throw new InvalidArgumentException('Enter a positive purchase quantity and a valid buying price.');
    $total = round($qty*$cost, 2);
    if (!is_finite($total) || $total > 9999999999.99) throw new InvalidArgumentException('Purchase total is too large.');
    $status = (string)($source['payment_status'] ?? 'paid');
    $method = (string)($source['payment_method'] ?? 'cash');
    if (!in_array($status, ['paid','partial','due'], true)) throw new InvalidArgumentException('Select a valid payment status.');
    if (!in_array($method, ['cash','card','bank_transfer','cheque'], true)) throw new InvalidArgumentException('Select a valid payment method.');
    $paid = $status === 'paid' ? $total : 0.0;
    if ($status === 'partial') {
        $raw = $source['purchase_paid_amount'] ?? '';
        if (!is_numeric($raw) || !is_finite((float)$raw)) throw new InvalidArgumentException('Enter a valid paid amount.');
        $paid = round((float)$raw,2);
        if ($paid <= 0 || $paid >= $total) throw new InvalidArgumentException('Partial payment must be greater than zero and less than the purchase total.');
    }
    $invoice = trim((string)($source['purchase_invoice_no'] ?? ''));
    if (strlen($invoice) > 80) throw new InvalidArgumentException('Supplier invoice number is too long.');
    return ['date'=>$date,'status'=>$status,'method'=>$method,'total'=>$total,'paid'=>$paid,'balance'=>round($total-$paid,2),'invoice'=>$invoice ?: null];
}

// Called inside the same transaction as the new stock item. No separate opening stock is added.
function stock_create_initial_purchase(PDO $pdo, int $itemId, array $values, array $purchase): void
{
    $user = current_user()['id'] ?? null;
    $s = $pdo->prepare("INSERT INTO stock_purchases (purchase_no,supplier_id,purchase_date,invoice_no,payment_status,paid_amount,balance_amount,total_amount,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?,'received',?,?)");
    $s->execute(['TMP-'.bin2hex(random_bytes(8)),$values['supplier_id'],$purchase['date'],$purchase['invoice'],$purchase['status'],$purchase['paid'],$purchase['balance'],$purchase['total'],$values['notes'],$user]);
    $purchaseId = (int)$pdo->lastInsertId();
    $no = 'PUR-'.date('ymd',strtotime($purchase['date'])).'-'.str_pad((string)$purchaseId,4,'0',STR_PAD_LEFT);
    $pdo->prepare('UPDATE stock_purchases SET purchase_no=? WHERE id=?')->execute([$no,$purchaseId]);
    $s = $pdo->prepare('INSERT INTO stock_purchase_items (purchase_id,stock_item_id,quantity,unit_cost,amount,buying_price,selling_price,line_total) VALUES (?,?,?,?,?,?,?,?)');
    $s->execute([$purchaseId,$itemId,$values['stock_qty'],$values['buying_price'],$purchase['total'],$values['buying_price'],$values['selling_price'],$purchase['total']]);
    $lineId = (int)$pdo->lastInsertId();
    $s = $pdo->prepare('INSERT INTO stock_batches (stock_item_id,stock_purchase_item_id,quantity_received,quantity_remaining,buying_price,selling_price_at_purchase,purchase_date,supplier_id,batch_reference) VALUES (?,?,?,?,?,?,?,?,?)');
    $s->execute([$itemId,$lineId,$values['stock_qty'],$values['stock_qty'],$values['buying_price'],$values['selling_price'],$purchase['date'],$values['supplier_id'],$no]);
    $s = $pdo->prepare("INSERT INTO stock_movements (stock_item_id,movement_type,quantity,quantity_before,quantity_after,unit_cost,reference_type,reference_id,notes,created_by) VALUES (?,'restock',?,0,?,?,'stock_purchase',?,?,?)");
    $s->execute([$itemId,$values['stock_qty'],$values['stock_qty'],$values['buying_price'],$purchaseId,'Initial stock purchase',$user]);
    stock_record_payment($pdo,$purchaseId,$purchase['paid'],$purchase['method'],$purchase['date'].' '.date('H:i:s'),true);
}

function stock_new_item_destination(int $itemId): string
{
    $s = database()->prepare('SELECT purchase_id FROM stock_purchase_items WHERE stock_item_id=? ORDER BY id LIMIT 1');
    $s->execute([$itemId]);
    $id = (int)$s->fetchColumn();
    return $id ? 'index.php?page=admin&section=stock-purchase-receipt&id='.$id : 'index.php?page=admin&section=stock';
}
