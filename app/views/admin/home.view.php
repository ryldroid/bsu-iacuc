<?php

/** @var array|null  $user */
/** @var array       $protocols */
/** @var array       $statuses */

$title = 'Staff Dashboard';

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

// ===== Status metadata: color + icon + plain-language description (mirrors My Protocols) =====
$statusMeta = [
    'to-review' => [
        'label' => 'To Review',
        'color' => '#0072B2',
        'icon'  => 'clock-icon',
        'desc'  => 'Newly submitted protocols waiting on an initial review.',
    ],
    'returned-for-revision' => [
        'label' => 'Returned for Revision',
        'color' => '#D55E00',
        'icon'  => 'alert-triangle-icon',
        'desc'  => 'Sent back to the researcher with feedback. No action needed until they resubmit.',
    ],
    'reviewed' => [
        'label' => 'Reviewed',
        'color' => '#CC79A7',
        'icon'  => 'checkbox-icon',
        'desc'  => 'Reviewer has finished their assessment. Ready to be marked as endorsed.',
    ],
    'endorsed' => [
        'label' => 'Endorsed',
        'color' => '#E69F00',
        'icon'  => 'shield-check-icon',
        'desc'  => 'Endorsed and awaiting a clearance document before it can be marked approved.',
    ],
    'approved' => [
        'label' => 'Approved',
        'color' => '#009E73',
        'icon'  => 'check-circle-icon',
        'desc'  => 'Clearance issued. The protocol is fully approved.',
    ],
];

/**
 * Status icon: references a symbol already defined in sprites.php.
 */
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
    $protocol['version_display'] = $protocol['latest_version'] ? 'v' . (int) $protocol['latest_version'] : 'v1';
}
unset($protocol);

// ===== Compute per-status counts for metric cards and filter pill badges =====
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

$approvedThisMonth = 0;
$currentMonth = date('Y-m');
foreach ($protocols as $p) {
    if ($p['filter_slug'] === 'approved' && str_starts_with($p['submitted_at'], $currentMonth)) {
        $approvedThisMonth++;
    }
}

?>

<link rel="stylesheet" href="<?= asset_css('protocol-list.css') ?>">
<link rel="stylesheet" href="<?= asset_css('admin/admin-home.css') ?>">

<div class="body">
    <?php include dirname(__DIR__) . '/includes/navigation.php'; ?>

    <main class="main-content" id="main-content" tabindex="-1">

        <!-- ===== Page header with search bar ===== -->
        <div class="dashboard-page-header">
            <h1 class="dashboard-page-title">Protocol Inbox</h1>

            <div class="inbox-search-wrap">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
                <input type="text" id="inboxSearchInput" class="inbox-search-input"
                    placeholder="Search by title or researcher..." autocomplete="off">
                <button class="inbox-search-clear" id="inboxSearchClear" aria-label="Clear search">
                    &#x2715;
                </button>
            </div>

            <?php if (($user['role'] ?? '') === 'reviewer'): ?>
                <button class="row-btn row-btn-primary" id="uploadClearanceBtn" type="button" hidden
                    onclick="openClearanceScreenshotModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#upload-icon" />
                    </svg>
                    Upload Clearance Screenshots
                </button>
            <?php elseif (($user['role'] ?? '') === 'admin'): ?>
                <a class="row-btn row-btn-primary" id="downloadAllPaidBtn" hidden
                    href="<?= ROOT ?>/apply/download_all_paid" title="Download the latest protocol PDF for every reviewed, paid protocol">
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#download-icon" />
                    </svg>
                    Download All Paid
                </a>
            <?php endif; ?>
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

        <!-- ===== Metric cards ===== -->
        <div class="metrics-row dashboard-metrics">
            <div class="metric-card">
                <span class="metric-card-value"><?= $toReviewCount ?></span>
                <span class="metric-card-label">to review</span>
            </div>
            <div class="metric-card">
                <span class="metric-card-value"><?= $revisionCount ?></span>
                <span class="metric-card-label">awaiting revision</span>
            </div>
            <div class="metric-card">
                <span class="metric-card-value"><?= $reviewedCount ?></span>
                <span class="metric-card-label">reviewed</span>
            </div>
            <div class="metric-card">
                <span class="metric-card-value"><?= $approvedThisMonth ?></span>
                <span class="metric-card-label">approved this month</span>
            </div>
        </div>

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
            <div class="filter-wrapper">
                <div class="mobile-status-filters button">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#filter-icon" />
                    </svg>
                    Status: <span id="mobileFilterLabel" class="mobile-filter-label">To review</span>
                </div>

                <div class="status-filters" id="filterPillsRow">
                    <button class="status-card" data-filter="all" data-label="All">
                        <p>All <span class="status-count"><?= $totalCount ?></span></p>
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
                ];
                $paymentLabels = [
                    'unpaid'         => 'Unpaid',
                    'proof_submitted' => 'Proof Submitted',
                    'rejected'       => 'Proof Rejected',
                    'paid'           => 'Paid',
                ];
                ?>

                <?php foreach ($protocols as $protocol):
                    $submittedDate  = date('M j, Y', strtotime($protocol['submitted_at']));
                    $statusDisplay  = $protocol['status_display'];
                    $badgeClass     = $protocol['badge_class'];
                    $filterSlug     = $protocol['filter_slug'];
                    $statusLower    = strtolower($protocol['status']);
                    $protocolId     = (int) $protocol['protocol_id'];
                    $paymentStatus  = $protocol['payment_status'] ?? 'unpaid';
                    $hasSignedScan  = !empty($protocol['latest_signed_scan_version_id']);
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
                                switch ($paymentStatus) {
                                    case 'proof_submitted':
                                        $actions = [
                                            [
                                                'label' => 'Review Payment',
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
                                        break;

                                    case 'paid':
                                        $actions = [
                                            [
                                                'label' => 'View',
                                                'action' => 'view',
                                                'icon' => 'review',
                                                'primary' => true
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
                                        break;

                                    default:
                                        // unpaid / rejected: waiting on the researcher
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
                                break;

                            case 'approved':
                                $actions = [
                                    [
                                        'label' => 'Show Clearance',
                                        'action' => 'view-clearance',
                                        'icon' => 'download',
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
                                if ($paymentStatus !== 'paid') {
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
                                            'label' => 'Show History',
                                            'action' => 'show-history',
                                            'icon' => 'history'
                                        ]
                                    ];
                                }
                                break;

                            case 'endorsed':
                                $hasClearance = !empty($protocol['latest_clearance_version_id']);
                                $actions = [
                                    $hasClearance ? [
                                        'label' => 'Mark as Approved',
                                        'action' => 'mark-approved',
                                        'icon' => 'check',
                                        'primary' => true
                                    ] : [
                                        'label' => 'Go to Clearance Pool',
                                        'href' => ROOT . '/admin/clearances',
                                        'icon' => 'upload',
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
                                        'label' => 'Revert to Reviewed',
                                        'action' => 'revert-endorsed',
                                        'icon' => 'undo'
                                    ]
                                ];
                                break;

                            case 'approved':
                                $actions = [
                                    [
                                        'label' => 'Show Clearance',
                                        'action' => 'view-clearance',
                                        'icon' => 'download',
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
                        data-researcher="<?= strtolower(htmlspecialchars($protocol['first_name'] . ' ' . $protocol['last_name'], ENT_QUOTES, 'UTF-8')) ?>">

                        <span class="protocol-status-icon" style="background:<?= $statusMeta[$filterSlug]['color'] ?? 'var(--muted-text)' ?>">
                            <?= statusIconSvg($statusMeta[$filterSlug]['icon'] ?? 'check-circle-icon', 15) ?>
                        </span>

                        <div class="protocol-body">
                            <div class="protocol-meta">
                                <p class="research-title">
                                    <?= $title ?>
                                    <?php if (in_array($statusLower, ['reviewed', 'endorsed', 'approved'], true)): ?>
                                        <span class="payment-badge payment-badge--<?= htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($paymentLabels[$paymentStatus] ?? 'Unpaid', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                                <p class="protocol-meta-line">
                                    <?= $protocol['version_display'] ?> &middot; <button type="button" class="researcher-name-link" data-user-id="<?= (int) $protocol['user_id'] ?>" data-researcher-name="<?= $researcherName ?>"><?= $researcherName ?></button><?php if (!empty($protocol['school'])): ?> &middot; <?= htmlspecialchars($protocol['school'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?> &middot; <?= $submittedDate ?>
                                </p>
                            </div>

                            <div class="actions">
                                <?php if ($userRole === 'admin' && $statusLower === 'reviewed' && !empty($protocol['latest_protocol_version_id'])): ?>
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

    </main>
</div>

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
    const ENDORSED_COUNT = <?= (int) $endorsedCount ?>;

    // ===== DOM refs =====
    const protocolsList = document.getElementById('protocolsList');
    const noResultsMsg = document.getElementById('noResultsMsg');
    const filterPills = document.querySelectorAll('.status-card');
    const mobileFilter = document.querySelector('.mobile-status-filters');
    const statusFiltersEl = document.getElementById('filterPillsRow');
    const searchInput = document.getElementById('inboxSearchInput');
    const searchClearBtn = document.getElementById('inboxSearchClear');
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationBtns = document.getElementById('paginationButtons');
    const rowsPerPageSel = document.getElementById('rowsPerPageSelect');
    const uploadClearanceBtn = document.getElementById('uploadClearanceBtn');
    const downloadAllPaidBtn = document.getElementById('downloadAllPaidBtn');

    const allRows = protocolsList ? [...protocolsList.querySelectorAll('.protocol')] : [];

    let activeFilter = 'to-review';
    let searchQuery = '';
    let currentPage = 1;
    let rowsPerPage = 10;

    // ===== Only show "Upload Clearance Screenshots" while viewing the Endorsed tab, and only if there's an endorsed protocol =====
    function updateUploadClearanceBtnVisibility() {
        if (!uploadClearanceBtn) return;
        uploadClearanceBtn.hidden = !(activeFilter === 'endorsed' && ENDORSED_COUNT > 0);
    }

    // ===== Only show "Download All Paid" while viewing the Reviewed tab =====
    function updateDownloadAllPaidBtnVisibility() {
        if (!downloadAllPaidBtn) return;
        downloadAllPaidBtn.hidden = activeFilter !== 'reviewed';
    }

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

    // ===== Mobile status dropdown toggle =====
    mobileFilter?.addEventListener('click', e => {
        e.stopPropagation();
        statusFiltersEl.classList.toggle('active');
    });
    document.addEventListener('click', e => {
        if (!statusFiltersEl?.contains(e.target) && !mobileFilter?.contains(e.target)) {
            statusFiltersEl?.classList.remove('active');
        }
    });

    // ===== Status filter tabs =====
    filterPills.forEach(pill => {
        pill.addEventListener('click', () => {
            filterPills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            activeFilter = pill.dataset.filter;
            currentPage = 1;

            const mobileFilterLabel = document.getElementById('mobileFilterLabel');
            if (mobileFilterLabel) mobileFilterLabel.textContent = pill.dataset.label;

            updateStatusGuide(activeFilter);
            statusFiltersEl.classList.remove('active');

            const url = new URL(window.location);
            url.searchParams.set('status', activeFilter);
            history.replaceState(null, '', url);
            updateUploadClearanceBtnVisibility();
            updateDownloadAllPaidBtnVisibility();
            renderTable();
        });
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
        const requestedStatus = new URLSearchParams(window.location.search).get('status');
        if (requestedStatus) {
            const matchingPill = [...filterPills].find(p => p.dataset.filter === requestedStatus);
            if (matchingPill) {
                filterPills.forEach(p => p.classList.remove('active'));
                matchingPill.classList.add('active');
                activeFilter = requestedStatus;
                const mobileFilterLabel = document.getElementById('mobileFilterLabel');
                if (mobileFilterLabel) mobileFilterLabel.textContent = matchingPill.dataset.label;
            }
        } else {
            const url = new URL(window.location);
            url.searchParams.set('status', activeFilter);
            history.replaceState(null, '', url);
        }
        updateStatusGuide(activeFilter);
        updateUploadClearanceBtnVisibility();
        updateDownloadAllPaidBtnVisibility();
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

            return matchesFilter && matchesSearch;
        });

        allRows.forEach(row => row.classList.add('protocol-row-hidden'));

        const totalRows = visibleRows.length;
        const totalPages = Math.max(1, Math.ceil(totalRows / rowsPerPage));
        if (currentPage > totalPages) currentPage = totalPages;

        const startIndex = (currentPage - 1) * rowsPerPage;
        const pageRows = visibleRows.slice(startIndex, startIndex + rowsPerPage);

        pageRows.forEach(row => row.classList.remove('protocol-row-hidden'));

        if (noResultsMsg) {
            noResultsMsg.style.display = totalRows === 0 ? 'flex' : 'none';
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
                }).then(ok => ok && submitStatusChange(protocolId, 'Endorsed'));
                break;

            case 'revert-endorsed':
                confirmAction('Revert this protocol back to Reviewed? Use this if "Mark as Endorsed" was clicked by mistake.', {
                    okText: 'Revert',
                    cancelText: 'Cancel'
                }).then(ok => ok && submitStatusChange(protocolId, 'Reviewed'));
                break;

            case 'mark-approved':
                confirmAction('Mark this protocol as approved? Double-check that the attached clearance is correct before proceeding.', {
                    okText: 'Mark as Approved',
                    cancelText: 'Cancel'
                }).then(ok => ok && submitStatusChange(protocolId, 'Approved'));
                break;

            case 'revert-approved':
                confirmAction('Revert this protocol back to Endorsed? Use this if "Mark as Approved" was clicked by mistake.', {
                    okText: 'Revert',
                    cancelText: 'Cancel'
                }).then(ok => ok && submitStatusChange(protocolId, 'Endorsed'));
                break;

            case 'upload-signed-scan':
                openSignedScanModal(protocolId, protocol?.research_title ?? '');
                break;

            case 'undo-payment':
                confirmAction('Undo this "Mark as Paid"? The protocol will go back to "Proof Submitted".', {
                    okText: 'Undo',
                    cancelText: 'Cancel'
                }).then(ok => ok && submitPaymentUndo(protocolId));
                break;

            case 'review-payment':
                openReviewPaymentModal(protocolId, protocol?.research_title ?? '');
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
                document.getElementById('reviewPaymentApproveBtn')?.removeAttribute('disabled');
            }
        } catch (err) {
            alert('Network error. Please try again.');
            document.getElementById('reviewPaymentApproveBtn')?.removeAttribute('disabled');
        }
    }

    async function submitPaymentUndo(protocolId) {
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
                alert('Error: ' + (data.error ?? 'Could not undo payment mark.'));
            }
        } catch (err) {
            alert('Network error. Please try again.');
        }
    }

    // ===== Status change API call =====
    async function submitStatusChange(protocolId, newStatus) {
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
            } else if (data.queued) {} else {
                alert('Error: ' + (data.error ?? 'Could not update status.'));
            }
        } catch (err) {
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

    // ===== Initial render =====
    renderTable();
</script>

<!-- ===== History modal ===== -->
<div class="modal-backdrop" id="historyModalBackdrop">
    <div class="modal-card history-modal-card">
        <div class="history-modal-header">
            <div>
                <p class="history-modal-label">Submission History</p>
                <div class="history-modal-title-row">
                    <p class="history-modal-title" id="historyModalTitle"></p>
                    <button type="button" class="rename-history-toggle" id="renameHistoryToggle" hidden
                        aria-expanded="false" aria-controls="renameHistoryPanel" aria-label="Show rename history">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#chev-down-icon" />
                        </svg>
                    </button>
                </div>
                <div class="rename-history-panel" id="renameHistoryPanel" hidden></div>
            </div>
            <button class="button history-modal-close" onclick="closeHistoryModal()" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
            </button>
        </div>
        <div id="historyModalBody" class="history-modal-body">
            <p class="helper history-loading">Loading&hellip;</p>
        </div>
    </div>
</div>

<script>
    const historyBackdrop = document.getElementById('historyModalBackdrop');

    function openHistoryModal(protocolId, title) {
        document.getElementById('historyModalTitle').textContent = title;
        document.getElementById('historyModalBody').innerHTML = '<p class="helper history-loading">Loading…</p>';

        const renameToggle = document.getElementById('renameHistoryToggle');
        const renamePanel = document.getElementById('renameHistoryPanel');
        renameToggle.hidden = true;
        renameToggle.setAttribute('aria-expanded', 'false');
        renamePanel.hidden = true;
        renamePanel.innerHTML = '';

        historyBackdrop.classList.add('open');

        fetch(ROOT_URL + '/apply/allversions/' + protocolId)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('historyModalBody').innerHTML =
                        '<p class="helper history-error">' + data.error + '</p>';
                    return;
                }
                renderRenameHistory(data.title_history);
                renderHistory(data);
            })
            .catch(() => {
                document.getElementById('historyModalBody').innerHTML =
                    '<p class="helper history-offline">Submission history is not available offline. It will load once you reconnect.</p>';
            });
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, ch => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        } [ch]));
    }

    function renderRenameHistory(titleHistory) {
        const renameToggle = document.getElementById('renameHistoryToggle');
        const renamePanel = document.getElementById('renameHistoryPanel');

        if (!titleHistory || titleHistory.length === 0) {
            renameToggle.hidden = true;
            return;
        }

        renamePanel.innerHTML = '<div class="rename-history-panel-header">Title Name History</div>' + titleHistory.map(h => {
            const date = new Date(h.changed_at).toLocaleString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            const who = h.changed_by_name ?
                `${escapeHtml(h.changed_by_role ? h.changed_by_role.charAt(0).toUpperCase() + h.changed_by_role.slice(1) : '')} - ${escapeHtml(h.changed_by_name)}` :
                'Initial title';
            return `<div class="rename-history-entry">
                <div class="rename-history-entry-title">${escapeHtml(h.title)}</div>
                <div class="rename-history-entry-meta">${who} &middot; ${date}</div>
            </div>`;
        }).join('');

        renameToggle.hidden = false;
        renameToggle.onclick = () => {
            const isOpen = renameToggle.getAttribute('aria-expanded') === 'true';
            renameToggle.setAttribute('aria-expanded', String(!isOpen));
            renamePanel.hidden = isOpen;
        };
    }

    function closeHistoryModal() {
        historyBackdrop.classList.remove('open');
        closeFilePopup();
    }

    historyBackdrop.addEventListener('click', e => {
        if (e.target === historyBackdrop) closeHistoryModal();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeHistoryModal();
            closeFilePopup();
        }
    });

    function buildHistorySection(versions, protocolId) {
        if (!versions || versions.length === 0) return '';
        const rows = versions.map((v, i) => {
            const date = new Date(v.uploaded_at).toLocaleString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            const isLatest = i === 0;

            return `
                <div class="history-row${isLatest ? ' history-row--latest' : ''}">
                    <div class="history-row-meta">
                        <span class="history-ver">v${v.version_number}</span>
                        ${isLatest ? '<span class="history-latest-badge">Latest</span>' : ''}
                    </div>
                    <div class="history-row-detail">
                        <span class="history-filename">${v.title_at_version || v.original_name}</span>
                        <span class="helper">${date}</span>
                    </div>
                    <a class="button history-open-btn" href="${ROOT_URL}/apply/viewer/${protocolId}/${v.id}">
                        <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#review-icon" />
                        </svg>
                        Open
                    </a>
                </div>`;
        }).join('');
        return `<div class="history-section-label">Protocol Submissions</div>${rows}`;
    }

    function buildSimpleFileSection(files, label) {
        if (!files || files.length === 0) return '';
        const rows = files.map((v, i) => {
            const date = new Date(v.uploaded_at).toLocaleString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            const isLatest = i === 0;
            const who = v.first_name ? `${escapeHtml(v.first_name)} ${escapeHtml(v.last_name || '')}` : '';

            return `
                <div class="history-row${isLatest ? ' history-row--latest' : ''}">
                    <div class="history-row-meta">
                        <span class="history-ver">v${v.version_number}</span>
                        ${isLatest ? '<span class="history-latest-badge">Latest</span>' : ''}
                    </div>
                    <div class="history-row-detail">
                        <span class="history-filename">${escapeHtml(v.original_name)}</span>
                        <span class="helper">${who ? who + ' &middot; ' : ''}${date}</span>
                    </div>
                    <button type="button" class="button history-open-btn"
                        onclick="openFilePopup('${v.file_url}', '${escapeHtml(label)}')">
                        <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#review-icon" />
                        </svg>
                        Open
                    </button>
                </div>`;
        }).join('');
        return `<div class="history-section-label">${escapeHtml(label)}</div>${rows}`;
    }

    function renderHistory(data) {
        const body = document.getElementById('historyModalBody');

        const sections = [
            buildHistorySection(data.protocol_files, data.protocol_id),
            buildSimpleFileSection(data.payment_proof_files, 'Proof of Payment'),
            buildSimpleFileSection(data.signed_scan_files, 'Signed Scan'),
            buildSimpleFileSection(data.clearance_files, 'Clearance'),
        ].filter(Boolean);

        body.innerHTML = sections.length ?
            sections.join('') :
            '<p class="helper">No submission history found.</p>';
    }
</script>

<!-- ===== Researcher details modal ===== -->
<div class="modal-backdrop" id="researcherModalBackdrop">
    <div class="modal-card history-modal-card researcher-modal-card">
        <div class="history-modal-header">
            <div>
                <p class="history-modal-label">Researcher</p>
                <p class="history-modal-title" id="researcherModalName"></p>
            </div>
            <button class="button history-modal-close" onclick="closeResearcherModal()" aria-label="Close">
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

    function openResearcherModal(userId, fallbackName) {
        document.getElementById('researcherModalName').textContent = fallbackName || '';
        document.getElementById('researcherModalBody').innerHTML = '<p class="helper history-loading">Loading…</p>';
        researcherBackdrop.classList.add('open');

        if (!userId) {
            document.getElementById('researcherModalBody').innerHTML =
                '<p class="helper history-error">No account information available for this researcher.</p>';
            return;
        }

        fetch(ROOT_URL + '/admin/researcher_details?id=' + encodeURIComponent(userId))
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

        const joined = d.created_at ?
            new Date(d.created_at).toLocaleDateString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            }) :
            '—';

        const rows = [
            ['Username', d.username],
            ['Email', d.email],
            ['Phone', d.phone_number || '—'],
            ['School', d.school || '—'],
            ['Role', d.role ? d.role.charAt(0).toUpperCase() + d.role.slice(1) : '—'],
            ['Account status', d.status ? d.status.charAt(0).toUpperCase() + d.status.slice(1) : '—'],
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
                        ${p.submitted_at ? ' &middot; ' + new Date(p.submitted_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : ''}
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

<!-- ===== File popup modal (cert / auth letter / protocol versions) ===== -->
<div class="modal-backdrop" id="filePopupBackdrop">
    <div class="modal-card file-popup-card">
        <div class="file-popup-header">
            <span class="file-popup-title" id="filePopupTitle"></span>
            <button class="button file-popup-close" onclick="closeFilePopup()" aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
                Close
            </button>
        </div>
        <iframe class="file-popup-frame" id="filePopupFrame" title="Document preview" src="about:blank"></iframe>
    </div>
</div>

<script>
    const filePopupBackdrop = document.getElementById('filePopupBackdrop');

    function openFilePopup(fileUrl, title) {
        document.getElementById('filePopupTitle').textContent = title;
        document.getElementById('filePopupFrame').src = fileUrl;
        filePopupBackdrop.classList.add('open');
    }

    function closeFilePopup() {
        if (!filePopupBackdrop.classList.contains('open')) return;
        filePopupBackdrop.classList.remove('open');
        document.getElementById('filePopupFrame').src = 'about:blank';
    }

    filePopupBackdrop.addEventListener('click', e => {
        if (e.target === filePopupBackdrop) closeFilePopup();
    });
</script>

<!-- ===== Upload Signed Scan modal (admin only) ===== -->
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

    function openSignedScanModal(protocolId, title) {
        currentSignedScanProtocolId = protocolId;
        document.getElementById('signedScanSubtitle').textContent = title;
        document.getElementById('signed_scan_file').value = '';
        resetSignedScanFilePicker();
        document.getElementById('signedScanError').hidden = true;
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

        btn.disabled = true;
        errBox.hidden = true;

        const formData = new FormData();
        formData.append('protocol_id', currentSignedScanProtocolId);
        formData.append('signed_scan_file', fileInput.files[0]);
        formData.append('csrf_token', CSRF_TOKEN);

        try {
            const res = await fetch(SIGNED_SCAN_UPLOAD_API, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                window.location.reload();
            } else {
                errBox.textContent = data.error ?? 'Upload failed. Please try again.';
                errBox.hidden = false;
                btn.disabled = false;
            }
        } catch (err) {
            errBox.textContent = 'Network error. Please try again.';
            errBox.hidden = false;
            btn.disabled = false;
        }
    }
</script>

<!-- ===== Review Payment modal (reviewer only) — shows the proof image, then Approve / Reject ===== -->
<div class="modal-backdrop" id="reviewPaymentModalBackdrop">
    <div class="modal-card file-popup-card review-payment-card">
        <div class="file-popup-header">
            <span class="file-popup-title" id="reviewPaymentTitle"></span>
            <button class="button file-popup-close" type="button" onclick="closeReviewPaymentModal()" aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
                Close
            </button>
        </div>

        <div class="review-payment-image-frame" id="reviewPaymentImageFrame">
            <p class="helper">Loading proof of payment&hellip;</p>
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
                <label for="reject_payment_comment">Reason (the researcher will see this)</label>
                <textarea id="reject_payment_comment" rows="3" placeholder="e.g. the amount doesn't match, or the receipt is unreadable" required></textarea>
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

    function openReviewPaymentModal(protocolId, title) {
        currentReviewPaymentProtocolId = protocolId;
        document.getElementById('reviewPaymentTitle').textContent = title;
        resetReviewPaymentModal();
        reviewPaymentModal.classList.add('open');
        loadPaymentProofImage(protocolId);
    }

    function closeReviewPaymentModal() {
        reviewPaymentModal.classList.remove('open');
        currentReviewPaymentProtocolId = null;
    }

    function resetReviewPaymentModal() {
        document.getElementById('reviewPaymentImageFrame').innerHTML =
            '<p class="helper">Loading proof of payment&hellip;</p>';
        document.getElementById('reviewPaymentActions').hidden = false;
        document.getElementById('reviewPaymentRejectPanel').hidden = true;
        document.getElementById('reject_payment_comment').value = '';
        document.getElementById('rejectPaymentError').hidden = true;
        document.getElementById('rejectPaymentSubmitBtn').disabled = false;
        document.getElementById('reviewPaymentApproveBtn').disabled = false;
    }

    reviewPaymentModal.addEventListener('click', e => {
        if (e.target === reviewPaymentModal) closeReviewPaymentModal();
    });

    async function loadPaymentProofImage(protocolId) {
        const frame = document.getElementById('reviewPaymentImageFrame');
        try {
            const res = await fetch(ROOT_URL + '/apply/allversions/' + protocolId);
            const data = await res.json();
            const latest = data?.payment_proof_files?.[0];

            if (!latest) {
                frame.innerHTML = '<p class="helper">No proof of payment file was found for this protocol.</p>';
                return;
            }

            frame.innerHTML = '';
            const img = document.createElement('img');
            img.src = latest.file_url;
            img.alt = 'Proof of payment';
            img.onerror = () => {
                frame.innerHTML = '<p class="helper">Could not load the proof of payment image.</p>';
            };
            frame.appendChild(img);
        } catch (err) {
            frame.innerHTML = '<p class="helper">Network error while loading the proof of payment.</p>';
        }
    }

    function showRejectPaymentReason() {
        document.getElementById('reviewPaymentActions').hidden = true;
        document.getElementById('reviewPaymentRejectPanel').hidden = false;
        document.getElementById('reject_payment_comment').focus();
    }

    function hideRejectPaymentReason() {
        document.getElementById('reviewPaymentRejectPanel').hidden = true;
        document.getElementById('reviewPaymentActions').hidden = false;
    }

    function approveReviewedPayment() {
        confirmAction('Mark this protocol as paid? Make sure the proof of payment shown checks out.', {
            okText: 'Mark as Paid',
            cancelText: 'Cancel'
        }).then(ok => {
            if (!ok) return;
            const btn = document.getElementById('reviewPaymentApproveBtn');
            btn.disabled = true;
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
            'Reject this payment proof? The researcher will be notified and asked to resubmit.', {
                okText: 'Reject Proof',
                cancelText: 'Cancel',
                danger: true
            }
        );
        if (!ok) return;

        btn.disabled = true;

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
                btn.disabled = false;
            }
        } catch (err) {
            errBox.textContent = 'Network error. Please try again.';
            errBox.hidden = false;
            btn.disabled = false;
        }
    }
</script>

<!-- ===== Upload Clearance Screenshots modal (reviewer only) ===== -->
<div class="modal-backdrop" id="clearanceScreenshotModalBackdrop">
    <div class="modal-card">
        <h2>Upload Clearance Screenshots</h2>
        <p class="modal-notice">These will go into the shared pool for admins to sort and attach.</p>

        <div id="clearanceScreenshotError" class="alert error-messages" hidden></div>

        <div class="modal-file-row">
            <div class="modal-file-info">
                <div class="modal-file-title">Screenshots <span class="required-asterisk">*</span></div>
                <div class="modal-file-subtitle" id="clearanceScreenshotFileSubtitle">Image, you can select several at once &middot; max 10 MB each</div>
            </div>
            <label class="modal-file-picker">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#upload-icon" />
                </svg>
                <span id="clearanceScreenshotFilePickerLabel">Upload</span>
                <input type="file" id="clearance_screenshots" name="clearance_screenshots[]" multiple
                    accept=".jpg,.jpeg,.png,image/jpeg,image/png" required
                    onchange="handleClearanceScreenshotFileChange(this)">
            </label>
        </div>

        <input type="file" id="clearance_screenshots_add" multiple
            accept=".jpg,.jpeg,.png,image/jpeg,image/png" hidden>

        <div class="modal-file-previews" id="clearanceScreenshotPreviews" hidden></div>

        <div class="modal-actions">
            <button class="button" type="button" onclick="closeClearanceScreenshotModal()">Cancel</button>
            <button class="button btn-apply" type="button" id="clearanceScreenshotSubmitBtn"
                onclick="submitClearanceScreenshots()">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#upload-icon" />
                </svg>
                Upload
            </button>
        </div>
    </div>
</div>

<script>
    const CLEARANCE_POOL_UPLOAD_API = ROOT_URL + '/apply/clearance_pool_upload';
    const clearanceScreenshotModal = document.getElementById('clearanceScreenshotModalBackdrop');
    let clearanceScreenshotPreviewUrls = [];

    function openClearanceScreenshotModal() {
        document.getElementById('clearance_screenshots').value = '';
        resetClearanceScreenshotFilePicker();
        clearClearanceScreenshotPreviews();
        document.getElementById('clearanceScreenshotError').hidden = true;
        clearanceScreenshotModal.classList.add('open');
    }

    function closeClearanceScreenshotModal() {
        clearanceScreenshotModal.classList.remove('open');
        clearClearanceScreenshotPreviews();
    }

    clearanceScreenshotModal.addEventListener('click', e => {
        if (e.target === clearanceScreenshotModal) closeClearanceScreenshotModal();
    });

    function resetClearanceScreenshotFilePicker() {
        document.getElementById('clearanceScreenshotFilePickerLabel').textContent = 'Upload';
        const subtitle = document.getElementById('clearanceScreenshotFileSubtitle');
        subtitle.textContent = 'Image, you can select several at once · max 10 MB each';
        subtitle.classList.remove('done');
    }

    function handleClearanceScreenshotFileChange(input) {
        const subtitle = document.getElementById('clearanceScreenshotFileSubtitle');
        if (input.files.length) {
            document.getElementById('clearanceScreenshotFilePickerLabel').textContent = 'Replace';
            subtitle.textContent = input.files.length === 1 ?
                input.files[0].name :
                input.files.length + ' files selected';
            subtitle.classList.add('done');
        } else {
            resetClearanceScreenshotFilePicker();
        }
        renderClearanceScreenshotPreviews(input.files);
    }

    function clearClearanceScreenshotPreviews() {
        clearanceScreenshotPreviewUrls.forEach(url => URL.revokeObjectURL(url));
        clearanceScreenshotPreviewUrls = [];
        const container = document.getElementById('clearanceScreenshotPreviews');
        container.innerHTML = '';
        container.hidden = true;
    }

    function renderClearanceScreenshotPreviews(fileList) {
        clearanceScreenshotPreviewUrls.forEach(url => URL.revokeObjectURL(url));
        clearanceScreenshotPreviewUrls = [];

        const container = document.getElementById('clearanceScreenshotPreviews');
        container.innerHTML = '';

        if (!fileList.length) {
            container.hidden = true;
            return;
        }
        container.hidden = false;

        [...fileList].forEach((file, index) => {
            const url = URL.createObjectURL(file);
            clearanceScreenshotPreviewUrls.push(url);

            const card = document.createElement('div');
            card.className = 'modal-file-preview-card';

            const img = document.createElement('img');
            img.className = 'modal-file-preview-img';
            img.src = url;
            img.alt = file.name;

            const name = document.createElement('span');
            name.className = 'modal-file-preview-name';
            name.textContent = file.name;
            name.title = file.name;

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'modal-file-preview-remove';
            removeBtn.setAttribute('aria-label', 'Remove ' + file.name);
            removeBtn.textContent = '\u00d7';
            removeBtn.addEventListener('click', () => removeClearanceScreenshotFile(index));

            card.append(img, name, removeBtn);
            container.appendChild(card);
        });

        const addTile = document.createElement('button');
        addTile.type = 'button';
        addTile.className = 'modal-file-preview-add';
        addTile.setAttribute('aria-label', 'Add more screenshots');
        addTile.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#add-icon"></use></svg>';
        addTile.addEventListener('click', () => document.getElementById('clearance_screenshots_add').click());
        container.appendChild(addTile);
    }

    function removeClearanceScreenshotFile(index) {
        const input = document.getElementById('clearance_screenshots');
        const dataTransfer = new DataTransfer();
        [...input.files].forEach((file, i) => {
            if (i !== index) dataTransfer.items.add(file);
        });
        input.files = dataTransfer.files;
        handleClearanceScreenshotFileChange(input);
    }

    document.getElementById('clearance_screenshots_add').addEventListener('change', function() {
        if (!this.files.length) return;

        const mainInput = document.getElementById('clearance_screenshots');
        const dataTransfer = new DataTransfer();
        [...mainInput.files].forEach(file => dataTransfer.items.add(file));
        [...this.files].forEach(file => dataTransfer.items.add(file));
        mainInput.files = dataTransfer.files;

        this.value = '';
        handleClearanceScreenshotFileChange(mainInput);
    });

    async function submitClearanceScreenshots() {
        const fileInput = document.getElementById('clearance_screenshots');
        const errBox = document.getElementById('clearanceScreenshotError');
        const btn = document.getElementById('clearanceScreenshotSubmitBtn');

        if (!fileInput.files.length) {
            errBox.textContent = 'Please select at least one file.';
            errBox.hidden = false;
            return;
        }

        btn.disabled = true;
        errBox.hidden = true;

        const formData = new FormData();
        for (const file of fileInput.files) {
            formData.append('clearance_screenshots[]', file);
        }
        formData.append('csrf_token', CSRF_TOKEN);

        try {
            const res = await fetch(CLEARANCE_POOL_UPLOAD_API, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                window.location.reload();
            } else {
                errBox.textContent = (data.failures && data.failures.length) ? data.failures.join(' ') : (data.error ?? 'Upload failed.');
                errBox.hidden = false;
                btn.disabled = false;
            }
        } catch (err) {
            errBox.textContent = 'Network error. Please try again.';
            errBox.hidden = false;
            btn.disabled = false;
        }
    }
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>