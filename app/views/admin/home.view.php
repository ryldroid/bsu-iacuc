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
        <div class="metrics-row">
            <div class="metric-card">
                <div class="metric-card-label">To review</div>
                <div class="metric-card-value">
                    <?= $toReviewCount ?>
                    <span class="metric-unit">unread</span>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-card-label">Awaiting revision</div>
                <div class="metric-card-value"><?= $revisionCount ?></div>
            </div>
            <div class="metric-card">
                <div class="metric-card-label">Reviewed</div>
                <div class="metric-card-value"><?= $reviewedCount ?></div>
            </div>
            <div class="metric-card">
                <div class="metric-card-label">Approved this month</div>
                <div class="metric-card-value"><?= $approvedThisMonth ?></div>
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
                ];
                ?>

                <?php foreach ($protocols as $protocol):
                    $submittedDate  = date('M j, Y', strtotime($protocol['submitted_at']));
                    $statusDisplay  = $protocol['status_display'];
                    $badgeClass     = $protocol['badge_class'];
                    $filterSlug     = $protocol['filter_slug'];
                    $statusLower    = strtolower($protocol['status']);
                    $protocolId     = (int) $protocol['protocol_id'];
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
                                break;

                            case 'endorsed':
                                $actions = [
                                    [
                                        'label' => 'Upload Clearance and Mark as Approved',
                                        'action' => 'upload-clearance',
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
                                <p class="research-title"><?= $title ?></p>
                                <p class="protocol-meta-line">
                                    <?= $protocol['version_display'] ?> &middot; <?= $researcherName ?> &middot; <?= $submittedDate ?>
                                </p>
                            </div>

                            <div class="actions">
                                <?php foreach ($actions as $action): ?>
                                    <?php if (!empty($action['primary'])): ?>
                                        <button class="button button--primary" data-action="<?= $action['action'] ?>">
                                            <?php if (!empty($action['icon'])): ?>
                                                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <use href="<?= $iconMap[$action['icon']] ?>"></use>
                                                </svg>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($action['label']) ?>
                                        </button>
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
    const CLEARANCE_UPLOAD_API = ROOT_URL + '/apply/clearance_upload';
    const CLEARANCE_VIEW_URL = ROOT_URL + '/apply/clearance/';

    // ===== Status metadata (mirrors My Protocols) =====
    const statusMeta = <?= json_encode($statusMeta) ?>;

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

    const allRows = protocolsList ? [...protocolsList.querySelectorAll('.protocol')] : [];

    let activeFilter = 'to-review';
    let searchQuery = '';
    let currentPage = 1;
    let rowsPerPage = 10;

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
                confirmAction('Mark this protocol as endorsed? It will move forward for final clearance.', {
                    okText: 'Mark as Endorsed',
                    cancelText: 'Cancel'
                }).then(ok => ok && submitStatusChange(protocolId, 'Endorsed'));
                break;

            case 'upload-clearance':
                openClearanceModal(protocolId, protocol?.research_title ?? '');
                break;
        }
    });

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

    // ===== Status legend info panel (hover on desktop, tap on touch) =====
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

        wrapper.addEventListener('mouseenter', openPanel);
        wrapper.addEventListener('mouseleave', closePanel);
        btn.addEventListener('focus', openPanel);

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
                <p class="history-modal-title" id="historyModalTitle"></p>
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
        historyBackdrop.classList.add('open');

        fetch(ROOT_URL + '/apply/allversions/' + protocolId)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('historyModalBody').innerHTML =
                        '<p class="helper history-error">' + data.error + '</p>';
                    return;
                }
                renderHistory(data);
            })
            .catch(() => {
                document.getElementById('historyModalBody').innerHTML =
                    '<p class="helper history-offline">Submission history is not available offline. It will load once you reconnect.</p>';
            });
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

    function buildHistorySection(versions, sectionLabel, protocolId, isProtocolSection) {
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

            // Protocol submissions open the full reviewer viewer (PDF + comments
            // for that exact version), read-only when it's not the latest round.
            // Certs/auth letters have no annotations, so a quick file preview
            // is still the right call for those.
            const openBtn = isProtocolSection ?
                `<a class="button history-open-btn" href="${ROOT_URL}/apply/viewer/${protocolId}/${v.id}">
                        <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#review-icon" />
                        </svg>
                        Open
                    </a>` :
                `<button class="button history-open-btn"
                        data-file-url="${ROOT_URL + '/apply/file/' + v.id}"
                        data-file-title="${v.original_name.replace(/"/g, '&quot;')}"
                        onclick="openFilePopup(this.dataset.fileUrl, this.dataset.fileTitle)">
                        <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#review-icon" />
                        </svg>
                        Open
                    </button>`;

            return `
                <div class="history-row${isLatest ? ' history-row--latest' : ''}">
                    <div class="history-row-meta">
                        <span class="history-ver">v${v.version_number}</span>
                        ${isLatest ? '<span class="history-latest-badge">Latest</span>' : ''}
                    </div>
                    <div class="history-row-detail">
                        <span class="history-filename">${v.original_name}</span>
                        <span class="helper">${date}</span>
                    </div>
                    ${openBtn}
                </div>`;
        }).join('');
        return `<div class="history-section-label">${sectionLabel}</div>${rows}`;
    }

    function renderHistory(data) {
        const body = document.getElementById('historyModalBody');

        const hasProtocolFiles = data.protocol_files && data.protocol_files.length > 0;
        const hasCertFiles = data.cert_files && data.cert_files.length > 0;
        const hasAuthFiles = data.auth_files && data.auth_files.length > 0;

        if (!hasProtocolFiles && !hasCertFiles && !hasAuthFiles) {
            body.innerHTML = '<p class="helper">No submission history found.</p>';
            return;
        }

        let html = '';
        html += buildHistorySection(data.protocol_files, 'Protocol Submissions', data.protocol_id, true);
        html += buildHistorySection(data.cert_files, 'IACUC Training Certificates', data.protocol_id, false);
        if (!data.is_pi) {
            html += buildHistorySection(data.auth_files, 'Authorization Letters', data.protocol_id, false);
        }
        body.innerHTML = html;
    }
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

<!-- ===== Upload Clearance modal (admin only) ===== -->
<div class="modal-backdrop" id="clearanceModalBackdrop">
    <div class="modal-card clearance-modal-card">
        <h2>Upload Clearance</h2>
        <p id="clearanceSubtitle" class="helper clearance-modal-subtitle"></p>

        <div id="clearanceError" class="alert error-messages clearance-modal-error" hidden></div>

        <label for="clearance_file">Clearance document (PDF)</label>
        <input type="file" id="clearance_file" name="clearance_file"
            accept=".pdf,application/pdf" required>
        <p class="helper clearance-modal-hint">PDF only &middot; max 10 MB</p>

        <div class="modal-actions">
            <button class="button" type="button" onclick="closeClearanceModal()">Cancel</button>
            <button class="button btn-apply" type="button" id="clearanceSubmitBtn"
                onclick="submitClearanceUpload()">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#upload-icon" />
                </svg>
                Upload &amp; Mark as Approved
            </button>
        </div>
    </div>
</div>

<script>
    const clearanceModal = document.getElementById('clearanceModalBackdrop');
    let currentClearanceProtocolId = null;

    function openClearanceModal(protocolId, title) {
        currentClearanceProtocolId = protocolId;
        document.getElementById('clearanceSubtitle').textContent = title;
        document.getElementById('clearance_file').value = '';
        document.getElementById('clearanceError').hidden = true;
        clearanceModal.classList.add('open');
    }

    function closeClearanceModal() {
        clearanceModal.classList.remove('open');
        currentClearanceProtocolId = null;
    }

    clearanceModal.addEventListener('click', e => {
        if (e.target === clearanceModal) closeClearanceModal();
    });

    async function submitClearanceUpload() {
        const fileInput = document.getElementById('clearance_file');
        const errBox = document.getElementById('clearanceError');
        const btn = document.getElementById('clearanceSubmitBtn');

        if (!fileInput.files.length) {
            errBox.textContent = 'Please select a file.';
            errBox.hidden = false;
            return;
        }

        btn.disabled = true;
        errBox.hidden = true;

        const formData = new FormData();
        formData.append('protocol_id', currentClearanceProtocolId);
        formData.append('clearance_file', fileInput.files[0]);
        formData.append('csrf_token', CSRF_TOKEN);

        try {
            const res = await fetch(CLEARANCE_UPLOAD_API, {
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

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>