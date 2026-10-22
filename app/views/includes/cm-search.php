<?php

/**
 * Search box for a content manager list (see js/content-manager.js).
 *
 * @var string $cmSearchTarget       id of the .cm-list-wrap this box filters
 * @var string $cmSearchPlaceholder  placeholder text
 */
?>
<div class="inbox-search-wrap">
  <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <use href="#search-icon" />
  </svg>
  <input type="text" class="inbox-search-input" data-cm-search="<?= htmlspecialchars($cmSearchTarget, ENT_QUOTES) ?>"
    placeholder="<?= htmlspecialchars($cmSearchPlaceholder, ENT_QUOTES) ?>"
    aria-label="<?= htmlspecialchars($cmSearchPlaceholder, ENT_QUOTES) ?>" autocomplete="off">
  <button type="button" class="inbox-search-clear" data-cm-search-clear aria-label="Clear search">
    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <use href="#close-icon" />
    </svg>
  </button>
</div>