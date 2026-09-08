<?php
$title = 'Log In';
$hideHeaderAuth = true;
$hideHeader     = true;

include dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= asset_css('account.css') ?>">
<link rel="stylesheet" href="<?= asset_css('form.css') ?>">

<div class="body">
    <main class="main-content" id="main-content" tabindex="-1">
        <?php $themeToggleExtraClass = 'theme-toggle--card theme-toggle--floating'; ?>
        <?php include dirname(__DIR__) . '/includes/theme-toggle.php'; ?>

        <a class="btn-back button" href="<?= $_SERVER['HTTP_REFERER'] ?? ROOT . '/home' ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#back-icon">
            </svg>
            Back
        </a>

        <form method="POST" action="<?= ROOT ?>/users/login_process">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? $_SESSION['csrf_token'] ?? ''); ?>">

            <h1>Login</h1>

            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="success-message">
                    <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#info-icon">
                    </svg>
                    <?= htmlspecialchars($_SESSION['flash_success']); ?>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#info-icon">
                    </svg>
                    <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="input-group">
                <input type="text" id="username" name="username" placeholder=" " value="<?= htmlspecialchars($prefill ?? ''); ?>" required>
                <label for="username">Username or Email</label>
            </div>

            <div class="input-group">
                <input type="password" id="password" name="password" placeholder=" " minlength="8" required>
                <label for="password">Password</label>
            </div>

            <button type="submit" class="btn-login">Log In</button>

            <p class="underlined-p">Don't have an account? <a class="underlined" href="<?= ROOT ?>/users/register">Register here</a></p>
            <p class="underlined-p"><a class=" underlined" href="<?= ROOT ?>/users/forgot_password">Forgot Password</a></p>

            <p class="staff-login-link underlined-p">Are you staff? <a class="underlined" href="<?= ROOT ?>/admin/login">Staff login</a></p>
        </form>
    </main>
</div>

</body>

</html>