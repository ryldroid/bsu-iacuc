<?php

/** @var array  $protocol */
/** @var array  $version  */
/** @var bool   $isLatestVersion */
/** @var string $csrf     */
/** @var bool   $isStaff    */
/** @var bool   $isAdmin    */
/** @var bool   $isReviewer */
/** @var array|null $returnReason */
/** @var array|null $latestCertVersion */
/** @var array|null $latestAuthVersion */
/** @var bool   $hasCertOnFile */
/** @var bool   $canRename */
/** @var bool   $canRequestDeletion */
/** @var bool   $canDelete */
/** @var bool   $deletionRequested */
/** @var bool   $showTitleChangeBanner */
/** @var string $flashSuccess */
/** @var string $flashError */

$title = htmlspecialchars($protocol['research_title'] ?? 'Protocol', ENT_QUOTES, 'UTF-8');

$roleLabels = ['researcher' => 'Researcher', 'admin' => 'Admin', 'reviewer' => 'Reviewer'];

$statusLabels = [
    'under review'   => 'Under Review',
    'needs revision' => 'Needs Revision',
    'reviewed'       => 'Reviewed',
    'endorsed'       => 'Endorsed',
    'approved'       => 'Approved',
];
$statusKey       = strtolower($protocol['status'] ?? '');
$statusLabel     = $statusLabels[$statusKey] ?? ($protocol['status'] ?? '');
$isCompleted     = in_array($statusKey, ['approved'], true);
$isLatestVersion = $isLatestVersion ?? true;

$canReview = $isReviewer && $statusKey === 'under review' && $isLatestVersion;

$canResubmit = !$isStaff && $isLatestVersion && $statusKey === 'needs revision';

$rrWrongCert          = !empty($returnReason['wrong_cert']);
$rrWrongAuth          = !empty($returnReason['wrong_auth']);

$fileUrl    = ROOT . '/apply/file/' . (int) $version['id'];
$annotApi   = ROOT . '/apply/annotate';
$statusApi  = ROOT . '/apply/status';
$protocolId = (int) $protocol['protocol_id'];
$versionId  = (int) $version['id'];
$versionNum = (int) $version['version_number'];

$backUrl = $backUrl ?? ($isStaff ? ROOT . '/admin/home' : ROOT . '/submissions');

$versions   = $versions ?? [$version];
$fromFilter = $fromFilter ?? '';

function versionViewerUrl(int $protocolId, int $versionId, string $fromFilter): string
{
    $url = ROOT . '/apply/viewer/' . $protocolId . '/' . $versionId;
    return $fromFilter !== '' ? $url . '?from=' . urlencode($fromFilter) : $url;
}

$prevVersion = null;
$nextVersion = null;
foreach ($versions as $v) {
    if ((int) $v['version_number'] === $versionNum - 1) {
        $prevVersion = $v;
    }
    if ((int) $v['version_number'] === $versionNum + 1) {
        $nextVersion = $v;
    }
}

$submitterName     = trim(($protocol['submitter_first_name'] ?? '') . ' ' . ($protocol['submitter_last_name'] ?? ''));
$isPi              = ! empty($protocol['is_pi']);
$certRequired      = $hasCertOnFile && $rrWrongCert;
$authRequired      = !$isPi && $rrWrongAuth;
$certUrl           = ROOT . '/apply/cert/' . (int) $protocol['user_id'];
$latestCertFileUrl = ! empty($latestCertVersion['id']) ? ROOT . '/apply/file/' . (int) $latestCertVersion['id'] : null;
$latestAuthFileUrl = ! empty($latestAuthVersion['id']) ? ROOT . '/apply/file/' . (int) $latestAuthVersion['id'] : null;

$flaggedDocAccept = '.pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png';
$resubmitDocs      = [
    ['key' => 'protocol', 'title' => 'Revised protocol file', 'subtitle' => 'PDF only · max 10 MB', 'accept' => 'application/pdf,.pdf', 'required' => true],
];
if ($certRequired) {
    $resubmitDocs[] = ['key' => 'cert', 'title' => 'Training certificate', 'subtitle' => 'Flagged by the reviewer · PDF, JPG, or PNG · max 10 MB', 'accept' => $flaggedDocAccept, 'required' => true];
}
if ($authRequired) {
    $resubmitDocs[] = ['key' => 'auth', 'title' => 'Authorization letter', 'subtitle' => 'Flagged by the reviewer · PDF, JPG, or PNG · max 10 MB', 'accept' => $flaggedDocAccept, 'required' => true];
}
$resubmitIntro = ($certRequired || $authRequired)
    ? 'Upload your revised protocol file, plus the document(s) the reviewer flagged below.'
    : 'Upload your revised protocol file below.';

include 'includes/header.php';
?>

<!-- PDF.js from CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<link rel="stylesheet" href="<?= asset_css('viewer.css') ?>">
<?php if ($canResubmit): ?>
    <link rel="stylesheet" href="<?= asset_css('application.css') ?>">
<?php endif; ?>

<div class="viewer-body">

    <?php if (!empty($flashSuccess)): ?>
        <div class="return-reason-bar viewer-flash-success" id="viewerFlashSuccess">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#check-icon" />
            </svg>
            <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
        <div class="return-reason-bar viewer-flash-error" id="viewerFlashError">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#info-icon" />
            </svg>
            <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($isStaff): ?>
        <!-- <div class="notice-bar">
            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#account-icon" />
            </svg>
            <span>
                Submitted by <strong><?= htmlspecialchars($submitterName, ENT_QUOTES, 'UTF-8') ?></strong>
                <?= $isPi ? '(Principal Investigator)' : '(submitted with an authorization letter from the Principal Investigator)' ?>
            </span>
        </div> -->
    <?php endif; ?>

    <?php if ($isStaff && !empty($returnReason)): ?>
        <?php
        $rrItems = [];
        if (!empty($returnReason['wrong_cert']))   $rrItems[] = 'update IACUC training certificate';
        if (!empty($returnReason['wrong_auth']))   $rrItems[] = 'update authorization letter';
        if (!empty($returnReason['other_reason'])) $rrItems[] = 'revise protocol';
        $rrLabel = empty($rrItems) ? 'revise protocol' : implode('; ', $rrItems);
        ?>
        <div class="return-reason-bar">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#info-icon" />
            </svg>
            Previously returned to: <?= htmlspecialchars($rrLabel, ENT_QUOTES, 'UTF-8') ?>
            <?php if (!empty($returnReason['comment'])): ?>
                <span class="return-reason-bar-comment">"<?= htmlspecialchars($returnReason['comment'], ENT_QUOTES, 'UTF-8') ?>"</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($showTitleChangeBanner): ?>
        <div class="return-reason-bar title-change-bar">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#edit-icon" />
            </svg>
            The research title has been changed from “<?= htmlspecialchars($protocol['previous_title'], ENT_QUOTES, 'UTF-8') ?>” by
            <?= htmlspecialchars($roleLabels[$protocol['title_changed_by_role']] ?? ucfirst((string) $protocol['title_changed_by_role']), ENT_QUOTES, 'UTF-8') ?>
            - <?= htmlspecialchars($protocol['title_changed_by_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($isReviewer && !empty($protocol['deletion_requested_at'])): ?>
        <div class="return-reason-bar deletion-request-bar">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#trash-icon" />
            </svg>
            Deletion requested by
            <?= htmlspecialchars($roleLabels[$protocol['deletion_requested_by_role']] ?? ucfirst((string) $protocol['deletion_requested_by_role']), ENT_QUOTES, 'UTF-8') ?>
            <?= htmlspecialchars($protocol['deletion_requested_by_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>:
            <span class="return-reason-bar-comment">"<?= htmlspecialchars($protocol['deletion_request_reason'] ?? '', ENT_QUOTES, 'UTF-8') ?>"</span>
            <span class="deletion-request-bar-actions">
                <button class="tool-btn tool-btn--success" type="button" onclick="openDeletionReviewModal('approve')">
                    <svg width="13" height="13" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#check-icon" />
                    </svg>
                    Approve
                </button>
                <button class="tool-btn tool-btn--danger" type="button" onclick="openDeletionReviewModal('reject')">
                    <svg width="13" height="13" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#close-icon" />
                    </svg>
                    Reject
                </button>
            </span>
        </div>
    <?php elseif (!$isReviewer && !empty($protocol['deletion_requested_at'])): ?>
        <div class="return-reason-bar deletion-request-bar">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#trash-icon" />
            </svg>
            A deletion request is pending for this protocol (requested by
            <?= htmlspecialchars($roleLabels[$protocol['deletion_requested_by_role']] ?? ucfirst((string) $protocol['deletion_requested_by_role']), ENT_QUOTES, 'UTF-8') ?>
            <?= htmlspecialchars($protocol['deletion_requested_by_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>). The reviewer has been notified.
        </div>
    <?php endif; ?>

    <!-- ===== Top bar ===== -->
    <div class="viewer-topbar">
        <div class="viewer-topbar-left">
            <a href="<?= $backUrl ?>"
                class="tool-btn"
                <?php if ($canReview): ?>
                onclick="event.preventDefault();
   confirmAction(
       'Leave this review and return to the dashboard? Your comments have been saved.',
       {
           okText: 'Go Back',
           cancelText: 'Stay Here'
       }
   ).then(ok => {
       if (ok) window.location.href = this.href;
   });"
                <?php endif; ?>>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <use href="#back-icon" />
                </svg>
                Back
            </a>

            <span class="viewer-title"><?= $title ?></span>

            <?php if (!empty($titleHistory)): ?>
                <div class="title-history-switcher" id="titleHistorySwitcher">
                    <button type="button" class="title-history-trigger" id="titleHistoryTrigger"
                        aria-haspopup="true" aria-expanded="false" aria-label="Show rename history">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </button>
                    <div class="title-history-menu" id="titleHistoryMenu">
                        <div class="title-history-menu-header">Title Name History</div>
                        <div class="title-history-menu-label">Renamed from</div>
                        <?php foreach ($titleHistory as $h): ?>
                            <div class="title-history-item">
                                <div class="title-history-item-title"><?= htmlspecialchars($h['title'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="title-history-item-meta">
                                    <?= htmlspecialchars($h['changed_by_name'] ? ($roleLabels[$h['changed_by_role']] ?? ucfirst((string) $h['changed_by_role'])) . ' - ' . $h['changed_by_name'] : 'Initial title', ENT_QUOTES, 'UTF-8') ?>
                                    &middot; <?= htmlspecialchars(date('M j, Y g:i A', strtotime($h['changed_at'])), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="version-switcher" id="versionSwitcher">
                <a class="version-nav-btn<?= !$prevVersion ? ' is-disabled' : '' ?>"
                    <?= $prevVersion ? 'href="' . versionViewerUrl($protocolId, (int) $prevVersion['id'], $fromFilter) . '"' : 'aria-disabled="true" tabindex="-1"' ?>
                    aria-label="Previous version" title="Previous version">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <use href="#back-icon" />
                    </svg>
                </a>

                <button type="button" class="ver-badge ver-badge--dropdown" id="versionSwitcherTrigger"
                    aria-haspopup="true" aria-expanded="false">
                    v<?= $versionNum ?>
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </button>

                <a class="version-nav-btn<?= !$nextVersion ? ' is-disabled' : '' ?>"
                    <?= $nextVersion ? 'href="' . versionViewerUrl($protocolId, (int) $nextVersion['id'], $fromFilter) . '"' : 'aria-disabled="true" tabindex="-1"' ?>
                    aria-label="Next version" title="Next version">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#arrow-right-icon" />
                    </svg>
                </a>

                <div class="version-dropdown-menu" id="versionDropdownMenu">
                    <?php foreach ($versions as $v): ?>
                        <?php
                        $vNum   = (int) $v['version_number'];
                        $vId    = (int) $v['id'];
                        $vName  = trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? ''));
                        $vDate  = !empty($v['uploaded_at']) ? date('M j, Y g:i A', strtotime($v['uploaded_at'])) : '';
                        $vIsCur = $vId === $versionId;
                        ?>
                        <a class="version-dropdown-item<?= $vIsCur ? ' is-current' : '' ?>"
                            href="<?= versionViewerUrl($protocolId, $vId, $fromFilter) ?>">
                            <span class="version-dropdown-item-num">v<?= $vNum ?></span>
                            <span class="version-dropdown-item-meta">
                                <span class="version-dropdown-item-date"><?= htmlspecialchars($vDate, ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                            <?php if ($vIsCur): ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <use href="#location-icon" />
                                </svg>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($isLatestVersion): ?>
                <span class="status-badge status-badge--<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php else: ?>
                <span class="status-badge historical">
                    Viewing history: read only
                </span>
            <?php endif; ?>
        </div>

        <div class="viewer-topbar-right">
            <span class="page-indicator" id="pageIndicator">Loading…</span>

            <div class="viewer-view-actions">
                <button class="tool-btn tool-btn--ghost"
                    data-file-url="<?= htmlspecialchars($latestCertFileUrl ?? $certUrl, ENT_QUOTES, 'UTF-8') ?>"
                    onclick="openFilePopup(this.dataset.fileUrl, 'IACUC Training Certificate')">
                    <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#review-icon" />
                    </svg>
                    Training Certificate
                </button>

                <?php if (! $isPi && $latestAuthFileUrl): ?>
                    <button class="tool-btn tool-btn--ghost"
                        data-file-url="<?= htmlspecialchars($latestAuthFileUrl, ENT_QUOTES, 'UTF-8') ?>"
                        onclick="openFilePopup(this.dataset.fileUrl, 'Authorization Letter')">
                        <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#review-icon" />
                        </svg>
                        Authorization Letter
                    </button>
                <?php endif; ?>

                <a class="tool-btn tool-btn--ghost" href="<?= $fileUrl ?>" download="<?= htmlspecialchars($version['original_name'] ?? 'protocol', ENT_QUOTES, 'UTF-8') ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#download-icon" />
                    </svg>
                    Download
                </a>
            </div>

            <?php if ($canRename || $canDelete || $canRequestDeletion): ?>
                <span class="toolbar-divider" aria-hidden="true"></span>

                <div class="viewer-doc-actions">
                    <?php if ($canRename): ?>
                        <button class="tool-btn tool-btn--info" id="btnRename" type="button" onclick="openRenameModal()">
                            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#edit-icon" />
                            </svg>
                            Rename
                        </button>
                    <?php endif; ?>

                    <?php if ($canDelete): ?>
                        <button class="tool-btn tool-btn--danger" id="btnDelete" type="button" onclick="openDeleteModal()">
                            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#trash-icon" />
                            </svg>
                            Delete
                        </button>
                    <?php elseif ($canRequestDeletion): ?>
                        <button class="tool-btn tool-btn--danger" id="btnRequestDeletion" type="button"
                            onclick="openDeleteModal()" <?= $deletionRequested ? 'disabled' : '' ?>>
                            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#trash-icon" />
                            </svg>
                            <?= $deletionRequested ? 'Deletion Requested' : 'Request Deletion' ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($canReview || $canResubmit): ?>
                <span class="toolbar-divider" aria-hidden="true"></span>

                <div class="viewer-review-actions">
                    <?php if ($canReview): ?>
                        <button class="tool-btn tool-btn--warn" id="btnNeedsRevision"
                            onclick="openReturnModal()">
                            Return for Revision
                        </button>
                        <button class="tool-btn tool-btn--success" id="btnApprove"
                            onclick="confirmAction('Finish your review? This will send the protocol to the IACUC admin for endorsement.', { okText: 'Proceed', cancelText: 'Cancel' }).then(ok => ok && updateStatus('Reviewed'))">
                            Finish Review
                        </button>
                    <?php elseif ($canResubmit): ?>
                        <button class="tool-btn tool-btn--success" id="btnResubmit"
                            onclick="openReuploadModal()">
                            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#upload-icon" />
                            </svg>
                            Re-submit Protocol
                        </button>
                    <?php endif; ?>
                </div>
            <?php elseif ($isCompleted && $isLatestVersion): ?>
                <span class="ver-badge ver-badge--green">✓ Approved</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canReview): ?>
        <!-- ===== Annotation hint (reviewer only, while under review, latest version only) ===== -->
        <div class="annot-toolbar">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a2 2 0 01-1.414.586H9v-1.414A2 2 0 019 13z" />
            </svg>
            <span class="toolbar-hint" id="toolbarHint">Click and drag on the document to add a comment.</span>
        </div>
    <?php endif; ?>

    <!-- ===== Layout: PDF + sidebar ===== -->
    <div class="viewer-layout">

        <div class="pdf-column" id="pdfColumn"></div>

        <div class="annot-sidebar" id="annotSidebar">
            <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" aria-expanded="true">
                <span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="vertical-align:middle;margin-right:4px">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    Comments
                </span>
                <svg class="sidebar-toggle-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div class="annot-sidebar-inner">
                <h3>Comments</h3>
                <div id="annotList">
                    <p class="annot-empty">Loading…</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Comment popup (small screens: shown at tap position instead of the sidebar) ===== -->
    <div class="annot-popup" id="annotPopup">
        <div class="annot-item-header">
            <span class="annot-num" id="annotPopupNum"></span>
            <span class="annot-page" id="annotPopupPage"></span>
            <button class="annot-popup-close" id="annotPopupClose" aria-label="Close">&times;</button>
        </div>
        <p class="annot-comment" id="annotPopupComment"></p>
        <p class="annot-date" id="annotPopupDate"></p>
        <div class="annot-popup-actions" id="annotPopupActions" style="display:none">
            <button class="annot-edit" id="annotPopupEdit" title="Edit comment">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#edit-icon">
                </svg>
                Edit
            </button>
            <button class="annot-delete" id="annotPopupDelete" title="Delete comment">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#trash-icon">
                </svg>
                Delete
            </button>
        </div>
    </div>

    <!-- ===== Comment dialog (reviewer only, latest version, while under review) ===== -->
    <?php if ($canReview): ?>
        <div class="modal-backdrop" id="commentDialog">
            <div class="modal-card">
                <h2 id="commentDialogTitle">Add Comment</h2>
                <textarea id="commentText" rows="4" placeholder="Type your comment…"></textarea>
                <div class="modal-actions" id="commentDialogActions">
                    <button class="tool-btn active" id="commentDialogSave" onclick="saveComment()">Save</button>
                    <button class="tool-btn" onclick="cancelComment()">Cancel</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div><!-- .viewer-body -->

<?php if ($canReview): ?>
    <!-- ===== Return for Revision modal ===== -->
    <div class="modal-backdrop" id="returnRevisionBackdrop">
        <div class="modal-card panel-modal-card">
            <div class="panel-modal-header">
                <div>
                    <p class="panel-modal-label">Return for Revision</p>
                    <p class="panel-modal-title"><?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button class="tool-btn" onclick="closeReturnModal()" aria-label="Close">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <use href="#close-icon" />
                    </svg>
                    Close
                </button>
            </div>
            <div class="panel-modal-body">
                <p class="panel-modal-intro">Optionally select the issue(s) with this protocol. The researcher will see this feedback.</p>

                <fieldset class="return-reasons-fieldset">
                    <legend class="return-reasons-legend">Issues found <span class="return-comment-optional">(optional)</span></legend>

                    <label class="return-reason-option">
                        <input type="checkbox" name="return_reason" value="wrong_cert" id="returnReasonWrongCert">
                        <span class="return-reason-label">Wrong / invalid IACUC training certificate</span>
                    </label>

                    <label class="return-reason-option">
                        <input type="checkbox" name="return_reason" value="wrong_auth" id="returnReasonWrongAuth">
                        <span class="return-reason-label">Wrong / invalid authorization letter</span>
                    </label>
                </fieldset>

                <label class="return-comment-label" for="returnComment">
                    Additional details <span class="return-comment-optional">(optional)</span>
                </label>
                <textarea id="returnComment" class="return-comment-textarea"
                    placeholder="Add any notes for the researcher..."
                    rows="4" maxlength="1000"></textarea>
                <p class="return-char-count"><span id="returnCharCount">0</span> / 1000</p>

                <div id="returnRevisionError" class="error-messages" hidden></div>

                <div class="panel-modal-actions">
                    <button class="tool-btn" type="button" onclick="closeReturnModal()">Cancel</button>
                    <button class="tool-btn tool-btn--warn" type="button" id="returnRevisionSubmitBtn"
                        onclick="submitReturnRevision()">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#back-icon" />
                        </svg>
                        Return for Revision
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($canResubmit): ?>
    <!-- ===== Re-submit protocol modal (researcher only, this protocol, latest round) ===== -->
    <div class="modal-backdrop" id="reuploadModalBackdrop">
        <div class="modal-card panel-modal-card">
            <div class="panel-modal-header">
                <div>
                    <p class="panel-modal-label">Re-submit Protocol</p>
                    <p class="panel-modal-title"><?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button class="tool-btn close-modal" onclick="closeReuploadModal()" aria-label="Close">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <use href="#close-icon" />
                    </svg>
                    <!-- Close -->
                </button>
            </div>
            <div class="panel-modal-body">
                <p class="panel-modal-intro"><?= htmlspecialchars($resubmitIntro, ENT_QUOTES, 'UTF-8') ?></p>

                <div class="doc-list" id="reuploadDocList"></div>

                <div id="reuploadError" class="error-messages" hidden></div>

                <div class="panel-modal-actions">
                    <button class="tool-btn" type="button" onclick="closeReuploadModal()">Cancel</button>
                    <button class="tool-btn tool-btn--success" type="button" id="reuploadSubmitBtn"
                        onclick="submitReupload()">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#upload-icon" />
                        </svg>
                        Submit
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($canRename): ?>
    <!-- ===== Rename protocol modal ===== -->
    <div class="modal-backdrop" id="renameModalBackdrop">
        <div class="modal-card panel-modal-card">
            <div class="panel-modal-header">
                <div>
                    <p class="panel-modal-label">Rename Protocol</p>
                    <p class="panel-modal-title"><?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button class="tool-btn close-modal" onclick="closeRenameModal()" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <use href="#close-icon" />
                    </svg>
                </button>
            </div>
            <div class="panel-modal-body">
                <label class="return-comment-label" for="renameTitleInput">New research title</label>
                <input type="text" id="renameTitleInput" class="return-comment-textarea rename-title-input" maxlength="255"
                    value="<?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?>">

                <div id="renameError" class="error-messages" hidden></div>

                <div class="panel-modal-actions">
                    <button class="tool-btn" type="button" onclick="closeRenameModal()">Cancel</button>
                    <button class="tool-btn tool-btn--success" type="button" id="renameSubmitBtn" onclick="submitRename()">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#edit-icon" />
                        </svg>
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($canDelete || $canRequestDeletion): ?>
    <!-- ===== Delete / request deletion modal ===== -->
    <div class="modal-backdrop" id="deleteModalBackdrop">
        <div class="modal-card panel-modal-card">
            <div class="panel-modal-header">
                <div>
                    <p class="panel-modal-label"><?= $canDelete ? 'Delete Protocol' : 'Request Deletion' ?></p>
                    <p class="panel-modal-title"><?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button class="tool-btn close-modal" onclick="closeDeleteModal()" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <use href="#close-icon" />
                    </svg>
                </button>
            </div>
            <div class="panel-modal-body">
                <p class="panel-modal-intro">
                    <?= $canDelete
                        ? 'This will delete the protocol and notify the researcher. Please explain why.'
                        : 'The reviewer will be notified of your request along with the reason below.' ?>
                </p>

                <label class="return-comment-label" for="deleteReasonText">Reason <span class="return-comment-optional">(required)</span></label>
                <textarea id="deleteReasonText" class="return-comment-textarea"
                    placeholder="Explain why this protocol should be deleted..."
                    rows="4" maxlength="1000"></textarea>
                <p class="return-char-count"><span id="deleteCharCount">0</span> / 1000</p>

                <div id="deleteError" class="error-messages" hidden></div>

                <div class="panel-modal-actions">
                    <button class="tool-btn" type="button" onclick="closeDeleteModal()">Cancel</button>
                    <button class="tool-btn tool-btn--danger" type="button" id="deleteSubmitBtn" onclick="submitDelete()">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#trash-icon" />
                        </svg>
                        <?= $canDelete ? 'Delete' : 'Send Request' ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($isReviewer && !empty($protocol['deletion_requested_at'])): ?>
    <!-- ===== Approve / reject deletion request modal ===== -->
    <div class="modal-backdrop" id="deletionReviewModalBackdrop">
        <div class="modal-card panel-modal-card">
            <div class="panel-modal-header">
                <div>
                    <p class="panel-modal-label" id="deletionReviewLabel">Review Deletion Request</p>
                    <p class="panel-modal-title"><?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button class="tool-btn close-modal" onclick="closeDeletionReviewModal()" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <use href="#close-icon" />
                    </svg>
                </button>
            </div>
            <div class="panel-modal-body">
                <p class="panel-modal-intro" id="deletionReviewIntro"></p>

                <div id="deletionReviewReasonWrap">
                    <label class="return-comment-label" for="deletionReviewReasonText">Reason <span class="return-comment-optional">(required)</span></label>
                    <textarea id="deletionReviewReasonText" class="return-comment-textarea"
                        placeholder="Explain why this deletion request is being rejected..."
                        rows="4" maxlength="1000"></textarea>
                    <p class="return-char-count"><span id="deletionReviewCharCount">0</span> / 1000</p>
                </div>

                <div id="deletionReviewError" class="error-messages" hidden></div>

                <div class="panel-modal-actions">
                    <button class="tool-btn" type="button" onclick="closeDeletionReviewModal()">Cancel</button>
                    <button class="tool-btn" type="button" id="deletionReviewSubmitBtn" onclick="submitDeletionReview()">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ===== File popup modal ===== -->
<div class="modal-backdrop" id="filePopupBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:900;align-items:center;justify-content:center;">
    <div class="modal-card file-popup-card">
        <div class="file-popup-header">
            <span class="file-popup-title" id="filePopupTitle"></span>
            <button class="tool-btn" onclick="closeFilePopup()" aria-label="Close">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <use href="#close-icon" />
                </svg>
                Close
            </button>
        </div>
        <div class="file-popup-frame" id="filePopupFrame">
            <div id="filePopupPdfPages" class="file-popup-pdf-pages" hidden></div>
            <img id="filePopupImg" alt="">
            <p id="filePopupMessage" class="helper" style="padding:2rem">Loading…</p>
        </div>
    </div>
</div>
<style>
    #filePopupBackdrop.open {
        display: flex !important;
    }

    .docx-fallback {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        padding: 3rem 2rem;
        text-align: center;
        color: var(--text-muted, #6b7280);
    }

    .docx-fallback svg {
        opacity: .45;
    }

    .docx-fallback p {
        max-width: 28rem;
        line-height: 1.6;
    }
</style>

<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    const PDF_URL = <?= json_encode($fileUrl) ?>;
    const FILE_EXT = <?= json_encode(strtolower(pathinfo($version['original_name'] ?? '', PATHINFO_EXTENSION))) ?>;
    const VERSION_ID = <?= $versionId              ?>;
    const PROTOCOL_ID = <?= $protocolId             ?>;
    const IS_STAFF = <?= $isStaff    ? 'true' : 'false' ?>;
    const IS_ADMIN = <?= $isAdmin    ? 'true' : 'false' ?>;
    const IS_REVIEWER = <?= $isReviewer ? 'true' : 'false' ?>;
    const IS_COMPLETED = <?= $isCompleted ? 'true' : 'false' ?>;
    const STATUS_KEY = <?= json_encode($statusKey) ?>;
    const IS_LATEST_VERSION = <?= $isLatestVersion ? 'true' : 'false' ?>;
    const CAN_REVIEW = <?= $canReview ? 'true' : 'false' ?>;
    const CSRF_TOKEN = <?= json_encode($csrf)      ?>;
    const ANNOT_API = <?= json_encode($annotApi)  ?>;
    const STATUS_API = <?= json_encode($statusApi) ?>;
    const ROOT_URL = <?= json_encode(ROOT) ?>;
    const CAN_RESUBMIT = <?= $canResubmit ? 'true' : 'false' ?>;
    const RESUBMIT_DOCS = <?= json_encode($resubmitDocs) ?>;

    // ===== Auto-dismiss viewer flash messages =====
    (function() {
        function dismissFlash(elementId, delayMs) {
            const el = document.getElementById(elementId);
            if (!el) return;
            setTimeout(() => {
                el.style.transition = 'opacity 0.4s ease';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 420);
            }, delayMs);
        }
        dismissFlash('viewerFlashSuccess', 4000);
        dismissFlash('viewerFlashError', 7000);
    })();

    // ===== Version switcher dropdown =====
    const versionTrigger = document.getElementById('versionSwitcherTrigger');
    const versionMenu = document.getElementById('versionDropdownMenu');
    const versionSwitcher = document.getElementById('versionSwitcher');

    // ===== Title rename-history dropdown =====
    const titleHistoryTrigger = document.getElementById('titleHistoryTrigger');
    const titleHistoryMenu = document.getElementById('titleHistoryMenu');
    const titleHistorySwitcher = document.getElementById('titleHistorySwitcher');

    titleHistoryTrigger?.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = titleHistoryMenu.classList.toggle('open');
        titleHistoryTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', (e) => {
        if (titleHistorySwitcher && !titleHistorySwitcher.contains(e.target)) {
            titleHistoryMenu?.classList.remove('open');
            titleHistoryTrigger?.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            titleHistoryMenu?.classList.remove('open');
            titleHistoryTrigger?.setAttribute('aria-expanded', 'false');
        }
    });

    versionTrigger?.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = versionMenu.classList.toggle('open');
        versionTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', (e) => {
        if (versionSwitcher && !versionSwitcher.contains(e.target)) {
            versionMenu?.classList.remove('open');
            versionTrigger?.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            versionMenu?.classList.remove('open');
            versionTrigger?.setAttribute('aria-expanded', 'false');
        }
    });

    // ===== State =====
    let pdfDoc = null;
    let annotations = [];
    let pendingBox = null;
    let dragState = null;
    let editingAnnotId = null;

    // ===== Load PDF =====
    async function loadPdf() {
        if (FILE_EXT === 'docx') {
            const col = document.getElementById('pdfColumn');
            const name = <?= json_encode(htmlspecialchars($version['original_name'] ?? 'protocol.docx', ENT_QUOTES, 'UTF-8')) ?>;
            col.innerHTML =
                '<div class="docx-fallback">' +
                '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">' +
                '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>' +
                '<polyline points="14 2 14 8 20 8"/>' +
                '</svg>' +
                '<p>This protocol was submitted as a Word document (.docx) and cannot be previewed here.</p>' +
                '<a class="button" href="' + escHtml(PDF_URL) + '" download="' + escHtml(name) + '">Download to view</a>' +
                '</div>';
            await loadAnnotations();
            return;
        }
        try {
            pdfDoc = await pdfjsLib.getDocument(PDF_URL).promise;
            const col = document.getElementById('pdfColumn');
            col.innerHTML = '';

            for (let p = 1; p <= pdfDoc.numPages; p++) {
                const page = await pdfDoc.getPage(p);
                const scale = Math.min(1.5, (col.clientWidth - 48) / page.getViewport({
                    scale: 1
                }).width);
                const vp = page.getViewport({
                    scale
                });
                const outputScale = window.devicePixelRatio || 1;

                const wrapper = document.createElement('div');
                wrapper.className = 'page-wrapper';
                wrapper.dataset.page = p;
                wrapper.style.width = vp.width + 'px';

                const canvas = document.createElement('canvas');
                canvas.className = 'pdf-canvas';
                canvas.width = Math.floor(vp.width * outputScale);
                canvas.height = Math.floor(vp.height * outputScale);
                canvas.style.width = vp.width + 'px';
                canvas.style.height = vp.height + 'px';
                wrapper.appendChild(canvas);

                const overlay = document.createElement('div');
                overlay.className = 'annot-overlay';
                overlay.dataset.origW = vp.width;
                overlay.dataset.origH = vp.height;
                overlay.dataset.page = p;

                if (CAN_REVIEW) {
                    overlay.style.cursor = 'crosshair';
                    attachDrawListeners(overlay, p, canvas);
                }
                wrapper.appendChild(overlay);
                col.appendChild(wrapper);

                await page.render({
                    canvasContext: canvas.getContext('2d'),
                    viewport: vp,
                    transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : undefined
                }).promise;
            }

            document.getElementById('pageIndicator').textContent =
                pdfDoc.numPages + (pdfDoc.numPages === 1 ? ' page' : ' pages');

        } catch (err) {
            document.getElementById('pdfColumn').innerHTML =
                '<p class="error-msg">Could not load document: ' + escHtml(err.message) + '</p>';
        }
        await loadAnnotations();
    }

    // ===== Draw listeners =====
    // Pointer events cover mouse, touch, and pen with one set of handlers.
    function attachDrawListeners(overlay, pageNum, canvas) {
        overlay.addEventListener('pointerdown', e => {
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            e.preventDefault();
            overlay.setPointerCapture(e.pointerId);
            const rect = overlay.getBoundingClientRect();
            const ghost = document.createElement('div');
            ghost.className = 'annot-ghost';
            overlay.appendChild(ghost);
            dragState = {
                pointerId: e.pointerId,
                pageNum,
                startX: e.clientX - rect.left,
                startY: e.clientY - rect.top,
                canvas,
                ghost
            };
        });

        overlay.addEventListener('pointermove', e => {
            if (!dragState || dragState.pageNum !== pageNum || dragState.pointerId !== e.pointerId) return;
            e.preventDefault();
            const rect = overlay.getBoundingClientRect();
            const curX = e.clientX - rect.left;
            const curY = e.clientY - rect.top;
            const g = dragState.ghost;
            g.style.left = Math.min(dragState.startX, curX) + 'px';
            g.style.top = Math.min(dragState.startY, curY) + 'px';
            g.style.width = Math.abs(curX - dragState.startX) + 'px';
            g.style.height = Math.abs(curY - dragState.startY) + 'px';
        });

        overlay.addEventListener('pointerup', e => {
            if (!dragState || dragState.pageNum !== pageNum || dragState.pointerId !== e.pointerId) return;
            const rect = overlay.getBoundingClientRect();
            const curX = e.clientX - rect.left;
            const curY = e.clientY - rect.top;
            const rawW = Math.abs(curX - dragState.startX);
            const rawH = Math.abs(curY - dragState.startY);
            dragState.ghost?.remove();

            if (rawW < 10 || rawH < 10) {
                dragState = null;
                return;
            }

            const cRect = dragState.canvas.getBoundingClientRect();
            pendingBox = {
                pageNum: dragState.pageNum,
                x: Math.min(dragState.startX, curX) / cRect.width,
                y: Math.min(dragState.startY, curY) / cRect.height,
                w: rawW / cRect.width,
                h: rawH / cRect.height,
            };
            dragState = null;
            openCommentDialog();
        });

        overlay.addEventListener('pointercancel', e => {
            if (!dragState || dragState.pointerId !== e.pointerId) return;
            dragState.ghost?.remove();
            dragState = null;
        });
    }

    // ===== Comment dialog =====
    function openCommentDialog() {
        editingAnnotId = null;
        document.getElementById('commentDialogTitle').textContent = 'Add Comment';
        document.getElementById('commentDialogSave').textContent = 'Save';
        document.getElementById('commentText').value = '';
        document.getElementById('commentDialog').classList.add('open');
        document.getElementById('commentText').focus();
    }

    function editAnnotation(annotId) {
        const ann = annotations.find(a => a.id === annotId);
        if (!ann) return;
        pendingBox = null;
        editingAnnotId = annotId;
        document.getElementById('commentDialogTitle').textContent = 'Edit Comment';
        document.getElementById('commentDialogSave').textContent = 'Save Changes';
        document.getElementById('commentText').value = ann.comment;
        document.getElementById('commentDialog').classList.add('open');
        document.getElementById('commentText').focus();
    }

    function cancelComment() {
        pendingBox = null;
        editingAnnotId = null;
        document.getElementById('commentDialog').classList.remove('open');
    }
    async function saveComment() {
        const text = document.getElementById('commentText').value.trim();
        if (!text || (!pendingBox && !editingAnnotId)) return;
        document.getElementById('commentDialog').classList.remove('open');

        if (editingAnnotId) {
            const annotId = editingAnnotId;
            editingAnnotId = null;
            const res = await fetch(ANNOT_API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({
                    action: 'edit',
                    id: annotId,
                    comment: text,
                }),
            });
            const data = await res.json();
            if (data.ok) {
                const ann = annotations.find(a => a.id === annotId);
                if (ann) ann.comment = text;
                renderAnnotations();
            } else {
                alert('Could not save comment: ' + (data.error ?? 'unknown error'));
            }
            return;
        }

        const res = await fetch(ANNOT_API, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({
                action: 'save',
                version_id: VERSION_ID,
                page_number: pendingBox.pageNum,
                x: pendingBox.x,
                y: pendingBox.y,
                width: pendingBox.w,
                height: pendingBox.h,
                comment: text,
            }),
        });
        const data = await res.json();
        if (data.ok) {
            annotations.push({
                id: data.id,
                page_number: pendingBox.pageNum,
                x: pendingBox.x,
                y: pendingBox.y,
                width: pendingBox.w,
                height: pendingBox.h,
                comment: text,
                created_at: new Date().toISOString(),
            });
            pendingBox = null;
            renderAnnotations();
        } else if (data.queued) {
            annotations.push({
                id: 'queued-' + Date.now(),
                page_number: pendingBox.pageNum,
                x: pendingBox.x,
                y: pendingBox.y,
                width: pendingBox.w,
                height: pendingBox.h,
                comment: text,
                created_at: new Date().toISOString(),
                _queued: true,
            });
            pendingBox = null;
            renderAnnotations();
        } else {
            alert('Could not save comment: ' + (data.error ?? 'unknown error'));
        }
    }

    // ===== Load + render annotations =====
    async function loadAnnotations() {
        try {
            const res = await fetch(ANNOT_API + '?version_id=' + VERSION_ID);
            const data = await res.json();
            annotations = Array.isArray(data) ? data : [];
        } catch (err) {
            annotations = [];
        }
        renderAnnotations();
    }

    function renderAnnotations() {
        document.querySelectorAll('.annot-box').forEach(el => el.remove());

        annotations.forEach((ann, idx) => {
            const overlay = document.querySelector('.annot-overlay[data-page="' + ann.page_number + '"]');
            if (!overlay) return;

            const canvas = overlay.closest('.page-wrapper')?.querySelector('canvas');
            const pw = canvas ? canvas.getBoundingClientRect().width : parseFloat(overlay.dataset.origW);
            const ph = canvas ? canvas.getBoundingClientRect().height : parseFloat(overlay.dataset.origH);
            const box = document.createElement('div');
            box.className = 'annot-box' + (ann._queued ? ' annot-box--queued' : '');
            box.dataset.annotId = ann.id;
            box.style.left = (ann.x * pw) + 'px';
            box.style.top = (ann.y * ph) + 'px';
            box.style.width = (ann.width * pw) + 'px';
            box.style.height = (ann.height * ph) + 'px';
            box.title = ann._queued ? '[Queued - pending sync] ' + ann.comment : ann.comment;

            const label = document.createElement('span');
            label.className = 'annot-label';
            label.textContent = idx + 1;
            box.appendChild(label);
            box.addEventListener('click', (e) => handleAnnotBoxClick(e, ann.id));
            overlay.appendChild(box);
        });

        renderSidebar();
    }

    function renderSidebar() {
        const list = document.getElementById('annotList');
        if (!annotations.length) {
            list.innerHTML = '<p class="annot-empty">No comments yet.</p>';
            return;
        }
        list.innerHTML = annotations.map((ann, idx) => `
        <div class="annot-item${ann._queued ? ' annot-item--queued' : ''}" id="sidebar-${ann.id}" onclick="scrollToAnnotation(${ann.id})">
            <div class="annot-item-header">
                <span class="annot-num">${idx + 1}</span>
                <span class="annot-page">Page ${ann.page_number}</span>
                ${ann._queued ? '<span class="annot-queued-badge">Pending sync</span>' : ''}
                ${CAN_REVIEW && !ann._queued ? `
                <button class="annot-edit" title="Edit comment" onclick="event.stopPropagation(); editAnnotation(${ann.id})">
                     <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#edit-icon">
                    </svg>
                </button>
                <button class="annot-delete" title="Delete comment"
                    onclick="event.stopPropagation(); confirmAction('Delete this comment? This cannot be undone.', { okText: 'Delete', danger: true }).then(ok => ok && deleteAnnotation(${ann.id}))">
                     <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#trash-icon">
                    </svg>
                </button>` : ''}
            </div>
            <p class="annot-comment">${escHtml(ann.comment)}</p>
            <p class="annot-date">${formatAnnotDate(ann.created_at)}</p>
        </div>
    `).join('');
    }

    function formatAnnotDate(value) {
        if (!value) return '';
        const d = new Date(value);
        if (isNaN(d.getTime())) return '';
        return d.toLocaleString('en-PH', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function highlightSidebarItem(annotId) {
        document.querySelectorAll('.annot-item').forEach(el => el.classList.remove('highlight'));
        const el = document.getElementById('sidebar-' + annotId);
        if (el) {
            el.classList.add('highlight');
            el.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }
    }

    function scrollToAnnotation(annotId) {
        const box = document.querySelector('.annot-box[data-annot-id="' + annotId + '"]');
        if (box) box.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
        highlightSidebarItem(annotId);
    }

    // ===== Comment popup (small screens) =====
    // Below the layout breakpoint the sidebar stacks under the PDF instead of
    // sitting beside it, so tapping a marker shows the comment in a popup at
    // the tap position instead of just scrolling/highlighting the sidebar.
    const MOBILE_MQ = window.matchMedia('(max-width: 767px)');
    const annotPopup = document.getElementById('annotPopup');

    function updateToolbarHint() {
        const hint = document.getElementById('toolbarHint');
        if (!hint) return;
        hint.textContent = MOBILE_MQ.matches ?
            'Tap and drag on the document to add a comment.' :
            'Click and drag on the document to add a comment.';
    }
    updateToolbarHint();
    MOBILE_MQ.addEventListener('change', updateToolbarHint);

    function handleAnnotBoxClick(e, annotId) {
        highlightSidebarItem(annotId);
        if (MOBILE_MQ.matches) {
            e.stopPropagation();
            showAnnotPopup(annotId, e.clientX, e.clientY);
        }
    }

    function showAnnotPopup(annotId, x, y) {
        const idx = annotations.findIndex(a => a.id === annotId);
        if (idx === -1) return;
        const ann = annotations[idx];

        document.getElementById('annotPopupNum').textContent = idx + 1;
        document.getElementById('annotPopupPage').textContent = 'Page ' + ann.page_number;
        document.getElementById('annotPopupComment').textContent = ann.comment;
        document.getElementById('annotPopupDate').textContent = formatAnnotDate(ann.created_at);

        const actions = document.getElementById('annotPopupActions');
        const editBtn = document.getElementById('annotPopupEdit');
        const deleteBtn = document.getElementById('annotPopupDelete');
        if (CAN_REVIEW && !ann._queued) {
            actions.style.display = '';

            editBtn.onclick = () => {
                closeAnnotPopup();
                editAnnotation(annotId);
            };

            deleteBtn.onclick = () => {
                confirmAction('Delete this comment? This cannot be undone.', {
                        okText: 'Delete',
                        danger: true
                    })
                    .then(ok => ok && deleteAnnotation(annotId));
                closeAnnotPopup();
            };
        } else {
            actions.style.display = 'none';
            editBtn.onclick = null;
            deleteBtn.onclick = null;
        }

        // Position near the tap point, then clamp to stay on-screen.
        annotPopup.style.left = '0px';
        annotPopup.style.top = '0px';
        annotPopup.classList.add('open');

        const margin = 10;
        const rect = annotPopup.getBoundingClientRect();
        let left = x + margin;
        let top = y + margin;
        if (left + rect.width > window.innerWidth - margin) left = x - rect.width - margin;
        if (left < margin) left = margin;
        if (top + rect.height > window.innerHeight - margin) top = y - rect.height - margin;
        if (top < margin) top = margin;

        annotPopup.style.left = left + 'px';
        annotPopup.style.top = top + 'px';
    }

    function closeAnnotPopup() {
        annotPopup.classList.remove('open');
    }

    document.getElementById('annotPopupClose').addEventListener('click', closeAnnotPopup);

    document.addEventListener('click', (e) => {
        if (annotPopup.classList.contains('open') &&
            !annotPopup.contains(e.target) &&
            !e.target.closest('.annot-box')) {
            closeAnnotPopup();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeAnnotPopup();
    });

    window.addEventListener('scroll', closeAnnotPopup, true);
    MOBILE_MQ.addEventListener('change', closeAnnotPopup);

    // ===== Delete annotation =====
    async function deleteAnnotation(annotId) {
        const res = await fetch(ANNOT_API, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({
                action: 'delete',
                id: annotId
            }),
        });
        const data = await res.json();
        if (data.ok) {
            annotations = annotations.filter(a => a.id !== annotId);
            renderAnnotations();
        }
    }

    // ===== Status update =====
    async function updateStatus(newStatus) {
        try {
            const res = await fetch(STATUS_API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({
                    protocol_id: PROTOCOL_ID,
                    status: newStatus
                }),
            });
            const data = await res.json();
            if (data.ok) {
                window.location.href = <?= json_encode($backUrl) ?>;
            } else if (data.queued) {} else {
                alert('Error: ' + (data.error ?? 'unknown error'));
            }
        } catch (err) {
            alert('Could not reach the server and the action could not be queued. Please check your connection.');
        }
    }

    <?php if ($canRename): ?>
        // ===== Rename modal =====
        const RENAME_API = <?= json_encode(ROOT . '/apply/rename') ?>;
        const renameBackdrop = document.getElementById('renameModalBackdrop');
        const renameTitleInput = document.getElementById('renameTitleInput');

        function openRenameModal() {
            document.getElementById('renameError').hidden = true;
            renameBackdrop.classList.add('open');
            renameTitleInput.focus();
            renameTitleInput.select();
        }

        function closeRenameModal() {
            renameBackdrop.classList.remove('open');
        }

        renameBackdrop.addEventListener('click', e => {
            if (e.target === renameBackdrop) closeRenameModal();
        });

        async function submitRename() {
            const newTitle = renameTitleInput.value.trim();
            const errBox = document.getElementById('renameError');
            const submitBtn = document.getElementById('renameSubmitBtn');

            errBox.hidden = true;

            if (!newTitle) {
                errBox.textContent = 'Please enter a research title.';
                errBox.hidden = false;
                return;
            }

            const ok = await confirmAction(
                `Rename this protocol to "${newTitle}"?`, {
                    okText: 'Rename',
                    cancelText: 'Cancel'
                }
            );
            if (!ok) return;

            submitBtn.disabled = true;

            try {
                const res = await fetch(RENAME_API, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        protocol_id: PROTOCOL_ID,
                        title: newTitle
                    })
                });
                const data = await res.json();

                if (data.ok) {
                    window.location.reload();
                } else {
                    errBox.textContent = data.error ?? 'Could not rename this protocol.';
                    errBox.hidden = false;
                    submitBtn.disabled = false;
                }
            } catch {
                errBox.textContent = 'Network error. Please try again.';
                errBox.hidden = false;
                submitBtn.disabled = false;
            }
        }
    <?php endif; ?>

    <?php if ($canDelete || $canRequestDeletion): ?>
        // ===== Delete / request deletion modal =====
        const DELETE_API = <?= json_encode(ROOT . ($canDelete ? '/apply/delete' : '/apply/request_deletion')) ?>;
        const deleteBackdrop = document.getElementById('deleteModalBackdrop');
        const deleteReasonText = document.getElementById('deleteReasonText');
        const deleteCharCount = document.getElementById('deleteCharCount');

        function openDeleteModal() {
            deleteReasonText.value = '';
            deleteCharCount.textContent = '0';
            document.getElementById('deleteError').hidden = true;
            deleteBackdrop.classList.add('open');
        }

        function closeDeleteModal() {
            deleteBackdrop.classList.remove('open');
        }

        deleteBackdrop.addEventListener('click', e => {
            if (e.target === deleteBackdrop) closeDeleteModal();
        });

        deleteReasonText.addEventListener('input', () => {
            deleteCharCount.textContent = deleteReasonText.value.length;
        });

        async function submitDelete() {
            const reason = deleteReasonText.value.trim();
            const errBox = document.getElementById('deleteError');
            const submitBtn = document.getElementById('deleteSubmitBtn');

            errBox.hidden = true;

            if (!reason) {
                errBox.textContent = 'A reason is required.';
                errBox.hidden = false;
                return;
            }

            const confirmMessage = <?= $canDelete
                                        ? json_encode('Delete this protocol? The researcher will be notified with your reason. This cannot be undone.')
                                        : json_encode('Send a deletion request to the reviewer with this reason?') ?>;

            const ok = await confirmAction(confirmMessage, {
                okText: <?= $canDelete ? json_encode('Delete') : json_encode('Send Request') ?>,
                cancelText: 'Cancel',
                danger: <?= $canDelete ? 'true' : 'false' ?>
            });
            if (!ok) return;

            submitBtn.disabled = true;

            try {
                const res = await fetch(DELETE_API, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        protocol_id: PROTOCOL_ID,
                        reason: reason
                    })
                });
                const data = await res.json();

                if (data.ok) {
                    <?php if ($canDelete): ?>
                        window.location.href = <?= json_encode($backUrl) ?>;
                    <?php else: ?>
                        window.location.reload();
                    <?php endif; ?>
                } else {
                    errBox.textContent = data.error ?? 'Could not complete this action.';
                    errBox.hidden = false;
                    submitBtn.disabled = false;
                }
            } catch {
                errBox.textContent = 'Network error. Please try again.';
                errBox.hidden = false;
                submitBtn.disabled = false;
            }
        }
    <?php endif; ?>

    <?php if ($isReviewer && !empty($protocol['deletion_requested_at'])): ?>
        // ===== Approve / reject deletion request modal =====
        const deletionReviewBackdrop = document.getElementById('deletionReviewModalBackdrop');
        const deletionReviewLabel = document.getElementById('deletionReviewLabel');
        const deletionReviewIntro = document.getElementById('deletionReviewIntro');
        const deletionReviewReasonWrap = document.getElementById('deletionReviewReasonWrap');
        const deletionReviewReasonText = document.getElementById('deletionReviewReasonText');
        const deletionReviewCharCount = document.getElementById('deletionReviewCharCount');
        const deletionReviewSubmitBtn = document.getElementById('deletionReviewSubmitBtn');
        let deletionReviewAction = null;

        function openDeletionReviewModal(action) {
            deletionReviewAction = action;
            deletionReviewReasonText.value = '';
            deletionReviewCharCount.textContent = '0';
            document.getElementById('deletionReviewError').hidden = true;

            if (action === 'approve') {
                deletionReviewLabel.textContent = 'Approve Deletion Request';
                deletionReviewIntro.textContent = 'This will permanently delete the protocol and notify the researcher. This cannot be undone.';
                deletionReviewReasonWrap.style.display = 'none';
                deletionReviewSubmitBtn.textContent = 'Approve & Delete';
                deletionReviewSubmitBtn.className = 'tool-btn tool-btn--success';
            } else {
                deletionReviewLabel.textContent = 'Reject Deletion Request';
                deletionReviewIntro.textContent = 'The researcher will be notified that their deletion request was rejected, along with your explanation below.';
                deletionReviewReasonWrap.style.display = '';
                deletionReviewSubmitBtn.textContent = 'Reject Request';
                deletionReviewSubmitBtn.className = 'tool-btn tool-btn--danger';
            }

            deletionReviewBackdrop.classList.add('open');
        }

        function closeDeletionReviewModal() {
            deletionReviewBackdrop.classList.remove('open');
        }

        deletionReviewBackdrop.addEventListener('click', e => {
            if (e.target === deletionReviewBackdrop) closeDeletionReviewModal();
        });

        deletionReviewReasonText.addEventListener('input', () => {
            deletionReviewCharCount.textContent = deletionReviewReasonText.value.length;
        });

        async function submitDeletionReview() {
            const isApprove = deletionReviewAction === 'approve';
            const reason = deletionReviewReasonText.value.trim();
            const errBox = document.getElementById('deletionReviewError');

            errBox.hidden = true;

            if (!isApprove && !reason) {
                errBox.textContent = 'A reason is required.';
                errBox.hidden = false;
                return;
            }

            const confirmMessage = isApprove ?
                'Approve this deletion request? The protocol will be permanently deleted.' :
                'Reject this deletion request with the explanation you provided?';

            const ok = await confirmAction(confirmMessage, {
                okText: isApprove ? 'Approve & Delete' : 'Reject Request',
                cancelText: 'Cancel',
                danger: isApprove
            });
            if (!ok) return;

            deletionReviewSubmitBtn.disabled = true;

            try {
                const res = await fetch(<?= json_encode(ROOT) ?> + (isApprove ? '/apply/approve_deletion' : '/apply/reject_deletion'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        protocol_id: PROTOCOL_ID,
                        reason: reason
                    })
                });
                const data = await res.json();

                if (data.ok) {
                    if (isApprove) {
                        window.location.href = <?= json_encode($backUrl) ?>;
                    } else {
                        window.location.reload();
                    }
                } else {
                    errBox.textContent = data.error ?? 'Could not complete this action.';
                    errBox.hidden = false;
                    deletionReviewSubmitBtn.disabled = false;
                }
            } catch {
                errBox.textContent = 'Network error. Please try again.';
                errBox.hidden = false;
                deletionReviewSubmitBtn.disabled = false;
            }
        }
    <?php endif; ?>

    // ===== Mobile sidebar toggle =====
    function toggleSidebar() {
        const sidebar = document.getElementById('annotSidebar');
        const btn = document.getElementById('sidebarToggle');
        const isCollapsed = sidebar.classList.toggle('collapsed');
        btn.classList.toggle('collapsed', isCollapsed);
        btn.setAttribute('aria-expanded', String(!isCollapsed));
    }

    // ===== Util =====
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    loadPdf();

    // ===== File popup (cert / auth letter) =====
    const filePopupBackdrop = document.getElementById('filePopupBackdrop');
    const filePopupFrame = document.getElementById('filePopupFrame');
    const filePopupPdfPages = document.getElementById('filePopupPdfPages');
    const filePopupImg = document.getElementById('filePopupImg');
    const filePopupMessage = document.getElementById('filePopupMessage');
    let filePopupObjectUrl = null;

    // Exactly one of these three stays visible at a time.
    function showFilePopupState(state) {
        filePopupPdfPages.hidden = state !== 'pdf';
        filePopupImg.hidden = state !== 'img';
        filePopupMessage.hidden = state !== 'message';
    }

    async function renderPopupPdf(fileUrl) {
        filePopupPdfPages.innerHTML = '';
        const frameWidth = filePopupFrame.clientWidth;
        const doc = await pdfjsLib.getDocument(fileUrl).promise;

        for (let p = 1; p <= doc.numPages; p++) {
            const page = await doc.getPage(p);
            const scale = Math.min(1.5, (frameWidth - 48) / page.getViewport({
                scale: 1
            }).width);
            const vp = page.getViewport({
                scale
            });

            const canvas = document.createElement('canvas');
            const outputScale = window.devicePixelRatio || 1;
            canvas.width = Math.floor(vp.width * outputScale);
            canvas.height = Math.floor(vp.height * outputScale);
            canvas.style.width = vp.width + 'px';
            canvas.style.height = vp.height + 'px';
            filePopupPdfPages.appendChild(canvas);

            await page.render({
                canvasContext: canvas.getContext('2d'),
                viewport: vp,
                transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : undefined
            }).promise;
        }
    }

    async function openFilePopup(fileUrl, title) {
        document.getElementById('filePopupTitle').textContent = title;
        filePopupBackdrop.classList.add('open');
        filePopupFrame.scrollTop = 0;

        filePopupMessage.textContent = 'Loading…';
        showFilePopupState('message');

        try {
            const res = await fetch(fileUrl);
            if (!res.ok) throw new Error('Failed to load file');

            const contentType = res.headers.get('content-type') || '';

            if (contentType.includes('pdf')) {
                await renderPopupPdf(fileUrl);
                showFilePopupState('pdf');
                filePopupFrame.scrollTop = 0;
                return;
            }

            // Images: fetch as a blob so we control sizing via CSS
            // (object-fit: contain) instead of leaving it to the browser's
            // bare image viewer, which doesn't reliably scale to fit an iframe.
            const blob = await res.blob();

            if (filePopupObjectUrl) URL.revokeObjectURL(filePopupObjectUrl);
            filePopupObjectUrl = URL.createObjectURL(blob);

            filePopupImg.src = filePopupObjectUrl;
            filePopupImg.alt = title;
            showFilePopupState('img');
        } catch (err) {
            filePopupMessage.textContent = 'Could not load this file.';
            showFilePopupState('message');
        }
    }

    function closeFilePopup() {
        filePopupBackdrop.classList.remove('open');
        filePopupPdfPages.innerHTML = '';
        filePopupImg.removeAttribute('src');
        if (filePopupObjectUrl) {
            URL.revokeObjectURL(filePopupObjectUrl);
            filePopupObjectUrl = null;
        }
    }

    filePopupBackdrop.addEventListener('click', e => {
        if (e.target === filePopupBackdrop) closeFilePopup();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeFilePopup();
    });

    let _resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(_resizeTimer);
        _resizeTimer = setTimeout(renderAnnotations, 100);
    });

    <?php if ($canReview): ?>
        // ===== Return for Revision modal =====
        const RETURN_REVISION_API = <?= json_encode(ROOT . '/apply/return_revision') ?>;
        const returnBackdrop = document.getElementById('returnRevisionBackdrop');
        const returnComment = document.getElementById('returnComment');
        const returnCharCount = document.getElementById('returnCharCount');

        function openReturnModal() {
            document.querySelectorAll('input[name="return_reason"]').forEach(cb => cb.checked = false);
            returnComment.value = '';
            returnCharCount.textContent = '0';
            document.getElementById('returnRevisionError').hidden = true;
            returnBackdrop.classList.add('open');
        }

        function closeReturnModal() {
            returnBackdrop.classList.remove('open');
        }

        returnBackdrop.addEventListener('click', e => {
            if (e.target === returnBackdrop) closeReturnModal();
        });

        returnComment.addEventListener('input', () => {
            returnCharCount.textContent = returnComment.value.length;
        });

        async function submitReturnRevision() {
            const ok = await confirmAction(
                'Return this protocol for revision? The researcher will be notified and asked to resubmit.', {
                    okText: 'Return for Revision',
                    cancelText: 'Cancel',
                    danger: true
                }
            );
            if (!ok) return;

            const selectedReasons = [...document.querySelectorAll('input[name="return_reason"]:checked')].map(cb => cb.value);
            const commentText = returnComment.value.trim();
            const errBox = document.getElementById('returnRevisionError');
            const submitBtn = document.getElementById('returnRevisionSubmitBtn');

            errBox.hidden = true;
            submitBtn.disabled = true;

            try {
                const res = await fetch(RETURN_REVISION_API, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        protocol_id: PROTOCOL_ID,
                        reasons: selectedReasons,
                        comment: commentText
                    })
                });
                const data = await res.json();

                if (data.ok) {
                    window.location.href = <?= json_encode($backUrl) ?>;
                } else {
                    errBox.textContent = data.error ?? 'Could not return protocol. Please try again.';
                    errBox.hidden = false;
                    submitBtn.disabled = false;
                }
            } catch {
                errBox.textContent = 'Network error. Please try again.';
                errBox.hidden = false;
                submitBtn.disabled = false;
            }
        }
    <?php endif; ?>

    <?php if ($canResubmit): ?>
        // ===== Re-submit protocol (protocol file + any docs the reviewer flagged) =====
        const resubmitModal = document.getElementById('reuploadModalBackdrop');
        const RESUBMIT_ENDPOINTS = {
            protocol: {
                path: '/apply/reupload',
                field: 'protocol_file'
            },
            cert: {
                path: '/apply/reuploadcert',
                field: 'cert_file'
            },
            auth: {
                path: '/apply/reuploadauth',
                field: 'auth_file'
            }
        };
        let resubmitFiles = {};

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function validateResubmitFile(key, file) {
            const ext = file.name.split('.').pop().toLowerCase();
            if (key === 'protocol') {
                if (file.type !== 'application/pdf' || ext !== 'pdf') {
                    return 'Only PDF files are accepted for the protocol form.';
                }
            } else if (!['pdf', 'jpg', 'jpeg', 'png'].includes(ext)) {
                return 'Only PDF, JPG, or PNG files are accepted.';
            }
            if (file.size > 10 * 1024 * 1024) {
                return 'File is too large. Maximum size is 10 MB.';
            }
            return null;
        }

        function resubmitDocRow(doc) {
            const file = resubmitFiles[doc.key];
            const action = file ?
                `<div class="doc-row-done">
                    <svg width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#check-circle-icon" /></svg>
                    <label class="doc-row-replace">
                        Replace
                        <input type="file" accept="${doc.accept}" onchange="handleResubmitUpload(event,'${doc.key}')">
                    </label>
                </div>` :
                `<label class="btn-upload-inline">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#upload-icon" /></svg>
                    Upload
                    <input type="file" accept="${doc.accept}" onchange="handleResubmitUpload(event,'${doc.key}')">
                </label>`;
            const subLine = file ?
                `<span class="doc-row-sub done">${escHtml(file.name)} &middot; ${formatFileSize(file.size)}</span>` :
                `<span class="doc-row-sub">${doc.subtitle}</span>`;

            return `<div class="doc-row">
                <div class="doc-row-info">
                    <div class="doc-row-title">${doc.title}${doc.required ? ' <span class="req">*</span>' : ''}</div>
                    ${subLine}
                </div>
                <div class="doc-row-action">${action}</div>
            </div>`;
        }

        function renderResubmitDocs() {
            document.getElementById('reuploadDocList').innerHTML = RESUBMIT_DOCS.map(resubmitDocRow).join('');
        }

        function handleResubmitUpload(event, key) {
            const file = event.target.files[0];
            if (!file) return;
            const err = validateResubmitFile(key, file);
            if (err) {
                alert(err);
                event.target.value = '';
                return;
            }
            resubmitFiles[key] = file;
            renderResubmitDocs();
        }

        function openReuploadModal() {
            resubmitFiles = {};
            renderResubmitDocs();
            document.getElementById('reuploadError').hidden = true;
            resubmitModal.classList.add('open');
        }

        function closeReuploadModal() {
            resubmitModal.classList.remove('open');
        }
        resubmitModal.addEventListener('click', e => {
            if (e.target === resubmitModal) closeReuploadModal();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeReuploadModal();
        });

        async function uploadProtocolFile(endpoint, fieldName, file) {
            const formData = new FormData();
            formData.append('protocol_id', PROTOCOL_ID);
            if (file) formData.append(fieldName, file);

            const res = await fetch(ROOT_URL + endpoint, {
                method: 'POST',
                body: formData
            });
            return res.json();
        }

        async function submitReupload() {
            const errBox = document.getElementById('reuploadError');
            const submitBtn = document.getElementById('reuploadSubmitBtn');

            for (const doc of RESUBMIT_DOCS) {
                if (doc.required && !resubmitFiles[doc.key]) {
                    errBox.textContent = `Please select your ${doc.title.toLowerCase()}.`;
                    errBox.hidden = false;
                    return;
                }
            }

            const ok = await confirmAction(
                'Resubmit this protocol for review? It will be sent back to the reviewer.', {
                    okText: 'Resubmit',
                    cancelText: 'Cancel'
                }
            );
            if (!ok) return;

            submitBtn.disabled = true;
            errBox.hidden = true;

            try {
                for (const doc of RESUBMIT_DOCS) {
                    if (doc.key === 'protocol') continue;
                    const file = resubmitFiles[doc.key];
                    if (!file) continue;
                    const {
                        path,
                        field
                    } = RESUBMIT_ENDPOINTS[doc.key];
                    const result = await uploadProtocolFile(path, field, file);
                    if (!result.success) throw new Error(result.error ?? `${doc.title} upload failed. Please try again.`);
                }

                const {
                    path,
                    field
                } = RESUBMIT_ENDPOINTS.protocol;
                const protocolResult = await uploadProtocolFile(path, field, resubmitFiles.protocol);
                if (!protocolResult.success) throw new Error(protocolResult.error ?? 'Upload failed. Please try again.');

                window.location.reload();
            } catch (err) {
                errBox.textContent = err.message || 'Network error. Please try again.';
                errBox.hidden = false;
                submitBtn.disabled = false;
            }
        }
    <?php endif; ?>
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>