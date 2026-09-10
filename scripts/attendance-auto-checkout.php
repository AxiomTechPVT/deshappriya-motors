<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/attendance-auto-checkout.php';
try {
    echo attendance_auto_checkout(database()) . " attendance record(s) automatically checked out.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Attendance auto checkout failed. Check database availability.\n");
    exit(1);
}
