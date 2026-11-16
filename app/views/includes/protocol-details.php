<?php

/**
 * Details drawer at the bottom of a protocol card: a slim toggle strip plus the
 * collapsible label/value panel it controls (toggled by protocol-details.js).
 *
 * @var string                            $detailsId   Unique id suffix for the panel
 * @var array<int, array{0: string, 1: string, 2?: bool}> $detailItems [label, value, raw?] triples;
 *                                      value is plain text unless raw is true (trusted markup)
 */

?>
<div class="protocol-drawer">
    <button type="button" class="protocol-details-toggle" aria-expanded="false" aria-controls="protocol-details-<?= htmlspecialchars((string) $detailsId, ENT_QUOTES, 'UTF-8') ?>">
        <span>Details</span>
        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#chev-down-icon" />
        </svg>
    </button>
    <dl class="protocol-details" id="protocol-details-<?= htmlspecialchars((string) $detailsId, ENT_QUOTES, 'UTF-8') ?>" hidden>
        <?php foreach ($detailItems as $detailItem): ?>
            <div>
                <dt><?= htmlspecialchars((string) $detailItem[0], ENT_QUOTES, 'UTF-8') ?></dt>
                <dd><?= !empty($detailItem[2]) ? $detailItem[1] : htmlspecialchars((string) $detailItem[1], ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
</div>