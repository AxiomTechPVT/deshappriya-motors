<?php
declare(strict_types=1);

function attendance_auto_checkout(PDO $pdo, ?DateTimeImmutable $now = null): int
{
    $today = ($now ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Colombo')))
        ->setTimezone(new DateTimeZone('Asia/Colombo'))->format('Y-m-d');
    // TIME has no date component: use the final second of the attendance day.
    $statement = $pdo->prepare("UPDATE employee_attendance SET check_out='23:59:59'
        WHERE attendance_date < :today AND check_in IS NOT NULL AND check_out IS NULL
        AND attendance_status IN ('present', 'half_day')");
    $statement->execute(['today' => $today]);
    return $statement->rowCount();
}
