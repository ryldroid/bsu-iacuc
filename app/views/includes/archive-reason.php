<?php

/** @var array{tone: string, label: string, text: string, comment?: string} $archiveReason */

?>
<div class="archive-reason archive-reason--<?= htmlspecialchars($archiveReason['tone'], ENT_QUOTES, 'UTF-8') ?>">
    <?= statusIconSvg('archive-icon', 16) ?>
    <div>
        <p class="archive-reason-label"><?= htmlspecialchars($archiveReason['label'], ENT_QUOTES, 'UTF-8') ?></p>
        <p class="archive-reason-text"><?= htmlspecialchars($archiveReason['text'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php if (!empty($archiveReason['comment'])): ?>
            <p class="archive-reason-text"><strong>Reason:</strong> <?= htmlspecialchars($archiveReason['comment'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </div>
</div>
<?php unset($archiveReason); ?>
