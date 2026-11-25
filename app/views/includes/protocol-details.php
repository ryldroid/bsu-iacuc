<?php

/**
 * Info button in a protocol card's meta line plus the popover it opens with the
 * label/value details (toggled by protocol-details.js).
 *
 * @var string                            $detailsId   Unique id suffix for the panel
 * @var array<int, array{0: string, 1: string, 2?: bool}> $detailItems [label, value, raw?] triples;
 *                                      value is plain text unless raw is true (trusted markup)
 */

?>
<button type="button" class="protocol-details-trigger" aria-expanded="false" aria-controls="protocol-details-<?= htmlspecialchars((string) $detailsId, ENT_QUOTES, 'UTF-8') ?>"
    title="Submission details" aria-label="Submission details">
    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <use href="#info-icon" />
    </svg>
</button>
<div class="protocol-details-panel" id="protocol-details-<?= htmlspecialchars((string) $detailsId, ENT_QUOTES, 'UTF-8') ?>" role="dialog" aria-label="Submission details" hidden>
    <dl class="protocol-details">
        <?php foreach ($detailItems as $detailItem): ?>
            <div>
                <dt><?= htmlspecialchars((string) $detailItem[0], ENT_QUOTES, 'UTF-8') ?></dt>
                <dd><?= !empty($detailItem[2]) ? $detailItem[1] : htmlspecialchars((string) $detailItem[1], ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
</div>
