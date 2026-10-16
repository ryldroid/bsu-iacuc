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
/** @var array|null $returnReason */
/** @var array|null $latestCertVersion */
/** @var bool   $hasCertOnFile */
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
    'under review'   => ['color' => '#0072B2', 'icon' => 'clock-icon'],
    'needs revision' => ['color' => '#D55E00', 'icon' => 'alert-triangle-icon'],
    'reviewed'       => ['color' => '#CC79A7', 'icon' => 'checkbox-icon'],
    'endorsed'       => ['color' => '#E69F00', 'icon' => 'shield-check-icon'],
    'approved'       => ['color' => '#009E73', 'icon' => 'check-circle-icon'],
];
$statusKey       = strtolower($protocol['status'] ?? '');
$statusLabel     = $statusLabels[$statusKey] ?? ($protocol['status'] ?? '');
$isCompleted     = in_array($statusKey, ['approved'], true);
$isLatestVersion = $isLatestVersion ?? true;
$isSignedScan    = $isSignedScan ?? false;
$isAmendment     = $isAmendment ?? false;
$isReadOnlyFile  = $isSignedScan || $isAmendment;
$statusBadgeMeta  = $isReadOnlyFile
    ? ['color' => '#64748b', 'icon' => 'review-icon']
    : ($isLatestVersion ? ($statusMeta[$statusKey] ?? ['color' => '#64748b', 'icon' => 'info-icon']) : ['color' => '#64748b', 'icon' => 'history-icon']);
$statusBadgeLabel = match (true) {
    $isSignedScan    => 'Signed scan: read only',
    $isAmendment     => 'Amendment: read only',
    $isLatestVersion => $statusLabel,
    default          => 'Viewing history: read only',
};

$canReview = $isPersonnel && !$isStaff && $statusKey === 'under review' && $isLatestVersion;

$canResubmit = !$isPersonnel && $isLatestVersion && $statusKey === 'needs revision';

$paymentStatus     = $protocol['payment_status'] ?? 'unpaid';
$paymentMethod     = $protocol['payment_method'] ?? null;
$canConfirmPayment = !$isPersonnel && $isLatestVersion && $statusKey === 'reviewed'
    && in_array($paymentStatus, ['unpaid', 'rejected'], true);
$offerPaymentResubmit = $canConfirmPayment && $paymentStatus === 'rejected';
$isBsuResearcher   = stripos(trim($protocol['submitter_school'] ?? ''), 'Benguet State University') !== false;

$rrWrongCert          = !empty($returnReason['wrong_cert']);

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
$certRequired      = $hasCertOnFile && $rrWrongCert;
$certUrl           = ROOT . '/apply/cert/' . (int) $protocol['user_id'];
$latestCertFileUrl = ! empty($latestCertVersion['id']) ? ROOT . '/apply/file/' . (int) $latestCertVersion['id'] : null;
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
    $viewerNotes[] = ['warning', 'info-icon', 'Note from CCARD', $reviewerNote['comment']];
}
$latestClearanceFileUrl    = ! empty($latestClearanceVersion['id']) ? ROOT . '/apply/file/' . (int) $latestClearanceVersion['id'] : null;

$flaggedDocAccept = '.pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png';
$resubmitDocs      = [
    ['key' => 'protocol', 'title' => 'Revised protocol file', 'subtitle' => 'PDF, DOC, or DOCX · max 10 MB', 'accept' => '.pdf,.doc,.docx', 'required' => true],
];
if ($certRequired) {
    $resubmitDocs[] = ['key' => 'cert', 'title' => 'Training certificate', 'subtitle' => 'Flagged as wrong/incorrect by the reviewer · PDF, JPG, or PNG · max 10 MB', 'accept' => $flaggedDocAccept, 'required' => true];
}
$resubmitIntro = $certRequired
    ? 'Upload your revised protocol file, plus your IACUC training certificate, as flagged by the reviewer.'
    : 'Upload your revised protocol file below.';

include 'includes/header.php';
?>

<?php include_once 'includes/pdfjs.php'; ?>
<link rel="stylesheet" href="<?= asset_css('viewer.css') ?>">
<link rel="stylesheet" href="<?= asset_css('protocol-menu.css') ?>">
<link rel="stylesheet" href="<?= asset_css('history-modal.css') ?>">
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
                <span class="status-badge-icon" style="background:<?= $statusBadgeMeta['color'] ?>">
                    <svg width="12" height="12" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#<?= $statusBadgeMeta['icon'] ?>" />
                    </svg>
                </span>
                <?= htmlspecialchars($statusBadgeLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>

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
                'certUrl'        => $latestCertFileUrl ?? $certUrl,
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


            <?php if ($canReview || $canResubmit || $canConfirmPayment): ?>
                <div class="viewer-review-actions">
                    <?php if ($canReview): ?>
                        <button class="tool-btn tool-btn--success" id="btnFinishReview"
                            onclick="openReviewDecisionModal()">
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

    <?php if ($canReview): ?>
        <!-- ===== Annotation hint (personnel only, while under review, latest version only) ===== -->
        <div class="annot-toolbar">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a2 2 0 01-1.414.586H9v-1.414A2 2 0 019 13z" />
            </svg>
            <span class="toolbar-hint" id="toolbarHint">Click and drag on the document to add a comment.</span>
        </div>
    <?php endif; ?>

    <!-- ===== Layout: PDF + sidebar ===== -->
    <div class="viewer-layout">

        <div class="viewer-loading" id="viewerLoading" role="status" aria-live="polite">
            <span class="viewer-loading-spinner" aria-hidden="true"></span>
            Loading protocol...
        </div>

        <div class="viewer-main">
            <?php if ($viewerNotes): ?>
                <div class="viewer-notes">
                    <?php foreach ($viewerNotes as [$noteVariant, $noteIcon, $noteLabel, $noteText]): ?>
                        <?php include 'includes/viewer-note.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="pdf-column" id="pdfColumn"></div>
        </div>

        <?php if (!$isReadOnlyFile): ?>
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

    <!-- ===== Comment dialog (personnel only, latest version, while under review) ===== -->
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
    <!-- ===== Finish Review decision modal ===== -->
    <div class="modal-backdrop" id="reviewDecisionBackdrop">
        <div class="modal-card panel-modal-card">
            <div class="modal-header">
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
                <p class="panel-modal-intro">Choose what to do with this protocol.</p>

                <div class="panel-modal-actions">
                    <button class="tool-btn tool-btn--warn" type="button"
                        onclick="closeReviewDecisionModal(); openReturnModal();">
                        Return for Revision
                    </button>
                    <button class="tool-btn tool-btn--success" type="button" id="btnCompleteReview"
                        onclick="completeReview(this)">
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
                <p class="panel-modal-intro">Optionally select the issue(s) with this protocol. The researcher will see this feedback.</p>

                <fieldset class="return-reasons-fieldset">
                    <legend class="return-reasons-legend">Issues found <span class="return-comment-optional">(optional)</span></legend>

                    <label class="return-reason-option">
                        <input type="checkbox" name="return_reason" value="wrong_cert" id="returnReasonWrongCert">
                        <span class="return-reason-label">Wrong / invalid IACUC training certificate</span>
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
                    <button class="tool-btn" type="button" onclick="closeReturnModal(); openReviewDecisionModal();">Back</button>
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
    <div class="modal-backdrop" id="reuploadModalBackdrop" data-static>
        <div class="modal-card panel-modal-card">
            <div class="modal-header">
                <div>
                    <p class="modal-label">Re-submit Protocol</p>
                    <p class="modal-title"><?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button class="modal-close" onclick="closeReuploadModal()" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#close-icon" />
                    </svg>
                </button>
            </div>
            <div class="panel-modal-body">
                <p class="panel-modal-intro"><?= htmlspecialchars($resubmitIntro, ENT_QUOTES, 'UTF-8') ?></p>

                <div class="doc-list" id="reuploadDocList"></div>

                <div class="notice notice-info is-hidden" id="reuploadConvertedNotice">
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#info-icon" />
                    </svg>
                    <span><strong>Converted to PDF:</strong> Please review the converted file before submitting, since conversion can shift the layout slightly. <a href="#" class="underlined" onclick="previewStagedFile(event)">Preview PDF</a></span>
                </div>

                <div class="notice notice-info">
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#info-icon" />
                    </svg>
                    <span><strong>Signatures required:</strong> Print, sign, and scan the revised protocol, then upload the signed copy.</span>
                </div>

                <div id="reuploadError" class="error-messages" hidden></div>

                <div class="upload-progress-container" id="reuploadProgress"></div>

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

<?php if ($canConfirmPayment): ?>
    <!-- ===== Verify Payment modal ===== -->
    <div class="modal-backdrop" id="paymentModalBackdrop" data-static>
        <div class="modal-card">
            <h2>Verify Payment</h2>

            <p class="modal-notice">The BAI animal research clearance requires a Php 100.00 fee.</p>

            <div id="paymentModalError" class="alert error-messages" hidden></div>

            <?php if (!$isBsuResearcher): ?>
                <div class="consent-list">
                    <label class="consent-item">
                        <input type="radio" class="consent-checkbox" name="payment_method" value="in_person"
                            checked onchange="handlePaymentMethodChange()">
                        <span>Pay in person at CCARD</span>
                    </label>
                    <label class="consent-item">
                        <input type="radio" class="consent-checkbox" name="payment_method" value="online"
                            onchange="handlePaymentMethodChange()">
                        <span>Pay online</span>
                    </label>
                </div>
            <?php endif; ?>

            <div id="paymentInPersonPanel">
                <p class="modal-notice">Settle the fee at the BSU-CCARD office, then tick the box below to confirm payment.</p>
                <div class="consent-list">
                    <label class="consent-item">
                        <input type="checkbox" class="consent-checkbox" id="confirm_in_person_paid">
                        <span>I confirm that I have paid the Php 100.00 fee in person at CCARD.</span>
                    </label>
                </div>
            </div>

            <div id="paymentOnlinePanel" hidden>
                <p class="modal-notice">
                    <a href="<?= ROOT ?>/contact#director-contact" class="underlined" target="_blank" rel="noopener noreferrer">Contact the CCARD Director</a>
                    to arrange online payment. Once paid, upload a photo of your receipt (or a photo of you handing over the payment) below.
                </p>

                <div class="modal-file-row">
                    <div class="modal-file-info">
                        <div class="modal-file-title">Proof of payment <span class="required-asterisk">*</span></div>
                        <div class="modal-file-subtitle" id="paymentFileSubtitle">PDF or image &middot; max 10 MB</div>
                    </div>
                    <label class="modal-file-picker">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#upload-icon" />
                        </svg>
                        <span id="paymentFilePickerLabel">Upload</span>
                        <input type="file" id="payment_proof_file" name="payment_proof_file"
                            accept=".pdf,application/pdf,.jpg,.jpeg,.png,image/jpeg,image/png"
                            onchange="handlePaymentFileChange(this)">
                    </label>
                </div>

                <div class="upload-progress-container" id="paymentProofProgress"></div>
            </div>

            <div class="modal-actions">
                <button class="button" type="button" onclick="closePaymentModal()">Cancel</button>
                <button class="button btn-apply" type="button" id="paymentModalSubmitBtn"
                    onclick="submitPaymentProof()">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#upload-icon" />
                    </svg>
                    <span id="paymentModalSubmitLabel">Verify Payment</span>
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($isStaff && !empty($protocol['deletion_requested_at'])): ?>
    <!-- ===== Approve / reject deletion request modal ===== -->
    <div class="modal-backdrop" id="deletionReviewModalBackdrop" data-static>
        <div class="modal-card panel-modal-card">
            <div class="modal-header">
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
                    <button class="tool-btn tool-btn--success" type="button" onclick="selectDeletionAction('approve')">
                        <svg width="13" height="13" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#check-icon" />
                        </svg>
                        Approve
                    </button>
                    <button class="tool-btn tool-btn--danger" type="button" onclick="selectDeletionAction('reject')">
                        <svg width="13" height="13" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
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
                        <button class="tool-btn" type="button" onclick="backToDeletionChoice()">Back</button>
                        <button class="tool-btn" type="button" id="deletionReviewSubmitBtn" onclick="submitDeletionReview()">
                            Confirm
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($protocol['deletion_rejection_reason'])): ?>
    <!-- ===== Deletion request rejected modal ===== -->
    <div class="modal-backdrop" id="deletionRejectedModalBackdrop">
        <div class="modal-card panel-modal-card">
            <div class="modal-header">
                <div>
                    <p class="modal-title">Deletion Request Rejected</p>
                </div>
            </div>
            <div class="panel-modal-body">
                <p class="panel-modal-intro">Reason: <?= htmlspecialchars($protocol['deletion_rejection_reason'], ENT_QUOTES, 'UTF-8') ?></p>
                <div class="panel-modal-actions">
                    <button class="tool-btn" type="button" onclick="closeDeletionRejectedModal()">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>


<?php include __DIR__ . '/includes/protocol-modals.php'; ?>
<?php include __DIR__ . '/includes/history-modal.php'; ?>

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
    const IS_BSU_RESEARCHER = <?= $isBsuResearcher ? 'true' : 'false' ?>;
</script>
<script src="<?= asset_js('viewer.js') ?>"></script>
<?php if ($canConfirmPayment): ?>
    <script src="<?= asset_js('viewer-payment.js') ?>"></script>
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
<script src="<?= asset_js('viewer-boot.js') ?>"></script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>