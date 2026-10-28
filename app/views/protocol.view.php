<?php

/** @var array  $protocol */
/** @var array  $version  */
/** @var bool   $isLatestVersion */
/** @var bool   $isSignedScan */
/** @var bool   $isAmendment */
/** @var array  $versions */
/** @var string $csrf     */
/** @var bool   $isPersonnel    */
/** @var bool   $isStaff    */
/** @var array  $permissions */
/** @var bool   $canRename */
/** @var bool   $canRequestDeletion */
/** @var bool   $canDelete */
/** @var bool   $canAmend */
/** @var array  $amendments */
/** @var bool   $hasRenames */
/** @var bool   $hasPaymentHistory */
/** @var bool   $deletionRequested */
/** @var bool   $deletionRequestedByYou */
/** @var bool   $showTitleChangeBanner */
/** @var array|null $cert */
/** @var array|null $certReview */
/** @var bool   $isOriginalSubmission */
/** @var string $flashSuccess */
/** @var string $flashError */

$title = htmlspecialchars($protocol['research_title'] ?? 'Protocol', ENT_QUOTES, 'UTF-8');

$roleLabels = ['researcher' => 'Researcher', 'staff' => 'Staff', 'reviewer' => 'Reviewer'];

$statusLabels = [
    'under review'   => 'Under Review',
    'needs revision' => 'Needs Revision',
    'reviewed'       => 'Reviewed',
    'endorsed'       => 'Endorsed',
    'approved'       => 'Approved',
];
$statusMeta = [
    'under review'   => ['class' => 'under-review', 'icon' => 'clock-icon'],
    'needs revision' => ['class' => 'needs-revision', 'icon' => 'alert-triangle-icon'],
    'reviewed'       => ['class' => 'reviewed', 'icon' => 'checkbox-icon'],
    'endorsed'       => ['class' => 'endorsed', 'icon' => 'shield-check-icon'],
    'approved'       => ['class' => 'approved', 'icon' => 'check-circle-icon'],
];
$statusKey       = strtolower($protocol['status'] ?? '');
$statusLabel     = $statusLabels[$statusKey] ?? ($protocol['status'] ?? '');
$isCompleted     = in_array($statusKey, ['approved'], true);
$isLatestVersion = $isLatestVersion ?? true;
$isSignedScan    = $isSignedScan ?? false;
$isAmendment     = $isAmendment ?? false;
$isReadOnlyFile  = $isSignedScan || $isAmendment;
$statusBadgeMeta  = $isReadOnlyFile
    ? ['class' => 'neutral', 'icon' => 'review-icon']
    : ($isLatestVersion ? ($statusMeta[$statusKey] ?? ['class' => 'neutral', 'icon' => 'info-icon']) : ['class' => 'neutral', 'icon' => 'history-icon']);
$statusBadgeLabel = match (true) {
    $isSignedScan    => 'Signed scan: read only',
    $isAmendment     => 'Amendment: read only',
    $isLatestVersion => $statusLabel,
    default          => 'Viewing history: read only',
};

$canReview = $isPersonnel && !$isStaff && $statusKey === 'under review' && $isLatestVersion;

$canResubmit = !$isPersonnel && $isLatestVersion && $statusKey === 'needs revision';

$researcherCertStatus = $cert['cert_status'] ?? null;
$canResubmitCert      = !$isPersonnel && $researcherCertStatus === 'rejected' && $isLatestVersion;

$paymentStatus     = $protocol['payment_status'] ?? 'unpaid';
$paymentMethod     = $protocol['payment_method'] ?? null;
$canConfirmPayment = !$isPersonnel && $isLatestVersion && $statusKey === 'reviewed'
    && in_array($paymentStatus, ['unpaid', 'rejected'], true);
$offerPaymentResubmit = $canConfirmPayment && $paymentStatus === 'rejected';
$mustPayInPerson   = UserModel::mustPayInPerson($protocol['submitter_school'] ?? '');

$fileUrl    = ROOT . '/apply/file/' . (int) $version['id'];
$annotApi   = ROOT . '/apply/annotate';
$statusApi  = ROOT . '/apply/status';
$protocolId = (int) $protocol['protocol_id'];
$versionId  = (int) $version['id'];
$versionNum = (int) $version['version_number'];

$backUrl = $backUrl ?? ($isPersonnel ? ROOT . '/personnel/home' : ROOT . '/submissions');

$scanIsImage = $isSignedScan
    && in_array(strtolower(pathinfo($version['original_name'] ?? '', PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true);

$fromFilter = $fromFilter ?? '';

// ===== Version viewer URL helper =====
function versionViewerUrl(int $protocolId, int $versionId, string $fromFilter): string
{
    $url = ROOT . '/apply/viewer/' . $protocolId . '/' . $versionId;
    return $fromFilter !== '' ? $url . '?from=' . urlencode($fromFilter) : $url;
}

// $versions is newest first: the entry before the current one is newer, the one after is older.
$prevVersion  = null;
$nextVersion  = null;
$versionLabel = submission_round_label($versionNum);
foreach ($versions as $i => $v) {
    if ((int) $v['id'] === $versionId) {
        $nextVersion  = $versions[$i - 1] ?? null;
        $prevVersion  = $versions[$i + 1] ?? null;
        $versionLabel = $v['label'];
        break;
    }
}

$submitterName     = trim(($protocol['submitter_first_name'] ?? '') . ' ' . ($protocol['submitter_last_name'] ?? ''));
$latestSignedScanViewerUrl = ! empty($latestSignedScanVersion['id']) ? versionViewerUrl($protocolId, (int) $latestSignedScanVersion['id'], $fromFilter) : null;
$amendRedirect = ROOT . '/apply/viewer/' . $protocolId . ($fromFilter !== '' ? '?from=' . urlencode($fromFilter) : '');

// ===== Notes shown above the document =====
$viewerNotes = [];
if (!$isStaff && $deletionRequested) {
    $requestedBy   = $deletionRequestedByYou
        ? 'you'
        : trim(($roleLabels[$protocol['deletion_requested_by_role']] ?? ucfirst((string) $protocol['deletion_requested_by_role'])) . ' ' . ($protocol['deletion_requested_by_name'] ?? ''));
    $viewerNotes[] = ['danger', 'trash-icon', 'Deletion requested', 'A deletion request is pending for this protocol, requested by ' . $requestedBy . '.'];
}
if ($isAmendment && !empty($version['note'])) {
    $viewerNotes[] = ['info', 'info-icon', 'Amendment note', $version['note']];
}
if (!$isPersonnel && !empty($reviewerNote['comment'])) {
    $viewerNotes[] = ['warning', 'info-icon', 'Note:', $reviewerNote['comment']];
}
if ($canResubmitCert) {
    $viewerNotes[] = ['danger', 'alert-triangle-icon', 'Training certificate rejected', 'Your review is on hold until you resubmit it.' . (!empty($cert['cert_note']) ? ' Reason: ' . $cert['cert_note'] . '.' : '')];
}
if ($isPersonnel && $researcherCertStatus === 'rejected') {
    $viewerNotes[] = ['warning', 'alert-triangle-icon', 'Training certificate rejected', 'Waiting for the researcher to resubmit it.' . (!empty($certReview['note']) ? ' Reason: "' . $certReview['note'] . '.' : '') . '" Finishing the review or returning the protocol is unavailable until it is verified.'];
}
if ($isPersonnel && $researcherCertStatus === 'pending') {
    $viewerNotes[] = ['info', 'info-icon', 'Training certificate not verified yet', 'Reload this page to review it. Finishing the review or returning the protocol is unavailable until it is verified.', 'cert-pending'];
}
$certVerifiedTitle = $certReview && $researcherCertStatus === 'verified'
    ? 'Verified by ' . trim($certReview['actor_name'] ?? '') . ' on ' . date(DATE_FORMAT, strtotime($certReview['created_at'])) . '.'
    : '';
$latestClearanceFileUrl    = ! empty($latestClearanceVersion['id']) ? ROOT . '/apply/file/' . (int) $latestClearanceVersion['id'] : null;

$resubmitDocs      = [
    ['key' => 'protocol', 'title' => 'Revised protocol file', 'subtitle' => 'PDF, DOC, or DOCX · max 10 MB', 'accept' => '.pdf,.doc,.docx', 'required' => true],
];

$hideWelcomeGuide = true;
include 'includes/header.php';
?>

<?php include_once 'includes/pdfjs.php'; ?>
<link rel="stylesheet" href="<?= asset_css('viewer.css') ?>">
<link rel="stylesheet" href="<?= asset_css('protocol-menu.css') ?>">
<link rel="stylesheet" href="<?= asset_css('history-modal.css') ?>">
<?php if ($isPersonnel): ?>
    <link rel="stylesheet" href="<?= asset_css('payment-badge.css') ?>">
    <link rel="stylesheet" href="<?= asset_css('researcher-modal.css') ?>">
<?php endif; ?>
<?php if ($canResubmit || $canConfirmPayment || $canAmend || $amendments || $hasPaymentHistory): ?>
    <link rel="stylesheet" href="<?= asset_css('application.css') ?>">
<?php endif; ?>

<div class="viewer-body">

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
           okText: 'Leave',
           cancelText: 'Cancel'
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

            <span class="viewer-title" id="viewerTitle" role="button" tabindex="0" aria-expanded="false" title="<?= $title ?>"><?= $title ?></span>

            <span class="status-badge">
                <span class="status-badge-icon status-badge-icon--<?= $statusBadgeMeta['class'] ?>">
                    <svg width="12" height="12" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#<?= $statusBadgeMeta['icon'] ?>" />
                    </svg>
                </span>
                <?= htmlspecialchars($statusBadgeLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="pending-sync-badge" data-pending-sync="<?= $protocolId ?>" hidden></span>
            <?php if ($isPersonnel && $researcherCertStatus !== null): ?>
                <span class="status-badge" id="certVerifiedBadge" title="<?= htmlspecialchars($certVerifiedTitle, ENT_QUOTES, 'UTF-8') ?>" <?= $researcherCertStatus === 'verified' ? '' : 'hidden' ?>>
                    <span class="status-badge-icon status-badge-icon--approved">
                        <svg width="12" height="12" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#check-circle-icon" />
                        </svg>
                    </span>
                    Training certificate verified
                </span>
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
                    <?= htmlspecialchars($versionLabel, ENT_QUOTES, 'UTF-8') ?>
                    <svg width="10" height="10" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#chev-down-icon" />
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
                        $vId    = (int) $v['id'];
                        $vName  = trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? ''));
                        $vDate  = !empty($v['uploaded_at']) ? date(DATETIME_FORMAT, strtotime($v['uploaded_at'])) : '';
                        $vIsCur = $vId === $versionId;
                        ?>
                        <a class="version-dropdown-item<?= $vIsCur ? ' is-current' : '' ?>"
                            href="<?= versionViewerUrl($protocolId, $vId, $fromFilter) ?>">
                            <span class="version-dropdown-item-num"><?= htmlspecialchars($v['label'], ENT_QUOTES, 'UTF-8') ?></span>
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
        </div>

        <div class="viewer-topbar-right">
            <span class="page-indicator" id="pageIndicator" hidden></span>

            <?php
            $menu = ProtocolMenu::forViewer($protocol, $permissions, [
                'download'       => ['url' => $fileUrl, 'name' => $version['original_name'] ?? 'protocol'],
                'hasUpdate'      => $showTitleChangeBanner,
                'deleteRedirect' => $canDelete ? $backUrl : null,
                'amendRedirect'  => $amendRedirect,
            ]);
            include 'includes/protocol-menu.php';
            ?>

            <?php if ($isStaff && !empty($protocol['deletion_requested_at'])): ?>
                <button class="tool-btn tool-btn--urgent" type="button" onclick="openDeletionReviewModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#alert-triangle-icon" />
                    </svg>
                    Deletion Requested: Review
                </button>
            <?php endif; ?>


            <?php if ($canReview || $canResubmit || $canConfirmPayment || $canResubmitCert): ?>
                <div class="viewer-review-actions">
                    <?php if ($canReview): ?>
                        <button class="tool-btn tool-btn--success" id="btnFinishReview"
                            onclick="openReviewDecisionModal()"
                            <?= $researcherCertStatus === 'verified' ? '' : 'aria-disabled="true" title="The researcher\'s training certificate must be verified first."' ?>>
                            Finish Review
                        </button>
                    <?php elseif ($canResubmitCert): ?>
                        <button class="tool-btn tool-btn--success" id="btnResubmitCert" type="button"
                            onclick="openCertResubmitModal()">
                            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#upload-icon" />
                            </svg>
                            Resubmit Certificate
                        </button>
                    <?php elseif ($canResubmit): ?>
                        <button class="tool-btn tool-btn--success" id="btnResubmit"
                            onclick="openReuploadModal()">
                            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#upload-icon" />
                            </svg>
                            Re-submit Protocol
                        </button>
                    <?php elseif ($canConfirmPayment): ?>
                        <button class="tool-btn tool-btn--success" id="btnConfirmPayment" type="button"
                            onclick="openPaymentModal()">
                            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#upload-icon" />
                            </svg>
                            Verify Payment
                        </button>
                    <?php endif; ?>
                </div>
            <?php elseif ($isCompleted && $isLatestVersion): ?>
                <?php if ($latestClearanceFileUrl):
                    $clearanceExt = strtolower(pathinfo($latestClearanceVersion['original_name'] ?? '', PATHINFO_EXTENSION));
                    $clearanceIsImage = in_array($clearanceExt, ['jpg', 'jpeg', 'png'], true);
                ?>
                    <a class="tool-btn tool-btn--success download-clearance-btn" href="<?= htmlspecialchars($latestClearanceFileUrl, ENT_QUOTES, 'UTF-8') ?>"
                        download="<?= htmlspecialchars($latestClearanceVersion['original_name'] ?? 'clearance', ENT_QUOTES, 'UTF-8') ?>"
                        data-view-href="<?= htmlspecialchars($latestClearanceFileUrl, ENT_QUOTES, 'UTF-8') ?>"
                        data-is-image="<?= $clearanceIsImage ? '1' : '0' ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#download-icon" />
                        </svg>
                        Download Clearance
                    </a>
                <?php else: ?>
                    <span class="ver-badge ver-badge--green">✓ Approved</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $toastSuccess = $flashSuccess ?? '';
    $toastError   = $flashError ?? '';
    include __DIR__ . '/includes/flash.php';
    ?>

    <!-- ===== Annotation hint and zoom ===== -->
    <div class="annot-toolbar">
        <?php if ($canReview): ?>
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#edit-icon" />
            </svg>
            <span class="toolbar-hint" id="toolbarHint">Click and drag on the document to add a comment.</span>
        <?php endif; ?>
        <div class="zoom-controls" id="zoomControls" role="group" aria-label="Zoom">
            <button type="button" class="tool-btn" id="zoomOut" title="Zoom out" aria-label="Zoom out">&minus;</button>
            <span class="zoom-level" id="zoomLevel" aria-live="polite">100%</span>
            <button type="button" class="tool-btn" id="zoomIn" title="Zoom in" aria-label="Zoom in">+</button>
            <button type="button" class="tool-btn" id="zoomFit">Fit width</button>
        </div>
    </div>

    <!-- ===== Layout: PDF + sidebar ===== -->
    <div class="viewer-layout">

        <div class="viewer-loading" id="viewerLoading" role="status" aria-live="polite">
            <span class="viewer-loading-spinner" aria-hidden="true"></span>
            Loading protocol...
        </div>

        <div class="viewer-main">
            <?php if ($viewerNotes): ?>
                <div class="viewer-notes">
                    <?php foreach ($viewerNotes as $viewerNote): ?>
                        <?php [$noteVariant, $noteIcon, $noteLabel, $noteText, $noteKey] = array_pad($viewerNote, 5, ''); ?>
                        <?php include 'includes/viewer-note.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="pdf-column" id="pdfColumn"></div>
        </div>

        <?php if (!$isReadOnlyFile): ?>
            <div class="annot-sidebar" id="annotSidebar">
                <div class="annot-sidebar-inner">
                    <h3>Comments</h3>
                    <p class="helper" id="annotOfflineNote" hidden></p>
                    <div id="annotList">
                        <p class="annot-empty">Loading…</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===== Comment popup (small screens: shown at tap position instead of the sidebar) ===== -->
    <div class="annot-popup" id="annotPopup">
        <div class="annot-item-header">
            <span class="annot-num" id="annotPopupNum"></span>
            <span class="annot-page" id="annotPopupPage"></span>
            <button class="annot-popup-close" id="annotPopupClose" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
            </button>
        </div>
        <p class="annot-comment" id="annotPopupComment"></p>
        <p class="annot-date" id="annotPopupDate"></p>
        <div class="annot-popup-actions" id="annotPopupActions" hidden>
            <button class="annot-edit" id="annotPopupEdit" title="Edit comment">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#edit-icon" />
                </svg>
                Edit
            </button>
            <button class="annot-delete" id="annotPopupDelete" title="Delete comment">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#trash-icon" />
                </svg>
                Delete
            </button>
        </div>
    </div>

    <!-- ===== Comment dialog (personnel only, latest version, while under review) ===== -->
    <?php if ($canReview): ?>
        <div class="modal-backdrop" id="commentDialog">
            <div class="modal-card panel-modal-card">
                <div class="modal-header">
                    <span class="modal-header-icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                            <use href="#edit-icon" />
                        </svg>
                    </span>
                    <p class="modal-title" id="commentDialogTitle">Add Comment</p>
                    <button class="modal-close" type="button" onclick="cancelComment()" aria-label="Close">
                        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#close-icon" />
                        </svg>
                    </button>
                </div>
                <div class="panel-modal-body">
                    <textarea id="commentText" class="return-comment-textarea" rows="4" placeholder="Type your comment…"></textarea>
                    <div class="panel-modal-actions" id="commentDialogActions">
                        <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="cancelComment()">Cancel</button>
                        <button class="confirm-modal-btn confirm-modal-ok" type="button" id="commentDialogSave" onclick="saveComment()">
                            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#edit-icon" />
                            </svg>
                            <span id="commentDialogSaveLabel">Save</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div><!-- .viewer-body -->

<?php if ($canReview): ?>
    <!-- ===== Finish Review decision modal ===== -->
    <div class="modal-backdrop" id="reviewDecisionBackdrop">
        <div class="modal-card panel-modal-card">
            <div class="modal-header">
                <span class="modal-header-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                        <use href="#review-icon" />
                    </svg>
                </span>
                <div>
                    <p class="modal-label">Finish Review</p>
                    <p class="modal-title"><?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button class="modal-close" onclick="closeReviewDecisionModal()" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#close-icon" />
                    </svg>
                </button>
            </div>
            <div class="panel-modal-body">
                <p class="panel-modal-intro" id="reviewDecisionSummary">Choose what to do with this protocol.</p>

                <div class="panel-modal-actions">
                    <button class="confirm-modal-btn confirm-modal-ok confirm-modal-ok-warn" type="button"
                        onclick="closeReviewDecisionModal(); openReturnModal();">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#back-icon" />
                        </svg>
                        Return for Revision
                    </button>
                    <button class="confirm-modal-btn confirm-modal-ok" type="button" id="btnCompleteReview"
                        onclick="completeReview(this)">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#check-icon" />
                        </svg>
                        Complete Review
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Return for Revision modal ===== -->
    <div class="modal-backdrop" id="returnRevisionBackdrop" data-static>
        <div class="modal-card panel-modal-card">
            <div class="modal-header">
                <span class="modal-header-icon modal-header-icon--warn" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                        <use href="#back-icon" />
                    </svg>
                </span>
                <div>
                    <p class="modal-label">Return for Revision</p>
                    <p class="modal-title"><?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button class="modal-close" onclick="closeReturnModal()" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#close-icon" />
                    </svg>
                </button>
            </div>
            <div class="panel-modal-body">
                <p class="panel-modal-intro">Tell the researcher what needs to change. They will see this feedback.</p>

                <label class="return-comment-label" for="returnComment">
                    Additional details <span class="return-comment-optional">(optional)</span>
                </label>
                <textarea id="returnComment" class="return-comment-textarea"
                    placeholder="Add any notes for the researcher..."
                    rows="4" maxlength="1000"></textarea>
                <p class="return-char-count"><span id="returnCharCount">0</span> / 1000</p>

                <div id="returnRevisionError" class="error-messages" hidden></div>

                <div class="panel-modal-actions">
                    <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="closeReturnModal(); openReviewDecisionModal();">Back</button>
                    <button class="confirm-modal-btn confirm-modal-ok confirm-modal-ok-warn" type="button" id="returnRevisionSubmitBtn"
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
    <div class="modal-backdrop" id="reuploadModalBackdrop" data-static>
        <div class="modal-card panel-modal-card resub-card" role="dialog" aria-modal="true" aria-labelledby="reuploadModalTitle">
            <div class="modal-header">
                <span class="modal-header-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                        <use href="#upload-icon" />
                    </svg>
                </span>
                <div>
                    <p class="modal-label">Re-submit Protocol</p>
                    <p class="modal-title" id="reuploadModalTitle"><?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button class="modal-close" onclick="closeReuploadModal()" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#close-icon" />
                    </svg>
                </button>
            </div>
            <div class="panel-modal-body resub-body">
                <p class="panel-modal-intro">Upload your revised protocol file below.</p>

                <div class="resub-summary" id="reuploadSummary" aria-live="polite"></div>

                <div class="resub-list" id="reuploadDocList"></div>

                <div class="resub-notes">
                    <div class="notice notice-info is-hidden" id="reuploadConvertedNotice">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#info-icon" />
                        </svg>
                        <span><strong>Converted to PDF:</strong> Please review the converted file before submitting, since conversion can shift the layout slightly.</span>
                    </div>

                    <div class="notice notice-info">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#info-icon" />
                        </svg>
                        <span><strong>Signatures required:</strong> Print, sign, and scan the revised protocol, then upload the signed copy.</span>
                    </div>
                </div>

                <div id="reuploadError" class="error-messages" role="alert" hidden></div>

                <div class="upload-progress-container" id="reuploadProgress"></div>

                <div class="panel-modal-actions resub-actions">
                    <button class="confirm-modal-btn confirm-modal-cancel" type="button" id="reuploadCancelBtn" onclick="closeReuploadModal()">Cancel</button>
                    <button class="confirm-modal-btn confirm-modal-ok" type="button" id="reuploadSubmitBtn"
                        onclick="submitReupload()" disabled>
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#upload-icon" />
                        </svg>
                        Re-submit
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($canConfirmPayment):
    $paymentModalTitle = $protocol['research_title'];
    include 'includes/payment-modal.php';
endif; ?>

<?php if ($isStaff && !empty($protocol['deletion_requested_at'])): ?>
    <!-- ===== Approve / reject deletion request modal ===== -->
    <div class="modal-backdrop" id="deletionReviewModalBackdrop" data-static>
        <div class="modal-card panel-modal-card">
            <div class="modal-header">
                <span class="modal-header-icon modal-header-icon--danger" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                        <use href="#trash-icon" />
                    </svg>
                </span>
                <div>
                    <p class="modal-label" id="deletionReviewLabel">Deletion Requested</p>
                    <p class="modal-title"><?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button class="modal-close" onclick="closeDeletionReviewModal()" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#close-icon" />
                    </svg>
                </button>
            </div>
            <div class="panel-modal-body">
                <p class="panel-modal-intro" id="deletionReviewRequestInfo">
                    Deletion requested by
                    <?= htmlspecialchars($roleLabels[$protocol['deletion_requested_by_role']] ?? ucfirst((string) $protocol['deletion_requested_by_role']), ENT_QUOTES, 'UTF-8') ?>
                    <?= htmlspecialchars($protocol['deletion_requested_by_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    for the reason of:
                    <span class="return-reason-bar-comment">"<?= htmlspecialchars($protocol['deletion_request_reason'] ?? '', ENT_QUOTES, 'UTF-8') ?>"</span>
                </p>

                <div id="deletionReviewChoiceActions" class="panel-modal-actions">
                    <button class="confirm-modal-btn confirm-modal-ok" type="button" onclick="selectDeletionAction('approve')">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#check-icon" />
                        </svg>
                        Approve
                    </button>
                    <button class="confirm-modal-btn confirm-modal-ok confirm-modal-ok-danger" type="button" onclick="selectDeletionAction('reject')">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#close-icon" />
                        </svg>
                        Reject
                    </button>
                </div>

                <div id="deletionReviewActionPanel" hidden>
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
                        <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="backToDeletionChoice()">Back</button>
                        <button class="confirm-modal-btn confirm-modal-ok" type="button" id="deletionReviewSubmitBtn" onclick="submitDeletionReview()">
                            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use id="deletionReviewSubmitIcon" href="#check-icon" />
                            </svg>
                            <span id="deletionReviewSubmitLabel">Confirm</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($protocol['deletion_rejection_reason'])): ?>
    <!-- ===== Deletion request rejected modal ===== -->
    <div class="modal-backdrop" id="deletionRejectedModalBackdrop" role="dialog" aria-modal="true" aria-labelledby="deletionRejectedTitle">
        <div class="modal-card panel-modal-card deletion-rejected-card">
            <div class="modal-header">
                <span class="modal-header-icon modal-header-icon--danger" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                        <use href="#alert-triangle-icon" />
                    </svg>
                </span>
                <div>
                    <p class="modal-label">Deletion request</p>
                    <p class="modal-title" id="deletionRejectedTitle">Request rejected</p>
                </div>
                <button class="modal-close" type="button" onclick="closeDeletionRejectedModal()" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#close-icon" />
                    </svg>
                </button>
            </div>
            <div class="panel-modal-body">
                <p class="panel-modal-intro">The request to delete this protocol was not approved, so the protocol stays as it is.</p>

                <div class="deletion-rejected-reason">
                    <span class="deletion-rejected-reason-label">Reason given</span>
                    <p><?= htmlspecialchars($protocol['deletion_rejection_reason'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>

                <div class="panel-modal-actions">
                    <button class="confirm-modal-btn confirm-modal-ok" type="button" id="deletionRejectedCloseBtn" onclick="closeDeletionRejectedModal()">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#check-icon" />
                        </svg>
                        Got it
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>


<?php include __DIR__ . '/includes/protocol-modals.php'; ?>
<?php include __DIR__ . '/includes/history-modal.php'; ?>
<?php if ($isPersonnel): ?>
    <?php include __DIR__ . '/includes/researcher-modal.php'; ?>
<?php endif; ?>
<?php if ($canResubmitCert):
    include __DIR__ . '/includes/cert-resubmit-modal.php';
endif; ?>

<script src="<?= asset_js('history-modal.js') ?>"></script>
<script src="<?= asset_js('protocol-menu.js') ?>"></script>
<script>
    const PDF_URL = <?= json_encode($fileUrl) ?>;
    const VERSION_ID = <?= $versionId              ?>;
    const PROTOCOL_ID = <?= $protocolId             ?>;
    const IS_PERSONNEL = <?= $isPersonnel    ? 'true' : 'false' ?>;
    const IS_STAFF = <?= $isStaff    ? 'true' : 'false' ?>;
    const IS_COMPLETED = <?= $isCompleted ? 'true' : 'false' ?>;
    const STATUS_KEY = <?= json_encode($statusKey) ?>;
    const IS_LATEST_VERSION = <?= $isLatestVersion ? 'true' : 'false' ?>;
    const IS_SIGNED_SCAN = <?= $isSignedScan ? 'true' : 'false' ?>;
    const IS_READ_ONLY_FILE = <?= $isReadOnlyFile ? 'true' : 'false' ?>;
    const SCAN_IS_IMAGE = <?= $scanIsImage ? 'true' : 'false' ?>;
    const CAN_REVIEW = <?= $canReview ? 'true' : 'false' ?>;
    const CSRF_TOKEN = <?= json_encode($csrf)      ?>;
    const ANNOT_API = <?= json_encode($annotApi)  ?>;
    const STATUS_API = <?= json_encode($statusApi) ?>;
    const ROOT_URL = <?= json_encode(ROOT) ?>;
    const CAN_RESUBMIT = <?= $canResubmit ? 'true' : 'false' ?>;
    const RESUBMIT_DOCS = <?= json_encode($resubmitDocs) ?>;
    const PAYMENT_RESUBMIT = <?= $offerPaymentResubmit ? json_encode([
                                    'note'  => 'This payment was rejected' . (!empty($paymentRejection['comment']) ? ' for the reason: ' . $paymentRejection['comment'] : ''),
                                    'label' => 'Resubmit Payment',
                                ]) : 'null' ?>;
    const BACK_URL = <?= json_encode($backUrl) ?>;
    const SIGNED_SCAN_VIEWER_URL = <?= json_encode($latestSignedScanViewerUrl ?: null) ?>;
    const MUST_PAY_IN_PERSON = <?= $mustPayInPerson ? 'true' : 'false' ?>;
    const CERT_STATUS = <?= json_encode($researcherCertStatus) ?>;
    const RESEARCHER_ID = <?= (int) $protocol['user_id'] ?>;
    const RESEARCHER_NAME = <?= json_encode($submitterName) ?>;
    const IS_ORIGINAL_SUBMISSION = <?= $isOriginalSubmission ? 'true' : 'false' ?>;
</script>
<script src="<?= asset_js('viewer.js') ?>"></script>
<?php if ($canConfirmPayment): ?>
    <script src="<?= asset_js('payment-modal.js') ?>"></script>
<?php endif; ?>
<?php if ($isStaff && !empty($protocol['deletion_requested_at'])): ?>
    <script src="<?= asset_js('viewer-deletion-review.js') ?>"></script>
<?php endif; ?>
<?php if (!empty($protocol['deletion_rejection_reason'])): ?>
    <script src="<?= asset_js('viewer-deletion-rejected.js') ?>"></script>
<?php endif; ?>
<?php if ($canReview): ?>
    <script src="<?= asset_js('viewer-review.js') ?>"></script>
<?php endif; ?>
<?php if ($canResubmit): ?>
    <script src="<?= asset_js('viewer-resubmit.js') ?>"></script>
<?php endif; ?>
<?php if ($isPersonnel): ?>
    <script src="<?= asset_js('viewer-cert.js') ?>"></script>
<?php endif; ?>
<?php if ($canResubmitCert): ?>
    <script src="<?= asset_js('cert-resubmit-modal.js') ?>"></script>
<?php endif; ?>
<script src="<?= asset_js('viewer-boot.js') ?>"></script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>