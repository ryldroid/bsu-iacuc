<?php

/** @var string $csrf */
/** @var string $token */
/** @var string $route */
/** @var array $errors */
$title = 'Reset Password';
$hideHeader = true;
include dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= asset_css('account.css') ?>">
<link rel="stylesheet" href="<?= asset_css('form.css') ?>">

<div class="body">
    <main class="main-content" id="main-content" tabindex="-1">
        <form method="POST" action="<?= ROOT ?>/<?= $route ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <h1>Reset Password</h1>

            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="input-group">
                <input type="password" id="password" name="password" placeholder=" " required>
                <label for="password">New Password</label>
            </div>

            <div class="input-group">
                <input type="password" id="confirm_password" name="confirm_password" placeholder=" " required>
                <label for="confirm_password">Confirm New Password</label>
            </div>

            <div class="password-requirements">
                Password must contain:
                <ul>
                    <li>At least 8 characters</li>
                    <li>Uppercase letter</li>
                    <li>Lowercase letter</li>
                    <li>Number</li>
                    <li>Special character (! @ # $ % ^ &amp; *)</li>
                </ul>
            </div>

            <button type="submit">Reset Password</button>
        </form>
    </main>
</div>