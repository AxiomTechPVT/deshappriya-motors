<aside class="sidebar" data-sidebar>
    <div class="brand"><div class="brand-mark">DM</div><div><strong>Deshappriya Motors</strong><small>Garage management</small></div></div>
    <nav class="sidebar-nav"><span class="nav-label">Workspace</span><a class="nav-link active" href="index.php?page=dashboard"><span>●</span> Dashboard</a></nav>
    <div class="sidebar-bottom"><div class="account-card"><strong><?= e($user['name']) ?></strong><small><?= e(ucfirst($user['role'])) ?></small></div><a class="side-action" href="index.php?page=password">Account settings</a><form method="post" action="index.php?page=logout"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="side-action side-button">Sign out</button></form></div>
</aside>
