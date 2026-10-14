<?php

/** @var array $themeModes [mode, label, icon] list defined in header.php */
?>
<div class="theme-toggle-wrapper">
  <button
    id="theme-toggle"
    class="theme-toggle"
    type="button"
    aria-expanded="false"
    aria-haspopup="true"
    aria-label="Theme"
    data-tooltip="Theme"
    aria-controls="theme-menu">
    <svg class="theme-toggle-icon theme-toggle-icon-sun" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <use href="#sun-icon" />
    </svg>
    <svg class="theme-toggle-icon theme-toggle-icon-moon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <use href="#moon-icon" />
    </svg>
    <span class="chev-icon">
      <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <use href="#chev-down-icon" />
      </svg>
    </span>
  </button>

  <div id="theme-menu" role="menu">
    <div class="theme-menu-header" role="presentation">Theme</div>
    <?php foreach ($themeModes as [$themeMode, $themeLabel, $themeIcon]): ?>
      <button type="button" role="menuitemradio" data-mode="<?= $themeMode ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#<?= $themeIcon ?>" />
        </svg>
        <?= $themeLabel ?>

        <svg class="theme-menu-check" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#check-icon" />
        </svg>
      </button>
    <?php endforeach; ?>
  </div>
</div>