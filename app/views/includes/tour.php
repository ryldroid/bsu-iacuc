<?php
$tourId       = $tourId ?? '';
$tourSteps    = $tourSteps ?? [];
$tourOpen     = $tourOpen ?? false;
$tourShowOnce = $tourShowOnce ?? false;
?>
<div class="modal-backdrop tour-modal<?= $tourOpen ? ' open' : '' ?>" data-tour="<?= htmlspecialchars($tourId, ENT_QUOTES, 'UTF-8') ?>" <?= $tourShowOnce ? ' data-show-once' : '' ?>>
  <div class="tour-spotlight" aria-hidden="true"></div>
  <div class="modal-card welcome-modal-card">
    <button type="button" class="modal-close" aria-label="Close">
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <use href="#close-icon" />
      </svg>
    </button>
    <div class="welcome-dots" aria-hidden="true"></div>
    <?php foreach ($tourSteps as $i => $step): ?>
      <?php [$stepTitle, $stepText, $stepTarget, $stepMobileTarget] = array_pad($step, 4, ''); ?>
      <section class="welcome-step" data-welcome-step<?= $i > 0 ? ' hidden' : '' ?> data-tour-target="<?= htmlspecialchars($stepTarget, ENT_QUOTES, 'UTF-8') ?>" <?= $stepMobileTarget ? ' data-tour-target-mobile="' . htmlspecialchars($stepMobileTarget, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
        <span class="welcome-step-label">Step <?= $i + 1 ?> of <?= count($tourSteps) ?></span>
        <h3><?= htmlspecialchars($stepTitle, ENT_QUOTES, 'UTF-8') ?></h3>
        <p><?= htmlspecialchars($stepText, ENT_QUOTES, 'UTF-8') ?></p>
      </section>
    <?php endforeach; ?>
    <div class="modal-actions welcome-actions">
      <button type="button" class="button" data-tour-back hidden>Back</button>
      <button type="button" class="button welcome-next" data-tour-next>Next</button>
    </div>
  </div>
</div>
<?php unset($tourId, $tourSteps, $tourOpen, $tourShowOnce); ?>