<?php

/** @var array $statusMeta     stage slug => ['label' => ...] */
/** @var array $countsBySlug   stage slug => number of protocols in that stage */
/** @var int   $allCount       number of protocols in the main list (archive excluded) */
?>
<div class="status-filters" id="statusFilters" role="group" aria-label="Filter by status">
    <button type="button" class="status-card active" data-status="" aria-pressed="true">
        All <span class="status-filter-count"><?= $allCount ?></span>
    </button>
    <?php foreach ($statusMeta as $statusSlug => $meta): ?>
        <button type="button" class="status-card" data-status="<?= $statusSlug ?>" aria-pressed="false">
            <?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?>
            <span class="status-filter-count"><?= (int) ($countsBySlug[$statusSlug] ?? 0) ?></span>
        </button>
    <?php endforeach; ?>
</div>
