<?php

/** @var string $updatesEndpoint */
/** @var string|null $updatesBaseline */
?>
<div id="updateWatcher" hidden
  data-updates-endpoint="<?= ROOT . '/' . htmlspecialchars($updatesEndpoint, ENT_QUOTES, 'UTF-8') ?>"
  data-updates-baseline="<?= htmlspecialchars($updatesBaseline ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>