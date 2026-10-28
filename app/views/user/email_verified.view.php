<?php

/** @var string $continueUrl */
$title = 'Email Verified';
$hideHeaderAuth = true;
$hideHeader     = true;

include dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= asset_css('account.css') ?>">
<link rel="stylesheet" href="<?= asset_css('form.css') ?>">

<div class="body">
  <main class="main-content main-content--pinned-nav" id="main-content" tabindex="-1">
    <form onsubmit="return false">
      <h1>Email Verified</h1>

      <p class="success-desc verify-desc">Your email address is verified. You may now close this window and go back to your open BSU-IACUC website tab.</p>

      <button type="button" class="btn-login btn-login--fit" id="close-verified-tab" data-continue-url="<?= htmlspecialchars($continueUrl, ENT_QUOTES, 'UTF-8') ?>">Close This Window</button>
    </form>
  </main>
</div>

<script src="<?= asset_js('email-verified.js') ?>" defer></script>

</body>

</html>