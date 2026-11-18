<?php

/** @var array $protocols */
/** @var array|null $cert */
/** @var bool  $mustPayInPerson */
/** @var string $csrf */
/** @var array $actor */

$title = RESEARCHER_HOME_LABEL;

include 'includes/header.php';
include 'includes/scroll-top.php';


$statusMeta = [
    'to-review' => [
        'label' => 'Under Review',
        'color' => '#0072B2',
        'icon'  => 'clock-icon',
        'desc'  => "CCARD is reviewing your protocol and IACUC training certificate, and you will be notified of every feedback. Your protocol cannot move forward without a verified training certificate. If it is rejected, resubmit it.",
    ],
    'returned-for-revision' => [
        'label' => 'Needs Revision',
        'color' => '#D55E00',
        'icon'  => 'alert-triangle-icon',
        'desc'  => "The reviewer sent your protocol back. Revise it following the comments and resubmit your file.",
    ],
    'reviewed' => [
        'label' => 'Reviewed',
        'color' => '#CC79A7',
        'icon'  => 'checkbox-icon',
        'desc'  => "Your protocol passed IACUC review. Press **Pay Clearance Fee** on the protocol card to pay the Php 100.00 clearance fee. BSU La Trinidad Campus and BSU Secondary Laboratory School researchers pay in person; other researchers may also pay online through the CCARD director. Then wait for CCARD to verify your payment and endorse your protocol.",
    ],
    'endorsed' => [
        'label' => 'Endorsed',
        'color' => '#E69F00',
        'icon'  => 'shield-check-icon',
        'desc'  => "No action needed. Your protocol was endorsed to the Department of Agriculture - Regional Field Office - Cordillera Administrative Region (DA-RFO-CAR). Please wait while the Bureau of Animal Industry (BAI) Central Office processes your clearance.",
    ],
    'approved' => [
        'label' => 'Approved',
        'color' => '#009E73',
        'icon'  => 'check-circle-icon',
        'desc'  => "Congratulations, your protocol has been approved! Download the soft copy of your animal research clearance and pick up the embossed copy at the CCARD office. When your research is completed or cancelled, press **Report as Completed** or **Report as Cancelled** on the protocol card so CCARD can update its records.",
    ],
];

$stageSlugByStatus = [
    'under review'   => 'to-review',
    'needs revision' => 'returned-for-revision',
];

// ===== Counts for the status tabs and the archive link =====
$countsBySlug = [];
foreach ($protocols as $protocol) {
    $slug = !empty($protocol['discontinued_at'])
        ? 'archived'
        : ($stageSlugByStatus[strtolower($protocol['status'])] ?? strtolower($protocol['status']));
    $countsBySlug[$slug] = ($countsBySlug[$slug] ?? 0) + 1;
}
$archivedCount = $countsBySlug['archived'] ?? 0;
$allCount      = count($protocols) - $archivedCount;
?>

<link rel="stylesheet" href="<?= asset_css('protocol-list.css') ?>">
<link rel="stylesheet" href="<?= asset_css('payment-badge.css') ?>">
<link rel="stylesheet" href="<?= asset_css('history-modal.css') ?>">
<link rel="stylesheet" href="<?= asset_css('pagination.css') ?>">
<link rel="stylesheet" href="<?= asset_css('submissions.css') ?>">
<link rel="stylesheet" href="<?= asset_css('protocol-menu.css') ?>">
<link rel="stylesheet" href="<?= asset_css('application.css') ?>">
<link rel="stylesheet" href="<?= asset_css('consent.css') ?>">
<?php if (($cert['cert_status'] ?? '') === 'rejected'): ?>
    <link rel="stylesheet" href="<?= asset_css('researcher-modal.css') ?>">
<?php endif; ?>

<!-- ===== Scripts ===== -->
<script src="<?= asset_js('dashboard-updates.js') ?>" defer></script>

<div class="body">
    <?php include 'includes/navigation.php'; ?>

    <!-- ===== My protocols page ===== -->
    <main class="main-content" id="main-content" tabindex="-1">
        <?php include 'includes/update-watcher.php'; ?>

        <div class="submission-header page-banner">
            <div class="page-banner-text">
                <div class="page-banner-title">
                    <h1><?= RESEARCHER_HOME_LABEL ?></h1>
                    <?php $pageGuideId = 'protocols';
                    include 'includes/page-guide.php'; ?>
                </div>
                <p class="page-subtitle">Track your submissions, revisions and clearances</p>
            </div>

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

        <?php include __DIR__ . '/includes/flash.php'; ?>

        <?php if (!empty($cert) && $cert['cert_status'] !== 'verified'): ?>
            <div class="notice notice-warning">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#alert-triangle-icon" />
                </svg>
                <?php if ($cert['cert_status'] === 'rejected'): ?>
                    <span>Your training certificate was rejected<?= !empty($cert['cert_note']) ? ': ' . htmlspecialchars($cert['cert_note'], ENT_QUOTES, 'UTF-8') : '' ?>. Your review is on hold until it is verified. <a href="#" class="underlined" onclick="openCertResubmitModal(); return false;">Resubmit Certificate</a></span>
                <?php else: ?>
                    <span>Your training certificate is waiting for verification. Until it is verified, you cannot submit new protocols and your protocols cannot be marked as Reviewed or returned for revision.</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ===== Sort, status tabs, search, archive and stage guide ===== -->
        <div class="results-bar results-bar--tabs">
            <?php $sortSelectId = 'submissionsSortSelect';
            $sortDefault  = 'updated_desc';
            $sortTarget   = '.protocols-list';
            $sortCompact  = true;
            include __DIR__ . '/includes/sort-control.php'; ?>
            <?php include __DIR__ . '/includes/status-filters.php'; ?>

            <div class="results-bar-end">
                <button type="button" class="legend-info-btn inbox-search-btn" id="inboxSearchBtn"
                    aria-haspopup="dialog" aria-label="Search protocols" title="Search protocols">
                    <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#search-icon" />
                    </svg>
                </button>
                <button type="button" class="action-link archived-link" id="archivedLink" aria-haspopup="dialog">
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#archive-icon" />
                    </svg>
                    Archive (<?= $archivedCount ?>)
                </button>

                <!-- ===== Stage guide ===== -->
                <div class="legend-info-wrapper" id="legendInfoWrapper">
                    <button type="button" class="legend-info-btn" id="legendInfoBtn"
                        aria-expanded="false" aria-controls="legendInfoPanel"
                        aria-label="What do the stages mean? What should I do?">
                        <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#question-info-icon" />
                        </svg>
                    </button>

                    <div class="legend-info-panel" id="legendInfoPanel" role="dialog" aria-label="What the stages mean">
                        <?php foreach ($statusMeta as $slug => $meta): ?>
                            <div class="legend-info-row">
                                <span class="legend-icon" style="background:<?= $meta['color'] ?>">
                                    <?= statusIconSvg($meta['icon'], 11) ?>
                                </span>
                                <div>
                                    <p class="legend-info-title" style="color:<?= $meta['color'] ?>">
                                        <?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($slug === 'returned-for-revision'): ?><span class="legend-info-tag">If applicable</span><?php endif; ?>
                                    </p>
                                    <p class="legend-info-desc">
                                        <?= nl2br(Notifier::toHtml($meta['desc'])) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php $searchPlaceholder = 'Search by title...';
        include __DIR__ . '/includes/inbox-search-modal.php'; ?>

        <?php if (empty($protocols)): ?>
            <div class="empty-state">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#file-x-icon" />
                    </svg>
                    No protocols yet
                </h3>
                <p><a href="<?= ROOT ?>/apply" class="underlined">Click here to apply for your first IACUC protocol review.</a></p>
            </div>

        <?php else: ?>
            <div class="protocols-list">
                <?php $archivedRows = '';
                foreach ($protocols as $protocol):
                    $date          = date(DATE_FORMAT, strtotime($protocol['submitted_at']));
                    $updatedDate   = date(DATE_FORMAT, strtotime($protocol['last_activity_at']));
                    $statusLower   = strtolower($protocol['status']);
                    $stageSlug     = $stageSlugByStatus[$statusLower] ?? $statusLower;
                    $isDiscontinued = !empty($protocol['discontinued_at']);
                    $needsRevision  = !$isDiscontinued && $statusLower === 'needs revision';
                    $returnIssues   = [];
                    if ($needsRevision) {
                        if (!empty($protocol['rr_other_reason'])) $returnIssues[] = 'Other';
                    }
                    $isApproved    = $statusLower === 'approved';
                    $recordStatus  = $protocol['record_status'] ?? '';
                    $clearanceExpired = $isApproved && !empty($protocol['clearance_ends_on']) && strtotime($protocol['clearance_ends_on']) < strtotime('today');
                    $isReviewedStatus = $statusLower === 'reviewed';
                    $paymentStatus = $protocol['payment_status'] ?? 'unpaid';
                    $canConfirmPayment = !$isDiscontinued && $isReviewedStatus && in_array($paymentStatus, ['unpaid', 'rejected'], true);
                    $versionNum    = submission_round_label((int) ($protocol['latest_version'] ?? 1));
                    $protocolIdInt = (int) $protocol['protocol_id'];
                    $viewerRef     = protocol_ref($protocolIdInt, (string) $protocol['research_title']);
                    $submittedIso  = date('c', strtotime($protocol['submitted_at']));
                    ob_start();
                ?>
                    <div class="protocol protocol--timeline" id="protocol-<?= $protocolIdInt ?>"
                        data-filter-slug="<?= $isDiscontinued ? 'archived' : htmlspecialchars($stageSlug, ENT_QUOTES, 'UTF-8') ?>"
                        data-submitted="<?= htmlspecialchars($submittedIso, ENT_QUOTES, 'UTF-8') ?>"
                        data-updated="<?= htmlspecialchars(date('c', strtotime($protocol['last_activity_at'])), ENT_QUOTES, 'UTF-8') ?>"
                        data-title="<?= htmlspecialchars(strtolower($protocol['research_title']), ENT_QUOTES, 'UTF-8') ?>">

                        <div class="protocol-meta">
                            <p class="research-title">
                                <?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($isDiscontinued): ?>
                                    <span class="status-dot status-dot--danger">Discontinued</span>
                                <?php elseif ($isReviewedStatus): ?>
                                    <span class="status-dot payment-badge payment-badge--<?= htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(ProtocolModel::PAYMENT_LABELS[$paymentStatus] ?? 'Unpaid', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                            <p class="protocol-meta-line">
                                <span class="protocol-updated">Last updated <?= $updatedDate ?></span>
                            </p>
                            <?php if ($isDiscontinued):
                                $archiveReason = [
                                    'tone'    => 'danger',
                                    'label'   => 'Archived because you discontinued it',
                                    'text'    => 'Discontinued on ' . date(DATE_FORMAT, strtotime($protocol['discontinued_at'])) . ' while ' . $protocol['status'] . '.',
                                    'comment' => (string) $protocol['discontinued_reason'],
                                ];
                                include 'includes/archive-reason.php';
                            endif; ?>

                            <?php if (!$isDiscontinued && $paymentStatus === 'rejected' && !empty($protocol['payment_rejection_comment'])): ?>
                                <div class="return-reason-inline">
                                    <p class="return-reason-by">Your payment was rejected:</p>
                                    <p class="return-reason-comment"><?= htmlspecialchars($protocol['payment_rejection_comment'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($isApproved && !$isDiscontinued):
                                $hardCopyPending = empty($protocol['hard_copy_released_at']);
                                $outcomeReported = in_array($recordStatus, RecordModel::OUTCOME_STATUSES, true);
                            ?>
                                <?php if ($hardCopyPending || $recordStatus === RecordModel::DEFAULT_STATUS || $outcomeReported): ?>
                                    <div class="clearance-notes">
                                        <?php if ($hardCopyPending): ?>
                                            <p class="clearance-reminder">
                                                <?= statusIconSvg('clock-icon', 14) ?>
                                                <span><strong>Embossed hard copy ready for pickup</strong> at the BSU-CCARD Office, Monday to Friday, 8:00 AM to 5:00 PM. Your downloaded clearance is for temporary reference only. <a href="<?= ROOT ?>/contact" class="underlined">Find the office</a></span>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($recordStatus === RecordModel::DEFAULT_STATUS): ?>
                                            <div class="clearance-reminder">
                                                <?= statusIconSvg('beaker-icon', 14) ?>
                                                <span>
                                                    <?php if ($clearanceExpired): ?>
                                                        <strong>Your research period ended on <?= date(DATE_FORMAT, strtotime($protocol['clearance_ends_on'])) ?>.</strong> Kindly let us know if you have completed or cancelled your research.
                                                    <?php else: ?>
                                                        <strong>Finished or stopped your research?</strong> Kindly let us know so we can update our records.
                                                    <?php endif; ?>
                                                </span>
                                                <div class="research-outcome-actions">
                                                    <?php foreach (RecordModel::OUTCOME_STATUSES as $outcome): ?>
                                                        <button type="button" class="button"
                                                            data-protocol-id="<?= $protocolIdInt ?>"
                                                            data-outcome="<?= $outcome ?>"
                                                            data-research-title="<?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?>"
                                                            onclick="reportResearchOutcome(+this.dataset.protocolId, this.dataset.outcome, this.dataset.researchTitle)">
                                                            Report as <?= $outcome ?>
                                                        </button>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php elseif ($outcomeReported): ?>
                                            <p class="clearance-reminder">
                                                <?= statusIconSvg('check-icon', 14) ?>
                                                <span><strong>Congratulations on completing your research!</strong> If this is not correct, please contact us.</span>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
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

                        <div class="protocol-progress">
                            <?php include 'includes/protocol-stepper.php'; ?>
                        </div>

                        <!-- Actions -->
                        <div class="actions">
                            <?php if ($needsRevision): ?>
                                <a class="button button--primary" href="<?= ROOT ?>/apply/viewer/<?= $viewerRef ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <use href="#upload-icon" />
                                    </svg>
                                    Review Comments &amp; Re-submit
                                </a>
                            <?php endif; ?>

                            <?php if ($isApproved):
                                $clearanceExt = strtolower(pathinfo($protocol['latest_clearance_original_name'] ?? '', PATHINFO_EXTENSION));
                                $clearanceIsImage = in_array($clearanceExt, ['jpg', 'jpeg', 'png'], true);
                            ?>
                                <a class="download-clearance-btn button button--primary"
                                    href="<?= ROOT ?>/apply/clearance/<?= $protocolIdInt ?>?download=1"
                                    data-view-href="<?= ROOT ?>/apply/clearance/<?= $protocolIdInt ?>"
                                    data-is-image="<?= $clearanceIsImage ? '1' : '0' ?>"
                                    rel="noopener">
                                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <use href="#download-icon" />
                                    </svg>
                                    Download Clearance
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

                            <div class="actions-secondary">
                                <?php if (!$needsRevision): ?>
                                    <a class="action-link protocol-view-btn" href="<?= ROOT ?>/apply/viewer/<?= $viewerRef ?><?= $isDiscontinued ? '?from=archived' : '' ?>"
                                        title="View Protocol" aria-label="View Protocol">
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

                        <?php
                        $detailsId   = $protocolIdInt;
                        $detailItems = [
                            ['Round', $versionNum],
                            ['Submitted', $date],
                        ];
                        include 'includes/protocol-details.php';
                        ?>
                    </div>
                <?php
                    $cardHtml = ob_get_clean();
                    if ($isDiscontinued) {
                        $archivedRows .= $cardHtml;
                    } else {
                        echo $cardHtml;
                    }
                endforeach; ?>

                <p class="no-results" id="noResultsMsg">
                    <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#file-x-icon" />
                    </svg>
                    <span id="noResultsText"></span>
                </p>
            </div>

            <!-- ===== Pagination ===== -->
            <div class="pagination-bar pagination-bar--pinned pagination-bar--on-page" id="paginationBar">
                <span class="pagination-info" id="paginationInfo"></span>
                <div class="pagination-buttons" id="paginationButtons"></div>
                <div class="rows-per-page-wrap">
                    <label for="rowsPerPageSelect">Rows per page:</label>
                    <select id="rowsPerPageSelect">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>

            <?php $archiveText = 'Protocols you discontinued are kept here for your records. They no longer move forward.';
            include 'includes/archive-modal.php'; ?>
        <?php endif; ?>
    </main>
</div>

<?php
$guideId    = 'protocols';
$guideSteps = [
    [RESEARCHER_HOME_LABEL, 'This page lists every protocol you have submitted and what to do with each one. Let us walk through it.'],
    ['New Application', 'Start a new protocol application here. If you have one in progress, you can click on **resume**.', '#apply-actions-sub'],
    ['Sort', 'Change the order of your list: newest or oldest submitted, recently updated, or by title.', '.dashboard-sort-group'],
    ['Status tabs', 'Show one stage at a time, with the number of protocols in each.', '#statusFilters'],
    ['Search', 'Press the magnifier to find a protocol in the list by its title. It stays highlighted while a search is active.', '#inboxSearchBtn'],
    ['Archive', 'Discontinued protocols move to the archive, out of your main list. Press **Archive** to open the archive window. Press the **X** or click outside the window to close it.', '#archivedLink'],
    ['Stage guide', 'Press the question mark to read what each stage means and what you need to do.', '.legend-info-wrapper'],
];
foreach ($statusMeta as $meta) {
    $guideSteps[] = [$meta['label'], $meta['desc'], '.legend-info-wrapper'];
}
$guideSteps = array_merge($guideSteps, [
    ['Your protocols', 'Each card is one protocol. It shows the title and a timeline of the stage it is at.', '.protocols-list .protocol:first-visible'],
    ['Details', 'Press the **show details** bar at the bottom of the card to see the round and the dates the protocol was submitted and last updated.', '.protocols-list .protocol .protocol-details-toggle:first-visible'],
    ['Actions', 'The **view** button opens the protocol so you can read your submission and any comments made. When applicable, you will be able to resubmit, pay your clearance processing fee, and download your digital animal research clearance.', '.protocols-list .protocol .actions:first-visible'],
    ['More actions', "The three dots menu holds everything else you can do with a protocol. You may download, rename, or discontinue your protocol, which moves it to your archive. View your protocol history, and submit an amendment to your protocol. \n\nSubmit an amendment of your protocol **IF AND ONLY IF** you absolutely must change details in your protocol after you have received your animal research clearance. Submitting an amendment informs CCARD of these necessary changes.", '.protocols-list .protocol .actions-secondary .protocol-menu-trigger:first-visible'],
    ['Pagination', 'Move between pages and choose how many protocols to show per page.', '#paginationBar'],
]);
include 'includes/guide.php';
?>

<?php include 'includes/history-modal.php'; ?>

<?php if (($cert['cert_status'] ?? '') === 'rejected'):
    include 'includes/cert-resubmit-modal.php';
    include 'includes/cert-rejected-modal.php'; ?>
    <script src="<?= asset_js('cert-resubmit-modal.js') ?>"></script>
    <script src="<?= asset_js('cert-rejected-modal.js') ?>"></script>
<?php endif; ?>
<script src="<?= asset_js('history-modal.js') ?>"></script>

<?php include 'includes/protocol-modals.php'; ?>
<script src="<?= asset_js('protocol-menu.js') ?>"></script>
<script src="<?= asset_js('protocol-details.js') ?>"></script>
<script src="<?= asset_js('protocol-list.js') ?>"></script>

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

<!-- ===== Page config ===== -->
<script>
    const CSRF_TOKEN = <?= json_encode($csrf ?? '') ?>;
    const MUST_PAY_IN_PERSON = <?= $mustPayInPerson ? 'true' : 'false' ?>;

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
<script src="<?= asset_js('archive-window.js') ?>"></script>
<script src="<?= asset_js('inbox-search.js') ?>"></script>
<script>
    const ROOT_URL = <?= json_encode(ROOT) ?>;
    const protocolsContainer = document.querySelector('.protocols-list');
    const sortSelect = document.getElementById('submissionsSortSelect');

    const pager = createPagination({
        info: document.getElementById('paginationInfo'),
        buttons: document.getElementById('paginationButtons'),
        rowsSelect: document.getElementById('rowsPerPageSelect'),
        noun: 'protocols',
        storageKey: CURRENT_USER_ID + ':protocols',
        onChange: () => renderProtocols()
    });

    // ===== Remember the page, so coming back from a protocol lands on the same page =====
    const PAGE_STORAGE_KEY = CURRENT_USER_ID + ':protocolsPage';
    const savedPage = parseInt(sessionStorage.getItem(PAGE_STORAGE_KEY), 10) || 1;

    // ===== Archive: discontinued protocols live in their own window =====
    const noResultsMsg = document.getElementById('noResultsMsg');
    const noResultsText = document.getElementById('noResultsText');
    const statusFilters = document.getElementById('statusFilters');
    let statusFilter = '';
    const archiveList = document.getElementById('archiveList');
    const archiveEmpty = document.getElementById('archiveEmpty');
    const archiveWindow = createArchiveWindow({
        button: document.getElementById('archivedLink'),
        backdrop: document.getElementById('archiveBackdrop')
    });
    archiveEmpty?.classList.toggle('is-visible', !archiveList?.querySelector('.protocol'));

    // ===== Search =====
    const inboxSearch = createInboxSearch({
        onChange: () => {
            pager.page = 1;
            renderProtocols();
        }
    });

    // ===== Status filter =====
    statusFilters.addEventListener('click', e => {
        const tab = e.target.closest('.status-card');
        if (!tab) return;
        statusFilter = tab.dataset.status;
        statusFilters.querySelectorAll('.status-card').forEach(btn => {
            btn.classList.toggle('active', btn === tab);
            btn.setAttribute('aria-pressed', btn === tab);
        });
        pager.page = 1;
        renderProtocols();
    });

    // ===== Render the current page (cards are already in sorted order) =====
    function protocolCards() {
        return protocolsContainer ? [...protocolsContainer.querySelectorAll('.protocol')] : [];
    }

    function inCurrentTab(card) {
        return !statusFilter || card.dataset.filterSlug === statusFilter;
    }

    function viewCards() {
        return protocolCards().filter(card =>
            inCurrentTab(card) && (!inboxSearch.query || card.dataset.title.includes(inboxSearch.query)));
    }

    function renderProtocols() {
        if (!protocolsContainer) return;
        const shownCards = viewCards();
        const pageCards = pager.paginate(shownCards);
        sessionStorage.setItem(PAGE_STORAGE_KEY, pager.page);

        protocolCards().forEach(card => {
            card.classList.toggle('protocol-row-hidden', !pageCards.includes(card));
        });

        noResultsMsg.classList.toggle('is-visible', shownCards.length === 0);
        if (inboxSearch.query) noResultsText.textContent = 'No protocols match your search.';
        else noResultsText.textContent = statusFilter ? 'No protocols in this stage.' : 'All your protocols are in the archive.';

        const tabTotal = protocolCards().filter(inCurrentTab).length;
        inboxSearch.setSummary(inboxSearch.query ?
            `Showing ${shownCards.length} of ${tabTotal} protocol${tabTotal === 1 ? '' : 's'}` :
            '');
    }

    // ===== Sort by =====
    const SORT_PREFERENCE = 'protocolsSort';

    function applySort(mode) {
        const container = document.querySelector(sortSelect.dataset.sortTarget);
        if (!container) return;
        [...container.querySelectorAll('.protocol')]
        .sort(protocolSortComparator(mode))
            .forEach(card => container.appendChild(card));
    }

    sortSelect?.addEventListener('change', () => {
        saveViewPreference(SORT_PREFERENCE, sortSelect.value);
        syncProtocolSortControls(sortSelect);
        applySort(sortSelect.value);
        pager.page = 1;
        renderProtocols();
    });

    restoreProtocolSort(sortSelect, SORT_PREFERENCE, applySort);

    pager.page = savedPage;

    (function highlightFromUrl() {
        const url = new URL(window.location);
        const id = url.searchParams.get('highlight');
        const openTarget = url.searchParams.get('open');
        if (id) {
            url.searchParams.delete('highlight');
            url.searchParams.delete('open');
            history.replaceState(null, '', url);
        }

        const card = id && document.getElementById('protocol-' + id);
        if (!card) return;
        if (card.closest('#archiveList')) archiveWindow.set(true);
        else pager.goToItem(viewCards(), card);
        card.scrollIntoView({
            block: 'center'
        });
        card.classList.add('protocol--highlight');

        if (openTarget === 'payment' &&
            card.querySelector('[data-rejection-reason]')) {
            openPaymentRejectedModal(+id);
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
                        <a href="${applyUrl}" class="btn-apply btn-apply-outline button" title="Continue Application">
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#arrow-right-icon" />
                            </svg>
                            <span class="label-full">Resume Application</span>
                            <span class="label-short">Resume</span>
                        </a>
                        <button type="button" class="btn-apply button" id="btn-sub-new"
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
                        headers: csrfHeaders({
                            'Content-Type': 'application/json'
                        })
                    }).finally(() => {
                        window.location.href = applyUrl;
                    });
                });
            })
            .catch(() => {});
    })();

    // ===== Download Clearance: also open images in a new tab =====
    document.querySelectorAll('.download-clearance-btn[data-is-image="1"]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (btn.dataset.viewHref) window.open(btn.dataset.viewHref, '_blank', 'noopener');
        });
    });
</script>

<?php include 'includes/footer.php'; ?>