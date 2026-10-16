<?php

/**
 * One note card for the protocol viewer (amendment note, CCARD note, deletion request).
 *
 * @var string $noteVariant 'info', 'warning' or 'danger'
 * @var string $noteIcon    sprite id
 * @var string $noteLabel   bold lead-in
 * @var string $noteText    plain text, escaped here
 */
?>
<div class="viewer-note viewer-note--<?= $noteVariant ?>">
  <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <use href="#<?= $noteIcon ?>" />
  </svg>
  <p>
    <strong><?= $noteLabel ?></strong>
    <?= htmlspecialchars($noteText, ENT_QUOTES, 'UTF-8') ?>
  </p>
</div>