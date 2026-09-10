<div class="appointment-list-background" inert aria-hidden="true">
<?php
(static function (): void {
    $filters = appointment_filters();
    $rows = appointment_rows($filters);
    $summary = appointment_summary();
    require __DIR__ . '/appointments.php';
})();
?>
</div>
