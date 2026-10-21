<?php
$guideId       = $guideId ?? '';
$guideSteps    = $guideSteps ?? [];
$guideOpen     = $guideOpen ?? false;
$guideShowOnce = $guideShowOnce ?? false;
?>
<div class="modal-backdrop guide-modal<?= $guideOpen ? ' open' : '' ?>" data-guide="<?= htmlspecialchars($guideId, ENT_QUOTES, 'UTF-8') ?>" <?= $guideShowOnce ? ' data-show-once' : '' ?>>
  <div class="guide-spotlight" aria-hidden="true"></div>
  <div class="modal-card welcome-modal-card">
    <button type="button" class="modal-close" aria-label="Close">
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <use href="#close-icon" />
      </svg>
    </button>
    <div class="welcome-dots" aria-hidden="true"></div>
    <?php foreach ($guideSteps as $i => $step): ?>
      <?php [$stepTitle, $stepText, $stepTarget, $stepMobileTarget, $stepDesktopOnly] = array_pad($step, 5, ''); ?>
      <section class="welcome-step" data-welcome-step<?= $stepDesktopOnly ? ' data-guide-desktop-only' : '' ?><?= $i > 0 ? ' hidden' : '' ?> data-guide-target="<?= htmlspecialchars($stepTarget, ENT_QUOTES, 'UTF-8') ?>" <?= $stepMobileTarget ? ' data-guide-target-mobile="' . htmlspecialchars($stepMobileTarget, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
        <span class="welcome-step-label">Step <?= $i + 1 ?> of <?= count($guideSteps) ?></span>
        <h3><?= htmlspecialchars($stepTitle, ENT_QUOTES, 'UTF-8') ?></h3>
        <p><?= preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', htmlspecialchars($stepText, ENT_QUOTES, 'UTF-8')) ?></p>
      </section>
    <?php endforeach; ?>
    <div class="modal-actions welcome-actions">
      <button type="button" class="confirm-modal-btn confirm-modal-cancel" data-guide-back hidden>
        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#back-icon" />
        </svg>
        Back
      </button>
      <button type="button" class="confirm-modal-btn confirm-modal-ok" data-guide-next>
        <span data-guide-next-label>Next</span>
        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#arrow-right-icon" />
        </svg>
      </button>
    </div>
  </div>
</div>
<?php unset($guideId, $guideSteps, $guideOpen, $guideShowOnce); ?>