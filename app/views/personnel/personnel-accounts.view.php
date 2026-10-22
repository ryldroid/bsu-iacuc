<?php
$title = 'Administration';
include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/scroll-top.php';

$user   = $user ?? $_SESSION['user'] ?? [];
$csrf   = $csrf ?? '';
$isStaff = ($user['role'] ?? '') === 'staff';
$auditDateRange = $auditDateRange ?? ['earliest' => null, 'latest' => null];
$auditDefaults  = $auditDefaults ?? ['from' => '', 'to' => ''];
$auditLogs      = $auditLogs      ?? [];
$auditPage      = $auditPage      ?? 1;
$auditPages     = $auditPages     ?? 1;
$auditTotal     = $auditTotal     ?? 0;
$auditPerPage   = $auditPerPage   ?? 10;
$auditFilterDate = $auditFilterDate ?? null;
$auditOffset    = ($auditPage - 1) * $auditPerPage;

$allowedTabs = ['audit-logs', 'accounts', 'backups'];
$activeTab   = !empty($invite_url) ? 'accounts' : (in_array($_GET['tab'] ?? '', $allowedTabs, true)
    ? $_GET['tab']
    : $allowedTabs[0]);
?>

<link rel="stylesheet" href="<?= asset_css('personnel/personnel-base.css') ?>">
<link rel="stylesheet" href="<?= asset_css('pagination.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/personnel-home.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/accounts.css') ?>">
<link rel="stylesheet" href="<?= asset_css('tabs.css') ?>">

<div class="body">
    <?php include dirname(__DIR__) . '/includes/navigation.php'; ?>

    <main class="main-content" id="main-content" tabindex="-1">

        <div class="dashboard-page-header">
            <h1 class="dashboard-page-title">Administration</h1>
        </div>

        <?php include dirname(__DIR__) . '/includes/flash.php'; ?>

        <div class="tab-strip" role="tablist" aria-label="Administration sections" data-tab-panels="accountsTabPanels" data-tab-param="tab">
            <button type="button" role="tab" class="has-guide" id="tab-audit-logs" data-tab="audit-logs"
                aria-selected="<?= $activeTab === 'audit-logs' ? 'true' : 'false' ?>"
                aria-controls="panel-audit-logs">Audit Logs</button>
            <?php $tabGuideId = 'audit-logs';
            $tabGuideLabel = 'Audit Logs';
            include dirname(__DIR__) . '/includes/tab-guide.php'; ?>
            <button type="button" role="tab" class="has-guide" id="tab-accounts" data-tab="accounts"
                aria-selected="<?= $activeTab === 'accounts' ? 'true' : 'false' ?>"
                aria-controls="panel-accounts">Manage Accounts</button>
            <?php $tabGuideId = 'accounts';
            $tabGuideLabel = 'Manage Accounts';
            include dirname(__DIR__) . '/includes/tab-guide.php'; ?>
            <button type="button" role="tab" class="has-guide" id="tab-backups" data-tab="backups"
                aria-selected="<?= $activeTab === 'backups' ? 'true' : 'false' ?>"
                aria-controls="panel-backups">Backups</button>
            <?php $tabGuideId = 'backups';
            $tabGuideLabel = 'Backups';
            include dirname(__DIR__) . '/includes/tab-guide.php'; ?>
        </div>

        <div id="accountsTabPanels">
            <div class="tab-panel" id="panel-audit-logs" role="tabpanel" aria-labelledby="tab-audit-logs"
                data-tab-panel="audit-logs" <?= $activeTab === 'audit-logs' ? '' : 'hidden' ?>>
                <div class="sections-column">
                    <!-- AUDIT LOG VIEWER -->
                    <section class="accounts-card audit-viewer-section" id="audit-log-viewer">
                        <div class="audit-viewer-header">
                            <h2>
                                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <use href="#history-icon" />
                                </svg>
                                Audit Log Viewer
                            </h2>

                            <div class="audit-jump-to-date">
                                <button type="button" id="audit-refresh" class="audit-refresh-btn" aria-label="Refresh audit logs" title="Refresh">
                                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <use href="#refresh-icon" />
                                    </svg>
                                </button>
                                <input type="date" id="audit-jump-date"
                                    value="<?= htmlspecialchars($auditFilterDate ?? '') ?>"
                                    <?= $auditDateRange['earliest'] ? 'min="' . htmlspecialchars($auditDateRange['earliest']) . '"' : '' ?>
                                    <?= $auditDateRange['latest'] ? 'max="' . htmlspecialchars($auditDateRange['latest']) . '"' : '' ?>>
                                <button type="button" id="audit-jump-clear" class="accounts-btn-primary" <?= empty($auditFilterDate) ? 'hidden' : '' ?>>
                                    Show all
                                </button>
                            </div>
                        </div>

                        <div id="audit-log-results" data-page="<?= $auditPage ?>" data-date="<?= htmlspecialchars($auditFilterDate ?? '') ?>">
                            <?php include __DIR__ . '/partials/audit-log-results.view.php'; ?>
                        </div>
                    </section>

                    <!-- DOWNLOAD AUDIT LOGS -->
                    <section class="accounts-card audit-section">
                        <h2>Download Audit Logs</h2>
                        <p class="audit-description">Export system activity logs to Excel (default at 90 days selection)</p>

                        <form method="POST" action="<?= ROOT ?>/personnel/downloadAuditLogs" class="audit-form" id="auditForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                            <div class="form-section">
                                <label for="audit-from-date">From</label>
                                <input type="date" id="audit-from-date" name="from_date"
                                    value="<?= htmlspecialchars($auditDefaults['from']) ?>"
                                    <?= $auditDateRange['earliest'] ? 'min="' . htmlspecialchars($auditDateRange['earliest']) . '"' : '' ?>
                                    <?= $auditDateRange['latest'] ? 'max="' . htmlspecialchars($auditDateRange['latest']) . '"' : '' ?>>
                            </div>

                            <div class="form-section">
                                <label for="audit-to-date">To</label>
                                <input type="date" id="audit-to-date" name="to_date"
                                    value="<?= htmlspecialchars($auditDefaults['to']) ?>"
                                    <?= $auditDateRange['earliest'] ? 'min="' . htmlspecialchars($auditDateRange['earliest']) . '"' : '' ?>
                                    <?= $auditDateRange['latest'] ? 'max="' . htmlspecialchars($auditDateRange['latest']) . '"' : '' ?>>
                            </div>

                            <div class="form-section audit-full-history">
                                <label class="audit-checkbox-label">
                                    <input type="checkbox" id="audit-full-history" name="full_history" value="1">
                                    Export full history
                                </label>
                            </div>

                            <button type="submit" class="accounts-btn-primary">
                                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <use href="#download-icon" />
                                </svg>
                                Download Audit Logs
                            </button>
                        </form>
                    </section>
                </div>
            </div>

            <div class="tab-panel" id="panel-accounts" role="tabpanel" aria-labelledby="tab-accounts"
                data-tab-panel="accounts" <?= $activeTab === 'accounts' ? '' : 'hidden' ?>>
                <?php if ($isStaff): ?>
                    <!-- PENDING ACCOUNTS -->
                    <section class="accounts-card pending-section">
                        <h2>Pending Personnel Approvals</h2>

                        <?php if (empty($pending)): ?>
                            <div class="empty-state">
                                <h3>
                                    <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <use href="#check-circle-icon" />
                                    </svg>
                                    All caught up
                                </h3>
                                <p>No pending applications right now.</p>
                            </div>

                        <?php else: ?>
                            <p class="pending-count"><?= count($pending); ?> pending application(s)</p>

                            <?php foreach ($pending as $applicant): ?>
                                <div class="pending-box">
                                    <div>
                                        <span class="bold"><?= htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?></span>
                                        <span class="username">@<?= htmlspecialchars($applicant['username']); ?></span>
                                        <div class="pending-meta">
                                            <?= htmlspecialchars($applicant['email']); ?>
                                            &middot; <?= htmlspecialchars($applicant['role']); ?>
                                            &middot; <?= date(DATETIME_FORMAT, strtotime($applicant['created_at'])) ?>
                                        </div>
                                    </div>

                                    <div class="actions">
                                        <form method="POST" action="<?= ROOT ?>/personnel/approve"
                                            data-confirm-message="Approve this application?"
                                            data-confirm-ok-text="Approve">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf); ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $applicant['id']; ?>">
                                            <button type="submit" class="accounts-btn-primary">Approve</button>
                                        </form>

                                        <form method="POST" action="<?= ROOT ?>/personnel/reject"
                                            data-confirm-message="Reject and delete this application?"
                                            data-confirm-ok-text="Confirm"
                                            data-confirm-danger="true">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf); ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $applicant['id']; ?>">
                                            <button type="submit" class="reject-btn">Reject</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>

                    <!-- GENERATE INVITE LINK -->
                    <section class="accounts-card invite-section">
                        <h2>Personnel Registration Link</h2>

                        <form class="invite-form" action="<?= ROOT ?>/personnel/generate_invite" method="POST">
                            <input type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($csrf) ?>">

                            <div class="form-section">
                                <label for="invite-role">Select Role:</label>
                                <select name="invite_role" id="invite-role">
                                    <option value="staff">Administrative Staff</option>
                                    <option value="reviewer">Reviewer</option>
                                </select>
                            </div>

                            <button type="submit" class="accounts-btn-primary">
                                Generate Invite Link
                            </button>
                        </form>

                        <?php if (!empty($invite_url)): ?>
                            <div class="invite-link-container">
                                <label for="invite-link">Invite Link</label>

                                <div class="invite-link-row">
                                    <input
                                        id="invite-link"
                                        type="text"
                                        readonly
                                        value="<?= htmlspecialchars($invite_url) ?>">

                                    <button
                                        type="button"
                                        class="accounts-btn-primary"
                                        onclick="copyInviteLink()">
                                        Copy
                                    </button>
                                </div>

                                <p id="copy-message"></p>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <!-- MANAGE PERSONNEL & ROLES -->
                <section class="accounts-card personnel-manage-section">
                    <h2>
                        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#shield-check-icon" />
                        </svg>
                        CCARD Personnel
                    </h2>

                    <?php if (empty($personnel)): ?>
                        <div class="empty-state">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <use href="#check-circle-icon" />
                                </svg>
                                No personnel yet
                            </h3>
                            <p>Approved staff and reviewers will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="data-table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($personnel as $p): ?>
                                        <?php
                                        $isSelf = (int) $p['id'] === (int) ($user['user_id'] ?? 0);
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="personnel-name">
                                                    <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?>
                                                    <?php if ($isSelf): ?>
                                                        <span class="personnel-status-pill personnel-status-active personnel-you-tag">You</span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="personnel-username">@<?= htmlspecialchars($p['username']) ?></span>
                                            </td>
                                            <td>
                                                <form method="POST" action="<?= ROOT ?>/personnel/update_role" class="personnel-role-form">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                    <input type="hidden" name="user_id" value="<?= (int) $p['id'] ?>">
                                                    <input type="hidden" name="password" class="personnel-role-password">
                                                    <select name="role" class="personnel-role-select"
                                                        data-username="<?= htmlspecialchars($p['username']) ?>"
                                                        data-original-role="<?= htmlspecialchars($p['role']) ?>"
                                                        <?= $isSelf ? 'disabled' : '' ?>
                                                        onchange="handleRoleChange(this)">
                                                        <option value="staff" <?= $p['role'] === 'staff' ? 'selected' : '' ?>>Administrative Staff</option>
                                                        <option value="reviewer" <?= $p['role'] === 'reviewer' ? 'selected' : '' ?>>Reviewer</option>
                                                    </select>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <div class="tab-panel" id="panel-backups" role="tabpanel" aria-labelledby="tab-backups"
                data-tab-panel="backups" <?= $activeTab === 'backups' ? '' : 'hidden' ?>>
                <div class="sections-column">
                    <!-- BACKUP MANAGER -->
                    <section class="accounts-card audit-section" id="backup-manager"
                        data-endpoint="<?= ROOT ?>/backups" data-csrf="<?= htmlspecialchars($csrf) ?>">
                        <div class="audit-viewer-header">
                            <h2>
                                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <use href="#download-icon" />
                                </svg>
                                Backups
                            </h2>
                            <div class="backup-actions">
                                <button type="button" id="backup-upload" class="accounts-btn-primary">
                                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <use href="#upload-icon" />
                                    </svg>
                                    Upload Backup
                                </button>
                                <button type="button" id="backup-create" class="accounts-btn-primary">
                                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <use href="#add-icon" />
                                    </svg>
                                    Create Backup
                                </button>
                                <input type="file" id="backup-upload-input" accept=".zip,application/zip" hidden>
                            </div>
                        </div>
                        <p class="audit-description" id="backup-status">Each backup holds the database, protocol files and site uploads. The 10 newest are kept.</p>
                        <div id="backup-health"></div>
                        <div class="upload-progress-container" id="backup-progress"></div>
                        <div class="alert error-messages" id="backup-error" hidden></div>
                        <div id="backup-results"></div>
                    </section>
                </div>
            </div>
        </div>

    </main>
</div>

<?php
$guideId    = 'audit-logs';
$guideSteps = [
    ['Audit Logs', 'This page keeps a record of actions taken in the system. Let us walk through it.'],
    ['Audit log viewer', 'Each entry shows when it happened, who did it, the action, what it affected, the details, and the IP address.', '#audit-log-results'],
    ['Refresh and date', 'Press the refresh icon to load the latest entries, or pick a date to see only that day. **Show all** brings every entry back.', '.audit-jump-to-date'],
];
if ($auditPages > 1) {
    $guideSteps[] = ['Pagination', 'Move between pages of entries.', '#audit-log-results .pagination-bar'];
}
$guideSteps[] = ['Download Audit Logs', 'Export entries to Excel for the dates you choose. The default is the last 90 days, or tick **Export full history** for everything.', '#auditForm'];
$guideSteps[] = ['Manage Accounts tab', 'Switch to **Manage Accounts** to approve new personnel and manage roles.', '#tab-accounts'];
include dirname(__DIR__) . '/includes/guide.php';

$guideId    = 'accounts';
$guideSteps = [
    ['Manage Accounts', 'Approve new personnel, invite staff or reviewers, and manage roles. Let us walk through it.'],
];
if ($isStaff) {
    $guideSteps[] = ['Pending approvals', 'New personnel applications wait here. **Approve** gives access, and **Reject** deletes the application.', '.pending-section'];
    $guideSteps[] = ['Registration link', 'Choose a role and generate a registration link to send to a new staff member or reviewer. The link works for 48 hours.', '.invite-section'];
}
$guideSteps[] = ['CCARD Personnel', 'Everyone approved is listed here. Use the role dropdown to switch someone between Administrative Staff and Reviewer. You will be asked for your password to confirm, and your own role cannot be changed.', '.personnel-manage-section'];
$guideSteps[] = ['Audit Logs tab', 'Switch to **Audit Logs** to review activity and download it.', '#tab-audit-logs'];
include dirname(__DIR__) . '/includes/guide.php';

$guideId    = 'backups';
$guideSteps = [
    ['Backups', 'Create and download copies of the database and uploaded files. Let us walk through it.'],
    ['Create a backup', 'Press **Create Backup**. It runs in steps, so keep this page open until the bar reaches 100%.', '#backup-create'],
    ['Upload a backup', 'Press **Upload Backup** and choose a backup ZIP you downloaded earlier. It is sent in small pieces and checked before it appears in the list. Then use **Restore** to bring it back.', '#backup-upload'],
    ['Backup health', 'This line shows the last backup time and the limits of the server. A red notice appears when the newest backup is 3 days old or more, or when none exists.', '#backup-health'],
    ['Saved backups', '**Download** a backup to keep a copy off the server, **Verify** it to check that it is intact without restoring it, or **Delete** ones you no longer need. The 10 newest are kept. Backups marked **Encrypted** can only be restored with the BACKUP_PASSPHRASE from your .env file. If you lose it, those backups cannot be opened.', '#backup-results'],
    ['Restore', '**Restore** replaces all current data and uploads with a backup. A safety backup of the current data is saved first, and you confirm with your password. If a backup has data that points to missing records, the restore is refused and nothing changes. Use a newer backup instead.', '#backup-results'],
];
include dirname(__DIR__) . '/includes/guide.php';
?>

<!-- Confirm role change modal -->

<!-- ===== Role change confirmation modal ===== -->
<div class="modal-backdrop" id="roleConfirmBackdrop" data-static>
    <div class="modal-card panel-modal-card">
        <div class="modal-header">
            <span class="modal-header-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                    <use href="#shield-check-icon" />
                </svg>
            </span>
            <p class="modal-title">Confirm Role Change</p>
            <button class="modal-close" type="button" onclick="closeRoleConfirm()" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
            </button>
        </div>
        <div class="panel-modal-body">
            <p class="helper" id="roleConfirmText"></p>

            <div id="roleConfirmError" class="alert error-messages" hidden></div>

            <div class="role-confirm-field">
                <label for="roleConfirmPassword">Enter your password to confirm</label>
                <input type="password" id="roleConfirmPassword" class="role-confirm-password" autocomplete="current-password">
            </div>

            <div class="modal-actions">
                <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="closeRoleConfirm()">Cancel</button>
                <button class="confirm-modal-btn confirm-modal-ok" type="button" id="roleConfirmSubmitBtn" onclick="submitRoleConfirm()">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#check-icon" />
                    </svg>
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Restore backup modal -->
<div class="modal-backdrop" id="backupRestoreBackdrop" data-static>
    <div class="modal-card panel-modal-card">
        <div class="modal-header">
            <span class="modal-header-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                    <use href="#undo-icon" />
                </svg>
            </span>
            <p class="modal-title">Restore Backup</p>
            <button class="modal-close" type="button" id="backupRestoreClose" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
            </button>
        </div>
        <div class="panel-modal-body">
            <p class="helper" id="backupRestoreText"></p>
            <p class="helper">This replaces all current data and uploaded files with the contents of this backup. A safety backup of the current data is saved first. Anything submitted while the restore runs will be lost, and you may be signed out afterwards.</p>

            <div id="backupRestoreError" class="alert error-messages" hidden></div>

            <div class="role-confirm-field">
                <label for="backupRestorePassword">Enter your password to confirm</label>
                <input type="password" id="backupRestorePassword" class="role-confirm-password" autocomplete="current-password">
            </div>

            <div class="role-confirm-field">
                <label for="backupRestoreConfirm">Type RESTORE to confirm</label>
                <input type="text" id="backupRestoreConfirm" class="role-confirm-password" autocomplete="off">
            </div>

            <div class="modal-actions">
                <button class="confirm-modal-btn confirm-modal-cancel" type="button" id="backupRestoreCancel">Cancel</button>
                <button class="confirm-modal-btn confirm-modal-ok" type="button" id="backupRestoreSubmit">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#undo-icon" />
                    </svg>
                    Restore
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== Scripts ===== -->
<script src="<?= asset_js('tabs.js') ?>" defer></script>
<script src="<?= asset_js('flash-dismiss.js') ?>" defer></script>
<script src="<?= asset_js('backups.js') ?>" defer></script>

<script>
    // ===== Copy invite link =====
    function copyInviteLink() {
        const input = document.getElementById('invite-link');

        navigator.clipboard.writeText(input.value)
            .then(() => {
                document.getElementById('copy-message').textContent =
                    'Copied to clipboard!';
            })
            .catch(() => {
                document.getElementById('copy-message').textContent =
                    'Failed to copy.';
            });
    }

    const auditFullHistoryCheckbox = document.getElementById('audit-full-history');
    const auditFromDate = document.getElementById('audit-from-date');
    const auditToDate = document.getElementById('audit-to-date');

    if (auditFullHistoryCheckbox) {
        auditFullHistoryCheckbox.addEventListener('change', () => {
            const disabled = auditFullHistoryCheckbox.checked;
            auditFromDate.disabled = disabled;
            auditToDate.disabled = disabled;
        });
    }

    // ===== Role change: confirm with password =====
    (function() {
        const backdrop = document.getElementById('roleConfirmBackdrop');
        const text = document.getElementById('roleConfirmText');
        const error = document.getElementById('roleConfirmError');
        const passwordInput = document.getElementById('roleConfirmPassword');
        const submitBtn = document.getElementById('roleConfirmSubmitBtn');

        if (!backdrop) return;

        let pendingSelect = null;

        window.handleRoleChange = function(select) {
            pendingSelect = select;

            const roleLabel = select.options[select.selectedIndex].text;
            const username = select.dataset.username;

            text.textContent = `Change @${username}'s role to ${roleLabel}?`;
            error.hidden = true;
            passwordInput.value = '';

            backdrop.classList.add('open');
            passwordInput.focus();
        };

        window.closeRoleConfirm = function() {
            backdrop.classList.remove('open');
            if (pendingSelect) {
                pendingSelect.value = pendingSelect.dataset.originalRole;
                pendingSelect = null;
            }
        };

        window.submitRoleConfirm = function() {
            if (!pendingSelect) return;

            const password = passwordInput.value;
            if (!password) {
                error.textContent = 'Please enter your password.';
                error.hidden = false;
                passwordInput.focus();
                return;
            }

            const form = pendingSelect.closest('form');
            form.querySelector('.personnel-role-password').value = password;

            setButtonBusy(submitBtn, true, 'Confirming...');
            pendingSelect = null;
            form.submit();
        };

        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) closeRoleConfirm();
        });

        passwordInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitRoleConfirm();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && backdrop.classList.contains('open')) closeRoleConfirm();
        });
    })();

    // ===== Audit log viewer: pagination & jump to date =====
    (function() {
        const results = document.getElementById('audit-log-results');
        const jumpDate = document.getElementById('audit-jump-date');
        const jumpClear = document.getElementById('audit-jump-clear');
        const refreshButton = document.getElementById('audit-refresh');

        if (!results) return;

        function loadAuditPage(page, date) {
            results.classList.add('is-loading');

            const params = new URLSearchParams({
                audit_page: page
            });
            if (date) params.set('audit_date', date);

            fetch(`<?= ROOT ?>/personnel/auditLogResults?${params.toString()}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then((response) => response.json())
                .then((data) => {
                    if (!data.ok) return;

                    results.innerHTML = data.html;
                    results.dataset.page = data.page;
                    results.dataset.date = date || '';
                    jumpClear.hidden = !date;

                    const url = new URL(location.href);
                    url.searchParams.set('audit_page', data.page);
                    if (date) url.searchParams.set('audit_date', date);
                    else url.searchParams.delete('audit_date');
                    history.replaceState(history.state, '', url);
                })
                .catch(() => {})
                .finally(() => {
                    results.classList.remove('is-loading');
                });
        }

        results.addEventListener('click', (event) => {
            const link = event.target.closest('.js-audit-page');
            if (!link) return;

            event.preventDefault();
            loadAuditPage(link.dataset.page, results.dataset.date);
        });

        if (jumpDate) {
            jumpDate.addEventListener('change', () => {
                loadAuditPage(1, jumpDate.value);
            });
        }

        if (refreshButton) {
            refreshButton.addEventListener('click', () => {
                loadAuditPage(results.dataset.page, results.dataset.date);
            });
        }

        if (jumpClear) {
            jumpClear.addEventListener('click', () => {
                jumpDate.value = '';
                jumpClear.hidden = true;
                loadAuditPage(1, '');
            });
        }
    })();
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>