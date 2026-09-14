<?php

/** @var string $updatesEndpoint */
/** @var string|null $updatesBaseline */
?>
<div class="update-banner" id="updateBanner" hidden
  data-updates-endpoint="<?= ROOT . '/' . htmlspecialchars($updatesEndpoint, ENT_QUOTES, 'UTF-8') ?>"
  data-updates-baseline="<?= htmlspecialchars($updatesBaseline ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <span class="new-activity-banner-text">New activity detected. <button type="button" class="update-banner-refresh">Click to refresh.</button></span>
  <button type="button" class="update-banner-dismiss" aria-label="Dismiss">&#x2715;</button>
</div>