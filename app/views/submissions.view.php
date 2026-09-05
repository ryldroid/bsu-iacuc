<?php

/** @var array $protocols */
/** @var array $statuses  */
/** @var bool  $hasCertOnFile */

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
        'desc'  => 'Being reviewed by the IACUC reviewer. No action needed. You will be notified if changes are required or once a decision is made.',
    ],
    'needs-revision' => [
        'label' => 'Needs Revision',
        'color' => '#D55E00',
        'icon'  => 'alert-triangle-icon',
        'desc'  => 'The reviewer found an issue and sent it back. Review the comments and re-submit your protocol to upload your revised file.',
    ],
    'reviewed' => [
        'label' => 'Reviewed',
        'color' => '#CC79A7',
        'icon'  => 'checkbox-icon',
        'desc'  => 'The reviewer has finished their assessment. No action needed. Pending endorsement to DA-CARFU and the BAI Central Office.',
    ],
    'endorsed' => [
        'label' => 'Endorsed',
        'color' => '#E69F00',
        'icon'  => 'shield-check-icon',
        'desc'  => 'Your protocol has been endorsed. No action needed. Please wait as BAI processes your animal research permit.',
    ],
    'approved' => [
        'label' => 'Approved',
        'color' => '#009E73',
        'icon'  => 'check-circle-icon',
        'desc'  => 'Congratulations, your protocol has been approved! Click "Download Clearance" to get your official IACUC clearance certificate.',
    ],
];

function statusIconSvg(string $iconId, int $size = 14): string
{
    return '<svg class="status-icon-svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<use href="#' . htmlspecialchars($iconId, ENT_QUOTES, 'UTF-8') . '" />'
        . '</svg>';
}
?>

<link rel="stylesheet" href="<?= asset_css('protocol-list.css') ?>">
<link rel="stylesheet" href="<?= asset_css('submissions.css') ?>">

<div class="body">
    <?php include 'includes/navigation.php'; ?>

    <main class="main-content" id="main-content" tabindex="-1">
        <div class="submission-header">
            <h1>My Protocols</h1>

            <div id="apply-actions-sub">
                <a href="<?= ROOT ?>/apply" class="btn-apply button">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#add-icon" />
                    </svg>
                    <span>New Application</span>
                </a>
            </div>
        </div>

        <?php if (isset($_GET['submitted'])): ?>
            <div class="alert success-message" id="flashSuccess">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#check-icon">
                </svg>
                Your protocol has been submitted successfully. We will review it shortly.
            </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert success-message" id="flashSuccess">
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

        <!-- Status filter bar -->
        <div class="filter-wrapper">
            <div class="mobile-status-filters button">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#filter-icon" />
                </svg>
                Status: <span id="mobileFilterLabel" class="mobile-filter-label">All</span>
            </div>

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
                    $date          = date('M j, Y', strtotime($protocol['submitted_at']));
                    $statusKey     = strtolower(str_replace(' ', '-', $protocol['status']));
                    $statusLabel   = htmlspecialchars($protocol['status'], ENT_QUOTES, 'UTF-8');
                    $needsRevision  = strtolower($protocol['status']) === 'needs revision';
                    $returnIssues   = [];
                    if ($needsRevision) {
                        if (!empty($protocol['rr_wrong_cert']))  $returnIssues[] = 'Wrong / invalid training certificate';
                        if (!empty($protocol['rr_wrong_auth']))  $returnIssues[] = 'Wrong / invalid authorization letter';
                        if (!empty($protocol['rr_other_reason'])) $returnIssues[] = 'Other';
                    }
                    $isApproved    = strtolower($protocol['status']) === 'approved';
                    $versionNum    = $protocol['latest_version'] ? 'v' . (int) $protocol['latest_version'] : 'v1';
                    $protocolIdInt = (int) $protocol['protocol_id'];
                ?>
                    <div class="protocol" data-status="<?= $statusKey ?>">

                        <span class="protocol-status-icon" style="background:<?= $statusMeta[$statusKey]['color'] ?? 'var(--muted-text)' ?>">
                            <?= statusIconSvg($statusMeta[$statusKey]['icon'] ?? 'check-circle-icon', 19) ?>
                        </span>

                        <div class="protocol-body">
                            <div class="protocol-meta">
                                <p class="research-title">
                                    <?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <p class="protocol-meta-line" title="Version no. (number of rounds submitted) and the submission date">
                                    <?= $versionNum ?> &middot; <?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>
                                </p>

                                <?php if ($needsRevision && (!empty($returnIssues) || !empty($protocol['rr_comment']))): ?>
                                    <div class="return-reason-inline">
                                        <p class="return-reason-by">
                                            Note from the reviewer:
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
                                <a class="button button--primary" href="<?= ROOT ?>/apply/viewer/<?= $protocolIdInt ?>"
                                    onclick="event.preventDefault(); openProtocol(<?= $protocolIdInt ?>)">
                                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <use href="#<?= $needsRevision ? 'upload-icon' : 'eye-icon' ?>" />
                                    </svg>
                                    <?= $needsRevision ? 'Open &amp; Re-submit' : 'Open' ?>
                                </a>

                                <?php if ($isApproved): ?>
                                    <a class="download-clearance-btn button button--primary"
                                        href="<?= ROOT ?>/apply/clearance/<?= $protocolIdInt ?>" target="_blank" rel="noopener">
                                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <use href="#download-icon" />
                                        </svg>
                                        Download Clearance
                                    </a>
                                <?php endif; ?>

                                <div class="actions-secondary">
                                    <button class="action-link"
                                        data-protocol-id="<?= $protocolIdInt ?>"
                                        data-title="<?= htmlspecialchars($protocol['research_title'], ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="openHistoryModal(+this.dataset.protocolId, this.dataset.title)">
                                        Show History
                                    </button>
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
        <?php endif; ?>
    </main>
</div>

<!-- History modal -->
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
            <button class="history-modal-close button" onclick="closeHistoryModal()" aria-label="Close">
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

<!-- File popup modal (cert / auth letter / protocol versions) -->
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
    const ROOT_URL = <?= json_encode(ROOT) ?>;
    const statusMeta = <?= json_encode($statusMeta) ?>;
    const filterBtns = document.querySelectorAll('.status-card');
    const protocolCards = document.querySelectorAll('.protocol');
    const mobileFilter = document.querySelector('.mobile-status-filters');
    const statusFilters = document.querySelector('.status-filters');
    let currentFilter = 'all';

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

        const activeBtn = [...filterBtns].find(b => b.dataset.status === selected);
        const mobileFilterLabel = document.getElementById('mobileFilterLabel');
        if (mobileFilterLabel && activeBtn) mobileFilterLabel.textContent = activeBtn.dataset.label;

        const url = new URL(window.location);
        if (selected === 'all') {
            url.searchParams.delete('status');
        } else {
            url.searchParams.set('status', selected);
        }
        history.replaceState(null, '', url);

        protocolCards.forEach(card => {
            card.style.display = (selected === 'all' || card.dataset.status === selected) ? '' : 'none';
        });

        const visibleCards = [...protocolCards].filter(c => c.style.display !== 'none');
        const emptyMsg = document.querySelector('.no-results');
        if (emptyMsg) emptyMsg.style.display = visibleCards.length === 0 ? 'block' : 'none';
    }

    mobileFilter?.addEventListener('click', e => {
        e.stopPropagation();
        statusFilters.classList.toggle('active');
    });
    document.addEventListener('click', e => {
        if (!statusFilters?.contains(e.target) && !mobileFilter?.contains(e.target)) {
            statusFilters?.classList.remove('active');
        }
    });

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            applySubmissionsFilter(btn.dataset.status);
            statusFilters.classList.remove('active');
        });
    });

    function openProtocol(protocolId) {
        window.location.href = ROOT_URL + '/apply/viewer/' + parseInt(protocolId, 10) + '?from=' + encodeURIComponent(currentFilter);
    }

    (function restoreFilterFromUrl() {
        const requestedStatus = new URLSearchParams(window.location.search).get('status');
        if (requestedStatus && [...filterBtns].some(b => b.dataset.status === requestedStatus)) {
            applySubmissionsFilter(requestedStatus);
        }
    })();

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
                            <span>Resume Application</span>
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
                            <span class="new-span">New Application</span>
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

    // ===== History modal =====
    const historyBackdrop = document.getElementById('historyModalBackdrop');

    function openHistoryModal(protocolId, title) {
        document.getElementById('historyModalTitle').textContent = title;
        document.getElementById('historyModalBody').innerHTML = '<p class="helper history-loading">Loading&hellip;</p>';

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
                    '<p class="helper history-error">Network error. Please try again.</p>';
            });
    }

    function renderRenameHistory(titleHistory) {
        const renameToggle = document.getElementById('renameHistoryToggle');
        const renamePanel = document.getElementById('renameHistoryPanel');

        if (!titleHistory || titleHistory.length === 0) {
            renameToggle.hidden = true;
            return;
        }

        renamePanel.innerHTML = '<div class="rename-history-panel-header">Title Name History</div>' + titleHistory.map(h => {
            const who = h.changed_by_name ?
                `${escapeHtml(h.changed_by_role ? h.changed_by_role.charAt(0).toUpperCase() + h.changed_by_role.slice(1) : '')} - ${escapeHtml(h.changed_by_name)}` :
                'Initial title';
            return `<div class="rename-history-entry">
                <div class="rename-history-entry-title">${escapeHtml(h.title)}</div>
                <div class="rename-history-entry-meta">${who} &middot; ${formatDate(h.changed_at)}</div>
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

    // ===== File popup (cert / auth letter / protocol versions) =====
    const filePopupBackdrop = document.getElementById('filePopupBackdrop');

    function openFilePopup(fileUrl, title) {
        document.getElementById('filePopupTitle').textContent = title;
        document.getElementById('filePopupFrame').src = fileUrl;
        filePopupBackdrop.classList.add('open');
    }

    function closeFilePopup() {
        filePopupBackdrop.classList.remove('open');
        document.getElementById('filePopupFrame').src = 'about:blank';
    }

    filePopupBackdrop.addEventListener('click', e => {
        if (e.target === filePopupBackdrop) closeFilePopup();
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, ch => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        } [ch]));
    }

    function formatDate(isoString) {
        return new Date(isoString).toLocaleString('en-PH', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function buildReturnNote(reason) {
        const issueLabels = [];
        if (reason.wrong_cert) issueLabels.push('Wrong / invalid training certificate');
        if (reason.wrong_auth) issueLabels.push('Wrong / invalid authorization letter');
        if (reason.other_reason) issueLabels.push('Other');

        return `<div class="history-return-note">
            <div class="history-return-note-header">
                <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#info-icon" />
                </svg>
                Returned for revision by ${escapeHtml(reason.first_name)} ${escapeHtml(reason.last_name)}
            </div>
            <p class="history-return-date">${formatDate(reason.created_at)}</p>
            ${issueLabels.length > 0 ? `<ul class="history-return-issues">${issueLabels.map(l => `<li>${l}</li>`).join('')}</ul>` : ''}
            ${reason.comment ? `<p class="history-return-comment">${escapeHtml(reason.comment)}</p>` : ''}
        </div>`;
    }

    function buildVersionRows(versions, protocolId, returnReason) {
        if (!versions || versions.length === 0) return '';

        const rows = versions.map((v, index) => {
            const isLatest = index === 0;
            const dateString = formatDate(v.uploaded_at);

            return `
                <div class="history-entry">
                    <div class="history-row${isLatest ? ' history-row--latest' : ''}">
                        <div class="history-row-meta">
                            <span class="history-ver">v${v.version_number}</span>
                            ${isLatest ? '<span class="history-latest-badge">Latest</span>' : ''}
                        </div>
                        <div class="history-row-detail">
                            <span class="history-filename">${escapeHtml(v.title_at_version || v.original_name)}</span>
                            <span class="helper">${dateString}</span>
                        </div>
                        <a class="button history-open-btn" href="${ROOT_URL}/apply/viewer/${protocolId}/${v.id}">
                            <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#review-icon" />
                            </svg>
                            Open
                        </a>
                    </div>
                    ${isLatest && returnReason ? buildReturnNote(returnReason) : ''}
                </div>`;
        }).join('');

        return `<div class="history-section-label">Protocol Submissions</div>${rows}`;
    }

    function renderHistory(data) {
        const body = document.getElementById('historyModalBody');

        if (!data.protocol_files || data.protocol_files.length === 0) {
            body.innerHTML = '<p class="helper" style="padding:1.5rem">No submission history found.</p>';
            return;
        }

        body.innerHTML = buildVersionRows(data.protocol_files, data.protocol_id, data.return_reason);
    }

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

    // ===== Auto-dismiss flash messages =====
    (function() {
        function dismissFlash(elementId, delayMs) {
            const el = document.getElementById(elementId);
            if (!el) return;
            setTimeout(() => {
                el.style.transition = 'opacity 0.4s ease';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 420);
            }, delayMs);
        }
        dismissFlash('flashSuccess', 4000);
        dismissFlash('flashError', 7000);
    })();
</script>

<?php include 'includes/footer.php'; ?>