<?php
$title = 'Staff Registration';

/** @var array|null $user */
$first_name = $user['first_name'] ?? '';
$role = $user['role'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php $default = "BSU-IACUC"; ?>
    <title><?= isset($title) ? "$title - $default" : $default ?></title>

    <link rel="stylesheet" href="<?= asset_css('body.css') ?>">
    <link rel="stylesheet" href="<?= asset_css('account.css') ?>">
    <link rel="stylesheet" href="<?= asset_css('form.css') ?>">
    <link rel="stylesheet" href="<?= asset_css('admin/admin.css') ?>">

    <script src="<?= asset_js('password-toggle.js') ?>" defer></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Akt:wght@100..900&family=Alfa+Slab+One&display=swap" rel="stylesheet">
</head>

<body>
    <?php include dirname(__DIR__) . '/includes/sprites.php'; ?>
    <div class="body">
        <main class="main-content wide" id="main-content" tabindex="-1">

            <?php if (!empty($success)): ?>
                <h1>Application Submitted</h1>
                <div class="success-message">
                    <p>
                        Your application has been received and is pending approval.
                        We'll email you once your account has been reviewed. We've also sent a link to verify your email. You may now close this window.
                    </p>
                </div>

            <?php else: ?>
                <form method="POST" action="<?= ROOT ?>/admin/register_process">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? ''); ?>">
                    <input type="hidden" name="invite_token" value="<?= htmlspecialchars($token ?? ''); ?>">

                    <h1>Staff Registration</h1>

                    <?php if (!empty($errors)): ?>
                        <div class="error-messages">
                            <ul>
                                <?php foreach ($errors as $err): ?>
                                    <li><?= htmlspecialchars($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <span class="helper">(Alphabetic characters (including accented and non-English letters), hyphens, and apostrophes only.)</span>
                    <div class="label-group">
                        <div class="input-group">
                            <input type="text" id="first_name" name="first_name" placeholder=" "
                                value="<?= htmlspecialchars($old['first_name'] ?? ''); ?>" required>
                            <label for="first_name">First Name</label>
                        </div>
                        <div class="input-group">
                            <input type="text" id="last_name" name="last_name" placeholder=" "
                                value="<?= htmlspecialchars($old['last_name'] ?? ''); ?>" required>
                            <label for="last_name">Last Name</label>
                        </div>
                    </div>

                    <div class="input-group">
                        <input type="text" id="username" name="username" placeholder=" "
                            value="<?= htmlspecialchars($old['username'] ?? ''); ?>" required>
                        <label for="username">Username</label>
                    </div>

                    <div class="input-group">
                        <input type="email" id="email" name="email" placeholder=" "
                            value="<?= htmlspecialchars($old['email'] ?? ''); ?>" required>
                        <label for="email">Email</label>
                    </div>

                    <div class="input-group">
                        <input type="password" id="password" name="password" placeholder=" " required>
                        <label for="password">Create Password</label>
                    </div>

                    <div class="input-group">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder=" " required>
                        <label for="confirm_password">Confirm Password</label>
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

                    <label class="center">Applying as <span class="preset-role bold"><?= htmlspecialchars(ucfirst($preset_role ?? '')); ?></span>.</label>

                    <button type="submit">Submit Application</button>
                    <p class="helper-big">Your registration will be reviewed before you can log in. For inquiries, please contact the administrator who provided you with the link.</p>
                </form>
            <?php endif; ?>

        </main>
    </div>