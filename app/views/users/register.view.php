<?php
$title = 'Register';
$hideHeader = true;
include dirname(__DIR__) . '/includes/header.php';
?>

<?php if (!empty($success)): ?>
    <meta http-equiv="refresh" content="2; url=<?= ROOT ?>/users/login">
<?php endif; ?>

<link rel="stylesheet" href="<?= asset_css('account.css') ?>">
<link rel="stylesheet" href="<?= asset_css('form.css') ?>">

<div class="body">
    <main class="main-content wide" id="main-content" tabindex="-1">
        <?php $themeToggleExtraClass = 'theme-toggle--card theme-toggle--floating'; ?>
        <?php include dirname(__DIR__) . '/includes/theme-toggle.php'; ?>

        <a class="btn-back button" href="<?= $_SERVER['HTTP_REFERER'] ?? ROOT . '/home' ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#back-icon">
            </svg>
            Back
        </a>

        <form method="POST" action="<?= ROOT ?>/users/register_process">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? ''); ?>">

            <h1>Create Account</h1>

            <?php if (!empty($success)): ?>
                <div class="success-message">
                    Account created successfully! We've sent a link to verify your email.
                    <!-- <span>Redirecting in <span id="countdown">1</span> second...</span> -->

                    <span>Redirecting...</span>
                </div>
            <?php endif; ?>

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
                <input type="tel" id="phone_number" name="phone_number" placeholder=" "
                    value="<?= htmlspecialchars($old['phone_number'] ?? '+63'); ?>" required>
                <label for="phone_number">Phone Number</label>
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

            <button type="submit">Register</button>
            <p class="underlined-p">Already have an account? <a class="underlined" href="<?= ROOT ?>/users/login">Log in here</a></p>
        </form>
    </main>
</div>

<?php if (!empty($success)): ?>
    <script>
        let seconds = 1;
        const el = document.getElementById('countdown');
        if (el) {
            const timer = setInterval(() => {
                seconds--;
                if (seconds > 0) el.textContent = seconds;
                else clearInterval(timer);
            }, 1000);
        }
    </script>
<?php endif; ?>

</body>

</html>