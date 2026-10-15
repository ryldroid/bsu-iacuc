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
        <a class="btn-back button btn-back--pinned" id="account-back" href="<?= ROOT ?>/<?= str_contains($route, 'personnel') ? 'personnel/login' : 'user/login' ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#back-icon">
            </svg>
            Back
        </a>

        <form method="POST" action="<?= ROOT ?>/<?= $route ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <h1>Forgot Password</h1>
            <p class="form-label">
                Enter your email and we'll send you a reset link.
            </p>

            <?php
            $alertType = 'success';
            $alertMessages = $success;
            include dirname(__DIR__) . '/includes/alert.php';
            ?>

            <?php
            $alertType = 'error';
            $alertMessages = $error ?? '';
            include dirname(__DIR__) . '/includes/alert.php';
            ?>

            <div class="input-group">
                <input type="email" id="email" name="email" placeholder=" " required>
                <label for="email">Email</label>
            </div>

            <button type="submit" class="reset-link-btn">Send Reset Link</button>
        </form>
    </main>
</div>