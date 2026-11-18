<?php

/** @var string $archiveText  what the Archive holds */
/** @var string $archivedRows  the archived rows, already rendered */
/** @var array|null $archiveCategories  optional: key => ['label' => string, 'count' => int, 'desc' => string] */
/** @var string|null $archiveCategoryDefault */

$archiveCategories = $archiveCategories ?? [];

?>
<!-- ===== Archive window ===== -->
<div class="modal-backdrop" id="archiveBackdrop">
    <div class="modal-card panel-modal-card archive-modal-card" role="dialog" aria-modal="true" aria-labelledby="archiveTitle">
        <div class="modal-header">
            <span class="modal-header-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                    <use href="#archive-icon" />
                </svg>
            </span>
            <p class="modal-title" id="archiveTitle">Archive</p>
            <button class="modal-close" type="button" id="archiveClose" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
            </button>
        </div>
        <div class="panel-modal-body">
            <p class="panel-modal-intro" id="archiveDesc"><?= htmlspecialchars($archiveText, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($archiveCategories): ?>
                <div class="archive-categories" id="archiveCategories" role="group" aria-label="Archive categories">
                    <?php foreach ($archiveCategories as $categoryKey => $category):
                        $isDefault = $categoryKey === $archiveCategoryDefault; ?>
                        <button type="button" class="archive-category<?= $isDefault ? ' active' : '' ?>"
                            data-archive-category="<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>"
                            data-desc="<?= htmlspecialchars($category['desc'], ENT_QUOTES, 'UTF-8') ?>"
                            aria-pressed="<?= $isDefault ? 'true' : 'false' ?>">
                            <?= htmlspecialchars($category['label'], ENT_QUOTES, 'UTF-8') ?>
                            <span class="archive-category-count"><?= (int) $category['count'] ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="archive-list" id="archiveList">
                <?= $archivedRows ?>
                <p class="no-results" id="archiveEmpty">
                    <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#file-x-icon" />
                    </svg>
                    <span id="archiveEmptyText">No archived protocols.</span>
                </p>
            </div>
        </div>
    </div>
</div>
<?php unset($archiveText, $archivedRows, $archiveCategories, $archiveCategoryDefault); ?>
