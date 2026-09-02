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

$title = htmlspecialchars($protocol['research_title'] ?? 'Protocol', ENT_QUOTES, 'UTF-8');

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

    <?php if ($isStaff): ?>
        <div class="notice-bar">
            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#account-icon" />
            </svg>
            <span>
                Submitted by <strong><?= htmlspecialchars($submitterName, ENT_QUOTES, 'UTF-8') ?></strong>
                <?= $isPi ? '(Principal Investigator)' : '(submitted with an authorization letter from the Principal Investigator)' ?>
            </span>
            <div class="notice-btns">
                <?php if ($latestCertFileUrl): ?>
                    <button class="tool-btn"
                        data-file-url="<?= htmlspecialchars($latestCertFileUrl, ENT_QUOTES, 'UTF-8') ?>"
                        onclick="openFilePopup(this.dataset.fileUrl, 'IACUC Training Certificate')">
                        View IACUC Training Certificate
                    </button>
                <?php else: ?>
                    <button class="tool-btn"
                        data-file-url="<?= htmlspecialchars($certUrl, ENT_QUOTES, 'UTF-8') ?>"
                        onclick="openFilePopup(this.dataset.fileUrl, 'IACUC Training Certificate')">
                        View IACUC Training Certificate
                    </button>
                <?php endif; ?>
                <?php if (! $isPi && $latestAuthFileUrl): ?>
                    <button class="tool-btn"
                        data-file-url="<?= htmlspecialchars($latestAuthFileUrl, ENT_QUOTES, 'UTF-8') ?>"
                        onclick="openFilePopup(this.dataset.fileUrl, 'Authorization Letter')">
                        View Authorization Letter
                    </button>
                <?php endif; ?>
            </div>
        </div>
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

            <a class="tool-btn" href="<?= $fileUrl ?>" download="<?= htmlspecialchars($version['original_name'] ?? 'protocol', ENT_QUOTES, 'UTF-8') ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#download-icon" />
                </svg>
                Download
            </a>

            <?php if ($canReview): ?>
                <button class="tool-btn tool-btn--warn" id="btnNeedsRevision"
                    onclick="openReturnModal()">
                    Return for Revision
                </button>
                <button class="tool-btn tool-btn--success" id="btnApprove"
                    onclick="confirmAction('Finish your review? This will send the protocol to the IACUC admin for endorsement.', { okText: 'Finish Review', cancelText: 'Cancel' }).then(ok => ok && updateStatus('Reviewed'))">
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
            <span class="toolbar-hint">Click and drag on the document to add a comment.</span>
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

    <!-- ===== Comment dialog (reviewer only, latest version, while under review) ===== -->
    <?php if ($canReview): ?>
        <div class="modal-backdrop" id="commentDialog">
            <div class="modal-card">
                <h2>Add Comment</h2>
                <textarea id="commentText" rows="4" placeholder="Type your comment…"></textarea>
                <div class="modal-actions">
                    <button class="tool-btn" onclick="cancelComment()">Cancel</button>
                    <button class="tool-btn active" onclick="saveComment()">Save</button>
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

    // ===== Version switcher dropdown =====
    const versionTrigger = document.getElementById('versionSwitcherTrigger');
    const versionMenu = document.getElementById('versionDropdownMenu');
    const versionSwitcher = document.getElementById('versionSwitcher');

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

                const wrapper = document.createElement('div');
                wrapper.className = 'page-wrapper';
                wrapper.dataset.page = p;
                wrapper.style.width = vp.width + 'px';

                const canvas = document.createElement('canvas');
                canvas.className = 'pdf-canvas';
                canvas.width = vp.width;
                canvas.height = vp.height;
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
                    viewport: vp
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
    function attachDrawListeners(overlay, pageNum, canvas) {
        overlay.addEventListener('mousedown', e => {
            e.preventDefault();
            const rect = overlay.getBoundingClientRect();
            const ghost = document.createElement('div');
            ghost.className = 'annot-ghost';
            overlay.appendChild(ghost);
            dragState = {
                pageNum,
                startX: e.clientX - rect.left,
                startY: e.clientY - rect.top,
                canvas,
                ghost
            };
        });

        overlay.addEventListener('mousemove', e => {
            if (!dragState || dragState.pageNum !== pageNum) return;
            const rect = overlay.getBoundingClientRect();
            const curX = e.clientX - rect.left;
            const curY = e.clientY - rect.top;
            const g = dragState.ghost;
            g.style.left = Math.min(dragState.startX, curX) + 'px';
            g.style.top = Math.min(dragState.startY, curY) + 'px';
            g.style.width = Math.abs(curX - dragState.startX) + 'px';
            g.style.height = Math.abs(curY - dragState.startY) + 'px';
        });

        overlay.addEventListener('mouseup', e => {
            if (!dragState || dragState.pageNum !== pageNum) return;
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
    }

    // ===== Comment dialog =====
    function openCommentDialog() {
        document.getElementById('commentText').value = '';
        document.getElementById('commentDialog').classList.add('open');
        document.getElementById('commentText').focus();
    }

    function cancelComment() {
        pendingBox = null;
        document.getElementById('commentDialog').classList.remove('open');
    }
    async function saveComment() {
        const text = document.getElementById('commentText').value.trim();
        if (!text || !pendingBox) return;
        document.getElementById('commentDialog').classList.remove('open');

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
            box.addEventListener('click', () => highlightSidebarItem(ann.id));
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
                <button class="annot-delete" title="Delete comment"
                    onclick="confirmAction('Delete this comment? This cannot be undone.', { okText: 'Delete', danger: true }).then(ok => ok && deleteAnnotation(${ann.id}))">✕</button>` : ''}
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
            canvas.width = vp.width;
            canvas.height = vp.height;
            filePopupPdfPages.appendChild(canvas);

            await page.render({
                canvasContext: canvas.getContext('2d'),
                viewport: vp
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