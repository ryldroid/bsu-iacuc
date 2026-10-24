<?php
$title = 'Log In';
$hideHeaderAuth = true;
$hideHeader     = true;

include dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= asset_css('account.css') ?>">
<link rel="stylesheet" href="<?= asset_css('form.css') ?>">

<div class="body">
    <main class="main-content main-content--pinned-nav" id="main-content" tabindex="-1">
        <!-- <a class="btn-back button btn-back--pinned" href="<?= $_SERVER['HTTP_REFERER'] ?? ROOT . '/home' ?>"> -->
        <a class="btn-back button btn-back--pinned" href="<?= ROOT ?>/home">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#back-icon">
            </svg>
            Back
        </a>

        <?php $isPending = !empty($pendingEmail); ?>
        <form method="POST" action="<?= ROOT ?>/user/<?= $isPending ? 'resend_verification' : 'login_process' ?>"
            <?= $isPending ? 'id="pending-verification-form" data-status-url="' . ROOT . '/user/verification_status"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? $_SESSION['csrf_token'] ?? ''); ?>">

            <h1><?= $isPending ? 'Verify Your Email' : 'Login' ?></h1>

            <?php
            $alertType = 'success';
            $alertMessages = $_SESSION['flash_success'] ?? '';
            unset($_SESSION['flash_success']);
            include dirname(__DIR__) . '/includes/alert.php';
            ?>

            <?php
            $alertType = 'error';
            $alertMessages = $error ?? '';
            include dirname(__DIR__) . '/includes/alert.php';
            ?>

            <?php if ($isPending): ?>
                <div id="verify-alerts" class="verify-alerts"></div>
                <?php include dirname(__DIR__) . '/includes/alert-js.php'; ?>

                <p class="success-desc verify-desc">Send a verification link to your email, then open it to activate your account. This page continues on its own once you verify. If the address is wrong, correct it below first.</p>

                <div class="input-group">
                    <input type="email" id="verify_email" name="email" placeholder=" " value="<?= htmlspecialchars($pendingEmail ?? ''); ?>" autocomplete="email" required>
                    <label for="verify_email">Email</label>
                </div>

                <button type="submit" class="btn-login btn-login--fit">Send Verification Link</button>

                <p class="underlined-p"><a class="underlined" href="<?= ROOT ?>/user/login?switch=1">Back to login</a></p>
            <?php else: ?>
                <div class="input-group">
                    <input type="text" id="username" name="username" placeholder=" " value="<?= htmlspecialchars($prefill ?? ''); ?>" required>
                    <label for="username">Username or Email</label>
                </div>

                <div class="input-group">
                    <input type="password" id="password" name="password" placeholder=" " minlength="8" required>
                    <label for="password">Password</label>
                </div>

                <button type="submit" class="btn-login">Log In</button>

                <p class="underlined-p">
                    Don't have
                    <span aria-hidden="true">
                        <a
                            class="hidden-login-link"
                            href="<?= ROOT ?>/personnel/login"
                            tabindex="-1"
                            aria-hidden="true">an</a>
                    </span>
                    account?
                    <a class="underlined" href="<?= ROOT ?>/user/register">Register here</a>
                </p>
                <p class="underlined-p"><a class=" underlined" href="<?= ROOT ?>/user/forgot_password">Forgot Password</a></p>
            <?php endif; ?>
        </form>
    </main>
</div>

<?php if ($isPending): ?>
    <script src="<?= asset_js('login-verify.js') ?>" defer></script>
<?php endif; ?>

</body>

</html>