<?php
$section = $section ?? '';
$cashierMenu = [
    'Dashboard' => ['dashboard'],
    'My Account' => ['account'],
    'Customers' => ['customers', 'customers-add'],
    'Vehicles' => ['vehicles', 'vehicles-add'],
    'Job Cards' => ['jobcards-new', 'jobcards-pending', 'jobcards-ongoing', 'jobcards-completed'],
    'Estimates' => ['estimates'],
    'Cashier' => ['cashier-register', 'invoices-mine', 'invoices', 'invoices-quick', 'reports-payments'],
    'Expenses' => ['expenses'],
    'Other Income' => ['other-income'],
    'Employee Advance' => ['cashier-advance-requests'],
    'Employee Attendance' => ['employees-attendance'],
    'Stock / Parts' => ['stock', 'stock-low'],
    'Services' => ['services'],
    'Appointments' => ['appointments'],
];
$cashierLabels = [
    'dashboard' => 'Dashboard', 'account' => 'My Account', 'customers' => 'All Customers', 'customers-add' => 'Add Customer',
    'vehicles' => 'All Vehicles', 'vehicles-add' => 'Add Vehicle', 'jobcards-new' => 'New Job Card', 'jobcards-pending' => 'Pending Job Cards',
    'jobcards-ongoing' => 'Ongoing Job Cards', 'jobcards-completed' => 'Completed Job Cards',
    'estimates' => 'Estimates', 'invoices-mine' => 'My Invoices', 'invoices' => 'Invoice List', 'invoices-quick' => 'Quick Invoice',
    'cashier-register' => 'Daily Cash Drawer', 'reports-payments' => 'Payment History', 'expenses' => 'Expense List', 'other-income' => 'Other Income',
    'employees-advances' => 'Employee Advance', 'cashier-advance-requests' => 'Employee Advance', 'employees-attendance' => 'Attendance', 'stock' => 'Stock List', 'stock-low' => 'Low Stock',
    'services' => 'Services', 'appointments' => 'Appointments',
];
$cashierIcons = ['Dashboard' => '&#9632;', 'My Account' => '&#9673;', 'Customers' => '&#9787;', 'Vehicles' => '&#9638;', 'Job Cards' => '&#9881;', 'Estimates' => '&#9998;', 'Cashier' => '&#36;', 'Expenses' => '&#9888;', 'Other Income' => '&#43;', 'Employee Advance' => '&#9733;', 'Employee Attendance' => '&#128197;', 'Stock / Parts' => '&#9632;', 'Services' => '&#9881;', 'Appointments' => '&#128197;'];
$cashierHref = static fn(string $item): string => $item === 'dashboard' ? 'index.php?page=cashier' : ($item === 'account' ? 'index.php?page=account' : 'index.php?page=admin&section=' . rawurlencode($item));
?>
<aside class="sidebar cashier-sidebar" data-sidebar>
    <div class="brand"><div class="brand-mark">DM</div><div><strong>DESHAPPRIYA<br>MOTORS</strong><small>Garage management system</small></div></div>
    <nav class="sidebar-nav"><span class="nav-label">Main menu</span><?php foreach ($cashierMenu as $group => $items): ?><?php if (count($items) === 1): ?><a class="nav-link <?= $section === $items[0] || ($items[0] === 'dashboard' && ($section ?? '') === '') ? 'active' : '' ?>" href="<?= e($cashierHref($items[0])) ?>"><span><?= $cashierIcons[$group] ?? '&#8226;' ?></span><?= e($cashierLabels[$items[0]] ?? $group) ?></a><?php else: ?><details class="nav-group" <?= in_array($section, $items, true) ? 'open' : '' ?>><summary><span><?= $cashierIcons[$group] ?? '&#8226;' ?></span><?= e($group) ?></summary><?php foreach ($items as $item): ?><a class="nav-sublink <?= $section === $item ? 'active' : '' ?>" href="<?= e($cashierHref($item)) ?>"><?= e($cashierLabels[$item] ?? $item) ?></a><?php endforeach; ?></details><?php endif; ?><?php endforeach; ?></nav>
    <div class="sidebar-bottom"><div class="account-card"><strong><?= e($user['name']) ?></strong><small>Cashier</small></div><a class="side-action" href="index.php?page=account">Account settings</a><form method="post" action="index.php?page=logout"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="side-action side-button">Sign out</button></form></div>
</aside>
