<?php
$health = $health ?? [];
$free   = $health['free_bytes'] ?? null;
$last   = $health['last'] ?? null;
$stale  = !empty($health['warning']);
$days   = $last ? (int) floor((time() - $last) / 86400) : 0;
$when   = $days < 1 ? 'today' : ($days === 1 ? '1 day ago' : $days . ' days ago');
?>
<p class="backup-health-line <?= $stale ? 'backup-health-warning' : 'backup-health-ok' ?>">
  <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <use href="#<?= $stale ? 'alert-triangle-icon' : 'check-circle-icon' ?>" />
  </svg>
  <?= htmlspecialchars($stale ? $health['warning'] : 'Last backup: ' . $when) ?>
  <?php if (!$stale && $last): ?>
    <span class="backup-health-date">(<?= date(DATETIME_FORMAT, $last) ?>)</span>
  <?php endif; ?>
</p>
<details class="backup-technical">
  <summary>Technical details</summary>
  <p class="audit-description">
    Last backup: <?= $last ? date(DATETIME_FORMAT, $last) : 'none' ?>
    &middot; ZIP support: <?= $health['zip'] ? 'Available' : 'Missing' ?>
    &middot; Encryption: <?= $health['encryption'] ? 'On' : 'Off' ?>
    <?php if ($free !== null): ?>
      &middot; Free disk space: <?= $free >= 1073741824 ? round($free / 1073741824, 1) . ' GB' : round($free / 1048576) . ' MB' ?>
    <?php endif; ?>
    &middot; Memory limit: <?= $health['memory'] === '-1' ? 'No limit' : htmlspecialchars($health['memory']) ?>
    &middot; Time limit: <?= $health['time_limit'] > 0 ? $health['time_limit'] . ' seconds' : 'No limit' ?>
  </p>
</details>