<?php
$title = 'Accounts';
include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/scroll-top.php';

$user = $user ?? $_SESSION['user'] ?? [];
$csrf = $csrf ?? '';
?>

<link rel="stylesheet" href="<?= asset_css('admin/admin.css') ?>">
<link rel="stylesheet" href="<?= asset_css('admin/admin-home.css') ?>">
<link rel="stylesheet" href="<?= asset_css('admin/accounts.css') ?>">

<div class="body">
    <?php include dirname(__DIR__) . '/includes/navigation.php'; ?>

    <main class="main-content" id="main-content" tabindex="-1">

        <div class="dashboard-page-header">
            <h1 class="dashboard-page-title">Accounts</h1>
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

        <!-- GENERATE INVITE LINK -->
        <section class="accounts-card invite-section">
            <h2>Staff Registration Link</h2>

            <form class="invite-form" action="<?= ROOT ?>/admin/generate_invite" method="POST">
                <input type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrf) ?>">

                <div class="form-section">
                    <label for="invite-role">Select Role:</label>
                    <select name="invite_role" id="invite-role">
                        <option value="admin">Admin</option>
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

        <!-- PENDING ACCOUNTS -->
        <section class="accounts-card pending-section">
            <h2>Pending Staff Approvals</h2>

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
                            <form method="POST" action="<?= ROOT ?>/admin/approve"
                                data-confirm-message="Approve this application?"
                                data-confirm-ok-text="Approve">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $applicant['id']; ?>">
                                <button type="submit" class="accounts-btn-primary">Approve</button>
                            </form>

                            <form method="POST" action="<?= ROOT ?>/admin/reject"
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

    </main>
</div>

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
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>