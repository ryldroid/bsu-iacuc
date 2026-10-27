<?php

/** @var string $csrf */
/** @var string $action */
/** @var bool $enabled */
/** @var string $success */
/** @var string $error */

$title = 'Email Notifications';

$hideHeaderAuth = true;
$hideHeader     = true;

include dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= asset_css('account.css') ?>">
<link rel="stylesheet" href="<?= asset_css('form.css') ?>">

<div class="body">
  <main class="main-content main-content--pinned-nav" id="main-content" tabindex="-1">
    <form method="POST" action="<?= htmlspecialchars($action) ?>">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="email_notifications" value="<?= $enabled ? '0' : '1' ?>">

      <h1>Email Notifications</h1>
      <p class="form-label center">
        Email updates about your protocols are currently <strong><?= $enabled ? 'on' : 'off' ?></strong>.
      </p>

      <?php
      $alertType = $error !== '' ? 'error' : 'success';
      $alertMessages = $error !== '' ? $error : $success;
      include dirname(__DIR__) . '/includes/alert.php';
      ?>

      <button type="submit" class="reset-link-btn"><?= $enabled ? 'Unsubscribe' : 'Subscribe' ?></button>
    </form>
  </main>
</div>