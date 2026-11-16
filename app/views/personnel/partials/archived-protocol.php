<?php

/**
 * One compact row of the personnel Archive. View opens the protocol, Restore brings it back (not offered for inactive researchers).
 *
 * @var array  $protocol         the protocol row (see ProtocolModel::getAll)
 * @var int    $protocolId
 * @var string $title            already escaped
 * @var string $researcherName   already escaped
 * @var string $statusDisplay    the stage it was at
 * @var string $archiveCategory  ccard, discontinued or inactive (see personnel-home.view.php)
 */

$stageNote = 'while ' . $statusDisplay;

switch ($archiveCategory) {
    case 'ccard':
        $by       = trim((string) ($protocol['deleted_by_name'] ?? ''));
        $note     = 'Archived' . ($by !== '' ? ' by ' . $by : '') . ' on ' . date(DATE_FORMAT, strtotime($protocol['deleted_at'])) . ' ' . $stageNote . '.';
        $comment  = (string) ($protocol['deletion_reason'] ?? '');
        $tagLabel = 'Archived by CCARD';
        $tagIcon  = 'archive-icon';
        break;
    case 'discontinued':
        $note     = 'Discontinued by the researcher on ' . date(DATE_FORMAT, strtotime($protocol['discontinued_at'])) . ' ' . $stageNote . '.';
        $comment  = (string) ($protocol['discontinued_reason'] ?? '');
        $tagLabel = 'Discontinued';
        $tagIcon  = 'file-x-icon';
        break;
    default:
        $note     = 'Returns to the main list when the researcher reactivates their account.';
        $comment  = '';
        $tagLabel = 'Inactive researcher';
        $tagIcon  = 'account-icon';
}

$canRestore = $archiveCategory !== 'inactive';
?>
<div class="protocol protocol--archived"
    data-protocol-id="<?= $protocolId ?>"
    data-filter-slug="archived"
    data-archive-category="<?= $archiveCategory ?>"
    data-researcher="<?= strtolower($researcherName) ?>"
    data-submitted="<?= htmlspecialchars(date('c', strtotime($protocol['submitted_at'])), ENT_QUOTES, 'UTF-8') ?>"
    data-updated="<?= htmlspecialchars(date('c', strtotime($protocol['last_activity_at'])), ENT_QUOTES, 'UTF-8') ?>"
    data-title="<?= htmlspecialchars(strtolower($protocol['research_title']), ENT_QUOTES, 'UTF-8') ?>">

    <div class="protocol-meta">
        <span class="archive-tag">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#<?= $tagIcon ?>"></use>
            </svg>
            <?= $tagLabel ?>
        </span>
        <p class="research-title"><?= $title ?></p>
        <p class="protocol-meta-line">
            <?php if (!empty($protocol['school'])): ?><span class="protocol-school"><?= htmlspecialchars($protocol['school'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
            <?php if ($archiveCategory === 'inactive'): ?>
                <button type="button" class="researcher-name-link" data-user-id="<?= (int) $protocol['user_id'] ?>" data-researcher-name="<?= $researcherName ?>" aria-label="View researcher profile: <?= $researcherName ?>" title="View researcher profile"><?= $researcherName ?></button>
            <?php else: ?>
                <span class="protocol-school"><?= $researcherName ?></span>
            <?php endif; ?>
        </p>
        <p class="archive-note">
            <?= htmlspecialchars($note, ENT_QUOTES, 'UTF-8') ?>
            <?php if ($comment !== ''): ?>
                <span class="archive-note-reason"><strong>Reason:</strong> <?= htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </p>
    </div>

    <div class="actions">
        <button type="button" class="action-link protocol-view-btn" data-action="view" title="View Protocol" aria-label="View Protocol">
            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#eye-icon"></use>
            </svg>
            View
        </button>
        <?php if ($canRestore): ?>
            <button type="button" class="button button--primary" data-action="restore">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#undo-icon"></use>
                </svg>
                Restore
            </button>
        <?php endif; ?>
    </div>
</div>