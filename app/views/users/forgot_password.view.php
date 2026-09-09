<?php

/** @var string $route */
/** @var string $csrf */
/** @var string|null $error */
/** @var string|null $success */

$title = 'Forgot Password';

$hideHeaderAuth = true;
$hideHeader     = true;

include dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= asset_css('account.css') ?>">
<link rel="stylesheet" href="<?= asset_css('form.css') ?>">

<div class="body">
    <main class="main-content main-content--pinned-nav" id="main-content" tabindex="-1">
        <a class="btn-back button btn-back--pinned" id="account-back" href="<?= ROOT ?>/<?= str_contains($route, 'admin') ? 'admin/login' : 'users/login' ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#back-icon">
            </svg>
            Back to Login
        </a>

        <form method="POST" action="<?= ROOT ?>/<?= $route ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <h1>Forgot Password</h1>
            <p class="form-label">
                Enter your email and we'll send you a reset link.
            </p>

            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#info-icon">
                    </svg>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#info-icon">
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="input-group">
                <input type="email" id="email" name="email" placeholder=" " required>
                <label for="email">Email</label>
            </div>

            <button type="submit">Send Reset Link</button>
        </form>
    </main>
</div>