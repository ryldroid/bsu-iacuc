<?php

/** @var array|null  $user */
/** @var array       $protocols */
/** @var array       $statuses */
/** @var array       $actor */

$title = 'Personnel Dashboard';

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/scroll-top.php';

$user      = $user      ?? $_SESSION['user'] ?? [];
$csrf      = $csrf      ?? '';
$protocols = $protocols ?? [];

// ===== Map internal status strings to human-readable display labels =====
$statusDisplayMap = [
    'under review'   => 'To Review',
    'needs revision' => 'Returned for Revision',
    'reviewed'       => 'Reviewed',
    'endorsed'       => 'Endorsed',
    'approved'       => 'Approved',
];

$badgeClassMap = [
    'under review'   => 'badge-to-review',
    'needs revision' => 'badge-returned',
    'reviewed'       => 'badge-reviewed',
    'endorsed'       => 'badge-endorsed',
    'approved'       => 'badge-approved',
];

$filterSlugMap = [
    'under review'   => 'to-review',
    'needs revision' => 'returned-for-revision',
    'reviewed'       => 'reviewed',
    'endorsed'       => 'endorsed',
    'approved'       => 'approved',
];

// ===== Status metadata: color + icon + role-aware plain-language description =====
$personnelRole = ($user['role'] ?? '') === 'reviewer' ? 'reviewer' : 'staff';

$statusDescByRole = [
    'staff' => [
        'to-review'             => 'Submitted protocols waiting on the reviewer\'s review. You may view, download, rename, and delete protocols. Opening a protocol shows the researcher\'s training certificate first, and asks you to verify it if no one has yet. Only one personnel needs to do this. Reviewers can still read and comment when a certificate is rejected, but cannot finish a review or return a protocol until it is verified.',
        'returned-for-revision' => 'Sent back to the researcher with feedback. No action needed.',
        'reviewed'              => "The review has finished review. You may confirm clearance payments, assign IPNs, print protocols for the IACUC chairperson's signature and upload those signed scans, and mark them as **Endorsed**. Each card shows which of the five steps it is on and whether you or the researcher must act.",
        'endorsed'              => "Protocols have been endorsed to DA-CARFU. Upload released animal research clearances, assign AR numbers, match clearances to their protocols, then confirm to mark them **Approved**.",
        'approved'              => 'Animal research clearances issued! The protocols are now fully approved.',
    ],
    'reviewer' => [
        'to-review'             => 'Submitted protocols waiting on your feedback. You may open, review, download, rename, and delete protocols. Opening a protocol shows the researcher\'s training certificate first, and asks you to verify it if no one has yet. If it is rejected you can still read and comment, but you cannot finish the review or return the protocol until it is verified. Your part ends once you mark it Reviewed or return it for revision.',
        'returned-for-revision' => 'Sent back to the researcher with feedback. No action needed until they resubmit.',
        'reviewed'              => "You have finished the assessment. No action required. Administrative staff will now verify payments, assign IPNs, and upload the scan with the IACUC chairperson's sign.",
        'endorsed'              => 'Protocol has been endorsed to DA-CARFU. No action required. Administrative staff will upload the released animal research clearances and release them to the researchers.',
        'approved'              => 'Animal research clearances issued! The protocols are now fully approved.',
    ],
];

$statusMeta = [
    'to-review' => [
        'label' => 'To Review',
        'color' => '#0072B2',
        'icon'  => 'clock-icon',
        'desc'  => $statusDescByRole[$personnelRole]['to-review'],
    ],
    'returned-for-revision' => [
        'label' => 'Returned for Revision',
        'color' => '#D55E00',
        'icon'  => 'alert-triangle-icon',
        'desc'  => $statusDescByRole[$personnelRole]['returned-for-revision'],
    ],
    'reviewed' => [
        'label' => 'Reviewed',
        'color' => '#CC79A7',
        'icon'  => 'checkbox-icon',
        'desc'  => $statusDescByRole[$personnelRole]['reviewed'],
    ],
    'endorsed' => [
        'label' => 'Endorsed',
        'color' => '#E69F00',
        'icon'  => 'shield-check-icon',
        'desc'  => $statusDescByRole[$personnelRole]['endorsed'],
    ],
    'approved' => [
        'label' => 'Approved',
        'color' => '#009E73',
        'icon'  => 'check-circle-icon',
        'desc'  => $statusDescByRole[$personnelRole]['approved'],
    ],
];

function statusIconSvg(string $iconId, int $size = 14): string
{
    return '<svg class="status-icon-svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<use href="#' . htmlspecialchars($iconId, ENT_QUOTES, 'UTF-8') . '" />'
        . '</svg>';
}

foreach ($protocols as &$protocol) {
    $key = strtolower($protocol['status']);

    $protocol['status_display'] = $statusDisplayMap[$key] ?? $protocol['status'];
    $protocol['badge_class']    = $badgeClassMap[$key]    ?? 'badge-to-review';
    $protocol['filter_slug']    = $filterSlugMap[$key]    ?? 'other';
    $protocol['version_display'] = submission_round_label((int) ($protocol['latest_version'] ?? 1));
}
unset($protocol);

// ===== Compute per-status counts for filter pill badges =====
$countsBySlug = [];
foreach ($protocols as $p) {
    $slug = $p['filter_slug'];
    $countsBySlug[$slug] = ($countsBySlug[$slug] ?? 0) + 1;
}

$totalCount     = count($protocols);
$toReviewCount  = $countsBySlug['to-review']             ?? 0;
$revisionCount  = $countsBySlug['returned-for-revision'] ?? 0;
$reviewedCount  = $countsBySlug['reviewed']              ?? 0;
$endorsedCount  = $countsBySlug['endorsed']              ?? 0;
$approvedCount  = $countsBySlug['approved']              ?? 0;

// ===== Count reviewed protocols with a file to download, so the =====
$reviewedDownloadableCount = 0;
foreach ($protocols as $p) {
    if ($p['filter_slug'] === 'reviewed' && !empty($p['latest_protocol_version_id'])) {
        $reviewedDownloadableCount++;
    }
}

?>

<link rel="stylesheet" href="<?= asset_css('protocol-list.css') ?>">
<link rel="stylesheet" href="<?= asset_css('payment-badge.css') ?>">
<link rel="stylesheet" href="<?= asset_css('researcher-modal.css') ?>">
<link rel="stylesheet" href="<?= asset_css('history-modal.css') ?>">
<link rel="stylesheet" href="<?= asset_css('pagination.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/personnel-home.css') ?>">
<link rel="stylesheet" href="<?= asset_css('protocol-menu.css') ?>">
<link rel="stylesheet" href="<?= asset_css('status-underline.css') ?>">
<?php if ($personnelRole === 'staff'): ?>
    <link rel="stylesheet" href="<?= asset_css('personnel/clearances.css') ?>">
<?php endif; ?>
<script src="<?= asset_js('dashboard-updates.js') ?>" defer></script>
<script src="<?= asset_js('status-underline.js') ?>" defer></script>

<div class="body">
    <?php include dirname(__DIR__) . '/includes/navigation.php'; ?>

    <main class="main-content" id="main-content" tabindex="-1">
        <?php include dirname(__DIR__) . '/includes/update-banner.php'; ?>

        <div class="dashboard-panel">

            <!-- ===== Search bar ===== -->
            <div class="dashboard-search-row">
                <h1 class="dashboard-page-title">Protocol Inbox</h1>
                <?php $pageGuideId = 'inbox';
                include dirname(__DIR__) . '/includes/page-guide.php'; ?>
                <div class="inbox-search-wrap">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <input type="text" id="inboxSearchInput" class="inbox-search-input"
                        placeholder="Search by title or researcher..." autocomplete="off">
                    <button class="inbox-search-clear" id="inboxSearchClear" aria-label="Clear search">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#close-icon" />
                        </svg>
                    </button>
                </div>
            </div>

            <?php include dirname(__DIR__) . '/includes/flash.php'; ?>

            <?php if ($personnelRole === 'reviewer'): ?>
                <div class="notice notice-info">
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#info-icon" />
                    </svg>
                    <span><strong>Your role as a reviewer.</strong> <?= htmlspecialchars(ProtocolPermissions::REVIEWER_ROLE_NOTE, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <?php if (empty($protocols)): ?>
                <!-- ===== Empty state ===== -->
                <div class="empty-state">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#file-x-icon" />
                        </svg>
                        No protocols yet
                    </h3>
                    <p>No protocol submissions have been received.</p>
                </div>

            <?php else: ?>

                <!-- ── Search toolbar ────────────────────────────────
            <div class="inbox-toolbar">

            </div> -->

                <!-- ===== Status filter tabs ===== -->
                <div class="dashboard-filter-row">
                    <div class="filter-wrapper">
                        <div class="status-filters" id="filterPillsRow">
                            <button class="status-card" data-filter="all" data-label="All">
                                <p>All</p>
                            </button>
                            <button class="status-card active" data-filter="to-review" data-label="To review">
                                <p>To review <span class="status-count"><?= $toReviewCount ?></span></p>
                            </button>
                            <button class="status-card" data-filter="returned-for-revision" data-label="Returned for revision">
                                <p>Returned for revision <span class="status-count"><?= $revisionCount ?></span></p>
                            </button>
                            <button class="status-card" data-filter="reviewed" data-label="Reviewed">
                                <p>Reviewed <span class="status-count"><?= $reviewedCount ?></span></p>
                            </button>
                            <button class="status-card" data-filter="endorsed" data-label="Endorsed">
                                <p>Endorsed <span class="status-count"><?= $endorsedCount ?></span></p>
                            </button>
                            <button class="status-card" data-filter="approved" data-label="Approved">
                                <p>Approved <span class="status-count"><?= $approvedCount ?></span></p>
                            </button>
                        </div>

                        <div class="dashboard-field-group mobile-filter-group">
                            <p class="sort-filter-label">
                                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <use href="#filter-icon" />
                                </svg>
                                Status:
                            </p>

                            <div class="sort-wrapper">
                                <button type="button" class="mobile-filter-trigger dashboard-select-trigger mobile-dropdown-trigger" aria-haspopup="true" aria-expanded="false">
                                    <span id="mobileFilterLabel">To review</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <use href="#chev-down-icon" />
                                    </svg>
                                </button>

                                <div class="dropdown-panel" id="mobileFilterOptions">
                                    <button class="status-card" type="button" data-mobile-filter="all">All</button>
                                    <button class="status-card" type="button" data-mobile-filter="to-review">To review <span class="status-count"><?= $toReviewCount ?></span></button>
                                    <button class="status-card" type="button" data-mobile-filter="returned-for-revision">Returned for revision <span class="status-count"><?= $revisionCount ?></span></button>
                                    <button class="status-card" type="button" data-mobile-filter="reviewed">Reviewed <span class="status-count"><?= $reviewedCount ?></span></button>
                                    <button class="status-card" type="button" data-mobile-filter="endorsed">Endorsed <span class="status-count"><?= $endorsedCount ?></span></button>
                                    <button class="status-card" type="button" data-mobile-filter="approved">Approved <span class="status-count"><?= $approvedCount ?></span></button>
                                </div>
                            </div>
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
                            <select id="inboxSortSelect" class="dashboard-sort-select dashboard-select-trigger" aria-label="Sort protocols">
                                <option value="newest">Newest Submitted</option>
                                <option value="oldest" selected>Oldest Submitted</option>
                                <option value="title_asc">Title (A–Z)</option>
                                <option value="title_desc">Title (Z–A)</option>
                            </select>

                            <button type="button" class="mobile-sort-trigger dashboard-select-trigger mobile-dropdown-trigger" aria-haspopup="true" aria-expanded="false">
                                <span id="mobileSortLabel">Oldest Submitted</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <use href="#chev-down-icon" />
                                </svg>
                            </button>

                            <div class="dropdown-panel" id="mobileSortOptions">
                                <button class="status-card" data-sort="newest">Newest Submitted</button>
                                <button class="status-card active" data-sort="oldest">Oldest Submitted</button>
                                <button class="status-card" data-sort="title_asc">Title (A–Z)</button>
                                <button class="status-card" data-sort="title_desc">Title (Z–A)</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== Results summary ===== -->
                <div class="results-bar">
                    <p class="results-summary" id="resultsSummary" aria-live="polite"></p>
                </div>

                <!-- ===== Status legend ===== -->
                <div class="status-legend-bar">
                    <span class="legend-title">Current status</span>

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
                            aria-label="What do the statuses mean?">
                            <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
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

                <?php if (($user['role'] ?? '') === 'staff'): ?>
                    <!-- ===== Bulk actions: apply to every matching protocol in the current tab, not just one row ===== -->
                    <div class="bulk-actions-bar" id="bulkActionsBar" hidden>
                        <div id="paymentFilterWrapper" class="bulk-payment-filter" hidden>
                            <div class="dashboard-field-group">
                                <label class="sort-filter-label" for="paymentFilterSelect">
                                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <use href="#filter-icon" />
                                    </svg>
                                    Payment:
                                </label>
                                <select id="paymentFilterSelect" class="dashboard-select-trigger">
                                    <option value="">All payments</option>
                                    <?php foreach (ProtocolModel::PAYMENT_LABELS as $paymentValue => $paymentLabel): ?>
                                        <option value="<?= $paymentValue ?>"><?= $paymentLabel ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="action-link" id="paymentClearBtn" hidden>
                                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <use href="#close-icon" />
                                </svg>
                                Clear filter
                            </button>
                        </div>

                        <div class="bulk-select-controls" id="bulkSelectControls" hidden>
                            <span class="bulk-select-count" id="bulkSelectCount">0 selected</span>
                            <button type="button" class="row-btn row-btn-primary" id="bulkDownloadBtn" disabled>
                                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <use href="#download-icon" />
                                </svg>
                                Download Selected
                            </button>
                            <button type="button" class="row-btn row-btn-primary" id="bulkEndorseBtn" disabled>
                                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <use href="#check-icon" />
                                </svg>
                                Mark Selected as Endorsed
                            </button>
                            <button type="button" class="row-btn row-btn-outline" id="selectAllBtn">
                                Select All
                            </button>
                        </div>

                        <div class="clearance-bulk" id="clearanceBulk" hidden>
                            <span class="clearance-progress" id="clearanceProgress"></span>
                            <button type="button" class="row-btn row-btn-primary" id="confirmBtn" disabled>
                                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <use href="#check-icon" />
                                </svg>
                                Confirm all (<span id="stagedCount">0</span>)
                            </button>
                        </div>

                        <button type="button" class="row-btn row-btn-outline" id="toggleSelectBtn" hidden
                            title="Select protocols to download or endorse at once">
                            <svg id="toggleSelectBtnIcon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#checkbox-icon" />
                            </svg>
                            <span id="toggleSelectBtnLabel">Select</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="clearance-layout" id="clearanceLayout">
                    <?php if ($personnelRole === 'staff'): ?>
                        <!-- ===== Clearances: upload screenshots and match them to endorsed protocols (Endorsed filter only) ===== -->
                        <section class="clearance-panel" id="clearancePanel" hidden>
                            <div class="clearance-panel-head">
                                <h2>Clearances</h2>
                                <ol class="clearance-steps">
                                    <li>Upload each released clearance screenshot and enter its AR number</li>
                                    <li>Drag each one onto its protocol (on a touch screen, tap it, then tap the protocol's box)</li>
                                    <li>Confirm to mark the matched protocols as Approved</li>
                                </ol>
                            </div>

                            <div class="bulk-actions-bar clearance-actions">
                                <input type="file" id="clearanceFileInput" multiple
                                    accept=".jpg,.jpeg,.png,image/jpeg,image/png" hidden>

                                <button type="button" class="row-btn row-btn-outline" id="clearanceUploadBtn"
                                    title="Upload clearance screenshots (JPG or PNG, several at once)">
                                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <use href="#upload-icon" />
                                    </svg>
                                    Upload Clearances
                                </button>
                            </div>

                            <div class="clearance-tray" id="clearanceTrayCard">
                                <h3>Unsorted Screenshots <span class="clearance-count" id="trayCount"></span></h3>
                                <div class="clearance-tray-grid" id="trayGrid">
                                    <p class="helper clearance-empty">Loading&hellip;</p>
                                </div>
                            </div>
                        </section>
                    <?php endif; ?>

                    <!-- ===== Protocol list ===== -->
                    <div class="protocols-list" id="protocolsList">

                        <?php
                        $iconMap = [
                            'review'   => '#review-icon',
                            'history'  => '#history-icon',
                            'check'    => '#check-icon',
                            'upload'   => '#upload-icon',
                            'download' => '#download-icon',
                            'back'     => '#back-icon',
                            'reject'   => '#close-icon',
                            'undo'     => '#undo-icon',
                            'edit'     => '#edit-icon',
                        ];
                        ?>

                        <?php foreach ($protocols as $protocol):
                            $submittedDate  = date(DATE_FORMAT, strtotime($protocol['submitted_at']));
                            $statusDisplay  = $protocol['status_display'];
                            $badgeClass     = $protocol['badge_class'];
                            $filterSlug     = $protocol['filter_slug'];
                            $statusLower    = strtolower($protocol['status']);
                            $protocolId     = (int) $protocol['protocol_id'];
                            $paymentStatus  = $protocol['payment_status'] ?? 'unpaid';
                            $hasSignedScan  = !empty($protocol['latest_signed_scan_version_id']);
                            $hasIpn         = !empty($protocol['reference_no']);
                            $hasHardCopyReleased = !empty($protocol['hard_copy_released_at']);
                            $researcherName = htmlspecialchars(
                                $protocol['first_name'] . ' ' . $protocol['last_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            $title = htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8');

                            $userRole = $user['role'] ?? '';
                            $actions = [];

                            $userRole = strtolower($user['role'] ?? '');
                            $actions = [];

                            if ($statusLower === 'under review') {
                                $actions = $userRole === 'reviewer' ? [
                                    [
                                        'label' => 'Review',
                                        'action' => 'open',
                                        'icon' => 'review',
                                        'primary' => true
                                    ],
                                ] : [
                                    [
                                        'label' => 'View Protocol',
                                        'action' => 'view',
                                        'icon' => 'review'
                                    ],
                                ];
                            } elseif ($userRole === 'reviewer') {

                                switch ($statusLower) {

                                    case 'reviewed':
                                        $actions = [
                                            [
                                                'label' => 'View Protocol',
                                                'action' => 'view',
                                                'icon' => 'review'
                                            ]
                                        ];
                                        break;

                                    case 'approved':
                                        $actions = [
                                            [
                                                'label' => 'View Clearance',
                                                'action' => 'view-clearance',
                                                'icon' => 'review',
                                                'primary' => true
                                            ],
                                            [
                                                'label' => 'View Protocol',
                                                'action' => 'view',
                                                'icon' => 'review'
                                            ]
                                        ];
                                        break;

                                    default:
                                        $actions = [
                                            [
                                                'label' => 'View Protocol',
                                                'action' => 'view',
                                                'icon' => 'review'
                                            ]
                                        ];
                                }
                            } else {

                                switch ($statusLower) {

                                    case 'reviewed':
                                        if ($paymentStatus === 'proof_submitted') {
                                            $actions = [
                                                [
                                                    'label' => 'Verify Payment',
                                                    'action' => 'review-payment',
                                                    'icon' => 'review',
                                                    'primary' => true
                                                ],
                                                [
                                                    'label' => 'View Protocol',
                                                    'action' => 'view',
                                                    'icon' => 'review'
                                                ]
                                            ];
                                        } elseif ($paymentStatus !== 'paid') {
                                            $actions = [
                                                [
                                                    'label' => 'View Protocol',
                                                    'action' => 'view',
                                                    'icon' => 'review'
                                                ]
                                            ];
                                        } elseif (!$hasIpn) {
                                            $actions = [
                                                [
                                                    'label' => 'Assign IPN',
                                                    'action' => 'assign-ipn',
                                                    'icon' => 'edit',
                                                    'primary' => true
                                                ],
                                                [
                                                    'label' => 'View Protocol',
                                                    'action' => 'view',
                                                    'icon' => 'review'
                                                ],
                                                [
                                                    'label' => 'Undo "Mark as Paid"',
                                                    'action' => 'undo-payment',
                                                    'icon' => 'undo',
                                                    'menu' => true
                                                ]
                                            ];
                                        } elseif (!$hasSignedScan) {
                                            $actions = [
                                                [
                                                    'label' => 'Upload Signed Scan',
                                                    'action' => 'upload-signed-scan',
                                                    'icon' => 'upload',
                                                    'primary' => true
                                                ],
                                                [
                                                    'label' => 'View Protocol',
                                                    'action' => 'view',
                                                    'icon' => 'review'
                                                ],
                                                [
                                                    'label' => 'Edit IPN',
                                                    'action' => 'edit-ipn',
                                                    'icon' => 'edit',
                                                    'menu' => true
                                                ],
                                                [
                                                    'label' => 'Undo "Mark as Paid"',
                                                    'action' => 'undo-payment',
                                                    'icon' => 'undo',
                                                    'menu' => true
                                                ]
                                            ];
                                        } else {
                                            $actions = [
                                                [
                                                    'label' => 'Mark as Endorsed',
                                                    'action' => 'mark-endorsed',
                                                    'icon' => 'check',
                                                    'primary' => true
                                                ],
                                                [
                                                    'label' => 'View Protocol',
                                                    'action' => 'view',
                                                    'icon' => 'review'
                                                ],
                                                [
                                                    'label' => 'Edit IPN',
                                                    'action' => 'edit-ipn',
                                                    'icon' => 'edit',
                                                    'menu' => true
                                                ],
                                                [
                                                    'label' => 'Undo "Mark as Paid"',
                                                    'action' => 'undo-payment',
                                                    'icon' => 'undo',
                                                    'menu' => true
                                                ]
                                            ];
                                        }
                                        break;

                                    case 'endorsed':
                                        $hasClearance = !empty($protocol['latest_clearance_version_id']);
                                        $actions = [];
                                        if ($hasClearance) {
                                            $actions[] = [
                                                'label' => 'Mark as Approved',
                                                'action' => 'mark-approved',
                                                'icon' => 'check',
                                                'primary' => true
                                            ];
                                        }
                                        $actions = array_merge($actions, [
                                            [
                                                'label' => 'View Protocol',
                                                'action' => 'view',
                                                'icon' => 'review'
                                            ],
                                            [
                                                'label' => 'Revert to Reviewed',
                                                'action' => 'revert-endorsed',
                                                'icon' => 'undo',
                                                'menu' => true
                                            ],
                                            [
                                                'label' => 'Revert to To Review',
                                                'action' => 'revert-endorsed-to-review',
                                                'icon' => 'undo',
                                                'menu' => true
                                            ]
                                        ]);
                                        break;

                                    case 'approved':
                                        $actions = [
                                            [
                                                'label' => 'View Clearance',
                                                'action' => 'view-clearance',
                                                'icon' => 'review',
                                                'primary' => $hasHardCopyReleased
                                            ],
                                            [
                                                'label' => 'View Protocol',
                                                'action' => 'view',
                                                'icon' => 'review'
                                            ],
                                            [
                                                'label' => $hasHardCopyReleased ? 'Undo Pickup' : 'Confirm Pickup',
                                                'action' => $hasHardCopyReleased ? 'undo-hard-copy' : 'release-hard-copy',
                                                'icon' => $hasHardCopyReleased ? 'undo' : 'check',
                                                'primary' => !$hasHardCopyReleased,
                                                'menu' => $hasHardCopyReleased
                                            ]
                                        ];
                                        if (!$hasHardCopyReleased) {
                                            $actions[] = [
                                                'label' => 'Revert to Endorsed',
                                                'action' => 'revert-approved',
                                                'icon' => 'undo',
                                                'menu' => true
                                            ];
                                        }
                                        break;

                                    default:
                                        $actions = [
                                            [
                                                'label' => 'View Protocol',
                                                'action' => 'view',
                                                'icon' => 'review'
                                            ]
                                        ];
                                }
                            }

                            $nextStep = null;
                            $stepCount = count(ProtocolModel::ENDORSEMENT_STEPS);
                            if ($userRole === 'staff' && $statusLower === 'reviewed') {
                                $nextStep = ProtocolModel::nextStep($paymentStatus, $hasIpn, $hasSignedScan);
                            }
                        ?>
                            <div class="protocol"
                                data-protocol-id="<?= $protocolId ?>"
                                data-filter-slug="<?= $filterSlug ?>"
                                data-payment="<?= htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') ?>"
                                data-researcher="<?= strtolower(htmlspecialchars($protocol['first_name'] . ' ' . $protocol['last_name'], ENT_QUOTES, 'UTF-8')) ?>"
                                data-submitted="<?= htmlspecialchars(date('c', strtotime($protocol['submitted_at'])), ENT_QUOTES, 'UTF-8') ?>"
                                data-title="<?= htmlspecialchars(strtolower($protocol['research_title']), ENT_QUOTES, 'UTF-8') ?>">

                                <?php if ($userRole === 'staff' && $statusLower === 'reviewed'): ?>
                                    <label class="protocol-select-label" title="Select this protocol">
                                        <input type="checkbox" class="consent-checkbox protocol-select-checkbox"
                                            aria-label="Select protocol"
                                            <?= $protocol['can_endorse'] ? 'data-can-endorse' : '' ?>
                                            <?= empty($protocol['latest_protocol_version_id']) ? 'disabled' : '' ?>>
                                    </label>
                                <?php endif; ?>

                                <span class="protocol-status-icon" style="background:<?= $statusMeta[$filterSlug]['color'] ?? 'var(--muted-text)' ?>">
                                    <?= statusIconSvg($statusMeta[$filterSlug]['icon'] ?? 'check-circle-icon', 15) ?>
                                </span>

                                <div class="protocol-body">
                                    <div class="protocol-meta">
                                        <p class="research-title">
                                            <?= $title ?>
                                            <?php if ($statusLower === 'reviewed'): ?>
                                                <span class="payment-badge payment-badge--<?= htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') ?>" <?= $nextStep ? ' title="Step ' . $nextStep[0] . ' of ' . $stepCount . ': ' . htmlspecialchars($nextStep[1], ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                                    <?= htmlspecialchars(ProtocolModel::PAYMENT_LABELS[$paymentStatus] ?? 'Unpaid', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php elseif ($statusLower === 'approved'): ?>
                                                <span class="payment-badge payment-badge--<?= $hasHardCopyReleased ? 'paid' : 'proof_submitted' ?>">
                                                    <?= $hasHardCopyReleased ? 'Hard copy picked up' : 'Hard copy for pickup' ?>
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="protocol-meta-line">
                                            <?= $protocol['version_display'] ?> &middot; <button type="button" class="researcher-name-link" data-user-id="<?= (int) $protocol['user_id'] ?>" data-researcher-name="<?= $researcherName ?>"><?= $researcherName ?></button><?php if (!empty($protocol['school'])): ?> &middot; <?= htmlspecialchars($protocol['school'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?> &middot; <?= $submittedDate ?><?php if ($userRole === 'staff' && $hasIpn && in_array($statusLower, ['reviewed', 'endorsed'], true)): ?> &middot; IPN <?= htmlspecialchars($protocol['reference_no'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                        </p>
                                        <?php if ($nextStep): ?>
                                            <div class="protocol-next-step<?= $nextStep[2] ? ' protocol-next-step--waiting' : '' ?>">
                                                <span class="step-pips">
                                                    <?php for ($step = 1; $step <= $stepCount; $step++): ?>
                                                        <span class="step-pip<?= $step < $nextStep[0] ? ' step-pip--done' : ($step === $nextStep[0] ? ' step-pip--current' : '') ?>" title="Step <?= $step ?> of <?= $stepCount ?>: <?= htmlspecialchars(ProtocolModel::ENDORSEMENT_STEPS[$step], ENT_QUOTES, 'UTF-8') ?> (<?= $step < $nextStep[0] ? 'done' : ($step === $nextStep[0] ? 'current' : 'upcoming') ?>)"></span>
                                                    <?php endfor; ?>
                                                </span>
                                                <span class="step-count">Step <?= $nextStep[0] ?> of <?= $stepCount ?></span>
                                                <span class="step-label"><?= htmlspecialchars($nextStep[1], ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($userRole === 'staff' && $statusLower === 'endorsed' && empty($protocol['latest_clearance_version_id'])): ?>
                                            <!-- Filled by the clearance board script: drop target for an unsorted screenshot -->
                                            <div class="clearance-slot" data-protocol-id="<?= $protocolId ?>"></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="actions">
                                        <?php foreach ($actions as $action): ?>
                                            <?php if (!empty($action['primary'])): ?>
                                                <?php if (!empty($action['href'])): ?>
                                                    <a class="button button--primary" href="<?= htmlspecialchars($action['href'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <?php if (!empty($action['icon'])): ?>
                                                            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                                <use href="<?= $iconMap[$action['icon']] ?>"></use>
                                                            </svg>
                                                        <?php endif; ?>
                                                        <?= htmlspecialchars($action['label']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="button button--primary" data-action="<?= $action['action'] ?>">
                                                        <?php if (!empty($action['icon'])): ?>
                                                            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                                <use href="<?= $iconMap[$action['icon']] ?>"></use>
                                                            </svg>
                                                        <?php endif; ?>
                                                        <?= htmlspecialchars($action['label']) ?>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endforeach; ?>

                                        <div class="actions-secondary">
                                            <?php foreach ($actions as $action): ?>
                                                <?php if (empty($action['primary']) && empty($action['menu'])): ?>
                                                    <?php if ($action['action'] === 'view'): ?>
                                                        <button class="action-link protocol-view-btn" data-action="view" title="View Protocol" aria-label="View Protocol">
                                                            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                                <use href="#eye-icon" />
                                                            </svg>
                                                            View
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="action-link" data-action="<?= $action['action'] ?>">
                                                            <?= htmlspecialchars($action['label']) ?>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <?php $menu = ProtocolMenu::forRow($protocol, $actor, array_map(fn($a) => ['action' => $a['action'], 'label' => $a['label'], 'icon' => ltrim($iconMap[$a['icon']], '#')], array_values(array_filter($actions, fn($a) => !empty($a['menu'])))));
                                            include dirname(__DIR__) . '/includes/protocol-menu.php'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <p class="no-results" id="noResultsMsg">
                            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#file-x-icon" />
                            </svg>
                            No protocols match your search or filter.
                        </p>
                    </div><!-- /.protocols-list -->
                </div><!-- /.clearance-layout -->

                <!-- ===== Pagination ===== -->
                <div class="pagination-bar pagination-bar--pinned pagination-bar--on-page" id="paginationBar">
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

        </div>

    </main>
</div>

<?php if ($personnelRole === 'staff'): ?>
    <!-- Confirm review modal -->
    <div class="modal-backdrop" id="confirmReviewBackdrop">
        <div class="modal-card panel-modal-card clearance-modal-card">
            <div class="modal-header">
                <span class="modal-header-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                        <use href="#check-circle-icon" />
                    </svg>
                </span>
                <p class="modal-title" id="confirmReviewTitle"></p>
                <button class="modal-close" type="button" onclick="closeConfirmReview()" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#close-icon" />
                    </svg>
                </button>
            </div>
            <div class="panel-modal-body">
                <p class="helper clearance-modal-helper" id="confirmReviewHelper"></p>

                <div id="confirmReviewError" class="alert error-messages clearance-modal-error" hidden></div>

                <div class="modal-actions">
                    <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="closeConfirmReview()">Cancel</button>
                    <button class="confirm-modal-btn confirm-modal-ok" type="button" id="confirmProceedBtn" onclick="proceedConfirm()">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#check-icon" />
                        </svg>
                        Proceed
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Clearance upload modal: preview the screenshot and enter its AR number -->
    <div class="modal-backdrop" id="clearanceUploadBackdrop" data-static>
        <div class="modal-card panel-modal-card clearance-upload-modal-card">
            <div class="modal-header">
                <span class="modal-header-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                        <use href="#upload-icon" />
                    </svg>
                </span>
                <p class="modal-title" id="clearanceUploadTitle">Upload Clearance</p>
                <button class="modal-close" type="button" onclick="cancelClearanceUpload()" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#close-icon" />
                    </svg>
                </button>
            </div>
            <div class="panel-modal-body">
                <div class="clearance-upload-preview">
                    <img id="clearanceUploadImg" src="" alt="">
                </div>
                <p class="clearance-upload-name" id="clearanceUploadName"></p>

                <div id="clearanceUploadError" class="alert error-messages" hidden></div>

                <div class="clearance-number-field">
                    <label for="clearanceUploadAr">AR Number</label>
                    <input type="text" id="clearanceUploadAr" maxlength="50" placeholder="AR number from the BAI clearance">
                    <span class="helper">The animal research clearance ID printed on the clearance issued by BAI.</span>
                </div>

                <div class="modal-actions">
                    <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="cancelClearanceUpload()">Cancel</button>
                    <button class="confirm-modal-btn confirm-modal-ok" type="button" id="clearanceUploadSaveBtn" onclick="saveClearanceUpload()">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#upload-icon" />
                        </svg>
                        <span id="clearanceUploadSaveLabel">Upload</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit IPN modal -->
    <div class="modal-backdrop" id="numberModalBackdrop" data-static>
        <div class="modal-card panel-modal-card">
            <div class="modal-header">
                <span class="modal-header-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                        <use href="#edit-icon" />
                    </svg>
                </span>
                <p class="modal-title" id="numberModalTitle"></p>
                <button class="modal-close" type="button" onclick="closeNumberModal()" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#close-icon" />
                    </svg>
                </button>
            </div>
            <div class="panel-modal-body">
                <p class="helper" id="numberModalHelper"></p>

                <div id="numberModalError" class="alert error-messages" hidden></div>

                <div class="clearance-number-field">
                    <label for="numberModalInput" id="numberModalLabel"></label>
                    <input type="text" id="numberModalInput">
                </div>

                <div class="modal-actions">
                    <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="closeNumberModal()">Cancel</button>
                    <button class="confirm-modal-btn confirm-modal-ok" type="button" id="numberModalSaveBtn" onclick="saveNumber()">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#check-icon" />
                        </svg>
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ===== JavaScript ===== -->
<script src="<?= asset_js('pagination.js') ?>"></script>
<script>
    const protocolsData = <?= json_encode($protocols) ?>;
    const ROOT_URL = <?= json_encode(ROOT) ?>;
    const USER_ROLE = <?= json_encode($user['role'] ?? '') ?>;
    const CSRF_TOKEN = <?= json_encode($csrf) ?>;
    // ===== Status metadata (mirrors My Protocols) =====
    const statusMeta = <?= json_encode($statusMeta) ?>;
    const REVIEWED_DOWNLOADABLE_COUNT = <?= (int) $reviewedDownloadableCount ?>;
</script>
<script src="<?= asset_js('personnel-home.js') ?>"></script>
<script src="<?= asset_js('personnel-protocol-actions.js') ?>"></script>

<?php
$guideId    = 'inbox';
$guideSteps = [
    ['Protocol Inbox', $personnelRole === 'staff'
        ? 'This is where protocols submitted to CCARD show up, and what you need to do with each one. Let us walk through it.'
        : 'This is where protocols waiting for your feedback show up. ' . ProtocolPermissions::REVIEWER_ROLE_NOTE . ' Let us walk through it.'],
    ['Search', "Find a protocol by its title or the researcher's name.", '.inbox-search-wrap'],
    ['Status filters', 'Filter the inbox by status. It opens on **To review**, which are the protocols awaiting review. The number next to each name is how many protocols have that status.', '#filterPillsRow, .mobile-filter-group'],
    ['Sort', 'Change the order: newest or oldest submitted, or by title.', '.dashboard-sort-group'],
    ['Status legend', 'Every status has its own color and icon. The next steps explain what each one means for you. Press the question mark at the end of this bar to read them again anytime.', '.status-legend-bar'],
];
foreach (array_values($statusMeta) as $index => $meta) {
    $guideSteps[] = [$meta['label'], $meta['desc'], '.legend-items .legend-item:nth-child(' . ($index + 1) . ')'];
}
$guideSteps = array_merge($guideSteps, [
    ['Training certificates', "Opening a protocol first shows a window with the researcher's details and training certificate. If nobody has checked the certificate yet, you must verify it or request a resubmission before you continue. Only one personnel needs to do this, and whoever does it first settles it for everyone. When it has already been checked, the window just tells you whether it was verified or rejected. If it was rejected, reviewers may still read and comment, but cannot finish the review or return the protocol until the researcher resubmits and it is verified."],
    ['Protocol card', "Each card is one protocol. It shows the status icon, the research title, the submission round, and the researcher. Press the researcher's name to see their details and verify or review their training certificate.", '.protocols-list .protocol:first-visible'],
    ['Actions', $personnelRole === 'staff'
        ? 'Protocols **To Review** are handled by the reviewers, so their cards only offer **View Protocol**. Once a protocol is **Reviewed** or **Endorsed**, its card shows the next task instead, such as **Verify Payment**, **Assign IPN**, **Upload Signed Scan**, **Mark as Endorsed**, or **Mark as Approved**. The **eye button** (View Protocol) is always available beside the three dots, and **View Clearance** appears once approved.'
        : 'Press **Review** to open it, read it, and give your feedback. Protocols in other statuses show **View Protocol** instead, and **View Clearance** once approved.', '.protocols-list .protocol .actions:first-visible'],
    ['More actions', '**Actions** is the three dots beside the eye button (View Protocol). It holds **Version History** (every submission, reviewer note, the signed scan and any title changes), **Payment History**, **Download**, **Rename**, **Amendments**, and **Delete**. Each item only appears when it applies.', '.protocols-list .protocol .actions-secondary .protocol-menu-trigger:first-visible'],
    ['Pagination', 'Move between pages and choose how many rows to show per page.', '#paginationBar'],
]);
include dirname(__DIR__) . '/includes/guide.php';
?>
<?php include dirname(__DIR__) . '/includes/history-modal.php'; ?>
<script>
    window.historyModalConfig = {
        offlineMessage: 'Submission history is not available offline. It will load once you reconnect.'
    };
</script>
<script src="<?= asset_js('history-modal.js') ?>"></script>

<?php include dirname(__DIR__) . '/includes/researcher-modal.php'; ?>

<?php include dirname(__DIR__) . '/includes/protocol-modals.php'; ?>
<script src="<?= asset_js('protocol-menu.js') ?>"></script>

<!-- ===== Upload Signed Scan modal (administrative staff only) ===== -->
<div class="modal-backdrop" id="signedScanModalBackdrop" data-static>
    <div class="modal-card panel-modal-card">
        <div class="modal-header">
            <span class="modal-header-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                    <use href="#upload-icon" />
                </svg>
            </span>
            <div>
                <p class="modal-label">Upload Signed Scan</p>
                <p class="modal-title" id="signedScanSubtitle"></p>
            </div>
            <button class="modal-close" type="button" onclick="closeSignedScanModal()" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
            </button>
        </div>
        <div class="panel-modal-body">

            <div id="signedScanError" class="alert error-messages" hidden></div>

            <div class="modal-file-row">
                <div class="modal-file-info">
                    <div class="modal-file-title">Signed protocol (scanned PDF) <span class="required-asterisk">*</span></div>
                    <div class="modal-file-subtitle" id="signedScanFileSubtitle">PDF only &middot; max 10 MB</div>
                </div>
                <div class="resub-doc-actions">
                    <button type="button" class="resub-btn-link is-hidden" id="signedScanFilePreviewBtn"
                        onclick="previewPickedFile('signed_scan_file')">Preview</button>
                    <label class="modal-file-picker">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#upload-icon" />
                        </svg>
                        <span id="signedScanFilePickerLabel">Upload</span>
                        <input type="file" id="signed_scan_file" name="signed_scan_file"
                            accept=".pdf,application/pdf" required
                            onchange="handleSignedScanFileChange(this)">
                    </label>
                </div>
            </div>

            <div class="upload-progress-container" id="signedScanProgress"></div>

            <div class="modal-actions">
                <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="closeSignedScanModal()">Cancel</button>
                <button class="confirm-modal-btn confirm-modal-ok" type="button" id="signedScanSubmitBtn"
                    onclick="submitSignedScanUpload()">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#upload-icon" />
                    </svg>
                    Upload Signed Scan
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset_js('personnel-signed-scan-modal.js') ?>"></script>

<!-- ===== Verify Payment modal (administrative staff only): shows the proof image (if any), then Approve / Reject ===== -->
<div class="modal-backdrop" id="reviewPaymentModalBackdrop" data-static>
    <div class="modal-card file-popup-card review-payment-card">
        <div class="modal-header">
            <span class="modal-header-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                    <use href="#shield-check-icon" />
                </svg>
            </span>
            <p class="modal-title">Payment Proof for Validation</p>
            <button class="modal-close" type="button" onclick="closeReviewPaymentModal()" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
            </button>
        </div>

        <div class="review-payment-summary" id="reviewPaymentSummary">
            <p class="review-payment-summary-meta" id="reviewPaymentSummaryMeta"></p>
            <p class="review-payment-summary-title"><span id="reviewPaymentSummaryTitle"></span></p>
        </div>

        <div class="review-payment-image-frame" id="reviewPaymentImageFrame">
            <p class="review-payment-note">Loading proof of payment&hellip;</p>
        </div>

        <div class="review-payment-footer">
            <!-- Step 1: approve or start a rejection -->
            <div class="modal-actions review-payment-primary-actions" id="reviewPaymentActions">
                <button class="confirm-modal-btn confirm-modal-ok confirm-modal-ok-danger" type="button" onclick="showRejectPaymentReason()">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#close-icon" />
                    </svg>
                    Reject
                </button>
                <button class="confirm-modal-btn confirm-modal-ok" type="button" id="reviewPaymentApproveBtn"
                    onclick="approveReviewedPayment()">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#check-icon" />
                    </svg>
                    Approve Payment
                </button>
            </div>

            <!-- Step 2: rejection reason, shown only after clicking Reject -->
            <div id="reviewPaymentRejectPanel" hidden>
                <div class="clearance-number-field">
                    <label for="reject_payment_comment">Reason <span class="required-asterisk">*</span></label>
                    <textarea id="reject_payment_comment" rows="3" placeholder="e.g. the amount doesn't match, or the receipt is unreadable" required></textarea>
                </div>
                <div id="rejectPaymentError" class="alert error-messages clearance-modal-error" hidden></div>
                <div class="modal-actions">
                    <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="hideRejectPaymentReason()">Back</button>
                    <button class="confirm-modal-btn confirm-modal-ok confirm-modal-ok-danger" type="button" id="rejectPaymentSubmitBtn"
                        onclick="submitRejectPayment()">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#close-icon" />
                        </svg>
                        Confirm Rejection
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset_js('personnel-review-payment-modal.js') ?>"></script>

<!-- ===== Clearance board (staff only) ===== -->
<?php if ($personnelRole === 'staff' && !empty($protocols)): ?>
    <script src="<?= asset_js('personnel-clearance-board.js') ?>"></script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>