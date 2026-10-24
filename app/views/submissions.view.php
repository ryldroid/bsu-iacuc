<?php

/** @var array $protocols */
/** @var array $statuses  */
/** @var array|null $cert */
/** @var bool  $mustPayInPerson */
/** @var string $csrf */
/** @var array $actor */

$title = 'My Protocols';

include 'includes/header.php';
include 'includes/scroll-top.php';

$count = count($protocols);

$countsByStatusSlug = [];
foreach ($protocols as $p) {
    $slug = strtolower(str_replace(' ', '-', $p['status']));
    $countsByStatusSlug[$slug] = ($countsByStatusSlug[$slug] ?? 0) + 1;
}
$totalProtocolCount = count($protocols);

$statusMeta = [
    'under-review' => [
        'label' => 'Under Review',
        'color' => '#0072B2',
        'icon'  => 'clock-icon',
        'desc'  => 'No action needed. Your protocol is being reviewed by CCARD, and you will be notified for every feedback given.',
    ],
    'needs-revision' => [
        'label' => 'Needs Revision',
        'color' => '#D55E00',
        'icon'  => 'alert-triangle-icon',
        'desc'  => 'CCARD found an issue and sent your protocol back. Revise it following the comments and re-submit your file.',
    ],
    'reviewed' => [
        'label' => 'Reviewed',
        'color' => '#CC79A7',
        'icon'  => 'checkbox-icon',
        'desc'  => 'Your protocol passed CCARD review. Press **Pay Clearance Fee** on the protocol card to see your payment options for the Php 100.00 animal research clearance fee. Researchers from BSU La Trinidad Campus and the BSU Secondary Laboratory School must pay in person, while everyone else may pay in person or online by contacting the CCARD director. Once CCARD verifies your payment, kindly wait for your protocol to be endorsed to the Department of Agriculture-Cordillera Administrative Region Field Unit (DA-CARFU) Regulatory Division.',
    ],
    'endorsed' => [
        'label' => 'Endorsed',
        'color' => '#E69F00',
        'icon'  => 'shield-check-icon',
        'desc'  => 'No action needed. Your protocol has been endorsed to DA-CARFU. Please wait as the Bureau of Animal Industry (BAI) Central Office processes your animal research clearance.',
    ],
    'approved' => [
        'label' => 'Approved',
        'color' => '#009E73',
        'icon'  => 'check-circle-icon',
        'desc'  => 'Congratulations, your protocol has been approved! You may now download the soft copy of your animal research clearance. Please also visit the CCARD office to pick up your embossed copy. Note that your account will be automatically deactivated after your clearance expires and you have no pending protocols. You may reactivate at any time by logging in to this portal.',
    ],
];

// ===== Status icon helper =====
function statusIconSvg(string $iconId, int $size = 14): string
{
    return '<svg class="status-icon-svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<use href="#' . htmlspecialchars($iconId, ENT_QUOTES, 'UTF-8') . '" />'
        . '</svg>';
}
?>

<link rel="stylesheet" href="<?= asset_css('protocol-list.css') ?>">
<link rel="stylesheet" href="<?= asset_css('history-modal.css') ?>">
<link rel="stylesheet" href="<?= asset_css('pagination.css') ?>">
<link rel="stylesheet" href="<?= asset_css('submissions.css') ?>">
<link rel="stylesheet" href="<?= asset_css('protocol-menu.css') ?>">
<link rel="stylesheet" href="<?= asset_css('application.css') ?>">
<link rel="stylesheet" href="<?= asset_css('status-underline.css') ?>">

<!-- ===== Scripts ===== -->
<script src="<?= asset_js('dashboard-updates.js') ?>" defer></script>
<script src="<?= asset_js('status-underline.js') ?>" defer></script>

<div class="body">
    <?php include 'includes/navigation.php'; ?>

    <!-- ===== My protocols page ===== -->
    <main class="main-content" id="main-content" tabindex="-1">
        <?php include 'includes/update-banner.php'; ?>

        <div class="submission-header">
            <h1>My Protocols</h1>

            <?php $pageGuideId = 'protocols';
            include 'includes/page-guide.php'; ?>

            <div id="apply-actions-sub">
                <a href="<?= ROOT ?>/apply" class="btn-apply button">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#add-icon" />
                    </svg>
                    <span class="label-full">New Application</span>
                    <span class="label-short">New</span>
                </a>
            </div>
        </div>

        <?php
        $toastSuccess = isset($_GET['submitted'])
            ? 'Your protocol has been submitted successfully. We will review it shortly.'
            : ($_SESSION['flash_success'] ?? '');
        include __DIR__ . '/includes/flash.php';
        ?>

        <?php if (!empty($cert) && $cert['cert_status'] !== 'verified'): ?>
            <div class="notice notice-warning">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#alert-triangle-icon" />
                </svg>
                <?php if ($cert['cert_status'] === 'rejected'): ?>
                    <span>Your training certificate was rejected<?= !empty($cert['cert_note']) ? ': ' . htmlspecialchars($cert['cert_note'], ENT_QUOTES, 'UTF-8') : '' ?>. Open My Account to upload a new one.</span>
                <?php else: ?>
                    <span>Your training certificate is waiting for verification. Until it is verified, you cannot submit new protocols and your protocols cannot be marked as Reviewed.</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Status filter bar -->
        <div class="dashboard-filter-row">
            <div class="filter-wrapper">
                <div class="status-filters">
                    <button class="status-card active" data-status="all" data-label="All">
                        <p>All <span class="status-count"><?= $totalProtocolCount ?></span></p>
                    </button>
                    <?php foreach ($statuses as $status):
                        $statusSlug  = strtolower(str_replace(' ', '-', $status));
                        $statusCount = $countsByStatusSlug[$statusSlug] ?? 0;
                    ?>
                        <button class="status-card"
                            data-status="<?= htmlspecialchars($statusSlug, ENT_QUOTES, 'UTF-8') ?>"
                            data-label="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                            <p><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?> <span class="status-count"><?= $statusCount ?></span></p>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="dashboard-sort-group dashboard-field-group">
                <p class="sort-filter-label">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#sort-icon" />
                    </svg>
                    Sort by:
                </p>

                <div class="sort-wrapper">
                    <select id="submissionsSortSelect" class="dashboard-sort-select dashboard-select-trigger" data-sort-target=".protocols-list" aria-label="Sort protocols">
                        <option value="newest">Newest Submitted</option>
                        <option value="oldest">Oldest Submitted</option>
                        <option value="title_asc">Title (A–Z)</option>
                        <option value="title_desc">Title (Z–A)</option>
                    </select>

                    <button type="button" class="mobile-sort-trigger dashboard-select-trigger mobile-dropdown-trigger" aria-haspopup="true" aria-expanded="false">
                        <span id="mobileSortLabel">Newest Submitted</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#chev-down-icon" />
                        </svg>
                    </button>

                    <div class="dropdown-panel" id="mobileSortOptions">
                        <button class="status-card active" data-sort="newest">Newest Submitted</button>
                        <button class="status-card" data-sort="oldest">Oldest Submitted</button>
                        <button class="status-card" data-sort="title_asc">Title (A–Z)</button>
                        <button class="status-card" data-sort="title_desc">Title (Z–A)</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Status legend ===== -->
        <div class="status-legend-bar">
            <span class="legend-title">Current Status</span>

            <div class="legend-items">
                <?php foreach ($statusMeta as $meta): ?>
                    <span class="legend-item">
                        <span class="legend-icon" style="background:<?= $meta['color'] ?>">
                            <?= statusIconSvg($meta['icon'], 13) ?>
                        </span>
                        <?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endforeach; ?>
            </div>

            <div class="legend-info-wrapper" id="legendInfoWrapper">
                <button type="button" class="legend-info-btn" id="legendInfoBtn"
                    aria-expanded="false" aria-controls="legendInfoPanel"
                    aria-label="What do the statuses mean? What should I do?">
                    <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#question-info-icon" />
                    </svg>
                </button>

                <div class="legend-info-panel" id="legendInfoPanel" role="dialog" aria-label="What the statuses mean">
                    <?php foreach ($statusMeta as $meta): ?>
                        <div class="legend-info-row">
                            <span class="legend-icon" style="background:<?= $meta['color'] ?>">
                                <?= statusIconSvg($meta['icon'], 11) ?>
                            </span>
                            <div>
                                <p class="legend-info-title" style="color:<?= $meta['color'] ?>">
                                    <?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <p class="legend-info-desc">
                                    <?= Notifier::toHtml($meta['desc']) ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ===== Status guide (shows description for the active filter) ===== -->
        <div class="status-guide" id="statusGuide"></div>

        <?php if (empty($protocols)): ?>
            <div class="empty-state">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#file-x-icon" />
                    </svg>
                    No protocols yet
                </h3>
                <p>Submit your first IACUC protocol to get started.</p>
            </div>

        <?php else: ?>
            <div class="protocols-list">
                <?php foreach ($protocols as $protocol):
                    $date          = date(DATE_FORMAT, strtotime($protocol['submitted_at']));
                    $statusKey     = strtolower(str_replace(' ', '-', $protocol['status']));
                    $statusLabel   = htmlspecialchars($protocol['status'], ENT_QUOTES, 'UTF-8');
                    $needsRevision  = strtolower($protocol['status']) === 'needs revision';
                    $returnIssues   = [];
                    if ($needsRevision) {
                        if (!empty($protocol['rr_other_reason'])) $returnIssues[] = 'Other';
                    }
                    $isApproved    = strtolower($protocol['status']) === 'approved';
                    $isReviewedStatus = strtolower($protocol['status']) === 'reviewed';
                    $paymentStatus = $protocol['payment_status'] ?? 'unpaid';
                    $canConfirmPayment = $isReviewedStatus && in_array($paymentStatus, ['unpaid', 'rejected'], true);
                    $versionNum    = submission_round_label((int) ($protocol['latest_version'] ?? 1));
                    $protocolIdInt = (int) $protocol['protocol_id'];
                    $submittedIso  = date('c', strtotime($protocol['submitted_at']));
                ?>
                    <div class="protocol" id="protocol-<?= $protocolIdInt ?>" data-status="<?= $statusKey ?>"
                        data-submitted="<?= htmlspecialchars($submittedIso, ENT_QUOTES, 'UTF-8') ?>"
                        data-title="<?= htmlspecialchars(strtolower($protocol['research_title']), ENT_QUOTES, 'UTF-8') ?>"
                        data-signed-scan-url="<?= !empty($protocol['latest_signed_scan_version_id']) ? ROOT . '/apply/file/' . (int) $protocol['latest_signed_scan_version_id'] : '' ?>">

                        <span class="protocol-status-icon" style="background:<?= $statusMeta[$statusKey]['color'] ?? 'var(--muted-text)' ?>">
                            <?= statusIconSvg($statusMeta[$statusKey]['icon'] ?? 'check-circle-icon', 19) ?>
                        </span>

                        <div class="protocol-body">
                            <div class="protocol-meta">
                                <p class="research-title">
                                    <?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($statusKey === 'reviewed'): ?>
                                        <span class="payment-badge payment-badge--<?= htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(ProtocolModel::PAYMENT_LABELS[$paymentStatus] ?? 'Unpaid', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                                <p class="protocol-meta-line" title="Submission round and submission date">
                                    <?= $versionNum ?> &middot; <?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>
                                </p>

                                <?php if ($paymentStatus === 'rejected' && !empty($protocol['payment_rejection_comment'])): ?>
                                    <div class="return-reason-inline">
                                        <p class="return-reason-by">Your payment was rejected:</p>
                                        <p class="return-reason-comment"><?= htmlspecialchars($protocol['payment_rejection_comment'], ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($isApproved): ?>
                                    <div class="clearance-reminder">
                                        <?php if (!empty($protocol['hard_copy_released_at'])): ?>
                                            <strong>Embossed hard copy picked up</strong> on <?= date(DATE_FORMAT, strtotime($protocol['hard_copy_released_at'])) ?>.
                                        <?php else: ?>
                                            <strong>Your embossed hard copy is ready for pickup.</strong> The downloadable clearance is for temporary reference only. Please visit the BSU-CCARD Office to claim your embossed hard copy during office hours, Monday to Friday, 8:00 AM to 5:00 PM. For the office location, please visit our <a href="<?= ROOT ?>/contact" class="underlined">Contact Us</a> page.
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($needsRevision && (!empty($returnIssues) || !empty($protocol['rr_comment']))): ?>
                                    <div class="return-reason-inline">
                                        <p class="return-reason-by">
                                            Note from CCARD:
                                        </p>
                                        <?php if (!empty($returnIssues)): ?>
                                            <ul class="return-reason-issues">
                                                <?php foreach ($returnIssues as $issue): ?>
                                                    <li><?= htmlspecialchars($issue, ENT_QUOTES, 'UTF-8') ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        <?php if (!empty($protocol['rr_comment'])): ?>
                                            <p class="return-reason-comment"><?= htmlspecialchars($protocol['rr_comment'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Actions -->
                            <div class="actions">
                                <?php if ($needsRevision): ?>
                                    <a class="button button--primary" href="<?= ROOT ?>/apply/viewer/<?= $protocolIdInt ?>"
                                        onclick="event.preventDefault(); openProtocol(<?= $protocolIdInt ?>)">
                                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <use href="#upload-icon" />
                                        </svg>
                                        Review Comments &amp; Re-submit
                                    </a>
                                <?php endif; ?>

                                <?php if ($isApproved):
                                    $clearanceExt = strtolower(pathinfo($protocol['latest_clearance_original_name'] ?? '', PATHINFO_EXTENSION));
                                    $clearanceIsImage = in_array($clearanceExt, ['jpg', 'jpeg', 'png'], true);
                                    $clearanceClaimedOn = !empty($protocol['clearance_claimed_at']) ? date(DATE_FORMAT, strtotime($protocol['clearance_claimed_at'])) : '';
                                ?>
                                    <a class="download-clearance-btn button button--primary<?= $clearanceClaimedOn ? ' is-claimed' : '' ?>"
                                        href="<?= ROOT ?>/apply/clearance/<?= $protocolIdInt ?>?download=1"
                                        data-view-href="<?= ROOT ?>/apply/clearance/<?= $protocolIdInt ?>"
                                        data-is-image="<?= $clearanceIsImage ? '1' : '0' ?>"
                                        rel="noopener">
                                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <use href="#download-icon" />
                                        </svg>
                                        <span class="claim-label">
                                            <span class="label-claim">Download Clearance</span>
                                            <span class="label-claimed">Clearance downloaded!</span>
                                            <span class="label-claimed-on">Downloaded <span class="claimed-date"><?= htmlspecialchars($clearanceClaimedOn, ENT_QUOTES) ?></span></span>
                                        </span>
                                    </a>
                                <?php elseif ($canConfirmPayment): ?>
                                    <button class="button button--primary"
                                        data-protocol-id="<?= $protocolIdInt ?>"
                                        data-rejection-reason="<?= htmlspecialchars($paymentStatus === 'rejected' ? ($protocol['payment_rejection_comment'] ?? '') : '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-research-title="<?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="<?= $paymentStatus === 'rejected' ? 'openPaymentRejectedModal' : 'openPaymentModal' ?>(+this.dataset.protocolId)">
                                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <use href="#upload-icon" />
                                        </svg>
                                        <?= $paymentStatus === 'rejected' ? 'View rejection and resubmit' : 'Pay Clearance Fee' ?>
                                    </button>
                                <?php endif; ?>

                                <?php if ($isApproved && empty($protocol['hard_copy_released_at'])): ?>
                                    <button class="button" type="button"
                                        onclick="claimHardCopy(<?= $protocolIdInt ?>, this)">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
                                            <use href="#check-icon" />
                                        </svg>
                                        Mark Hard Copy as Claimed
                                    </button>
                                <?php endif; ?>

                                <div class="actions-secondary">
                                    <?php if (!$needsRevision): ?>
                                        <a class="action-link protocol-view-btn" href="<?= ROOT ?>/apply/viewer/<?= $protocolIdInt ?>"
                                            title="View Protocol" aria-label="View Protocol"
                                            onclick="event.preventDefault(); openProtocol(<?= $protocolIdInt ?>)">
                                            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <use href="#eye-icon" />
                                            </svg>
                                            View
                                        </a>
                                    <?php endif; ?>
                                    <?php $menu = ProtocolMenu::forRow($protocol, $actor);
                                    include 'includes/protocol-menu.php'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php $count--;
                endforeach; ?>

                <p class="no-results">
                    <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#file-x-icon" />
                    </svg>
                    No protocols found with this status
                </p>
            </div>

            <!-- ===== Pagination ===== -->
            <div class="pagination-bar" id="paginationBar">
                <span class="pagination-info" id="paginationInfo"></span>
                <div class="pagination-buttons" id="paginationButtons"></div>
                <div class="rows-per-page-wrap">
                    Rows per page:
                    <select id="rowsPerPageSelect">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php
$guideId    = 'protocols';
$guideSteps = [
    ['My Protocols', 'This page lists every protocol you have submitted and what to do with each one. Let us walk through it.'],
    ['New Application', 'Start a new protocol application here. If you have one in progress, you can resume it from the same spot.', '#apply-actions-sub'],
    ['Status filters', 'Filter your list by status. The number next to each name is how many of your protocols have that status.', '.status-filters'],
    ['Sort', 'Change the order of your list: newest or oldest submitted, or by title.', '.dashboard-sort-group'],
    ['Status legend', 'Every status has its own color and icon. The next steps explain what each one means and what you need to do. Press the question mark at the end of this bar to read them again anytime.', '.status-legend-bar'],
];
foreach (array_values($statusMeta) as $index => $meta) {
    $guideSteps[] = [$meta['label'], $meta['desc'], '.legend-items .legend-item:nth-child(' . ($index + 1) . ')'];
}
$guideSteps = array_merge($guideSteps, [
    ['Your protocols', 'Each card is one protocol. The icon on the left shows its current status, and the card shows its title and submission date.', '.protocols-list .protocol:first-visible'],
    ['Actions', 'The **eye button** (View Protocol) opens your submitted protocol file. When needed, you will also be able to **Review Comments & Re-submit** (after a revision request), **Pay Clearance Fee** (once your protocol passes review), **View rejection and resubmit** (if your payment proof is rejected), or **Download Clearance** (once approved).', '.protocols-list .protocol .actions:first-visible'],
    ['More actions', '**Actions** is the three dots beside the eye button. It holds **Version History** (every submission, reviewer note, the signed scan and any title changes), **Payment History**, **Download**, **Rename**, **Amendments**, and **Request Deletion**. Each item only appears when it applies.', '.protocols-list .protocol .actions-secondary .protocol-menu-trigger:first-visible'],
    ['Pagination', 'Move between pages and choose how many protocols to show per page.', '#paginationBar'],
]);
include 'includes/guide.php';
?>

<?php include 'includes/history-modal.php'; ?>
<script src="<?= asset_js('history-modal.js') ?>"></script>

<?php include 'includes/protocol-modals.php'; ?>
<script src="<?= asset_js('protocol-menu.js') ?>"></script>

<!-- Verify Payment modal -->

<!-- ===== Payment rejected modal: rejection reason + the proof the researcher submitted (read-only) ===== -->
<div class="modal-backdrop" id="paymentRejectedModalBackdrop">
    <div class="modal-card file-popup-card review-payment-card">
        <div class="modal-header">
            <span class="modal-header-icon modal-header-icon--danger" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                    <use href="#alert-triangle-icon" />
                </svg>
            </span>
            <p class="modal-title">Payment Proof Rejected</p>
            <button class="modal-close" type="button" onclick="closePaymentRejectedModal()" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
            </button>
        </div>

        <div class="review-payment-summary">
            <p class="review-payment-summary-title"><span id="paymentRejectedTitle"></span></p>
            <div class="form-alert form-alert--error" role="alert">
                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#alert-triangle-icon" />
                </svg>
                <div class="form-alert-body">
                    <p><strong>Reason for rejection:</strong> <span id="paymentRejectedReason"></span></p>
                </div>
            </div>
        </div>

        <div class="review-payment-image-frame" id="paymentRejectedProofFrame">
            <p class="review-payment-note">Loading your submitted proof&hellip;</p>
        </div>

        <div class="review-payment-footer">
            <div class="modal-actions">
                <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="closePaymentRejectedModal()">Close</button>
                <button class="confirm-modal-btn confirm-modal-ok" type="button" onclick="resubmitFromRejectedModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#upload-icon" />
                    </svg>
                    Resubmit Proof
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/payment-modal.php'; ?>
<script src="<?= asset_js('payment-modal.js') ?>"></script>

<!-- ===== Page config and hard copy claim ===== -->
<script>
    const CSRF_TOKEN = <?= json_encode($csrf ?? '') ?>;
    const MUST_PAY_IN_PERSON = <?= $mustPayInPerson ? 'true' : 'false' ?>;
    const HARD_COPY_CLAIM_API = <?= json_encode(ROOT . '/apply/hard_copy_claim') ?>;

    // ===== Hard copy claim =====
    function claimHardCopy(protocolId, btn) {
        confirmAction('Mark your embossed hard copy as claimed? Only do this once you have received it from the BSU-CCARD Office.', {
            okText: 'Mark as Claimed',
            cancelText: 'Cancel'
        }).then(async ok => {
            if (!ok) return;
            setButtonBusy(btn, true, 'Saving...');
            try {
                const res = await fetch(HARD_COPY_CLAIM_API, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        protocol_id: protocolId
                    })
                });
                const data = await res.json();
                if (data.ok) {
                    window.location.reload();
                    return;
                }
                setButtonBusy(btn, false);
                showToast(data.error ?? 'Could not record the hard copy pickup.', 'error');
            } catch (err) {
                setButtonBusy(btn, false);
                showToast(navigator.onLine ? 'Network error. Please try again.' : 'You are offline. Please try again when reconnected.', 'error');
            }
        });
    }

    // ===== Payment rejected modal =====
    const paymentRejectedModal = document.getElementById('paymentRejectedModalBackdrop');
    let currentRejectedProtocolId = null;

    function openPaymentRejectedModal(protocolId) {
        const trigger = document.querySelector(`#protocol-${protocolId} [data-rejection-reason]`);
        if (!trigger) return;

        currentRejectedProtocolId = protocolId;
        document.getElementById('paymentRejectedTitle').textContent = trigger.dataset.researchTitle;
        document.getElementById('paymentRejectedReason').textContent = trigger.dataset.rejectionReason;
        document.getElementById('paymentRejectedProofFrame').innerHTML =
            '<p class="review-payment-note">Loading your submitted proof&hellip;</p>';
        paymentRejectedModal.classList.add('open');
        loadRejectedProof(protocolId);
    }

    function closePaymentRejectedModal() {
        paymentRejectedModal.classList.remove('open');
        currentRejectedProtocolId = null;
    }

    function resubmitFromRejectedModal() {
        openPaymentModal(currentRejectedProtocolId);
    }

    paymentRejectedModal.addEventListener('click', e => {
        if (e.target === paymentRejectedModal) closePaymentRejectedModal();
    });

    async function loadRejectedProof(protocolId) {
        const frame = document.getElementById('paymentRejectedProofFrame');
        const showNote = text => {
            frame.innerHTML = '';
            const note = document.createElement('p');
            note.className = 'review-payment-note';
            note.textContent = text;
            frame.appendChild(note);
        };

        try {
            const res = await fetch(ROOT_URL + '/apply/allversions/' + protocolId);
            const data = await res.json();
            const latest = data?.payment_proof_files?.[0];
            if (!latest) {
                showNote('No proof file is attached because this payment was submitted in person.');
                return;
            }

            frame.innerHTML = '';
            if (/\.pdf$/i.test(latest.original_name || '')) {
                const link = document.createElement('a');
                link.href = latest.file_url;
                link.target = '_blank';
                link.rel = 'noopener';
                link.className = 'underlined';
                link.textContent = 'Open submitted proof (PDF)';
                frame.appendChild(link);
                return;
            }

            const img = document.createElement('img');
            img.src = latest.file_url;
            img.alt = 'Submitted proof of payment';
            img.dataset.zoomSrc = latest.file_url;
            img.dataset.zoomAlt = img.alt;
            img.onerror = () => showNote('Could not load the submitted proof of payment.');
            frame.appendChild(img);
        } catch (err) {
            showNote('Network error while loading the submitted proof.');
        }
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && paymentRejectedModal.classList.contains('open')) closePaymentRejectedModal();
    });
</script>

<script src="<?= asset_js('pagination.js') ?>"></script>
<script>
    const ROOT_URL = <?= json_encode(ROOT) ?>;
    const statusMeta = <?= json_encode($statusMeta) ?>;
    const filterBtns = document.querySelectorAll('.status-filters .status-card');
    const protocolsContainer = document.querySelector('.protocols-list');
    const sortSelect = document.getElementById('submissionsSortSelect');
    const mobileSortTrigger = document.querySelector('.mobile-sort-trigger');
    const mobileSortOptions = document.getElementById('mobileSortOptions');
    const mobileSortLabel = document.getElementById('mobileSortLabel');
    let currentFilter = 'all';

    const pager = createPagination({
        info: document.getElementById('paginationInfo'),
        buttons: document.getElementById('paginationButtons'),
        rowsSelect: document.getElementById('rowsPerPageSelect'),
        noun: 'protocols',
        storageKey: CURRENT_USER_ID + ':protocols',
        onChange: () => renderProtocols()
    });

    // ===== Remember the page per status filter, so coming back from a protocol lands on the same page =====
    const PAGE_STORAGE_KEY = CURRENT_USER_ID + ':protocolsPage';
    const savedPage = JSON.parse(sessionStorage.getItem(PAGE_STORAGE_KEY) || 'null');

    // ===== Render: status filter first, then the current page (cards are already in sorted order) =====
    function matchingCards() {
        if (!protocolsContainer) return [];
        return [...protocolsContainer.querySelectorAll('.protocol')]
            .filter(card => currentFilter === 'all' || card.dataset.status === currentFilter);
    }

    function renderProtocols() {
        if (!protocolsContainer) return;
        const matching = matchingCards();
        const pageCards = pager.paginate(matching);
        sessionStorage.setItem(PAGE_STORAGE_KEY, JSON.stringify({
            filter: currentFilter,
            page: pager.page
        }));

        protocolsContainer.querySelectorAll('.protocol').forEach(card => {
            card.classList.toggle('protocol-row-hidden', !pageCards.includes(card));
        });

        const emptyMsg = protocolsContainer.querySelector('.no-results');
        if (emptyMsg) emptyMsg.classList.toggle('is-visible', matching.length === 0);
    }

    // ===== Sort by =====
    const SORT_STORAGE_KEY = CURRENT_USER_ID + ':protocolsSort';

    function applySort(mode) {
        const container = document.querySelector(sortSelect.dataset.sortTarget);
        if (!container) return;
        [...container.querySelectorAll('.protocol')]
        .sort(protocolSortComparator(mode))
            .forEach(card => container.appendChild(card));
    }

    sortSelect?.addEventListener('change', () => {
        localStorage.setItem(SORT_STORAGE_KEY, sortSelect.value);
        applySort(sortSelect.value);
        pager.page = 1;
        renderProtocols();
    });

    function hexToRgba(hex, alpha) {
        const h = hex.replace('#', '');
        const r = parseInt(h.substring(0, 2), 16);
        const g = parseInt(h.substring(2, 4), 16);
        const b = parseInt(h.substring(4, 6), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    function updateStatusGuide(selected) {
        const guide = document.getElementById('statusGuide');
        if (!guide) return;

        const meta = statusMeta[selected];
        if (!meta) {
            guide.classList.remove('open');
            guide.style.background = '';
            guide.innerHTML = '';
            return;
        }

        guide.innerHTML = `<p class="status-guide-text">                    
                                <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <use href="#info-icon" />
                                </svg> ${meta.desc}
                            </p>`;
        guide.style.background = hexToRgba(meta.color, 0.12);
        guide.classList.add('open');
    }

    function applySubmissionsFilter(selected) {
        currentFilter = selected;
        filterBtns.forEach(b => b.classList.toggle('active', b.dataset.status === selected));
        updateStatusGuide(selected);

        [...filterBtns].find(b => b.dataset.status === selected)?.scrollIntoView({
            block: 'nearest',
            inline: 'center'
        });

        const url = new URL(window.location);
        if (selected === 'all') {
            url.searchParams.delete('status');
        } else {
            url.searchParams.set('status', selected);
        }
        history.replaceState(null, '', url);

        pager.page = 1;
        renderProtocols();
    }

    // ===== Mobile sort dropdown open/close =====
    function openDropdown(trigger, panel) {
        if (!trigger || !panel) return;
        const isOpen = panel.classList.toggle('active');
        trigger.classList.toggle('open', isOpen);
        trigger.setAttribute('aria-expanded', isOpen);
        if (isOpen) {
            positionEdgeAwareDropdown(panel.closest('.sort-wrapper'), panel);
        }
    }

    function closeDropdown(trigger, panel) {
        if (!trigger || !panel) return;
        panel.classList.remove('active');
        trigger.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
    }

    mobileSortTrigger?.addEventListener('click', e => {
        e.stopPropagation();
        openDropdown(mobileSortTrigger, mobileSortOptions);
    });

    document.addEventListener('click', e => {
        if (!mobileSortOptions?.contains(e.target) && !mobileSortTrigger?.contains(e.target)) {
            closeDropdown(mobileSortTrigger, mobileSortOptions);
        }
    });

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            applySubmissionsFilter(btn.dataset.status);
        });
    });

    // ===== Mobile sort dropdown mirrors the desktop <select> =====
    const mobileSortBtns = mobileSortOptions?.querySelectorAll('.status-card') ?? [];
    mobileSortBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            mobileSortBtns.forEach(b => b.classList.toggle('active', b === btn));
            if (mobileSortLabel) mobileSortLabel.textContent = btn.textContent.trim();
            if (sortSelect) {
                sortSelect.value = btn.dataset.sort;
                sortSelect.dispatchEvent(new Event('change'));
            }
            closeDropdown(mobileSortTrigger, mobileSortOptions);
        });
    });

    function openProtocol(protocolId) {
        window.location.href = ROOT_URL + '/apply/viewer/' + parseInt(protocolId, 10) + '?from=' + encodeURIComponent(currentFilter);
    }

    restoreProtocolSort(sortSelect, SORT_STORAGE_KEY, applySort);

    (function restoreFilterFromUrl() {
        const requestedStatus = new URLSearchParams(window.location.search).get('status');
        if (requestedStatus && [...filterBtns].some(b => b.dataset.status === requestedStatus)) {
            applySubmissionsFilter(requestedStatus);
        }
    })();

    if (savedPage && savedPage.filter === currentFilter) pager.page = savedPage.page;

    (function highlightFromUrl() {
        const id = new URLSearchParams(window.location.search).get('highlight');
        const card = id && document.getElementById('protocol-' + id);
        if (!card) return;
        applySubmissionsFilter(card.dataset.status);
        pager.goToItem(matchingCards(), card);
        card.scrollIntoView({
            block: 'center'
        });
        card.classList.add('protocol--highlight');

        if (new URLSearchParams(window.location.search).get('open') === 'payment' &&
            card.querySelector('[data-rejection-reason]')) {
            openPaymentRejectedModal(+id);
        }

        if (new URLSearchParams(window.location.search).get('open') === 'signed_scan' && card.dataset.signedScanUrl) {
            openFilePopup(card.dataset.signedScanUrl, 'Signed Scan');
        }
    })();

    renderProtocols();

    // ===== Continue vs New Application =====
    (function() {
        const applyUrl = '<?= ROOT ?>/apply';
        const container = document.getElementById('apply-actions-sub');
        if (!container) return;

        fetch('<?= ROOT ?>/apply/draft')
            .then(r => r.json())
            .then(d => {
                const hasInProgressDraft = d.exists && (
                    d.step > 0 ||
                    d.agreedTerms ||
                    d.agreedPrivacy ||
                    d.title ||
                    d.cert ||
                    d.auth ||
                    d.protocol
                );
                if (!hasInProgressDraft) return;

                container.innerHTML = `
                    <div class="apply-actions">
                        <a href="${applyUrl}" class="btn-apply button" title="Continue Application">
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#arrow-right-icon" />
                            </svg>
                            <span class="label-full">Resume Application</span>
                            <span class="label-short">Resume</span>
                        </a>
                        <button type="button" class="btn-apply btn-apply-outline button" id="btn-sub-new"
                            data-confirm-message="You have an application in progress. Starting a new one will discard your saved progress. This cannot be undone."
                            data-confirm-ok-text="Yes, Start Over"
                            data-confirm-cancel-text="Go Back"
                            data-confirm-danger="true"
                            title="New Application">
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#add-icon" />
                            </svg>
                            <span class="label-full">New Application</span>
                            <span class="label-short">New</span>
                        </button>
                    </div>`;

                document.getElementById('btn-sub-new').addEventListener('confirm:accepted', () => {
                    fetch('<?= ROOT ?>/apply/draftclear', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': NOTIF_CSRF_TOKEN
                        }
                    }).finally(() => {
                        window.location.href = applyUrl;
                    });
                });
            })
            .catch(() => {});
    })();

    // ===== Status legend info panel (single click to open/close) =====
    (function() {
        const wrapper = document.getElementById('legendInfoWrapper');
        const btn = document.getElementById('legendInfoBtn');
        const panel = document.getElementById('legendInfoPanel');
        if (!wrapper || !btn || !panel) return;

        function openPanel() {
            panel.classList.add('open');
            btn.setAttribute('aria-expanded', 'true');
        }

        function closePanel() {
            panel.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        }

        btn.addEventListener('click', e => {
            e.stopPropagation();
            panel.classList.contains('open') ? closePanel() : openPanel();
        });

        document.addEventListener('click', e => {
            if (!wrapper.contains(e.target)) closePanel();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closePanel();
        });
    })();

    // ===== Download Clearance: always download, also open images in a new tab =====
    document.querySelectorAll('.download-clearance-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!btn.classList.contains('is-claimed')) {
                btn.querySelector('.claimed-date').textContent = formatDate(new Date());
                btn.classList.add('is-claimed');
            }
            if (btn.dataset.isImage === '1' && btn.dataset.viewHref) {
                window.open(btn.dataset.viewHref, '_blank', 'noopener');
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>