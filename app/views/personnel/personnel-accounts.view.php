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

$activeTab = in_array($_GET['tab'] ?? '', ['registration', 'roles', 'audit-logs'], true)
    ? $_GET['tab']
    : 'registration';
?>

<link rel="stylesheet" href="<?= asset_css('personnel/personnel-base.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/personnel-home.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/accounts.css') ?>">
<link rel="stylesheet" href="<?= asset_css('tabs.css') ?>">

<div class="body">
    <?php include dirname(__DIR__) . '/includes/navigation.php'; ?>

    <main class="main-content" id="main-content" tabindex="-1">

        <div class="dashboard-page-header">
            <h1 class="dashboard-page-title">Administration</h1>
        </div>

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

        <?php if ($isStaff): ?>
            <div class="tab-strip" role="tablist" aria-label="Administration sections" data-tab-panels="accountsTabPanels" data-tab-param="tab">
                <button type="button" role="tab" id="tab-registration" data-tab="registration"
                    aria-selected="<?= $activeTab === 'registration' ? 'true' : 'false' ?>"
                    aria-controls="panel-registration">Registration</button>
                <button type="button" role="tab" id="tab-roles" data-tab="roles"
                    aria-selected="<?= $activeTab === 'roles' ? 'true' : 'false' ?>"
                    aria-controls="panel-roles">Roles</button>
                <button type="button" role="tab" id="tab-audit-logs" data-tab="audit-logs"
                    aria-selected="<?= $activeTab === 'audit-logs' ? 'true' : 'false' ?>"
                    aria-controls="panel-audit-logs">Audit Logs</button>
            </div>
        <?php endif; ?>

        <div id="accountsTabPanels">
            <?php if ($isStaff): ?>
                <div class="tab-panel" id="panel-registration" role="tabpanel" aria-labelledby="tab-registration"
                    data-tab-panel="registration" <?= $activeTab === 'registration' ? '' : 'hidden' ?>>
                    <div class="sections">
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
                                            &nbsp;&middot;
                                            <span class="username">@<?= htmlspecialchars($applicant['username']); ?></span>
                                            &nbsp;&middot;
                                            <span><?= htmlspecialchars($applicant['email']); ?></span>
                                        </div>

                                        <div>
                                            <p>Applying as <span class="bold"><?= htmlspecialchars($applicant['role']); ?></span></p>
                                            &nbsp;&middot;

                                            <?php $date = date('M j, Y @ h:i A', strtotime($applicant['created_at'])); ?>
                                            <span class="application-date"><?= $date ?></span>
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
                                                data-confirm-ok-text="Reject"
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
                    </div>
                </div>

                <div class="tab-panel" id="panel-roles" role="tabpanel" aria-labelledby="tab-roles"
                    data-tab-panel="roles" <?= $activeTab === 'roles' ? '' : 'hidden' ?>>
                    <!-- MANAGE PERSONNEL & ROLES -->
                    <section class="accounts-card personnel-manage-section">
                        <h2>
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#shield-check-icon" />
                            </svg>
                            Manage Personnel &amp; Roles
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
                            <div class="personnel-table-wrap">
                                <table class="personnel-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th aria-label="Actions"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($personnel as $p): ?>
                                            <?php
                                            $isSelf         = (int) $p['id'] === (int) ($user['user_id'] ?? 0);
                                            $isDeactivated  = $p['status'] === 'deactivated';
                                            ?>
                                            <tr>
                                                <td>
                                                    <span class="personnel-name"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></span>
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
                                                <td>
                                                    <span class="personnel-status-pill <?= $isDeactivated ? 'personnel-status-inactive' : 'personnel-status-active' ?>">
                                                        <?= $isDeactivated ? 'Deactivated' : 'Active' ?>
                                                    </span>
                                                </td>
                                                <td class="actions-cell">
                                                    <?php if ($isSelf): ?>
                                                        <span class="personnel-you-tag">You</span>
                                                    <?php else: ?>
                                                        <form method="POST" action="<?= ROOT ?>/personnel/toggle_status"
                                                            data-confirm-message="<?= $isDeactivated ? 'Reactivate' : 'Deactivate' ?> <?= htmlspecialchars($p['username']) ?>&#39;s account?"
                                                            data-confirm-ok-text="<?= $isDeactivated ? 'Reactivate' : 'Deactivate' ?>"
                                                            data-confirm-danger="<?= $isDeactivated ? 'false' : 'true' ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                            <input type="hidden" name="user_id" value="<?= (int) $p['id'] ?>">
                                                            <button type="submit" class="personnel-status-btn <?= $isDeactivated ? 'accounts-btn-primary' : 'reject-btn' ?>">
                                                                <?= $isDeactivated ? 'Reactivate' : 'Deactivate' ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            <?php endif; ?>

            <div class="tab-panel" id="panel-audit-logs" role="tabpanel" <?= $isStaff ? 'aria-labelledby="tab-audit-logs"' : '' ?>
                data-tab-panel="audit-logs" <?= (!$isStaff || $activeTab === 'audit-logs') ? '' : 'hidden' ?>>
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
                        <p class="audit-description">Export system activity logs to Excel. Select a date range, with the last 90 days selected by default.</p>

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
                                    Export full history instead
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
        </div>

    </main>
</div>

<!-- Confirm role change modal -->
<div class="modal-backdrop" id="roleConfirmBackdrop">
    <div class="modal-card">
        <h2>Confirm Role Change</h2>
        <p class="helper" id="roleConfirmText"></p>

        <div id="roleConfirmError" class="alert error-messages" hidden></div>

        <div class="role-confirm-field">
            <label for="roleConfirmPassword">Enter your password to confirm</label>
            <input type="password" id="roleConfirmPassword" class="role-confirm-password" autocomplete="current-password">
        </div>

        <div class="modal-actions">
            <button class="button" type="button" onclick="closeRoleConfirm()">Cancel</button>
            <button class="button btn-apply" type="button" id="roleConfirmSubmitBtn" onclick="submitRoleConfirm()">Confirm</button>
        </div>
    </div>
</div>

<script src="<?= asset_js('tabs.js') ?>" defer></script>

<script>
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

    // ROLE CHANGE - confirm with password before submitting
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

    // AUDIT LOG VIEWER - AJAX pagination + jump to date
    (function() {
        const results = document.getElementById('audit-log-results');
        const jumpDate = document.getElementById('audit-jump-date');
        const jumpClear = document.getElementById('audit-jump-clear');

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