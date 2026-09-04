<?php $user = current_user(); $isAdmin = $user['role'] === 'administrator'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Dashboard') ?> | Deshappriya Motors</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php $customerLayout = $isAdmin && strpos((string) ($section ?? ''), 'customers') === 0; ?>
<div class="app-shell <?= $customerLayout ? 'customer-layout' : '' ?>">
    <?php require __DIR__ . ($isAdmin ? '/admin-sidebar.php' : '/sidebar.php'); ?>
    <div class="main-area">
        <header class="topbar <?= $isAdmin ? 'admin-topbar' : '' ?>">
            <button class="btn menu-toggle d-lg-none" data-sidebar-toggle aria-label="Open navigation">MENU</button>
            <?php if (!$isAdmin): ?><div><span class="eyebrow">Operations hub</span><span class="topbar-subtitle d-none d-md-inline">Keep the workshop moving.</span></div><?php endif; ?>
            <div class="topbar-tools"><span class="header-date d-none d-md-inline"><?= date('D, d M Y') ?></span><span class="notification-dot" aria-label="Notifications">!</span><div class="user-chip"><div class="avatar"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></div><div class="d-none d-sm-block"><strong><?= e($user['name']) ?></strong><small><?= e(ucfirst($user['role'])) ?></small></div></div></div>
        </header>
        <main class="content-area">
            <?php if ($message = flash('success')): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
