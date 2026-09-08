<?php require __DIR__ . '/../includes/header.php'; ?>

<div class="account-page-heading">
    <div class="account-heading-copy">
        <span class="account-heading-mark">A</span>
        <div>
            <span class="eyebrow">Account settings</span>
            <h1>Change password</h1>
            <p>Protect your workshop account with a strong, unique password.</p>
        </div>
    </div>
    <span class="account-security-status"><span></span> Secure account</span>
</div>

<div class="account-settings-layout">
    <section class="form-card password-form-card">
        <div class="password-card-header">
            <span class="password-card-icon">*</span>
            <div>
                <h2>Update your password</h2>
                <p>Use a password you do not reuse on other websites.</p>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <div><?= e($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="password-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="password-field">
                <label class="form-label" for="current-password">Current password</label>
                <input class="form-control" id="current-password" name="current_password" type="password" autocomplete="current-password" required>
            </div>
            <div class="password-form-divider">Create a new password</div>
            <div class="password-field">
                <label class="form-label" for="new-password">New password</label>
                <input class="form-control" id="new-password" name="password" type="password" minlength="8" autocomplete="new-password" required>
            </div>
            <div class="password-field">
                <label class="form-label" for="password-confirmation">Confirm new password</label>
                <input class="form-control" id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            </div>
            <div class="password-requirements">
                <strong>Password tips</strong>
                <span>Use at least 8 characters with a mix of words, numbers, and symbols.</span>
            </div>
            <div class="password-form-actions">
                <a class="btn btn-light" href="index.php?page=dashboard">Cancel</a>
                <button class="btn btn-primary" type="submit">Update password</button>
            </div>
        </form>
    </section>

    <aside class="security-side-card">
        <div class="security-side-heading">
            <span class="security-shield">+</span>
            <div>
                <span class="eyebrow">Security overview</span>
                <h2>Keep access protected</h2>
            </div>
        </div>
        <div class="security-user">
            <span class="security-user-avatar"><?= e(strtoupper(substr($user['name'] ?? 'U', 0, 1))) ?></span>
            <div>
                <strong><?= e($user['name'] ?? 'User') ?></strong>
                <span><?= e($user['email'] ?? '') ?></span>
            </div>
        </div>
        <div class="security-checklist">
            <div><span class="security-check">1</span><span>Never share your password with anyone.</span></div>
            <div><span class="security-check">2</span><span>Avoid using easily guessed personal details.</span></div>
            <div><span class="security-check">3</span><span>Update it immediately if you suspect access was shared.</span></div>
        </div>
        <div class="security-note">
            <strong>Good practice</strong>
            <span>Change your password regularly and sign out on shared computers.</span>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
