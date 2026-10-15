<?php
// ===== Guide "?" disc (shared markup for page and tab guides) =====
$guideDiscId    = $guideDiscId ?? '';
$guideDiscLabel = $guideDiscLabel ?? 'Page guide';
$guideDiscClass = $guideDiscClass ?? '';
?>
<button type="button" class="guide-disc<?= $guideDiscClass ? ' ' . htmlspecialchars($guideDiscClass, ENT_QUOTES, 'UTF-8') : '' ?>" data-guide-start="<?= htmlspecialchars($guideDiscId, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($guideDiscLabel, ENT_QUOTES, 'UTF-8') ?>" title="Page guide">
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <circle class="guide-disc-fill" cx="12" cy="12" r="11" stroke="none" />
    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
    <path d="M12 17h.01" />
  </svg>
</button>
<?php unset($guideDiscId, $guideDiscLabel, $guideDiscClass); ?>