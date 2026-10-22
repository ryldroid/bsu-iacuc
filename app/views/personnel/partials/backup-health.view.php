<?php
$health = $health ?? [];
$free   = $health['free_bytes'] ?? null;
?>
<?php if (!empty($health['warning'])): ?>
  <div class="alert error-messages"><?= htmlspecialchars($health['warning']) ?></div>
<?php endif; ?>
<p class="audit-description">
  Last backup: <?= $health['last'] ? date(DATETIME_FORMAT, $health['last']) : 'none' ?>
  &middot; ZIP support: <?= $health['zip'] ? 'Available' : 'Missing' ?>
  &middot; Encryption: <?= $health['encryption'] ? 'On' : 'Off' ?>
  <?php if ($free !== null): ?>
    &middot; Free disk space: <?= $free >= 1073741824 ? round($free / 1073741824, 1) . ' GB' : round($free / 1048576) . ' MB' ?>
  <?php endif; ?>
  &middot; Memory limit: <?= $health['memory'] === '-1' ? 'No limit' : htmlspecialchars($health['memory']) ?>
  &middot; Time limit: <?= $health['time_limit'] > 0 ? $health['time_limit'] . ' seconds' : 'No limit' ?>
</p>