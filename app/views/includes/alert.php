<?php

/**
 * Shared alert for form pages (success and error use the same shape).
 *
 * @var string       $alertType        'success' or 'error'
 * @var string|array $alertMessages    One message, or several (several are shown as a list)
 * @var int          $alertAutoDismiss Success only: ms before it fades out, 0 keeps it (default 4000)
 */
$alertType     = $alertType ?? 'error';
$alertMessages = array_values(array_filter((array) ($alertMessages ?? []), 'strlen'));
$alertIsError  = $alertType === 'error';
$alertDelay    = $alertIsError ? 5000 : (int) ($alertAutoDismiss ?? 4000);

if ($alertMessages):
?>
  <div class="form-alert form-alert--<?= $alertIsError ? 'error' : 'success' ?>"
    role="<?= $alertIsError ? 'alert' : 'status' ?>"
    <?= $alertDelay ? 'data-autodismiss="' . $alertDelay . '"' : '' ?>>
    <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <use href="#<?= $alertIsError ? 'alert-triangle-icon' : 'check-circle-icon' ?>" />
    </svg>

    <div class="form-alert-body">
      <?php if (count($alertMessages) === 1): ?>
        <p><?= htmlspecialchars($alertMessages[0], ENT_QUOTES, 'UTF-8') ?></p>
      <?php else: ?>
        <ul>
          <?php foreach ($alertMessages as $message): ?>
            <li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <button type="button" class="form-alert-close" aria-label="Dismiss message">
      <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <use href="#close-icon" />
      </svg>
    </button>
  </div>

  <?php include __DIR__ . '/alert-js.php'; ?>
<?php endif; ?>
<?php unset($alertType, $alertMessages, $alertIsError, $alertDelay, $alertAutoDismiss); ?>