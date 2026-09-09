<?php $user = current_user(); $isAdmin = $user['role'] === 'administrator'; $pendingCashHandoverCount = 0; if ($isAdmin) { try { $pendingCashHandoverCount = (int) database()->query("SELECT COUNT(*) FROM cashier_registers WHERE handover_status = 'pending'")->fetchColumn(); } catch (Throwable $ignored) {} } ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Dashboard') ?> | Deshappriya Motors</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
    <?php if (strpos((string) ($section ?? ''), 'reports-') === 0): ?><link href="assets/css/reports.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/reports.css') ?>" rel="stylesheet"><link href="assets/css/reports-overrides.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/reports-overrides.css') ?>" rel="stylesheet"><?php endif; ?>
    <?php if (strpos((string) ($section ?? ''), 'jobcards') === 0): ?><link href="assets/css/jobcards.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/jobcards.css') ?>" rel="stylesheet"><?php endif; ?>
    <?php if (($section ?? '') === 'jobcards-edit'): ?><link href="assets/css/jobcard-edit.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/jobcard-edit.css') ?>" rel="stylesheet"><?php endif; ?>
    <?php if (in_array(($section ?? ''), ['jobcards-view', 'jobcards-receipt'], true)): ?><link href="assets/css/jobcard-payment.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/jobcard-payment.css') ?>" rel="stylesheet"><?php endif; ?>
    <?php if (($section ?? '') === 'jobcards-view'): ?><link href="assets/css/jobcard-payment-calc.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/jobcard-payment-calc.css') ?>" rel="stylesheet"><?php endif; ?>
    <?php if (($section ?? '') === 'jobcards-receipt'): ?><link href="assets/css/thermal-receipt.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/thermal-receipt.css') ?>" rel="stylesheet"><?php endif; ?>
    <?php if (($section ?? '') === 'jobcards-receipt'): ?><link href="assets/css/receipt-lines.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/receipt-lines.css') ?>" rel="stylesheet"><?php endif; ?>
    <?php if (strpos((string) ($section ?? ''), 'invoices') === 0): ?><link href="assets/css/invoices.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/invoices.css') ?>" rel="stylesheet"><?php endif; ?>
</head>
<body class="<?= $isAdmin ? 'admin-user' : 'cashier-user' ?>">
<?php $referenceLayout = $isAdmin && (strpos((string) ($section ?? ''), 'customers') === 0 || strpos((string) ($section ?? ''), 'vehicles') === 0 || strpos((string) ($section ?? ''), 'suppliers') === 0 || strpos((string) ($section ?? ''), 'employees') === 0 || strpos((string) ($section ?? ''), 'jobcards') === 0); ?>
<div class="app-shell <?= $referenceLayout ? 'customer-layout' : '' ?> <?= ($section ?? '') === 'vehicles' ? 'vehicle-list-page' : '' ?>">
    <?php require __DIR__ . ($isAdmin ? '/admin-sidebar.php' : '/sidebar.php'); ?>
    <div class="main-area">
        <header class="topbar <?= $isAdmin ? 'admin-topbar' : '' ?>">
            <button class="btn menu-toggle d-lg-none" data-sidebar-toggle aria-label="Open navigation">MENU</button>
            <?php if (!$isAdmin): ?><div><span class="eyebrow">Operations hub</span><span class="topbar-subtitle d-none d-md-inline">Keep the workshop moving.</span></div><?php endif; ?>
            <div class="topbar-tools"><span class="header-date d-none d-md-inline"><?= date('D, d M Y') ?></span><?php if ($isAdmin): ?><a class="notification-dot <?= $pendingCashHandoverCount > 0 ? 'notification-active' : '' ?>" href="index.php?page=admin&amp;section=cashier-handovers" aria-label="Cashier handovers" title="Cashier handovers"><?= $pendingCashHandoverCount > 0 ? $pendingCashHandoverCount : '!' ?></a><?php else: ?><span class="notification-dot" aria-label="Notifications">!</span><?php endif; ?><div class="user-chip"><div class="avatar"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></div><div class="d-none d-sm-block"><strong><?= e($user['name']) ?></strong><small><?= e(ucfirst($user['role'])) ?></small></div></div></div>
        </header>
        <main class="content-area">
            <?php if ($message = flash('success')): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
            <?php if ($message = flash('error')): ?><div class="alert alert-danger"><?= e($message) ?></div><?php endif; ?>
