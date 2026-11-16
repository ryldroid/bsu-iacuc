<?php

/** @var string $archiveBannerText */
/** @var array|null $archiveCategories  optional: key => ['label' => string, 'count' => int, 'desc' => string] */
/** @var string|null $archiveCategoryDefault */

$archiveCategories = $archiveCategories ?? [];

?>
<!-- ===== Archive banner ===== -->
<div class="archive-banner" id="archiveBanner" role="region" aria-label="Archive" hidden>
    <div class="archive-banner-text">
        <p class="archive-banner-title">
            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#archive-icon" />
            </svg>
            Archive
        </p>
        <p class="archive-banner-desc" id="archiveBannerDesc"><?= htmlspecialchars($archiveBannerText, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <button type="button" class="button button--primary archive-back-btn" id="archiveBackBtn">
        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#back-icon" />
        </svg>
        Back to protocols
    </button>
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
</div>
<?php unset($archiveBannerText, $archiveCategories, $archiveCategoryDefault); ?>