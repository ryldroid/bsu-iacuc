<?php

/** @var string|null $themeToggleExtraClass Extra class(es) for positioning/coloring this instance */
$themeToggleExtraClass = $themeToggleExtraClass ?? '';
?>
<div class="theme-toggle-wrapper <?= htmlspecialchars($themeToggleExtraClass, ENT_QUOTES, 'UTF-8') ?>">
  <button
    id="theme-toggle"
    class="theme-toggle"
    type="button"
    aria-expanded="false"
    aria-haspopup="true"
    aria-label="Theme options"
    aria-controls="theme-menu">
    <svg class="theme-toggle-icon theme-toggle-icon-sun" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <use href="#sun-icon" />
    </svg>
    <svg class="theme-toggle-icon theme-toggle-icon-moon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <use href="#moon-icon" />
    </svg>
    <svg class="theme-toggle-icon theme-toggle-icon-auto" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <use href="#monitor-icon" />
    </svg>
    <span class="theme-toggle-label">Theme: <span id="theme-toggle-current">Auto</span></span>
    <span class="chev-icon">
      <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <use href="#chev-down-icon" />
      </svg>
    </span>
  </button>

  <div id="theme-menu" role="menu">
    <button type="button" role="menuitemradio" data-mode="light">
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <use href="#sun-icon" />
      </svg>
      Light
      <svg class="theme-menu-check" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <use href="#check-icon" />
      </svg>
    </button>
    <button type="button" role="menuitemradio" data-mode="dark">
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <use href="#moon-icon" />
      </svg>
      Dark
      <svg class="theme-menu-check" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <use href="#check-icon" />
      </svg>
    </button>
    <button type="button" role="menuitemradio" data-mode="auto">
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <use href="#monitor-icon" />
      </svg>
      Auto
      <svg class="theme-menu-check" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <use href="#check-icon" />
      </svg>
    </button>
  </div>
</div>