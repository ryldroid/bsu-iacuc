<?php

/**
 * Floating toasts for page-level flash messages (success and error).
 *
 * @var string $toastSuccess Optional. Defaults to $_SESSION['flash_success']
 * @var string $toastError   Optional. Defaults to $_SESSION['flash_error']
 *
 * Also gives the page showToast(message, 'success'|'error') for JS-triggered toasts.
 */
$toastSuccess   = $toastSuccess ?? ($_SESSION['flash_success'] ?? '');
$toastError     = $toastError ?? ($_SESSION['flash_error'] ?? '');
$toastLoadJs    = !defined('FORM_ALERT_JS_LOADED');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
if ($toastLoadJs) {
  define('FORM_ALERT_JS_LOADED', true);
}
?>
<div class="toast-stack" id="toast-stack">
  <?php
  $alertType     = 'success';
  $alertMessages = $toastSuccess;
  include __DIR__ . '/alert.php';

  $alertType     = 'error';
  $alertMessages = $toastError;
  include __DIR__ . '/alert.php';
  ?>
</div>
<?php if ($toastLoadJs): ?>
  <script src="<?= asset_js('flash-dismiss.js') ?>"></script>
<?php endif; ?>
<?php unset($toastSuccess, $toastError, $toastLoadJs); ?>