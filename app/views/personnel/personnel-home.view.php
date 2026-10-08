<?php

/** @var array|null  $user */
/** @var array       $protocols */
/** @var array       $statuses */

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
        'to-review'             => "Submitted protocols waiting on the reviewer's feedback.",
        'returned-for-revision' => 'Sent back to the researcher with feedback. No action needed until they resubmit.',
        'reviewed'              => "The reviewer has finished the assessment. Confirm the PI's payment, assign the IPN, print the protocol for the IACUC chair's signature, upload the signed scan, then mark it as Endorsed.",
        'endorsed'              => "Protocol has been endorsed to DA-CARFU. Upload the released animal research clearances below, assign each protocol's AR number, match each clearance to its protocol, then confirm to mark them Approved.",
        'approved'              => 'Animal research clearances issued! The protocols are now fully approved.',
    ],
    'reviewer' => [
        'to-review'             => 'Submitted protocols waiting on your feedback.',
        'returned-for-revision' => 'Sent back to the researcher with feedback. No action needed until they resubmit.',
        'reviewed'              => "You have finished the assessment. No action required. Administrative staff will now verify payments, assign IPNs, and upload the scan with the IACUC chair's sign.",
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

$paymentLabels = [
    'unpaid'          => 'Unpaid',
    'proof_submitted' => 'Awaiting Confirmation',
    'rejected'        => 'Payment Rejected',
    'paid'            => 'Paid',
];

// ===== Count reviewed protocols with a file to download, so the =====
$reviewedDownloadableCount = 0;
foreach ($protocols as $p) {
    if ($p['filter_slug'] === 'reviewed' && !empty($p['latest_protocol_version_id'])) {
        $reviewedDownloadableCount++;
    }
}

?>

<link rel="stylesheet" href="<?= asset_css('protocol-list.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/personnel-home.css') ?>">
<link rel="stylesheet" href="<?= asset_css('status-underline.css') ?>">
<?php if ($personnelRole === 'staff'): ?>
    <link rel="stylesheet" href="<?= asset_css('personnel/clearances.css') ?>">
<?php endif; ?>
<script src="<?= asset_js('dashboard-updates.js') ?>" defer></script>
<script src="<?= asset_js('protocol-sort.js') ?>"></script>
<script src="<?= asset_js('status-underline.js') ?>" defer></script>

<div class="body">
    <?php include dirname(__DIR__) . '/includes/navigation.php'; ?>

    <main class="main-content" id="main-content" tabindex="-1">
        <?php include dirname(__DIR__) . '/includes/update-banner.php'; ?>

        <div class="dashboard-panel">

            <!-- ===== Search bar ===== -->
            <div class="dashboard-search-row">
                <h1 class="dashboard-page-title">Protocol Inbox</h1>
                <?php if (($user['role'] ?? '') === 'reviewer'): ?>
                    <button type="button" class="action-link" data-tour-start="inbox">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#info-icon" />
                        </svg>
                        Page tour
                    </button>
                <?php endif; ?>
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

            <!-- ===== Flash messages ===== -->
            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert success-message" id="flashSuccess">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#check-icon" />
                    </svg>
                    <?= htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert error-messages" id="flashError">
                    <?= htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
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
                    </div>

                    <div class="dashboard-sort-group dashboard-field-group">
                        <p class="sort-filter-label">
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#sort-icon" />
                            </svg>
                            Sort:
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
                <p class="results-summary" id="resultsSummary" aria-live="polite"></p>

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
                                            <?= htmlspecialchars($meta['desc'], ENT_QUOTES, 'UTF-8') ?>
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
                                    <?php foreach ($paymentLabels as $paymentValue => $paymentLabel): ?>
                                        <option value="<?= $paymentValue ?>"><?= $paymentLabel ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="bulk-select-controls" id="bulkSelectControls" hidden>
                            <span class="bulk-select-count" id="bulkSelectCount">0 selected</span>
                            <button type="button" class="row-btn row-btn-outline" id="selectAllBtn">
                                Select All
                            </button>
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

                <?php if ($personnelRole === 'staff'): ?>
                    <!-- ===== Clearances: upload screenshots and match them to endorsed protocols (Endorsed filter only) ===== -->
                    <section class="clearance-panel" id="clearancePanel" hidden>
                        <div class="clearance-panel-head">
                            <h2>Clearances</h2>
                            <p class="helper">Upload the released animal research clearances, then drag each screenshot onto its protocol below. On a touch screen, tap a screenshot, then tap the clearance box of its protocol. Confirm to mark the matched protocols as Approved.</p>
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

                            <button type="button" class="row-btn row-btn-primary" id="confirmBtn" disabled>
                                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <use href="#check-icon" />
                                </svg>
                                Confirm (<span id="stagedCount">0</span>)
                            </button>
                        </div>

                        <div class="upload-progress-container" id="clearanceUploadProgress"></div>

                        <div class="clearance-tray" id="clearanceTrayCard">
                            <h3>Unsorted Screenshots</h3>
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
                        'undo'     => '#back-icon',
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

                        if ($userRole === 'reviewer') {

                            switch ($statusLower) {

                                case 'under review':
                                    $actions = [
                                        [
                                            'label' => 'Review',
                                            'action' => 'open',
                                            'icon' => 'review',
                                            'primary' => true
                                        ],
                                        [
                                            'label' => 'Show History',
                                            'action' => 'show-history',
                                            'icon' => 'history'
                                        ]
                                    ];
                                    break;

                                case 'reviewed':
                                    $actions = [
                                        [
                                            'label' => 'View',
                                            'action' => 'view',
                                            'icon' => 'review',
                                            'primary' => true
                                        ],
                                        [
                                            'label' => 'Show History',
                                            'action' => 'show-history',
                                            'icon' => 'history'
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
                                            'label' => 'View',
                                            'action' => 'view',
                                            'icon' => 'review'
                                        ],
                                        [
                                            'label' => 'Show History',
                                            'action' => 'show-history',
                                            'icon' => 'history'
                                        ]
                                    ];
                                    break;

                                default:
                                    $actions = [
                                        [
                                            'label' => 'View',
                                            'action' => 'view',
                                            'icon' => 'review',
                                            'primary' => true
                                        ],
                                        [
                                            'label' => 'Show History',
                                            'action' => 'show-history',
                                            'icon' => 'history'
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
                                                'label' => 'View',
                                                'action' => 'view',
                                                'icon' => 'review'
                                            ],
                                            [
                                                'label' => 'Show History',
                                                'action' => 'show-history',
                                                'icon' => 'history'
                                            ]
                                        ];
                                    } elseif ($paymentStatus !== 'paid') {
                                        $actions = [
                                            [
                                                'label' => 'View',
                                                'action' => 'view',
                                                'icon' => 'review',
                                                'primary' => true
                                            ],
                                            [
                                                'label' => 'Show History',
                                                'action' => 'show-history',
                                                'icon' => 'history'
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
                                                'label' => 'View',
                                                'action' => 'view',
                                                'icon' => 'review'
                                            ],
                                            [
                                                'label' => 'Undo "Mark as Paid"',
                                                'action' => 'undo-payment',
                                                'icon' => 'undo'
                                            ],
                                            [
                                                'label' => 'Show History',
                                                'action' => 'show-history',
                                                'icon' => 'history'
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
                                                'label' => 'View',
                                                'action' => 'view',
                                                'icon' => 'review'
                                            ],
                                            [
                                                'label' => 'Edit IPN',
                                                'action' => 'edit-ipn',
                                                'icon' => 'edit'
                                            ],
                                            [
                                                'label' => 'Undo "Mark as Paid"',
                                                'action' => 'undo-payment',
                                                'icon' => 'undo'
                                            ],
                                            [
                                                'label' => 'Show History',
                                                'action' => 'show-history',
                                                'icon' => 'history'
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
                                                'label' => 'View',
                                                'action' => 'view',
                                                'icon' => 'review'
                                            ],
                                            [
                                                'label' => 'Edit IPN',
                                                'action' => 'edit-ipn',
                                                'icon' => 'edit'
                                            ],
                                            [
                                                'label' => 'Undo "Mark as Paid"',
                                                'action' => 'undo-payment',
                                                'icon' => 'undo'
                                            ],
                                            [
                                                'label' => 'Show History',
                                                'action' => 'show-history',
                                                'icon' => 'history'
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
                                            'label' => 'View',
                                            'action' => 'view',
                                            'icon' => 'review'
                                        ],
                                        [
                                            'label' => 'Show History',
                                            'action' => 'show-history',
                                            'icon' => 'history'
                                        ],
                                        [
                                            'label' => 'Revert to Reviewed',
                                            'action' => 'revert-endorsed',
                                            'icon' => 'undo'
                                        ]
                                    ]);
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
                                            'label' => 'View',
                                            'action' => 'view',
                                            'icon' => 'review'
                                        ],
                                        [
                                            'label' => 'Show History',
                                            'action' => 'show-history',
                                            'icon' => 'history'
                                        ],
                                        [
                                            'label' => 'Revert to Endorsed',
                                            'action' => 'revert-approved',
                                            'icon' => 'undo'
                                        ]
                                    ];
                                    break;

                                default:
                                    $actions = [
                                        [
                                            'label' => 'View',
                                            'action' => 'view',
                                            'icon' => 'review',
                                            'primary' => true
                                        ],
                                        [
                                            'label' => 'Show History',
                                            'action' => 'show-history',
                                            'icon' => 'history'
                                        ]
                                    ];
                            }
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
                                            <span class="payment-badge payment-badge--<?= htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($paymentLabels[$paymentStatus] ?? 'Unpaid', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="protocol-meta-line">
                                        <?= $protocol['version_display'] ?> &middot; <button type="button" class="researcher-name-link" data-user-id="<?= (int) $protocol['user_id'] ?>" data-researcher-name="<?= $researcherName ?>"><?= $researcherName ?></button><?php if (!empty($protocol['school'])): ?> &middot; <?= htmlspecialchars($protocol['school'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?> &middot; <?= $submittedDate ?><?php if ($userRole === 'staff' && $hasIpn && in_array($statusLower, ['reviewed', 'endorsed'], true)): ?> &middot; IPN <?= htmlspecialchars($protocol['reference_no'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                    </p>
                                    <?php if ($userRole === 'staff' && $statusLower === 'endorsed' && empty($protocol['latest_clearance_version_id'])): ?>
                                        <!-- Filled by the clearance board script: drop target for an unsorted screenshot -->
                                        <div class="clearance-slot" data-protocol-id="<?= $protocolId ?>"></div>
                                    <?php endif; ?>
                                </div>

                                <div class="actions">
                                    <?php if ($userRole === 'staff' && $statusLower === 'reviewed' && !empty($protocol['latest_protocol_version_id'])): ?>
                                        <a class="quick-download-btn"
                                            href="<?= ROOT ?>/apply/file/<?= (int) $protocol['latest_protocol_version_id'] ?>?download=1"
                                            title="Download protocol PDF" aria-label="Download protocol PDF">
                                            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <use href="#download-icon"></use>
                                            </svg>
                                        </a>
                                    <?php endif; ?>
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
                                            <?php if (empty($action['primary'])): ?>
                                                <button class="action-link" data-action="<?= $action['action'] ?>">
                                                    <?= htmlspecialchars($action['label']) ?>
                                                </button>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
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

        </div>

    </main>
</div>

<?php if ($personnelRole === 'staff'): ?>
    <!-- Confirm review modal -->
    <div class="modal-backdrop" id="confirmReviewBackdrop">
        <div class="modal-card clearance-modal-card">
            <h2>Confirm Clearances</h2>
            <p class="helper clearance-modal-helper"><strong class="clearance-modal-warning">Double-check all entries below before proceeding.</strong> Once you proceed, each protocol is marked <strong>Approved</strong>.</p>

            <div id="confirmReviewError" class="alert error-messages clearance-modal-error" hidden></div>

            <div class="modal-actions">
                <button class="button" type="button" onclick="closeConfirmReview()">Cancel</button>
                <button class="button btn-apply" type="button" id="confirmProceedBtn" onclick="proceedConfirm()">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#check-icon" />
                    </svg>
                    Proceed
                </button>
            </div>
        </div>
    </div>

    <!-- Add/Edit IPN and AR number modal -->
    <div class="modal-backdrop" id="numberModalBackdrop">
        <div class="modal-card">
            <h2 id="numberModalTitle"></h2>
            <p class="helper" id="numberModalHelper"></p>

            <div id="numberModalError" class="alert error-messages" hidden></div>

            <div class="clearance-number-field">
                <label for="numberModalInput" id="numberModalLabel"></label>
                <input type="text" id="numberModalInput">
            </div>

            <div class="modal-actions">
                <button class="button" type="button" onclick="closeNumberModal()">Cancel</button>
                <button class="button btn-apply" type="button" id="numberModalSaveBtn" onclick="saveNumber()">Save</button>
            </div>
        </div>
    </div>

    <!-- Image zoom lightbox -->
    <div class="modal-backdrop clearance-zoom-backdrop" id="clearanceZoomBackdrop">
        <div class="clearance-zoom-card">
            <button type="button" class="image-zoom-close" onclick="closeZoom()" title="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
            </button>
            <img id="clearanceZoomImg" src="" alt="">
            <p class="clearance-zoom-caption" id="clearanceZoomCaption"></p>
        </div>
    </div>
<?php endif; ?>

<!-- ===== JavaScript ===== -->
<script>
    const protocolsData = <?= json_encode($protocols) ?>;
    const ROOT_URL = <?= json_encode(ROOT) ?>;
    const USER_ROLE = <?= json_encode($user['role'] ?? '') ?>;
    const CSRF_TOKEN = <?= json_encode($csrf) ?>;
    const STATUS_API = ROOT_URL + '/apply/status';
    const VERIFY_PAYMENT_API = ROOT_URL + '/apply/verify_payment';
    const UNDO_PAYMENT_API = ROOT_URL + '/apply/undo_payment';
    const REJECT_PAYMENT_API = ROOT_URL + '/apply/reject_payment';
    const SIGNED_SCAN_UPLOAD_API = ROOT_URL + '/apply/signed_scan_upload';
    const CLEARANCE_VIEW_URL = ROOT_URL + '/apply/clearance/';

    // ===== Status metadata (mirrors My Protocols) =====
    const statusMeta = <?= json_encode($statusMeta) ?>;
    const REVIEWED_DOWNLOADABLE_COUNT = <?= (int) $reviewedDownloadableCount ?>;

    // ===== DOM refs =====
    const protocolsList = document.getElementById('protocolsList');
    const noResultsMsg = document.getElementById('noResultsMsg');
    const filterPills = document.querySelectorAll('#filterPillsRow .status-card');
    const mobileSortTrigger = document.querySelector('.mobile-sort-trigger');
    const mobileSortOptions = document.getElementById('mobileSortOptions');
    const mobileSortLabel = document.getElementById('mobileSortLabel');
    const searchInput = document.getElementById('inboxSearchInput');
    const searchClearBtn = document.getElementById('inboxSearchClear');
    const paginationInfo = document.getElementById('paginationInfo');
    const resultsSummary = document.getElementById('resultsSummary');
    const paginationBtns = document.getElementById('paginationButtons');
    const rowsPerPageSel = document.getElementById('rowsPerPageSelect');
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const paymentFilterWrapper = document.getElementById('paymentFilterWrapper');
    const paymentFilterSelect = document.getElementById('paymentFilterSelect');
    const clearancePanel = document.getElementById('clearancePanel');
    const toggleSelectBtn = document.getElementById('toggleSelectBtn');
    const toggleSelectBtnLabel = document.getElementById('toggleSelectBtnLabel');
    const toggleSelectBtnIcon = document.getElementById('toggleSelectBtnIcon');
    const VISIBLE_SELECTABLE = '.protocol:not(.protocol-row-hidden) .protocol-select-checkbox:not(:disabled)';
    const bulkSelectControls = document.getElementById('bulkSelectControls');
    const bulkSelectCount = document.getElementById('bulkSelectCount');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const bulkDownloadBtn = document.getElementById('bulkDownloadBtn');
    const bulkEndorseBtn = document.getElementById('bulkEndorseBtn');
    const sortSelect = document.getElementById('inboxSortSelect');

    const allRows = protocolsList ? [...protocolsList.querySelectorAll('.protocol')] : [];

    let activeFilter = 'to-review';
    let searchQuery = '';
    let currentPage = 1;
    let rowsPerPage = 10;

    // ===== Sort by =====
    const SORT_STORAGE_KEY = 'inboxSort';
    const VIEW_STORAGE_KEY = 'inboxView';

    function saveView() {
        sessionStorage.setItem(VIEW_STORAGE_KEY, JSON.stringify({
            filter: activeFilter,
            search: searchInput?.value ?? '',
            page: currentPage,
            rows: rowsPerPage,
            payment: paymentFilterSelect?.value ?? ''
        }));
    }

    function applySort(mode) {
        allRows.sort(protocolSortComparator(mode));
        allRows.forEach(row => protocolsList.appendChild(row));
    }

    sortSelect?.addEventListener('change', () => {
        localStorage.setItem(SORT_STORAGE_KEY, sortSelect.value);
        applySort(sortSelect.value);
        currentPage = 1;
        renderTable();
    });

    // ===== Only show the payment filter and select button while viewing the Reviewed tab, and
    // the clearance panel while viewing the Endorsed tab; hide the whole
    // bar when none of them has anything to do =====
    function updateBulkActionsBarVisibility() {
        const canSelect = activeFilter === 'reviewed' && REVIEWED_DOWNLOADABLE_COUNT > 0;

        if (paymentFilterWrapper) {
            paymentFilterWrapper.hidden = !canSelect;
            if (!canSelect) paymentFilterSelect.value = '';
        }
        if (clearancePanel) {
            const showClearances = activeFilter === 'endorsed';
            clearancePanel.hidden = !showClearances;
            if (showClearances && typeof ensureClearanceBoard === 'function') ensureClearanceBoard();
        }
        if (toggleSelectBtn) {
            toggleSelectBtn.hidden = !canSelect;
            if (toggleSelectBtn.hidden) {
                exitSelectionMode();
            }
        }
        if (bulkActionsBar) {
            bulkActionsBar.hidden = (paymentFilterWrapper?.hidden ?? true) &&
                (toggleSelectBtn?.hidden ?? true);
        }
    }

    // ===== Selection mode =====
    function exitSelectionMode() {
        protocolsList?.classList.remove('selection-mode');
        if (toggleSelectBtnLabel) toggleSelectBtnLabel.textContent = 'Select';
        if (toggleSelectBtnIcon) toggleSelectBtnIcon.hidden = false;
        if (bulkSelectControls) bulkSelectControls.hidden = true;
        protocolsList?.querySelectorAll('.protocol-select-checkbox').forEach(cb => cb.checked = false);
        updateBulkSelectUI();
    }

    function updateBulkSelectUI() {
        const checkboxes = [...(protocolsList?.querySelectorAll(VISIBLE_SELECTABLE) ?? [])];
        const selected = checkboxes.filter(cb => cb.checked);

        if (bulkSelectCount) {
            bulkSelectCount.textContent = `${selected.length} selected`;
        }
        if (bulkDownloadBtn) {
            bulkDownloadBtn.disabled = selected.length === 0;
        }
        if (bulkEndorseBtn) {
            bulkEndorseBtn.disabled = !selected.some(cb => cb.hasAttribute('data-can-endorse'));
        }
        if (selectAllBtn) {
            selectAllBtn.textContent = (checkboxes.length > 0 && selected.length === checkboxes.length) ?
                'Deselect All' :
                'Select All';
        }
    }

    toggleSelectBtn?.addEventListener('click', () => {
        const active = protocolsList.classList.toggle('selection-mode');
        if (toggleSelectBtnLabel) toggleSelectBtnLabel.textContent = active ? 'Cancel' : 'Select';
        if (toggleSelectBtnIcon) toggleSelectBtnIcon.hidden = active;
        if (bulkSelectControls) bulkSelectControls.hidden = !active;
        if (!active) {
            protocolsList.querySelectorAll('.protocol-select-checkbox').forEach(cb => cb.checked = false);
        }
        updateBulkSelectUI();
    });

    protocolsList?.addEventListener('change', e => {
        if (!e.target.classList.contains('protocol-select-checkbox')) return;
        updateBulkSelectUI();
    });

    protocolsList?.addEventListener('click', e => {
        if (!protocolsList.classList.contains('selection-mode')) return;
        if (e.target.closest('a, button, input, label, select')) return;
        const checkbox = e.target.closest('.protocol')?.querySelector('.protocol-select-checkbox:not(:disabled)');
        if (!checkbox) return;
        checkbox.checked = !checkbox.checked;
        updateBulkSelectUI();
    });

    selectAllBtn?.addEventListener('click', () => {
        const checkboxes = [...(protocolsList?.querySelectorAll(VISIBLE_SELECTABLE) ?? [])];
        const allSelected = checkboxes.length > 0 && checkboxes.every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allSelected);
        updateBulkSelectUI();
    });

    // ===== Selected protocol ids =====
    function selectedProtocolIds(selector) {
        return [...(protocolsList?.querySelectorAll(selector) ?? [])]
            .map(cb => parseInt(cb.closest('.protocol').dataset.protocolId, 10))
            .filter(Boolean);
    }

    bulkDownloadBtn?.addEventListener('click', () => {
        const protocolIds = selectedProtocolIds('.protocol-select-checkbox:checked');
        if (protocolIds.length === 0) return;
        window.location.href = ROOT_URL + '/apply/download_selected?ids=' + protocolIds.join(',');
    });

    bulkEndorseBtn?.addEventListener('click', () => {
        const protocolIds = selectedProtocolIds('.protocol-select-checkbox[data-can-endorse]:checked');
        const skippedCount = selectedProtocolIds('.protocol-select-checkbox:checked').length - protocolIds.length;

        if (protocolIds.length === 0) return;

        const skippedNote = skippedCount > 0 ?
            ` ${skippedCount} selected protocol${skippedCount === 1 ? ' is' : 's are'} not paid with a signed scan and will be skipped.` :
            '';

        confirmAction(
            `Mark ${protocolIds.length} selected protocol${protocolIds.length === 1 ? '' : 's'} as endorsed? Double-check that payment is verified and the signed scans are correct before proceeding.${skippedNote}`, {
                okText: 'Mark as Endorsed',
                cancelText: 'Cancel'
            }
        ).then(ok => ok && submitBulkEndorse(protocolIds, bulkEndorseBtn));
    });

    async function submitBulkEndorse(protocolIds, btn) {
        setButtonBusy(btn, true, 'Endorsing...');

        const results = await Promise.all(protocolIds.map(protocolId =>
            fetch(STATUS_API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({
                    protocol_id: protocolId,
                    status: 'Endorsed'
                }),
            })
            .then(res => res.json())
            .then(data => ({
                protocolId,
                ok: !!data.ok
            }))
            .catch(() => ({
                protocolId,
                ok: false
            }))
        ));

        const failedCount = results.filter(r => !r.ok).length;
        const okCount = results.length - failedCount;

        if (failedCount > 0) {
            setButtonBusy(btn, false);
            alert(
                okCount > 0 ?
                `${okCount} of ${results.length} protocol(s) were marked as endorsed. ${failedCount} could not be updated and may no longer be eligible.` :
                `Could not mark the selected protocol(s) as endorsed. They may no longer be eligible, or a network error occurred.`
            );
        }

        if (okCount > 0) {
            window.location.reload();
        }
    }


    // ===== Status guide (colors & text) =====
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

    // ===== Status filter tabs =====
    filterPills.forEach(pill => {
        pill.addEventListener('click', () => {
            filterPills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            activeFilter = pill.dataset.filter;
            currentPage = 1;

            pill.scrollIntoView({
                block: 'nearest',
                inline: 'center'
            });

            updateStatusGuide(activeFilter);

            const url = new URL(window.location);
            url.searchParams.set('status', activeFilter);
            history.replaceState(null, '', url);
            updateBulkActionsBarVisibility();
            renderTable();
        });
    });

    // ===== Payment status filter =====
    paymentFilterSelect?.addEventListener('change', () => {
        currentPage = 1;
        renderTable();
    });

    // ===== Search =====
    searchInput?.addEventListener('input', () => {
        searchQuery = searchInput.value.trim().toLowerCase();
        searchClearBtn.classList.toggle('visible', searchQuery.length > 0);
        currentPage = 1;
        renderTable();
    });

    searchClearBtn?.addEventListener('click', () => {
        searchInput.value = '';
        searchQuery = '';
        searchClearBtn.classList.remove('visible');
        currentPage = 1;
        renderTable();
        searchInput.focus();
    });

    // ===== Rows-per-page selector =====
    rowsPerPageSel?.addEventListener('change', () => {
        rowsPerPage = parseInt(rowsPerPageSel.value, 10);
        currentPage = 1;
        renderTable();
    });

    // ===== Restore filter from URL param (?status=...) =====
    (function restoreFilterFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const requestedStatus = urlParams.get('status') || (urlParams.get('tab') === 'clearance' ? 'endorsed' : null);
        if (requestedStatus) {
            const matchingPill = [...filterPills].find(p => p.dataset.filter === requestedStatus);
            if (matchingPill) {
                filterPills.forEach(p => p.classList.remove('active'));
                matchingPill.classList.add('active');
                activeFilter = requestedStatus;
                matchingPill.scrollIntoView({
                    block: 'nearest',
                    inline: 'center'
                });
            }
        } else {
            const url = new URL(window.location);
            url.searchParams.set('status', activeFilter);
            history.replaceState(null, '', url);
        }
        updateStatusGuide(activeFilter);
        updateBulkActionsBarVisibility();
    })();

    // ===== Main render =====
    function renderTable() {
        const visibleRows = allRows.filter(row => {
            const slug = row.dataset.filterSlug;
            const title = row.querySelector('.research-title')?.textContent.toLowerCase() ?? '';
            const researcher = row.dataset.researcher ?? '';

            const matchesFilter =
                activeFilter === 'all' ||
                slug === activeFilter;

            const matchesSearch = !searchQuery ||
                title.includes(searchQuery) ||
                researcher.includes(searchQuery);

            const matchesPayment = !paymentFilterSelect?.value ||
                row.dataset.payment === paymentFilterSelect.value;

            return matchesFilter && matchesSearch && matchesPayment;
        });

        allRows.forEach(row => row.classList.add('protocol-row-hidden'));

        const totalRows = visibleRows.length;
        const totalPages = Math.max(1, Math.ceil(totalRows / rowsPerPage));
        if (currentPage > totalPages) currentPage = totalPages;
        saveView();

        const startIndex = (currentPage - 1) * rowsPerPage;
        const pageRows = visibleRows.slice(startIndex, startIndex + rowsPerPage);

        pageRows.forEach(row => row.classList.remove('protocol-row-hidden'));

        allRows.filter(row => row.classList.contains('protocol-row-hidden'))
            .forEach(row => row.querySelectorAll('.protocol-select-checkbox').forEach(cb => cb.checked = false));
        updateBulkSelectUI();

        if (noResultsMsg) {
            noResultsMsg.style.display = totalRows === 0 ? 'flex' : 'none';
        }

        if (resultsSummary) {
            resultsSummary.textContent = `Showing ${totalRows} protocol${totalRows === 1 ? '' : 's'}`;
        }

        if (paginationInfo) {
            if (totalRows === 0) {
                paginationInfo.textContent = 'No protocols found';
            } else {
                const from = startIndex + 1;
                const to = Math.min(startIndex + rowsPerPage, totalRows);
                paginationInfo.textContent = `Showing ${from}–${to} of ${totalRows} protocols`;
            }
        }

        renderPaginationButtons(totalPages);
    }

    // ===== Pagination buttons =====
    function renderPaginationButtons(totalPages) {
        if (!paginationBtns) return;
        paginationBtns.innerHTML = '';

        function makeBtn(label, page, isActive) {
            const btn = document.createElement('button');
            btn.className = 'pagination-btn' + (isActive ? ' active' : '');
            btn.textContent = label;
            btn.addEventListener('click', () => {
                currentPage = page;
                renderTable();
            });
            return btn;
        }

        function makeEllipsis() {
            const span = document.createElement('span');
            span.className = 'pagination-ellipsis';
            span.textContent = '...';
            return span;
        }

        const prevBtn = document.createElement('button');
        prevBtn.className = 'pagination-btn';
        prevBtn.innerHTML = '&#8249;';
        prevBtn.setAttribute('aria-label', 'Previous page');
        prevBtn.disabled = currentPage === 1;
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderTable();
            }
        });
        paginationBtns.appendChild(prevBtn);

        const pageSet = buildPageSet(currentPage, totalPages);
        let prevPageNum = null;
        pageSet.forEach(pageNum => {
            if (prevPageNum !== null && pageNum - prevPageNum > 1) {
                paginationBtns.appendChild(makeEllipsis());
            }
            paginationBtns.appendChild(makeBtn(pageNum, pageNum, pageNum === currentPage));
            prevPageNum = pageNum;
        });

        const nextBtn = document.createElement('button');
        nextBtn.className = 'pagination-btn';
        nextBtn.innerHTML = '&#8250;';
        nextBtn.setAttribute('aria-label', 'Next page');
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.addEventListener('click', () => {
            if (currentPage < totalPages) {
                currentPage++;
                renderTable();
            }
        });
        paginationBtns.appendChild(nextBtn);
    }

    // ===== Page number set =====
    function buildPageSet(current, total) {
        const pages = new Set();
        pages.add(1);
        if (total > 1) pages.add(total);
        for (let i = Math.max(1, current - 1); i <= Math.min(total, current + 1); i++) {
            pages.add(i);
        }
        return [...pages].sort((a, b) => a - b);
    }

    // ===== Row action buttons =====
    protocolsList?.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        const row = btn.closest('.protocol');
        const protocolId = parseInt(row?.dataset.protocolId, 10);
        const protocol = protocolsData.find(p => p.protocol_id == protocolId);
        const action = btn.dataset.action;

        if (!protocolId || !action) return;

        switch (action) {
            case 'open':
                window.location.href = ROOT_URL + '/apply/viewer/' + protocolId + '?from=' + encodeURIComponent(activeFilter);
                break;

            case 'view':
                window.location.href = ROOT_URL + '/apply/viewer/' + protocolId + '?from=' + encodeURIComponent(activeFilter);
                break;

            case 'show-history':
                openHistoryModal(protocolId, protocol?.research_title ?? '');
                break;

            case 'view-clearance':
                window.open(CLEARANCE_VIEW_URL + protocolId, '_blank', 'noopener');
                break;

            case 'mark-endorsed':
                confirmAction('Mark this protocol as endorsed? Double-check that payment is verified and the signed scan is correct before proceeding.', {
                    okText: 'Mark as Endorsed',
                    cancelText: 'Cancel'
                }).then(ok => ok && submitStatusChange(protocolId, 'Endorsed', btn));
                break;

            case 'revert-endorsed':
                confirmAction('Revert this protocol back to Reviewed? Use this if "Mark as Endorsed" was clicked by mistake.', {
                    okText: 'Revert',
                    cancelText: 'Cancel'
                }).then(ok => ok && submitStatusChange(protocolId, 'Reviewed', btn));
                break;

            case 'mark-approved':
                confirmAction('Mark this protocol as approved? Double-check that the attached clearance is correct before proceeding.', {
                    okText: 'Mark as Approved',
                    cancelText: 'Cancel'
                }).then(ok => ok && submitStatusChange(protocolId, 'Approved', btn));
                break;

            case 'revert-approved':
                confirmAction('Revert this protocol back to Endorsed? Use this if "Mark as Approved" was clicked by mistake.', {
                    okText: 'Revert',
                    cancelText: 'Cancel'
                }).then(ok => ok && submitStatusChange(protocolId, 'Endorsed', btn));
                break;

            case 'upload-signed-scan':
                openSignedScanModal(protocolId, protocol?.research_title ?? '');
                break;

            case 'assign-ipn':
                openNumberModal('ipn', protocolId, '');
                break;

            case 'edit-ipn':
                if (!protocol?.latest_signed_scan_version_id) {
                    openNumberModal('ipn', protocolId, protocol?.reference_no ?? '');
                    break;
                }
                confirmAction('This protocol already has a signed scan. Changing the IPN will make the signed scan not match the record.', {
                    okText: 'Edit IPN',
                    cancelText: 'Cancel',
                    danger: true
                }).then(ok => ok && openNumberModal('ipn', protocolId, protocol.reference_no ?? ''));
                break;

            case 'undo-payment':
                confirmAction('Undo this "Mark as Paid"? The protocol will go back to "Proof Submitted".', {
                    okText: 'Undo',
                    cancelText: 'Cancel'
                }).then(ok => ok && submitPaymentUndo(protocolId, btn));
                break;

            case 'review-payment':
                openReviewPaymentModal(
                    protocolId,
                    protocol?.research_title ?? '',
                    protocol?.payment_method ?? 'online',
                    `${protocol?.first_name ?? ''} ${protocol?.last_name ?? ''}`.trim(),
                    protocol?.school ?? ''
                );
                break;
        }
    });

    // ===== Payment API calls =====
    async function submitPaymentVerify(protocolId) {
        try {
            const res = await fetch(VERIFY_PAYMENT_API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({
                    protocol_id: protocolId
                }),
            });
            const data = await res.json();
            if (data.ok) {
                window.location.reload();
            } else {
                alert('Error: ' + (data.error ?? 'Could not mark as paid.'));
                setButtonBusy(document.getElementById('reviewPaymentApproveBtn'), false);
            }
        } catch (err) {
            alert('Network error. Please try again.');
            setButtonBusy(document.getElementById('reviewPaymentApproveBtn'), false);
        }
    }

    async function submitPaymentUndo(protocolId, btn) {
        setButtonBusy(btn, true, 'Undoing...');
        try {
            const res = await fetch(UNDO_PAYMENT_API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({
                    protocol_id: protocolId
                }),
            });
            const data = await res.json();
            if (data.ok) {
                window.location.reload();
            } else {
                setButtonBusy(btn, false);
                alert('Error: ' + (data.error ?? 'Could not undo payment mark.'));
            }
        } catch (err) {
            setButtonBusy(btn, false);
            alert('Network error. Please try again.');
        }
    }

    // ===== Status change API call =====
    async function submitStatusChange(protocolId, newStatus, btn) {
        setButtonBusy(btn, true);
        try {
            const res = await fetch(STATUS_API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({
                    protocol_id: protocolId,
                    status: newStatus
                }),
            });
            const data = await res.json();
            if (data.ok) {
                window.location.reload();
            } else if (data.queued) {
                setButtonBusy(btn, false);
            } else {
                setButtonBusy(btn, false);
                alert('Error: ' + (data.error ?? 'Could not update status.'));
            }
        } catch (err) {
            setButtonBusy(btn, false);
            if (!navigator.onLine) {
                alert('You are offline. The action could not be queued. Please try again when reconnected.');
            } else {
                alert('Network error. Please try again.');
            }
        }
    }

    // ===== Flash message auto-dismiss =====
    (function() {
        function dismissFlash(id, delay) {
            const el = document.getElementById(id);
            if (!el) return;
            setTimeout(() => {
                el.style.transition = 'opacity 0.4s ease';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 420);
            }, delay);
        }
        dismissFlash('flashSuccess', 4000);
        dismissFlash('flashError', 7000);
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

    // ===== Restore saved sort =====
    (function restoreSort() {
        if (!sortSelect) return;
        const savedSort = localStorage.getItem(SORT_STORAGE_KEY);
        if ([...sortSelect.options].some(option => option.value === savedSort)) {
            sortSelect.value = savedSort;
            mobileSortBtns.forEach(btn => btn.classList.toggle('active', btn.dataset.sort === savedSort));
            if (mobileSortLabel) mobileSortLabel.textContent = sortSelect.selectedOptions[0].textContent;
        }
        applySort(sortSelect.value);
    })();

    // ===== Restore saved search, rows per page, payment filter and page =====
    (function restoreView() {
        const saved = JSON.parse(sessionStorage.getItem(VIEW_STORAGE_KEY) || '{}');

        if (searchInput && saved.search) {
            searchInput.value = saved.search;
            searchQuery = saved.search.trim().toLowerCase();
            searchClearBtn.classList.toggle('visible', searchQuery.length > 0);
        }

        if (rowsPerPageSel && [...rowsPerPageSel.options].some(option => option.value === String(saved.rows))) {
            rowsPerPageSel.value = saved.rows;
            rowsPerPage = parseInt(saved.rows, 10);
        }

        if (saved.filter !== activeFilter) return;
        currentPage = saved.page || 1;
        if (paymentFilterSelect && [...paymentFilterSelect.options].some(option => option.value === saved.payment)) {
            paymentFilterSelect.value = saved.payment;
        }
    })();

    // ===== Initial render =====
    renderTable();
</script>

<?php if (($user['role'] ?? '') === 'reviewer') {
    $tourId    = 'inbox';
    $tourSteps = [
        ['Protocol Inbox', 'This is where protocols waiting for your feedback show up. Let us walk through it.'],
        ['Search', "Find a protocol by its title or the researcher's name.", '.inbox-search-wrap'],
        ['Status filters', 'Filter the inbox by status. It opens on To review, which is your queue. The number next to each name is how many protocols have that status.', '#filterPillsRow'],
        ['Sort', 'Change the order: newest or oldest submitted, or by title.', '.dashboard-sort-group'],
        ['Status colors', 'Every status has its own color and icon. Press the question mark at the end of this bar to see what each status means for you.', '.status-legend-bar'],
    ];
    if ($protocols) {
        $tourSteps = array_merge($tourSteps, [
            ['Protocol card', "Each card is one protocol. It shows the status icon, the research title, the submission round, and the researcher. Press the researcher's name to see their details.", '.protocols-list .protocol:first-visible'],
            ['Review', 'Press Review on a To review protocol to open it, read it, and give your feedback. Protocols in other statuses show View instead, and View Clearance once approved.', '.protocols-list .protocol .actions:first-visible'],
            ['Show History', "See every submission round of a protocol, including the researcher's revisions.", '.protocols-list .protocol .actions-secondary:first-visible'],
            ['Pagination', 'Move between pages and choose how many rows to show per page.', '#paginationBar'],
        ]);
    }
    include dirname(__DIR__) . '/includes/tour.php';
} ?>
<?php include dirname(__DIR__) . '/includes/history-modal.php'; ?>
<script>
    window.historyModalConfig = {
        offlineMessage: 'Submission history is not available offline. It will load once you reconnect.'
    };
</script>
<script src="<?= asset_js('history-modal.js') ?>"></script>

<!-- ===== Researcher details modal ===== -->
<div class="modal-backdrop" id="researcherModalBackdrop">
    <div class="modal-card history-modal-card researcher-modal-card">
        <div class="modal-header">
            <div>
                <p class="modal-label">Researcher</p>
                <p class="modal-title" id="researcherModalName"></p>
            </div>
            <button class="modal-close" onclick="closeResearcherModal()" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
            </button>
        </div>
        <div id="researcherModalBody" class="history-modal-body researcher-modal-body">
            <p class="helper history-loading">Loading&hellip;</p>
        </div>
    </div>
</div>

<script>
    const researcherBackdrop = document.getElementById('researcherModalBackdrop');

    // ===== Open & render researcher details =====
    function openResearcherModal(userId, fallbackName) {
        document.getElementById('researcherModalName').textContent = fallbackName || '';
        document.getElementById('researcherModalBody').innerHTML = '<p class="helper history-loading">Loading…</p>';
        researcherBackdrop.classList.add('open');

        if (!userId) {
            document.getElementById('researcherModalBody').innerHTML =
                '<p class="helper history-error">No account information available for this researcher.</p>';
            return;
        }

        fetch(ROOT_URL + '/personnel/researcher_details?id=' + encodeURIComponent(userId))
            .then(r => r.json())
            .then(data => {
                if (!data.ok) {
                    document.getElementById('researcherModalBody').innerHTML =
                        '<p class="helper history-error">' + escapeHtml(data.message || 'Could not load researcher details.') + '</p>';
                    return;
                }
                renderResearcherDetails(data.data);
            })
            .catch(() => {
                document.getElementById('researcherModalBody').innerHTML =
                    '<p class="helper history-offline">Researcher details are not available offline. It will load once you reconnect.</p>';
            });
    }

    function renderResearcherDetails(d) {
        document.getElementById('researcherModalName').textContent = (d.first_name + ' ' + d.last_name).trim();

        const joined = d.created_at ? formatDate(d.created_at) : 'N/A';

        const rows = [
            ['Username', d.username],
            ['Email', d.email],
            ['Phone', d.phone_number || 'N/A'],
            ['School', d.school || 'N/A'],
            ['Role', d.role ? d.role.charAt(0).toUpperCase() + d.role.slice(1) : 'N/A'],
            ['Account status', d.status ? d.status.charAt(0).toUpperCase() + d.status.slice(1) : 'N/A'],
            ['Joined', joined],
        ].map(([label, value]) => `
            <div class="researcher-detail-row">
                <span class="researcher-detail-label">${escapeHtml(label)}</span>
                <span class="researcher-detail-value">${escapeHtml(value)}</span>
            </div>
        `).join('');

        const protocolsHtml = (d.protocols && d.protocols.length) ?
            d.protocols.map(p => `
                <li class="researcher-protocol-item">
                    <span class="researcher-protocol-title">${escapeHtml(p.research_title)}</span>
                    <span class="researcher-protocol-meta">
                        ${escapeHtml(p.reference_no || '')} &middot; ${escapeHtml(p.status)}
                        ${p.submitted_at ? ' &middot; ' + formatDate(p.submitted_at) : ''}
                    </span>
                </li>
            `).join('') :
            '<li class="researcher-protocol-item researcher-protocol-empty">No protocols submitted yet.</li>';

        document.getElementById('researcherModalBody').innerHTML = `
            <div class="researcher-detail-rows">${rows}</div>
            <div class="researcher-protocol-section">
                <p class="researcher-protocol-heading">Protocols (${d.protocol_count})</p>
                <ul class="researcher-protocol-list">${protocolsHtml}</ul>
            </div>
        `;
    }

    // ===== Close researcher modal =====
    function closeResearcherModal() {
        researcherBackdrop.classList.remove('open');
    }

    researcherBackdrop.addEventListener('click', e => {
        if (e.target === researcherBackdrop) closeResearcherModal();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeResearcherModal();
    });

    document.querySelectorAll('.researcher-name-link').forEach(btn => {
        btn.addEventListener('click', () => {
            openResearcherModal(btn.dataset.userId, btn.dataset.researcherName);
        });
    });
</script>

<?php include dirname(__DIR__) . '/includes/file-popup.php'; ?>
<script src="<?= asset_js('file-popup.js') ?>"></script>

<!-- ===== Upload Signed Scan modal (administrative staff only) ===== -->
<div class="modal-backdrop" id="signedScanModalBackdrop">
    <div class="modal-card">
        <h2>Upload Signed Scan</h2>
        <p id="signedScanSubtitle" class="modal-subtitle"></p>

        <div id="signedScanError" class="alert error-messages" hidden></div>

        <div class="modal-file-row">
            <div class="modal-file-info">
                <div class="modal-file-title">Signed protocol (scan or photo) <span class="required-asterisk">*</span></div>
                <div class="modal-file-subtitle" id="signedScanFileSubtitle">PDF or image &middot; max 10 MB</div>
            </div>
            <label class="modal-file-picker">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#upload-icon" />
                </svg>
                <span id="signedScanFilePickerLabel">Upload</span>
                <input type="file" id="signed_scan_file" name="signed_scan_file"
                    accept=".pdf,application/pdf,.jpg,.jpeg,.png,image/jpeg,image/png" required
                    onchange="handleSignedScanFileChange(this)">
            </label>
        </div>

        <div class="upload-progress-container" id="signedScanProgress"></div>

        <div class="modal-actions">
            <button class="button" type="button" onclick="closeSignedScanModal()">Cancel</button>
            <button class="button btn-apply" type="button" id="signedScanSubmitBtn"
                onclick="submitSignedScanUpload()">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#upload-icon" />
                </svg>
                Upload Signed Scan
            </button>
        </div>
    </div>
</div>

<script>
    const signedScanModal = document.getElementById('signedScanModalBackdrop');
    let currentSignedScanProtocolId = null;

    // ===== Signed scan modal script =====
    function openSignedScanModal(protocolId, title) {
        currentSignedScanProtocolId = protocolId;
        document.getElementById('signedScanSubtitle').textContent = title;
        document.getElementById('signed_scan_file').value = '';
        resetSignedScanFilePicker();
        document.getElementById('signedScanError').hidden = true;
        document.getElementById('signedScanProgress').innerHTML = '';
        signedScanModal.classList.add('open');
    }

    function closeSignedScanModal() {
        signedScanModal.classList.remove('open');
        currentSignedScanProtocolId = null;
    }

    signedScanModal.addEventListener('click', e => {
        if (e.target === signedScanModal) closeSignedScanModal();
    });

    function resetSignedScanFilePicker() {
        document.getElementById('signedScanFilePickerLabel').textContent = 'Upload';
        const subtitle = document.getElementById('signedScanFileSubtitle');
        subtitle.textContent = 'PDF or image · max 10 MB';
        subtitle.classList.remove('done');
    }

    function handleSignedScanFileChange(input) {
        const subtitle = document.getElementById('signedScanFileSubtitle');
        if (input.files.length) {
            document.getElementById('signedScanFilePickerLabel').textContent = 'Replace';
            subtitle.textContent = input.files[0].name;
            subtitle.classList.add('done');
        } else {
            resetSignedScanFilePicker();
        }
    }

    async function submitSignedScanUpload() {
        const fileInput = document.getElementById('signed_scan_file');
        const errBox = document.getElementById('signedScanError');
        const btn = document.getElementById('signedScanSubmitBtn');

        if (!fileInput.files.length) {
            errBox.textContent = 'Please select a file.';
            errBox.hidden = false;
            return;
        }

        setButtonBusy(btn, true, 'Uploading...');
        errBox.hidden = true;

        const progressContainer = document.getElementById('signedScanProgress');
        progressContainer.innerHTML = '';
        const bar = createUploadProgressBar(progressContainer);

        const formData = new FormData();
        formData.append('protocol_id', currentSignedScanProtocolId);
        formData.append('signed_scan_file', fileInput.files[0]);
        formData.append('csrf_token', CSRF_TOKEN);

        try {
            const data = await uploadWithProgress(SIGNED_SCAN_UPLOAD_API, formData, {
                headers: {
                    'X-CSRF-Token': CSRF_TOKEN
                },
                onProgress: pct => bar.update(pct)
            });

            if (data.success) {
                window.location.reload();
            } else {
                errBox.textContent = data.error ?? 'Upload failed. Please try again.';
                errBox.hidden = false;
                setButtonBusy(btn, false);
                bar.remove();
            }
        } catch (err) {
            errBox.textContent = err.message || 'Network error. Please try again.';
            errBox.hidden = false;
            setButtonBusy(btn, false);
            bar.remove();
        }
    }
</script>

<!-- ===== Verify Payment modal (administrative staff only): shows the proof image (if any), then Approve / Reject ===== -->
<div class="modal-backdrop" id="reviewPaymentModalBackdrop">
    <div class="modal-card file-popup-card review-payment-card">
        <div class="file-popup-header">
            <span class="file-popup-title">Payment Proof for Validation</span>
            <button class="modal-close" type="button" onclick="closeReviewPaymentModal()" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
            </button>
        </div>

        <div class="review-payment-summary" id="reviewPaymentSummary">
            <p class="review-payment-summary-meta" id="reviewPaymentSummaryMeta"></p>
            <p class="review-payment-summary-title">For the research: "<span id="reviewPaymentSummaryTitle"></span>"</p>
        </div>

        <div class="review-payment-image-frame" id="reviewPaymentImageFrame">
            <p class="review-payment-note">Loading proof of payment&hellip;</p>
        </div>

        <div class="review-payment-footer">
            <!-- Step 1: approve or start a rejection -->
            <div class="modal-actions review-payment-primary-actions" id="reviewPaymentActions">
                <button class="button confirm-modal-ok-danger" type="button" onclick="showRejectPaymentReason()">
                    Reject
                </button>
                <button class="button btn-apply" type="button" id="reviewPaymentApproveBtn"
                    onclick="approveReviewedPayment()">
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
                    <button class="button" type="button" onclick="hideRejectPaymentReason()">Back</button>
                    <button class="button confirm-modal-ok-danger" type="button" id="rejectPaymentSubmitBtn"
                        onclick="submitRejectPayment()">
                        Confirm Rejection
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const reviewPaymentModal = document.getElementById('reviewPaymentModalBackdrop');
    let currentReviewPaymentProtocolId = null;

    function openReviewPaymentModal(protocolId, title, paymentMethod, researcherName, school) {
        currentReviewPaymentProtocolId = protocolId;
        document.getElementById('reviewPaymentSummaryTitle').textContent = title;
        document.getElementById('reviewPaymentSummaryMeta').textContent = [researcherName, school].filter(Boolean).join(' \u00b7 ');
        resetReviewPaymentModal();
        reviewPaymentModal.classList.add('open');
        if (paymentMethod === 'in_person') {
            document.getElementById('reviewPaymentImageFrame').innerHTML =
                '<p class="review-payment-note">The researcher confirmed paying the fee in person at CCARD. Verify with your records before approving.</p>';
        } else {
            loadPaymentProofImage(protocolId);
        }
    }

    function closeReviewPaymentModal() {
        reviewPaymentModal.classList.remove('open');
        currentReviewPaymentProtocolId = null;
    }

    function resetReviewPaymentModal() {
        document.getElementById('reviewPaymentImageFrame').innerHTML =
            '<p class="review-payment-note">Loading proof of payment&hellip;</p>';
        document.getElementById('reviewPaymentActions').hidden = false;
        document.getElementById('reviewPaymentRejectPanel').hidden = true;
        document.getElementById('reject_payment_comment').value = '';
        document.getElementById('rejectPaymentError').hidden = true;
        setButtonBusy(document.getElementById('rejectPaymentSubmitBtn'), false);
        setButtonBusy(document.getElementById('reviewPaymentApproveBtn'), false);
    }

    reviewPaymentModal.addEventListener('click', e => {
        if (e.target === reviewPaymentModal) closeReviewPaymentModal();
    });

    // ===== Auto-open from notification/email links =====
    document.addEventListener('DOMContentLoaded', () => {
        const openPaymentId = parseInt(new URLSearchParams(window.location.search).get('open_payment'), 10);
        if (!openPaymentId) return;
        const protocol = protocolsData.find(p => p.protocol_id == openPaymentId);
        if (!protocol) return;
        openReviewPaymentModal(
            openPaymentId,
            protocol.research_title ?? '',
            protocol.payment_method ?? 'online',
            `${protocol.first_name ?? ''} ${protocol.last_name ?? ''}`.trim(),
            protocol.school ?? ''
        );
    });

    async function loadPaymentProofImage(protocolId) {
        const frame = document.getElementById('reviewPaymentImageFrame');
        try {
            const res = await fetch(ROOT_URL + '/apply/allversions/' + protocolId);
            const data = await res.json();
            const latest = data?.payment_proof_files?.[0];

            if (!latest) {
                frame.innerHTML = '<p class="review-payment-note">No proof of payment file was found for this protocol.</p>';
                return;
            }

            frame.innerHTML = '';
            const img = document.createElement('img');
            img.src = latest.file_url;
            img.alt = 'Proof of payment';
            img.onerror = () => {
                frame.innerHTML = '<p class="review-payment-note">Could not load the proof of payment image.</p>';
            };
            frame.appendChild(img);
        } catch (err) {
            frame.innerHTML = '<p class="review-payment-note">Network error while loading the proof of payment.</p>';
        }
    }

    // ===== Reject payment reason =====
    function showRejectPaymentReason() {
        document.getElementById('reviewPaymentActions').hidden = true;
        document.getElementById('reviewPaymentRejectPanel').hidden = false;
        document.getElementById('reject_payment_comment').focus();
    }

    function hideRejectPaymentReason() {
        document.getElementById('reviewPaymentRejectPanel').hidden = true;
        document.getElementById('reviewPaymentActions').hidden = false;
    }

    // ===== Approve reviewed payment =====
    function approveReviewedPayment() {
        confirmAction('Mark this protocol as paid? Make sure the payment checks out before approving.', {
            okText: 'Mark as Paid',
            cancelText: 'Cancel'
        }).then(ok => {
            if (!ok) return;
            const btn = document.getElementById('reviewPaymentApproveBtn');
            setButtonBusy(btn, true, 'Marking as Paid...');
            submitPaymentVerify(currentReviewPaymentProtocolId);
        });
    }

    async function submitRejectPayment() {
        const comment = document.getElementById('reject_payment_comment').value.trim();
        const errBox = document.getElementById('rejectPaymentError');
        const btn = document.getElementById('rejectPaymentSubmitBtn');

        if (!comment) {
            errBox.textContent = 'Please explain why this proof is being rejected.';
            errBox.hidden = false;
            return;
        }

        errBox.hidden = true;

        const ok = await confirmAction(
            'Reject this payment? The researcher will be notified and asked to resubmit.', {
                okText: 'Reject Proof',
                cancelText: 'Cancel',
                danger: true
            }
        );
        if (!ok) return;

        setButtonBusy(btn, true, 'Rejecting...');

        try {
            const res = await fetch(REJECT_PAYMENT_API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({
                    protocol_id: currentReviewPaymentProtocolId,
                    comment
                }),
            });
            const data = await res.json();

            if (data.ok) {
                window.location.reload();
            } else {
                errBox.textContent = data.error ?? 'Could not reject. Please try again.';
                errBox.hidden = false;
                setButtonBusy(btn, false);
            }
        } catch (err) {
            errBox.textContent = 'Network error. Please try again.';
            errBox.hidden = false;
            setButtonBusy(btn, false);
        }
    }
</script>

<!-- ===== Clearance board (staff only) ===== -->
<?php if ($personnelRole === 'staff' && !empty($protocols)): ?>
    <script>
        const CLEARANCE_POOL_API = ROOT_URL + '/apply/clearance_pool';
        const CLEARANCE_POOL_UPLOAD_API = ROOT_URL + '/apply/clearance_pool_upload';
        const CLEARANCE_STAGE_API = ROOT_URL + '/apply/clearance_stage';
        const CLEARANCE_UNSTAGE_API = ROOT_URL + '/apply/clearance_unstage';
        const CLEARANCE_CONFIRM_API = ROOT_URL + '/apply/clearance_confirm';
        const CLEARANCE_DELETE_API = ROOT_URL + '/apply/clearance_delete';
        const ASSIGN_IPN_API = ROOT_URL + '/apply/assign_ipn';
        const ASSIGN_AR_NUMBER_API = ROOT_URL + '/apply/assign_ar_number';

        let boardData = {
            unassigned: [],
            staged: [],
            endorsed_protocols: [],
        };
        let selectedPoolId = null;
        let clearanceBoardLoaded = false;

        function ensureClearanceBoard() {
            if (clearanceBoardLoaded) return;
            clearanceBoardLoaded = true;
            loadBoard();
        }

        // ===== Flash & thumbnail helpers =====
        function showFlash(message, isError = false) {
            const existing = document.getElementById('flashSuccess');
            if (existing) existing.remove();

            const flash = document.createElement('div');
            flash.className = isError ? 'alert error-messages' : 'alert success-message';
            flash.id = 'flashSuccess';
            flash.textContent = message;

            const main = document.getElementById('main-content');
            main.insertBefore(flash, main.firstChild);

            setTimeout(() => flash.remove(), 4000);
        }

        function isImage(name) {
            return /\.(jpe?g|png)$/i.test(name || '');
        }

        function thumbHtml(item, {
            allowDelete = true
        } = {}) {
            const deleteBtn = allowDelete ? `
            <button type="button" class="clearance-delete-btn" title="Delete" aria-label="Delete"
                onclick="event.stopPropagation(); deletePoolItem(${item.id}, this)">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#close-icon" /></svg>
            </button>` : '';

            if (!isImage(item.original_name)) {
                return `<div class="clearance-file-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#review-icon" /></svg>
                    ${deleteBtn}
                </div>`;
            }
            const safeName = escapeHtml(item.original_name).replace(/'/g, "\\'");
            return `<div class="clearance-thumb-media">
                <img src="${item.file_url}" alt="${escapeHtml(item.original_name)}" loading="lazy">
                <button type="button" class="clearance-zoom-btn" title="Preview Image" aria-label="Preview Image"
                    onclick="openZoom(event, '${item.file_url}', '${safeName}')">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#search-icon" /></svg>
                </button>
                ${deleteBtn}
            </div>`;
        }

        async function loadBoard() {
            try {
                const res = await fetch(CLEARANCE_POOL_API);
                boardData = await res.json();
            } catch (err) {
                showFlash('Could not load the clearances. Please refresh.', true);
                return;
            }
            if (selectedPoolId !== null && !(boardData.unassigned || []).some(item => Number(item.id) === selectedPoolId)) {
                selectedPoolId = null;
            }
            renderTray();
            renderSlots();
            applySelection();
            updateConfirmButton();
        }

        function toggleSelectThumb(poolId) {
            poolId = Number(poolId);
            selectedPoolId = selectedPoolId === poolId ? null : poolId;
            applySelection();
        }

        function applySelection() {
            document.querySelectorAll('#trayGrid .clearance-thumb').forEach(el => {
                el.classList.toggle('is-selected', selectedPoolId !== null && Number(el.dataset.poolId) === selectedPoolId);
            });
            document.querySelectorAll('.clearance-slot').forEach(el => {
                el.classList.toggle('is-target', selectedPoolId !== null && el.dataset.canReceive === '1');
            });
        }

        const listCaches = new Map();

        function reconcileList(container, entries, emptyHtml) {
            let cache = listCaches.get(container.id);
            if (!cache) {
                cache = new Map();
                listCaches.set(container.id, cache);
            }

            if (!entries.length) {
                cache.clear();
                container.innerHTML = emptyHtml;
                return;
            }

            container.querySelectorAll(':scope > .clearance-empty').forEach(el => el.remove());

            const seen = new Set();
            let prev = null;
            entries.forEach(({
                key,
                html
            }) => {
                seen.add(key);
                let rec = cache.get(key);
                if (!rec || rec.html !== html) {
                    const tpl = document.createElement('template');
                    tpl.innerHTML = html.trim();
                    const el = tpl.content.firstElementChild;
                    if (rec) rec.el.replaceWith(el);
                    rec = {
                        el,
                        html
                    };
                    cache.set(key, rec);
                }
                const expected = prev ? prev.nextElementSibling : container.firstElementChild;
                if (rec.el !== expected) container.insertBefore(rec.el, expected);
                prev = rec.el;
            });

            cache.forEach((rec, key) => {
                if (!seen.has(key)) {
                    rec.el.remove();
                    cache.delete(key);
                }
            });
        }

        function renderTray() {
            const grid = document.getElementById('trayGrid');
            const items = boardData.unassigned || [];

            reconcileList(grid, items.map(item => ({
                key: item.id,
                html: `
            <div class="clearance-thumb" draggable="true" data-pool-id="${item.id}"
                ondragstart="onDragStart(event, ${item.id})"
                onclick="toggleSelectThumb(${item.id})">
                ${thumbHtml(item)}
                <span class="clearance-thumb-name">${escapeHtml(item.original_name)}</span>
            </div>`
            })), '<p class="helper clearance-empty">No unsorted screenshots. Use Upload Clearances to add some.</p>');
        }

        function stagedFor(protocolId) {
            return (boardData.staged || []).find(s => Number(s.protocol_id) === Number(protocolId));
        }

        function renderSlots() {
            const endorsedById = new Map((boardData.endorsed_protocols || []).map(p => [Number(p.protocol_id), p]));

            document.querySelectorAll('.clearance-slot').forEach(slot => {
                const protocolId = Number(slot.dataset.protocolId);
                const p = endorsedById.get(protocolId);
                const staged = stagedFor(protocolId);
                const hasArNumber = !!(p && p.ar_number);
                const arNumber = p ? (p.ar_number || '') : '';

                let html = '';
                if (p && staged) {
                    html = `
                        <div class="clearance-thumb clearance-thumb--staged">
                            ${thumbHtml(staged, { allowDelete: false })}
                            <button type="button" class="clearance-unstage-btn" data-pool-id="${staged.id}" title="Remove" aria-label="Remove">
                                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#close-icon" /></svg>
                            </button>
                        </div>
                        <span class="helper clearance-slot-hint">Ready to confirm.</span>`;
                } else if (p && !p.latest_clearance_version_id) {
                    html = hasArNumber ?
                        `<span class="helper clearance-slot-hint">Drop a screenshot here, or tap it after selecting one.
                            AR No.: ${escapeHtml(arNumber)}
                            <a href="#" class="clearance-ar-link" data-protocol-id="${protocolId}" data-ar-number="${escapeHtml(arNumber)}">Edit AR number</a></span>` :
                        `<span class="helper clearance-slot-hint">Assign an AR number before attaching a clearance.
                            <a href="#" class="clearance-ar-link" data-protocol-id="${protocolId}" data-ar-number="">Add AR number</a></span>`;
                }

                slot.dataset.canReceive = p && hasArNumber && !p.latest_clearance_version_id ? '1' : '0';
                slot.classList.toggle('has-staged', !!staged);
                slot.classList.toggle('no-ar', !!p && !hasArNumber);

                if (slot.dataset.render !== html) {
                    slot.innerHTML = html;
                    slot.dataset.render = html;
                }
            });
        }

        function updateConfirmButton() {
            const count = (boardData.staged || []).length;
            document.getElementById('stagedCount').textContent = count;
            document.getElementById('confirmBtn').disabled = count === 0;
        }

        // ===== Upload: staff adds screenshots straight into the unsorted tray =====
        const clearanceFileInput = document.getElementById('clearanceFileInput');
        const clearanceUploadBtn = document.getElementById('clearanceUploadBtn');

        clearanceUploadBtn.addEventListener('click', () => clearanceFileInput.click());
        clearanceFileInput.addEventListener('change', () => uploadClearances(clearanceFileInput.files));

        async function uploadClearances(fileList) {
            if (!fileList.length) return;

            const progressContainer = document.getElementById('clearanceUploadProgress');
            progressContainer.innerHTML = '';
            const bar = createUploadProgressBar(progressContainer);
            setButtonBusy(clearanceUploadBtn, true, 'Uploading...');

            const formData = new FormData();
            for (const file of fileList) {
                formData.append('clearance_screenshots[]', file);
            }
            formData.append('csrf_token', CSRF_TOKEN);

            try {
                const data = await uploadWithProgress(CLEARANCE_POOL_UPLOAD_API, formData, {
                    headers: {
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    onProgress: pct => bar.update(pct)
                });

                if (data.success) {
                    const skipped = (data.failures && data.failures.length) ? ' Some files were skipped: ' + data.failures.join(' ') : '';
                    showFlash(`${data.inserted} screenshot(s) uploaded.${skipped}`, !!skipped);
                } else {
                    showFlash((data.failures && data.failures.length) ? data.failures.join(' ') : (data.error ?? 'Upload failed.'), true);
                }
            } catch (err) {
                showFlash(err.message || 'Network error. Please try again.', true);
            }

            bar.remove();
            setButtonBusy(clearanceUploadBtn, false);
            clearanceFileInput.value = '';
            loadBoard();
        }

        // ===== Drag and drop, and tap-to-place (for touch screens) =====
        function onDragStart(e, poolId) {
            e.dataTransfer.setData('text/plain', String(poolId));
        }

        function receivingSlot(e) {
            const slot = e.target.closest('.clearance-slot');
            return slot && slot.dataset.canReceive === '1' ? slot : null;
        }

        protocolsList.addEventListener('dragover', e => {
            const slot = receivingSlot(e);
            if (!slot) return;
            e.preventDefault();
            slot.classList.add('is-drag-over');
        });

        protocolsList.addEventListener('dragleave', e => {
            e.target.closest?.('.clearance-slot')?.classList.remove('is-drag-over');
        });

        protocolsList.addEventListener('drop', e => {
            const slot = receivingSlot(e);
            if (!slot) return;
            e.preventDefault();
            slot.classList.remove('is-drag-over');
            const poolId = Number(e.dataTransfer.getData('text/plain'));
            if (!poolId) return;
            stageItem(poolId, Number(slot.dataset.protocolId));
        });

        protocolsList.addEventListener('click', e => {
            const arLink = e.target.closest('.clearance-ar-link');
            if (arLink) {
                e.preventDefault();
                openNumberModal('ar', Number(arLink.dataset.protocolId), arLink.dataset.arNumber || '');
                return;
            }

            const unstageBtn = e.target.closest('.clearance-unstage-btn');
            if (unstageBtn) {
                unstage(Number(unstageBtn.dataset.poolId));
                return;
            }

            const slot = receivingSlot(e);
            if (!slot || selectedPoolId === null) return;
            const poolId = selectedPoolId;
            selectedPoolId = null;
            applySelection();
            stageItem(poolId, Number(slot.dataset.protocolId));
        });

        async function stageItem(poolId, protocolId) {
            try {
                const res = await fetch(CLEARANCE_STAGE_API, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        pool_id: poolId,
                        protocol_id: protocolId
                    }),
                });
                const data = await res.json();
                if (!data.success) showFlash(data.error || 'Could not match that screenshot.', true);
            } catch (err) {
                showFlash('Network error. Please try again.', true);
            }
            loadBoard();
        }

        async function unstage(poolId) {
            try {
                const res = await fetch(CLEARANCE_UNSTAGE_API, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        pool_id: poolId
                    }),
                });
                const data = await res.json();
                if (!data.success) showFlash(data.error || 'Could not undo that match.', true);
            } catch (err) {
                showFlash('Network error. Please try again.', true);
            }
            loadBoard();
        }

        async function deletePoolItem(poolId, btn) {
            const ok = await confirmAction('Delete this screenshot? This cannot be undone.', {
                okText: 'Delete',
                danger: true,
            });
            if (!ok) return;

            setButtonBusy(btn, true, 'Deleting...');

            try {
                const res = await fetch(CLEARANCE_DELETE_API, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        pool_id: poolId
                    }),
                });
                const data = await res.json();
                if (!data.success) showFlash(data.error || 'Could not delete that screenshot.', true);
            } catch (err) {
                showFlash('Network error. Please try again.', true);
            }
            loadBoard();
        }

        const numberModalBackdrop = document.getElementById('numberModalBackdrop');
        let numberModalKind = null;
        let numberModalProtocolId = null;

        const NUMBER_FIELDS = {
            ipn: {
                label: 'IPN',
                api: ASSIGN_IPN_API,
                key: 'reference_no',
                placeholder: 'e.g. 000026',
                pattern: /^\d{6}$/,
                formatError: 'IPN must be 6 digits, with the last 2 digits as the year (e.g. 000026).',
                maxlength: 6,
                helper: 'Write this IPN on the printed protocol before the IACUC chair signs it. It is kept in sync with the Records page.',
                saved: () => window.location.reload()
            },
            ar: {
                label: 'AR Number',
                api: ASSIGN_AR_NUMBER_API,
                key: 'ar_number',
                placeholder: 'AR number from the BAI clearance',
                helper: 'This is the animal research clearance ID printed on the clearance issued by BAI.',
                saved: () => {
                    showFlash('AR number saved.');
                    loadBoard();
                }
            }
        };

        // ===== Number modal (IPN / AR) =====
        function openNumberModal(kind, protocolId, currentValue) {
            const field = NUMBER_FIELDS[kind];
            numberModalKind = kind;
            numberModalProtocolId = protocolId;
            document.getElementById('numberModalTitle').textContent = (currentValue ? 'Edit ' : 'Add ') + field.label;
            document.getElementById('numberModalHelper').textContent = field.helper;
            document.getElementById('numberModalLabel').textContent = field.label;
            document.getElementById('numberModalInput').placeholder = field.placeholder;
            if (field.maxlength) document.getElementById('numberModalInput').maxLength = field.maxlength;
            else document.getElementById('numberModalInput').removeAttribute('maxlength');
            document.getElementById('numberModalInput').inputMode = field.maxlength ? 'numeric' : 'text';
            document.getElementById('numberModalInput').value = currentValue || '';
            document.getElementById('numberModalError').hidden = true;
            numberModalBackdrop.classList.add('open');
            document.getElementById('numberModalInput').focus();
        }

        function closeNumberModal() {
            numberModalBackdrop.classList.remove('open');
            numberModalKind = null;
            numberModalProtocolId = null;
        }

        numberModalBackdrop.addEventListener('click', e => {
            if (e.target === numberModalBackdrop) closeNumberModal();
        });

        async function saveNumber() {
            const field = NUMBER_FIELDS[numberModalKind];
            const input = document.getElementById('numberModalInput');
            const errBox = document.getElementById('numberModalError');
            const value = input.value.trim();

            if (!value) {
                errBox.textContent = `Please enter an ${field.label}.`;
                errBox.hidden = false;
                return;
            }
            if (field.pattern && !field.pattern.test(value)) {
                errBox.textContent = field.formatError;
                errBox.hidden = false;
                return;
            }

            const btn = document.getElementById('numberModalSaveBtn');
            setButtonBusy(btn, true, 'Saving...');

            try {
                const res = await fetch(field.api, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        protocol_id: numberModalProtocolId,
                        [field.key]: value
                    }),
                });
                const data = await res.json();

                if (!data.success) {
                    errBox.textContent = data.error || `Could not save this ${field.label}.`;
                    errBox.hidden = false;
                    setButtonBusy(btn, false);
                    return;
                }

                const onSaved = field.saved;
                closeNumberModal();
                onSaved();
            } catch (err) {
                errBox.textContent = 'Network error. Please try again.';
                errBox.hidden = false;
            }
            setButtonBusy(btn, false);
        }

        const confirmReviewBackdrop = document.getElementById('confirmReviewBackdrop');

        document.getElementById('confirmBtn').addEventListener('click', () => {
            const staged = boardData.staged || [];
            if (staged.length === 0) return;

            document.getElementById('confirmReviewError').hidden = true;
            confirmReviewBackdrop.classList.add('open');
        });

        // ===== Confirm review =====
        function closeConfirmReview() {
            confirmReviewBackdrop.classList.remove('open');
        }

        confirmReviewBackdrop.addEventListener('click', e => {
            if (e.target === confirmReviewBackdrop) closeConfirmReview();
        });

        async function proceedConfirm() {
            const btn = document.getElementById('confirmProceedBtn');
            const errBox = document.getElementById('confirmReviewError');
            errBox.hidden = true;
            setButtonBusy(btn, true, 'Confirming...');

            try {
                const res = await fetch(CLEARANCE_CONFIRM_API, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({}),
                });
                const data = await res.json();

                if (data.confirmed > 0) {
                    window.location.reload();
                    return;
                }

                if (data.failures && data.failures.length > 0) {
                    errBox.textContent = data.failures.join(' ');
                    errBox.hidden = false;
                } else if (data.error) {
                    errBox.textContent = data.error;
                    errBox.hidden = false;
                } else {
                    closeConfirmReview();
                }
                loadBoard();
            } catch (err) {
                errBox.textContent = 'Network error. Please try again.';
                errBox.hidden = false;
            }
            setButtonBusy(btn, false);
        }

        const zoomBackdrop = document.getElementById('clearanceZoomBackdrop');
        const zoomImg = document.getElementById('clearanceZoomImg');
        const zoomCaption = document.getElementById('clearanceZoomCaption');

        // ===== Image zoom =====
        function openZoom(e, url, caption) {
            e.stopPropagation();
            zoomImg.src = url;
            zoomImg.alt = caption;
            zoomCaption.textContent = caption;
            zoomBackdrop.classList.add('open');
        }

        function closeZoom() {
            zoomBackdrop.classList.remove('open');
            zoomImg.src = '';
        }

        zoomBackdrop.addEventListener('click', e => {
            if (e.target === zoomBackdrop) closeZoom();
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && zoomBackdrop.classList.contains('open')) closeZoom();
        });

        if (activeFilter === 'endorsed') ensureClearanceBoard();
    </script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>