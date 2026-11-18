<?php

/** @var string $sortSelectId  id of the hidden select that holds the current sort */
/** @var string $sortDefault   starting sort, e.g. submitted_desc */
/** @var string|null $sortTarget  optional selector of the list the sort applies to */
/** @var string[] $sortOmit  optional sort keys to leave out, e.g. ['status'] */
/** @var bool $sortCompact  optional: show one dropdown on tablet and desktop instead of pills */
$sortChoices = [
    'updated'   => ['Last updated', 'desc'],
    'status'    => ['Status', 'asc'],
    'submitted' => ['Date submitted', 'desc'],
    'title'     => ['Title', 'asc'],
];
$sortChoices    = array_diff_key($sortChoices, array_flip($sortOmit ?? []));
$sortDefaultKey = explode('_', $sortDefault)[0];
?>
<div class="dashboard-sort-group dashboard-field-group<?= !empty($sortCompact) ? ' sort-compact' : '' ?>">
    <p class="sort-filter-label">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#sort-icon" />
        </svg>
        <span<?= !empty($sortCompact) ? ' class="sr-only"' : '' ?>>Sort by</span>
    </p>

    <div class="sort-wrapper">
        <select id="<?= $sortSelectId ?>" class="dashboard-sort-select dashboard-select-trigger" <?= !empty($sortTarget) ? 'data-sort-target="' . $sortTarget . '"' : '' ?> aria-label="Sort protocols">
            <?php foreach ($sortChoices as $sortKey => [$sortLabel]): ?>
                <?php foreach (['asc' => '↑', 'desc' => '↓'] as $sortDir => $sortArrow): ?>
                    <option value="<?= $sortKey ?>_<?= $sortDir ?>" <?= $sortDefault === $sortKey . '_' . $sortDir ? 'selected' : '' ?>><?= $sortLabel ?> <?= $sortArrow ?></option>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </select>

        <button type="button" class="mobile-sort-trigger dashboard-select-trigger mobile-dropdown-trigger" aria-haspopup="true" aria-expanded="false">
            <span id="mobileSortLabel"></span>
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#chev-down-icon" />
            </svg>
        </button>

        <div class="dropdown-panel sort-options" id="sortOptions" role="group" aria-label="Sort protocols">
            <?php foreach ($sortChoices as $sortKey => [$sortLabel, $sortFirstDir]): ?>
                <?php $isCurrentSort = $sortKey === $sortDefaultKey; ?>
                <?php $sortCurrentDir = $isCurrentSort ? explode('_', $sortDefault)[1] : $sortFirstDir; ?>
                <button type="button" class="status-card<?= $isCurrentSort ? ' active' : '' ?>" data-sort-key="<?= $sortKey ?>" data-sort="<?= $sortKey ?>_<?= $sortCurrentDir ?>" data-first-dir="<?= $sortFirstDir ?>">
                    <?= $sortLabel ?>
                    <svg class="sort-direction" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#<?= $sortCurrentDir === 'asc' ? 'chev-up-icon' : 'chev-down-icon' ?>" />
                    </svg>
                </button>
            <?php endforeach; ?>

            <div class="sort-order" role="radiogroup" aria-label="Sort order">
                <?php foreach (['asc' => 'Ascending', 'desc' => 'Descending'] as $sortDir => $sortDirLabel): ?>
                    <label class="sort-order-option">
                        <input type="radio" name="<?= $sortSelectId ?>Order" value="<?= $sortDir ?>" <?= explode('_', $sortDefault)[1] === $sortDir ? 'checked' : '' ?>>
                        <?= $sortDirLabel ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>