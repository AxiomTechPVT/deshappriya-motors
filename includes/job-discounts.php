<?php
declare(strict_types=1);

function job_payment_allocation(float $partsTotal, float $serviceCharge, float $paid, float $currentPayment = 0): array
{
    $partsDue = max(0.0, min($partsTotal, $partsTotal + $serviceCharge));
    $serviceDue = max(0.0, $partsTotal + $serviceCharge - $partsDue);
    $paidBefore = max(0.0, $paid - $currentPayment);
    $partsPaid = min($partsDue, $paid);
    $servicePaid = min($serviceDue, max(0.0, $paid - $partsDue));
    $currentParts = min(max(0.0, $currentPayment), max(0.0, $partsDue - min($partsDue, $paidBefore)));
    $currentService = max(0.0, $currentPayment - $currentParts);
    return [
        'parts_due' => round($partsDue, 2),
        'parts_paid' => round($partsPaid, 2),
        'parts_balance' => round(max(0.0, $partsDue - $partsPaid), 2),
        'service_due' => round($serviceDue, 2),
        'service_paid' => round($servicePaid, 2),
        'service_balance' => round(max(0.0, $serviceDue - $servicePaid), 2),
        'current_parts' => round($currentParts, 2),
        'current_service' => round($currentService, 2),
    ];
}

function job_payment_calculation(array $job, array $source): array
{
    $base = round((float)$job['subtotal'] + (float)$job['service_charge'], 2);
    if ($base <= 0) throw new RuntimeException('Add a part or service charge before collecting payment.');
    if (($job['status'] ?? '') === 'cancelled') throw new RuntimeException('A cancelled job cannot receive payment.');
    $savedAmount = (float)($job['discount'] ?? 0);
    $savedType = $job['discount_type'] ?? ($savedAmount > 0 ? 'fixed' : '');
    $savedValue = $job['discount_value'] ?? $savedAmount;
    $type = (string)($source['discount_type'] ?? $savedType);
    if (!in_array($type, ['', 'fixed', 'percentage'], true)) throw new RuntimeException('Select a valid discount type.');
    $raw = $source['discount_value'] ?? $savedValue;
    if ($type === '') $raw = 0;
    if ($raw === '') $raw = 0;
    if (!is_numeric($raw) || !is_finite((float)$raw) || (float)$raw < 0) throw new RuntimeException('Enter a valid discount of zero or more.');
    $value = round((float)$raw, 2);
    if (($type === 'percentage' && $value > 100) || ($type === 'fixed' && $value > $base)) throw new RuntimeException('Discount cannot exceed the job amount or 100%.');
    $discount = $type === 'percentage' ? round($base * $value / 100, 2) : ($type === 'fixed' ? $value : 0.0);
    $paid = (float)$job['paid_amount'];
    if (($paid > 0 || ($job['status'] ?? '') === 'completed') && (abs($discount - $savedAmount) > 0.009 || $type !== $savedType || abs($value - (float)$savedValue) > 0.009)) {
        throw new RuntimeException('The discount cannot be changed after payment or completion.');
    }
    $total = round($base - $discount, 2);
    $balance = max(0.0, round($total - $paid, 2));
    if ($total < $paid - 0.009) throw new RuntimeException('Discount cannot reduce the total below payments already received.');
    if ($balance <= 0.009 && ($paid > 0 || ($job['status'] ?? '') === 'completed')) throw new RuntimeException('This job card is already fully paid.');
    $rawReceived = $source['amount'] ?? 0;
    if (!is_numeric($rawReceived) || !is_finite((float)$rawReceived) || (float)$rawReceived < 0) throw new RuntimeException('Enter a valid amount received.');
    $received = round((float)$rawReceived, 2);
    $method = (string)($source['payment_method'] ?? 'cash');
    if (!in_array($method, ['cash','card','bank','other'], true)) throw new RuntimeException('Select a valid payment method.');
    if ($balance > 0.009 && $received <= 0) throw new RuntimeException('Enter the amount received.');
    if ($method !== 'cash' && $received > $balance + 0.009) throw new RuntimeException('Card or bank payment cannot exceed the discounted outstanding balance.');
    $applied = min($received, $balance);
    $allocation = job_payment_allocation((float)$job['subtotal'], max(0.0, $total - (float)$job['subtotal']), $paid + $applied, $applied);
    return ['base'=>$base,'discount_type'=>$type ?: null,'discount_value'=>$value,'discount'=>$discount,'total'=>$total,'received'=>$received,'applied'=>$applied,'change'=>max(0.0, round($received-$applied,2)), 'paid'=>round($paid+$applied,2),'balance'=>max(0.0,round($balance-$applied,2)), 'method'=>$method] + $allocation;
}

