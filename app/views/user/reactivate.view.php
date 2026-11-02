<?php

/** @var string $csrf */
/** @var string $firstName */
/** @var string|null $deactivatedAt */
/** @var string|null $error */

$title = 'Reactivate Account';

$hideHeaderAuth = true;
$hideHeader     = true;

include dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= asset_css('account.css') ?>">
<link rel="stylesheet" href="<?= asset_css('form.css') ?>">

<div class="body">
    <main class="main-content main-content--pinned-nav" id="main-content" tabindex="-1">
        <a class="btn-back button btn-back--pinned" id="account-back" href="<?= ROOT ?>/user/login">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#back-icon">
            </svg>
            Back
        </a>

        <form method="POST" action="<?= ROOT ?>/user/reactivate">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <h1>Welcome back, <?= htmlspecialchars($firstName) ?></h1>
            <p class="form-label">
                Your account became inactive<?= $deactivatedAt ? ' on ' . date('F j, Y', strtotime($deactivatedAt)) : '' ?> because your latest clearance expired and you had no protocol pending. Your protocols and records were not changed.
            </p>
            <p class="form-label">
                Reactivate your account to sign in and submit a new protocol.
            </p>

            <?php
            $alertType = 'error';
            $alertMessages = $error ?? '';
            include dirname(__DIR__) . '/includes/alert.php';
            ?>

            <button type="submit" class="reset-link-btn">Reactivate my account</button>
        </form>
    </main>
</div>