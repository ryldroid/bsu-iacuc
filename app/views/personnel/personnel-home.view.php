<?php

/** @var array|null  $user */
/** @var array       $protocols */
/** @var array       $statuses */
/** @var array       $actor */

$title = PERSONNEL_HOME_LABEL;

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/scroll-top.php';

$user      = $user      ?? $_SESSION['user'] ?? [];
$csrf      = $csrf      ?? '';
$protocols = $protocols ?? [];

// ===== Map internal status strings to human-readable display labels =====
$statusDisplayMap = ProtocolPermissions::PERSONNEL_STATUS_LABELS + [
    'reviewed' => 'Reviewed',
    'endorsed' => 'Endorsed',
    'approved' => 'Approved',
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
        'to-review'             => "Submitted protocols waiting on the reviewer's review. You may view, download, rename, and archive protocols. Any CCARD personnel can verify a researcher's first-time IACUC training certificate submission. Reviewers can still read and comment when a certificate is rejected, but cannot finish a review or return a protocol until it is verified.",
        'returned-for-revision' => 'Sent back to the researcher with feedback. No action needed.',
        'reviewed'              => "The reviewer has finished review. Here, in a step-by-step process:\n(1) Wait for the researcher to pay their clearance processing fee,\n(2) Verify submitted payment,\n(3) Assign the IPN (Institutional Protocol Number), and\n(4) Upload the proof that the protocol was **endorsed** to DA-RFO-CAR.",
        'endorsed'              => "Protocols have been endorsed to Department of Agriculture - Regional Field Office - Cordillera Administrative Region (DA-RFO-CAR). On the **Endorsed** tab, press **Assign Clearances** (or **Assign clearance** on a protocol) and choose the released animal research clearance images. Each image gets a row: enter its AR Number (Animal Research Number) first, then pick its protocol, then press **Approve** to mark them **approved**.",
        'approved'              => "Digital animal research clearances issued! The protocols are now fully approved. Confirm when researchers have picked up the embossed hard copy of their clearances.",
    ],
    'reviewer' => [
        'to-review'             => "Submitted protocols waiting on your feedback. You may open, review, download, rename, and archive protocols. Any CCARD personnel can verify a researcher's first-time IACUC training certificate submission. You can still read and comment even if a certificate is rejected, but you cannot finish a review or return a protocol until it is verified.",
        'returned-for-revision' => 'Sent back to the researcher with feedback. No action needed until they resubmit.',
        'reviewed'              => "You have finished the assessment. No action required. Administrative staff will now verify payments, assign IPNs, and mark protocols as endorsed.",
        'endorsed'              => "Protocol has been endorsed to Department of Agriculture - Regional Field Office - Cordillera Administrative Region (DA-RFO-CAR). No action required. Administrative staff will upload the released animal research clearances and release them to the researchers.",
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

// ===== Archived: discontinued protocols and approved protocols of inactive researchers, kept out of the main list =====
$archivedMeta = [
    'label' => 'Archive',
    'color' => '#6B7280',
    'icon'  => 'archive-icon',
];

// ===== Archive categories: why a protocol is in the Archive =====
$archiveCategoryMeta = [
    'all'          => [
        'label' => 'All',
        'desc'  => $archivedMeta['desc'],
    ],
    'ccard'        => [
        'label' => 'Archived by CCARD',
        'desc'  => 'Protocols CCARD personnel archived. Restoring one returns it to the main list.',
    ],
    'discontinued' => [
        'label' => 'Discontinued',
        'desc'  => 'Protocols the researcher stopped. Restoring one lets the researcher continue it.',
    ],
    'inactive'     => [
        'label' => 'Inactive researchers',
        'desc'  => 'Approved protocols of researchers whose accounts are inactive. They return to the main list on their own when the researcher reactivates their account.',
    ],
];

foreach ($protocols as &$protocol) {
    $key = strtolower($protocol['status']);

    $protocol['status_display'] = $statusDisplayMap[$key] ?? $protocol['status'];
    $protocol['badge_class']    = $badgeClassMap[$key]    ?? 'badge-to-review';
    $protocol['filter_slug']    = $filterSlugMap[$key]    ?? 'other';
    $protocol['stage_slug']     = $protocol['filter_slug'];

    $protocol['archive_category'] = match (true) {
        !empty($protocol['deleted_at'])      => 'ccard',
        !empty($protocol['discontinued_at']) => 'discontinued',
        ($protocol['researcher_status'] ?? '') === 'deactivated' && $key === 'approved' => 'inactive',
        default                              => '',
    };
    if ($protocol['archive_category'] !== '') {
        $protocol['filter_slug'] = 'archived';
    }
    $protocol['version_display'] = submission_round_label((int) ($protocol['latest_version'] ?? 1));
}
unset($protocol);

// ===== Counts for the archive link and the stage-specific tools =====
$countsBySlug  = array_count_values(array_column($protocols, 'filter_slug'));
$archivedCount = $countsBySlug['archived'] ?? 0;

$archiveCategoryCounts = array_count_values(array_filter(array_column($protocols, 'archive_category')));
$archiveCategories     = [];
foreach ($archiveCategoryMeta as $categoryKey => $category) {
    $archiveCategories[$categoryKey] = $category + ['count' => $categoryKey === 'all' ? $archivedCount : ($archiveCategoryCounts[$categoryKey] ?? 0)];
}
// The Archive opens on All
$archiveCategoryDefault = 'all';
$endorsedCount = $countsBySlug['endorsed'] ?? 0;

// ===== Reviewed protocols with a file to download =====
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
<?php if ($personnelRole === 'staff'): ?>
    <link rel="stylesheet" href="<?= asset_css('personnel/clearances.css') ?>">
<?php endif; ?>
<script src="<?= asset_js('dashboard-updates.js') ?>" defer></script>

<div class="body">
    <?php include dirname(__DIR__) . '/includes/navigation.php'; ?>

    <main class="main-content" id="main-content" tabindex="-1">
        <?php include dirname(__DIR__) . '/includes/update-watcher.php'; ?>

        <div class="page-banner">
            <div class="page-banner-text">
                <div class="page-banner-title">
                    <h1><?= PERSONNEL_HOME_LABEL ?></h1>
                    <?php $pageGuideId = 'inbox';
                    include dirname(__DIR__) . '/includes/page-guide.php'; ?>
                </div>
                <p class="page-subtitle">Protocols submitted for review, endorsement and clearance</p>
            </div>
        </div>

        <div class="dashboard-panel">

            <?php include dirname(__DIR__) . '/includes/flash.php'; ?>

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

                <!-- ===== Sort, status tabs, search, archive and stage guide ===== -->
                <div class="results-bar results-bar--tabs">
                    <?php $sortSelectId = 'inboxSortSelect';
                    $sortDefault  = 'submitted_asc';
                    $sortTarget   = null;
                    $sortOmit     = ['status'];
                    $sortCompact  = true;
                    include dirname(__DIR__) . '/includes/sort-control.php'; ?>
                    <?php $allCount = count($protocols) - $archivedCount;
                    include dirname(__DIR__) . '/includes/status-filters.php'; ?>
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
                                aria-label="What do the stages mean?">
                                <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
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

                <?php $searchPlaceholder = 'Search by title or researcher...';
                include dirname(__DIR__) . '/includes/inbox-search-modal.php'; ?>

                <?php if (($user['role'] ?? '') === 'staff'): ?>
                    <!-- ===== Bulk actions ===== -->
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
                                    <use href="#upload-icon" />
                                </svg>
                                Upload Proof for Selected
                            </button>
                            <button type="button" class="row-btn row-btn-outline" id="selectAllBtn">
                                Select All
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
                    <!-- ===== Clearances ===== -->
                    <section class="clearance-panel" id="clearancePanel" hidden>
                        <div class="clearance-panel-bar">
                            <h2 class="clearance-panel-title">Clearances</h2>

                            <div class="bulk-actions-bar clearance-actions">
                                <button type="button" class="row-btn row-btn-primary" id="clearanceUploadBtn"
                                    title="Upload clearance images, enter their AR Numbers and assign each to a protocol">
                                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <use href="#upload-icon" />
                                    </svg>
                                    Assign Clearances
                                </button>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- ===== Protocol list ===== -->
                <div class="protocols-list" id="protocolsList">

                    <?php
                    $archivedRows = '';
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
                        $updatedDate    = date(DATE_FORMAT, strtotime($protocol['last_activity_at']));
                        $statusDisplay  = $protocol['status_display'];
                        $badgeClass     = $protocol['badge_class'];
                        $filterSlug     = $protocol['filter_slug'];
                        $statusLower    = strtolower($protocol['status']);
                        $protocolId     = (int) $protocol['protocol_id'];
                        $paymentStatus  = $protocol['payment_status'] ?? 'unpaid';
                        $hasIpn         = !empty($protocol['reference_no']);
                        $hasHardCopyReleased = !empty($protocol['hard_copy_released_at']);
                        $researcherName = htmlspecialchars(
                            $protocol['first_name'] . ' ' . $protocol['last_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        $title = htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8');

                        if ($filterSlug === 'archived') {
                            $archiveCategory = $protocol['archive_category'];
                            ob_start();
                            include __DIR__ . '/partials/archived-protocol.php';
                            $archivedRows .= ob_get_clean();
                            continue;
                        }

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
                                    } else {
                                        $actions = [
                                            [
                                                'label' => 'Upload Endorsement Proof',
                                                'action' => 'endorse-with-proof',
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
                                    }
                                    break;

                                case 'endorsed':
                                    $actions = [
                                        [
                                            'label' => 'View Protocol',
                                            'action' => 'view',
                                            'icon' => 'review'
                                        ],
                                        [
                                            'label' => 'Undo Endorsed',
                                            'action' => 'revert-endorsed',
                                            'icon' => 'undo',
                                            'menu' => true
                                        ],
                                        [
                                            'label' => 'Mark as returned by DA-RFO-CAR or BAI',
                                            'action' => 'return-endorsed-for-revision',
                                            'icon' => 'undo',
                                            'menu' => true
                                        ]
                                    ];
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
                                            'label' => 'Undo Approved',
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

                        if ($userRole === 'staff' && in_array($statusLower, ['endorsed', 'approved'], true) && !empty($protocol['latest_endorsement_proof_version_id'])) {
                            $actions[] = [
                                'label' => 'View Endorsement Proof',
                                'action' => 'view-endorsement-proof',
                                'icon' => 'review',
                                'menu' => true
                            ];
                        }

                        if (ProtocolPermissions::canUndoReviewed($protocol)) {
                            $actions[] = [
                                'label' => 'Undo Reviewed',
                                'action' => 'revert-reviewed',
                                'icon' => 'undo',
                                'menu' => true
                            ];
                        }

                        $nextStep = null;
                        $stepCount = count(ProtocolModel::ENDORSEMENT_STEPS);
                        if ($userRole === 'staff' && $statusLower === 'reviewed') {
                            $nextStep = ProtocolModel::nextStep($paymentStatus, $hasIpn);
                        }
                    ?>
                        <div class="protocol protocol--timeline"
                            data-protocol-id="<?= $protocolId ?>"
                            data-filter-slug="<?= $filterSlug ?>"
                            data-payment="<?= htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') ?>"
                            data-researcher="<?= strtolower(htmlspecialchars($protocol['first_name'] . ' ' . $protocol['last_name'], ENT_QUOTES, 'UTF-8')) ?>"
                            data-submitted="<?= htmlspecialchars(date('c', strtotime($protocol['submitted_at'])), ENT_QUOTES, 'UTF-8') ?>"
                            data-updated="<?= htmlspecialchars(date('c', strtotime($protocol['last_activity_at'])), ENT_QUOTES, 'UTF-8') ?>"
                            data-title="<?= htmlspecialchars(strtolower($protocol['research_title']), ENT_QUOTES, 'UTF-8') ?>">

                            <div class="protocol-meta">
                                <p class="research-title">
                                    <?= $title ?>
                                    <?php if ($statusLower === 'reviewed'): ?>
                                        <span class="status-dot payment-badge payment-badge--<?= htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') ?>" <?= $nextStep ? ' title="Step ' . $nextStep[0] . ' of ' . $stepCount . ': ' . htmlspecialchars($nextStep[1], ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                            <?= htmlspecialchars(ProtocolModel::PAYMENT_LABELS[$paymentStatus] ?? 'Unpaid', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php elseif ($statusLower === 'approved'): ?>
                                        <span class="status-dot payment-badge payment-badge--<?= $hasHardCopyReleased ? 'paid' : 'proof_submitted' ?>">
                                            <?= $hasHardCopyReleased ? 'Hard copy picked up' : 'Hard copy for pickup' ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="pending-sync-badge" data-pending-sync="<?= $protocolId ?>" hidden></span>
                                </p>
                                <p class="protocol-meta-line">
                                    <?php if ($userRole === 'staff' && $statusLower === 'reviewed'): ?>
                                        <label class="protocol-select-label" title="Select this protocol">
                                            <input type="checkbox" class="consent-checkbox protocol-select-checkbox"
                                                aria-label="Select protocol"
                                                <?= $protocol['can_endorse'] ? 'data-can-endorse' : '' ?>
                                                <?= empty($protocol['latest_protocol_version_id']) ? 'disabled' : '' ?>>
                                        </label>
                                    <?php endif; ?>
                                    <?php if (!empty($protocol['school'])): ?><span class="protocol-school"><?= htmlspecialchars($protocol['school'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                    <?php if (($protocol['researcher_status'] ?? 'active') === 'deactivated'): ?>
                                        <span class="status-dot status-dot--warning">Inactive</span>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <div class="protocol-progress">
                                <?php $stageSlug = $protocol['stage_slug'];
                                include dirname(__DIR__) . '/includes/protocol-stepper.php'; ?>
                                <?php if ($nextStep): ?>
                                    <p class="protocol-next-step<?= $nextStep[2] ? ' protocol-next-step--waiting' : '' ?>">
                                        <span class="step-count">Step <?= $nextStep[0] ?> of <?= $stepCount ?></span>
                                        <span class="step-label"><?= htmlspecialchars($nextStep[1], ENT_QUOTES, 'UTF-8') ?></span>
                                    </p>
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
                                    <?php if ($userRole === 'staff' && $statusLower === 'endorsed'): ?>
                                        <button type="button" class="action-link clearance-add-btn" data-protocol-id="<?= $protocolId ?>" aria-label="Assign clearance for <?= $title ?>">
                                            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <use href="#upload-icon" />
                                            </svg>
                                            Assign clearance
                                        </button>
                                    <?php endif; ?>
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

                            <?php
                            $detailsId   = $protocolId;
                            $researcherLink = '<button type="button" class="researcher-name-link" data-user-id="' . (int) $protocol['user_id'] . '" data-researcher-name="' . $researcherName . '" aria-label="View researcher profile: ' . $researcherName . '" title="View researcher profile">' . $researcherName . '</button>';
                            $detailItems = [
                                ['Round', $protocol['version_display']],
                                ['Researcher', $researcherLink, true],
                            ];
                            $detailItems[] = ['Submitted', $submittedDate];
                            $detailItems[] = ['Last updated', $updatedDate];
                            if ($userRole === 'staff' && $hasIpn && in_array($statusLower, ['reviewed', 'endorsed'], true)) {
                                $detailItems[] = ['IPN', $protocol['reference_no']];
                            }
                            include dirname(__DIR__) . '/includes/protocol-details.php';
                            ?>
                        </div>
                    <?php endforeach; ?>

                    <p class="no-results" id="noResultsMsg">
                        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#file-x-icon" />
                        </svg>
                        <span id="noResultsText">No protocols match your search.</span>
                    </p>
                </div><!-- /.protocols-list -->

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

                <?php $archiveText = $archiveCategories[$archiveCategoryDefault]['desc'];
                include dirname(__DIR__) . '/includes/archive-modal.php'; ?>

            <?php endif; ?>

        </div>

    </main>
</div>

<?php if ($personnelRole === 'staff'): ?>
    <?php $ipnFormat = NumberFormat::of('ipn')->forJs();
    $arFormat  = NumberFormat::of('ar')->forJs(); ?>

    <!-- ===== Clearances window: one row per image, with its AR number and protocol ===== -->
    <div class="modal-backdrop" id="clearanceWindowBackdrop" data-static data-confirm-discard
        data-pattern="<?= htmlspecialchars($arFormat['pattern'], ENT_QUOTES) ?>"
        data-message="<?= htmlspecialchars($arFormat['message'], ENT_QUOTES) ?>"
        data-leading="<?= htmlspecialchars($arFormat['leading'], ENT_QUOTES) ?>"
        data-placeholder="<?= htmlspecialchars($arFormat['placeholder'], ENT_QUOTES) ?>"
        data-maxlength="<?= $arFormat['maxlength'] ?>">
        <div class="modal-card panel-modal-card clearance-window-card">
            <div class="modal-header">
                <span class="modal-header-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                        <use href="#upload-icon" />
                    </svg>
                </span>
                <p class="modal-title">Assign Clearances</p>
                <button class="modal-close" type="button" onclick="closeClearanceWindow()" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#close-icon" />
                    </svg>
                </button>
            </div>
            <div class="panel-modal-body">
                <div class="clearance-dropzone" id="clearanceDropzone">
                    <span id="clearanceDropText">Drop JPG or PNG images here, or</span>
                    <input type="file" id="clearanceFileInput" multiple accept=".jpg,.jpeg,.png,image/jpeg,image/png" hidden>
                    <button type="button" class="modal-file-picker" id="clearanceChooseBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#upload-icon" />
                        </svg>
                        <span id="clearanceChooseLabel">Choose files</span>
                    </button>
                </div>
                <p class="helper clearance-modal-helper">The AR Number (Animal Research Number) is the clearance ID printed on the clearance issued by BAI.</p>

                <div id="clearanceWindowError" class="alert error-messages clearance-modal-error" hidden></div>

                <ul class="clearance-rows" id="clearanceRows"></ul>
                <p class="helper clearance-empty" id="clearanceEmpty">No images yet.</p>
            </div>
            <div class="clearance-window-footer">
                <span class="clearance-summary" id="clearanceSummary" aria-live="polite"></span>
                <div class="modal-actions">
                    <button class="confirm-modal-btn confirm-modal-cancel" type="button" id="clearanceCancelBtn">Cancel</button>
                    <button class="confirm-modal-btn confirm-modal-ok" type="button" id="clearanceApproveBtn" disabled>
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#check-icon" />
                        </svg>
                        <span id="clearanceApproveLabel">Approve</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Approve clearances: final check ===== -->
    <div class="modal-backdrop" id="confirmReviewBackdrop">
        <div class="modal-card panel-modal-card clearance-modal-card">
            <div class="modal-header">
                <span class="modal-header-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                        <use href="#check-circle-icon" />
                    </svg>
                </span>
                <p class="modal-title">Approve Clearances</p>
                <button class="modal-close" type="button" onclick="closeConfirmReview()" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#close-icon" />
                    </svg>
                </button>
            </div>
            <div class="panel-modal-body">
                <p class="helper clearance-modal-helper"><strong class="clearance-modal-warning">Double-check each AR Number and clearance image before proceeding.</strong> These protocols will be marked <strong>Approved</strong>:</p>
                <ul class="clearance-review-list" id="confirmReviewList"></ul>
                <p class="helper clearance-modal-helper" id="confirmReviewNote" hidden></p>

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

    <!-- Add/Edit IPN modal -->
    <div class="modal-backdrop" id="numberModalBackdrop" data-static
        data-pattern="<?= htmlspecialchars($ipnFormat['pattern'], ENT_QUOTES) ?>"
        data-placeholder="<?= htmlspecialchars($ipnFormat['placeholder'], ENT_QUOTES) ?>"
        data-maxlength="<?= $ipnFormat['maxlength'] ?>"
        data-numeric="<?= $ipnFormat['numeric'] ? '1' : '0' ?>"
        data-message="<?= htmlspecialchars($ipnFormat['message'], ENT_QUOTES) ?>">
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

    <!-- Endorsement proof modal: one photo can cover one or several protocols -->
    <div class="modal-backdrop" id="endorsementModalBackdrop" data-static>
        <div class="modal-card panel-modal-card">
            <div class="modal-header">
                <span class="modal-header-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                        <use href="#upload-icon" />
                    </svg>
                </span>
                <p class="modal-title">Upload Endorsement Proof</p>
                <button class="modal-close" type="button" onclick="closeEndorsementModal()" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#close-icon" />
                    </svg>
                </button>
            </div>
            <div class="panel-modal-body">
                <p class="modal-notice" id="endorsementNotice"></p>

                <ul class="endorsement-protocol-list" id="endorsementProtocolList"></ul>

                <div id="endorsementError" class="alert error-messages" hidden></div>

                <div class="modal-file-row">
                    <div class="modal-file-info">
                        <div class="modal-file-title">Proof of endorsement <span class="required-asterisk">*</span></div>
                        <div class="modal-file-subtitle" id="endorsementFileSubtitle">Photo or screenshot &middot; JPG or PNG &middot; max 10 MB</div>
                    </div>
                    <div class="resub-doc-actions">
                        <button type="button" class="resub-btn-link is-hidden" id="endorsementFilePreviewBtn"
                            onclick="previewPickedFile('endorsement_proof_file')">Preview</button>
                        <label class="modal-file-picker">
                            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#upload-icon" />
                            </svg>
                            <span id="endorsementFilePickerLabel">Upload</span>
                            <input type="file" id="endorsement_proof_file" name="endorsement_proof_file"
                                accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                onchange="handleEndorsementFileChange(this)">
                        </label>
                    </div>
                </div>

                <div class="upload-progress-container" id="endorsementProgress"></div>

                <div class="modal-actions">
                    <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="closeEndorsementModal()">Back</button>
                    <button class="confirm-modal-btn confirm-modal-ok" type="button" id="endorsementSubmitBtn" onclick="submitEndorsement()">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#upload-icon" />
                        </svg>
                        Upload &amp; Mark as Endorsed
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
    const CSRF_TOKEN = <?= json_encode($csrf) ?>;
    const REVIEWED_DOWNLOADABLE_COUNT = <?= (int) $reviewedDownloadableCount ?>;
    const ENDORSED_COUNT = <?= (int) $endorsedCount ?>;
</script>
<script src="<?= asset_js('archive-window.js') ?>"></script>
<script src="<?= asset_js('inbox-search.js') ?>"></script>
<script src="<?= asset_js('personnel-home.js') ?>"></script>
<script src="<?= asset_js('protocol-details.js') ?>"></script>
<script src="<?= asset_js('protocol-list.js') ?>"></script>
<script src="<?= asset_js('personnel-protocol-actions.js') ?>"></script>
<?php if ($personnelRole === 'staff'): ?>
    <script src="<?= asset_js('personnel-endorsement-modal.js') ?>"></script>
<?php endif; ?>

<?php
$guideId    = 'inbox';
$guideSteps = [
    [PERSONNEL_HOME_LABEL, $personnelRole === 'staff'
        ? 'This is where protocols submitted to CCARD show up, and what you need to do with each one. Let us walk through it.'
        : 'This is where protocols waiting for your feedback show up. Let us walk through it.'],
    ['Sort', 'Change the order: newest or oldest submitted, recently updated, or by title. Your choice is saved to your account.', '.dashboard-sort-group'],
    ['Status tabs', $personnelRole === 'staff'
        ? 'Show one stage at a time. The tools for a stage appear on its tab: **Reviewed** has selecting and downloading several protocols at once, and **Endorsed** has the clearances.'
        : 'Show one stage at a time, with the number of protocols in each.', '#statusFilters'],
    ['Search', "Press the magnifier to find a protocol in the list by its title or the researcher's name. It stays highlighted while a search is active.", '#inboxSearchBtn'],
    ['Stage guide', 'Press the question mark to read what each stage means any time.', '.legend-info-wrapper'],
];
foreach ($statusMeta as $meta) {
    $guideSteps[] = [$meta['label'], $meta['desc'], '.legend-info-wrapper'];
}
$guideSteps[] = [$archivedMeta['label'], $archivedMeta['desc'] . "\n\nPress **Archive** to open the archive window. Protocols in it are kept out of the main list and sorted into categories. **All** shows everything and is where the window opens, or pick a category at the top. Press **View** to open a protocol, or **Restore** to bring it back to the main list. Approved protocols of inactive researchers have no button, because they move back on their own when the researcher reactivates their account.\n\nPress the **X** or click outside the window to close it.", '#archivedLink'];
$guideSteps = array_merge($guideSteps, [
    ['Protocol card', "Each card represents one protocol. It shows the research title, the researcher's school or institution, and a timeline of the stage the protocol is at.", '.protocols-list .protocol:first-visible'],
    ['Details', "Press the **show details** bar at the bottom of the card to see the round, the researcher, and the dates the protocol was submitted and last updated.\n\nPress the researcher's name there to see their contact details or review their training certificate.", '.protocols-list .protocol .protocol-details-toggle:first-visible'],
    ['Card buttons', $personnelRole === 'staff'
        ? "The main button on a card is your next task, and it changes with the status.\n\n**Reviewed**: verify payment, assign IPN, upload proof of endorsement.\n**Endorsed**: assign the clearance, which approves the protocol. The three dots hold **view endorsement proof**.\n**Approved**: view clearance.\n\n**To review** cards have no task for you, since the reviewers handle them."
        : "Press **review** to read and give your feedback on protocols.\n\nCards in any other status have no task for you. **view clearance** appears once a protocol is approved.", '.protocols-list .protocol .actions:first-visible'],
    ['View button', $personnelRole === 'staff'
        ? "The **view** button opens the protocol so you can read it.\n\nIt is on every card."
        : "The **view** button opens the protocol so you can read it. It is on every card except where you press **review** instead.", '.protocols-list .protocol .actions-secondary .protocol-view-btn:first-visible'],
    ['Three-dot menu', "The three dots beside **view** hold everything else you can do with a protocol. Only the items that apply to that protocol are shown.", '.protocols-list .protocol .actions-secondary .protocol-menu-trigger:first-visible'],
    ['Pagination', 'Move between pages and choose how many rows to show per page.', '#paginationBar'],
]);
include dirname(__DIR__) . '/includes/guide.php';
?>
<?php include dirname(__DIR__) . '/includes/history-modal.php'; ?>
<script>
    window.historyModalConfig = {
        offlineMessage: 'History is not available offline. It will load once you reconnect.'
    };
</script>
<script src="<?= asset_js('history-modal.js') ?>"></script>

<?php include dirname(__DIR__) . '/includes/researcher-modal.php'; ?>

<?php include dirname(__DIR__) . '/includes/protocol-modals.php'; ?>
<script src="<?= asset_js('protocol-menu.js') ?>"></script>

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