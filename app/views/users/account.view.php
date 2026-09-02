<?php
$title = 'My Account';
$hideHeaderAuth = true;

include dirname(__DIR__) . '/includes/header.php';

$current_role = $old['role'] ?? '';
$is_staff = in_array($old['role'] ?? '', ['admin', 'reviewer']);
?>

<link rel="stylesheet" href="<?= asset_css('account.css') ?>">
<link rel="stylesheet" href="<?= asset_css('form.css') ?>">

<div class="body">
    <main class="main-content wide" id="main-content" tabindex="-1">
        <a class="btn-back button" id="account-back" href="<?= ROOT ?>/<?= $is_staff ? 'admin/home' : 'home' ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#back-icon">
            </svg>
            Back to Home
        </a>

        <?php if (empty($email_verified)): ?>
            <form id="resend-verification-form" class="hidden-form" method="POST" action="<?= ROOT ?>/users/resend_verification">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? $_SESSION['csrf_token'] ?? ''); ?>">
            </form>
        <?php endif; ?>

        <form class="account-form" method="POST" action="<?= ROOT ?>/users/update"
            data-confirm-message="Save changes to your account?"
            data-confirm-ok-text="Save Changes">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? $_SESSION['csrf_token'] ?? ''); ?>">

            <h1>My Account</h1>

            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="success-message">
                    <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#info-icon">
                    </svg>
                    <?= htmlspecialchars($_SESSION['flash_success']); ?>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($certificate)): ?>
                <section class="certificate-section">
                    <div class="certificate-section-header">
                        <div>
                            <h2>Your IACUC Training Certificate</h2>
                            <?php if (!empty($certificate['cert_uploaded_at'])): ?>
                                <p class="helper">Uploaded <?= htmlspecialchars(date('M j, Y', strtotime($certificate['cert_uploaded_at'])), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button"
                            data-cert-url="<?= htmlspecialchars(ROOT . '/apply/cert/' . (int) $_SESSION['user']['user_id'], ENT_QUOTES, 'UTF-8') ?>"
                            onclick="openFilePopup(this.dataset.certUrl, 'IACUC Training Certificate')">
                            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#open-mail-icon" />
                            </svg>
                            View
                        </button>
                    </div>
                </section>
            <?php endif; ?>

            <div class="label-group">
                <div class="input-group">
                    <input type="text" id="first_name" name="first_name" placeholder=" "
                        value="<?= htmlspecialchars($old['first_name'] ?? ''); ?>" required>
                    <label for="first_name">First Name <span class="required-asterisk">*</span></label>
                </div>
                <div class="input-group">
                    <input type="text" id="last_name" name="last_name" placeholder=" "
                        value="<?= htmlspecialchars($old['last_name'] ?? ''); ?>" required>
                    <label for="last_name">Last Name <span class="required-asterisk">*</span></label>
                </div>
            </div>

            <div class="input-group">
                <input type="text" id="username" name="username" placeholder=" "
                    value="<?= htmlspecialchars($old['username'] ?? ''); ?>" required>
                <label for="username">Username <span class="required-asterisk">*</span></label>
            </div>

            <div class="input-group">
                <input type="email" id="email" name="email" placeholder=" "
                    value="<?= htmlspecialchars($old['email'] ?? ''); ?>" required>
                <label for="email">
                    Email <span class="required-asterisk">*</span>
                    <?php if (empty($email_verified)): ?>
                        <span class="email-verify-badge">Unverified</span>
                    <?php endif; ?>
                </label>
            </div>

            <?php if (empty($email_verified)): ?>
                <button type="submit" form="resend-verification-form" class="email-verify-resend">Verify email</button>
            <?php endif; ?>

            <div class="input-group">
                <input type="tel" id="phone_number" name="phone_number" placeholder=" "
                    value="<?= htmlspecialchars($old['phone_number'] ?? '+63'); ?>" required>
                <label for="phone_number">Phone Number <span class="required-asterisk">*</span></label>
            </div>

            <span class="helper">Leave the password fields blank to keep your current password.</span>

            <div class="input-group">
                <input type="password" id="password" name="password" placeholder=" ">
                <label for="password">New Password</label>
            </div>

            <div class="input-group">
                <input type="password" id="confirm_password" name="confirm_password" placeholder=" ">
                <label for="confirm_password">Confirm New Password</label>
            </div>

            <div class="password-requirements">
                Password must contain:
                <ul>
                    <li>At least 8 characters</li>
                    <li>Uppercase letter</li>
                    <li>Lowercase letter</li>
                    <li>Number</li>
                    <li>Special character (! @ # $ % ^ &amp; *)</li>
                </ul>
            </div>

            <button type="submit" class="btn-save btn-green">Save Changes</button>
        </form>

        <fieldset class="form-actions">
            <legend>Account Actions</legend>

            <form method="POST"
                data-confirm-message="Are you sure? Reactivate your account at any time by logging in."
                data-confirm-ok-text="Deactivate"
                data-confirm-danger="true">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? $_SESSION['csrf_token'] ?? ''); ?>">
                <div class="popup-wrap">
                    <button type="submit" class="btn-deactivate btn-red">Deactivate Account</button>
                    <div class="info-wrapper">
                        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#info-icon" />
                        </svg>
                        <span class="info-popup">Deactivating your account will make your account details and submitted protocols invisible from the CCARD staff. However, they will not be deleted from the server. You may reactivate your account by logging in again.</span>
                    </div>
                </div>
            </form>

            <form method="POST" action="<?= ROOT ?>/users/delete"
                data-confirm-message="Are you sure? This cannot be undone."
                data-confirm-ok-text="Delete Account"
                data-confirm-danger="true">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? $_SESSION['csrf_token'] ?? ''); ?>">
                <div class="popup-wrap">
                    <button type="submit" class="btn-delete btn-red">Delete Account</button>
                    <div class="info-wrapper">
                        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#info-icon" />
                        </svg>
                        <span class="info-popup">Deleting your account will remove all your information and submitted files from the database. This action is irreversible.</span>
                    </div>
                </div>
            </form>

            <form class="logout-form" data-confirm-message="Confirm to log out?" data-confirm-ok-text="Log Out"
                action="<?= ROOT ?>/<?= $is_staff ? 'admin/logout' : 'users/logout' ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? $_SESSION['csrf_token'] ?? ''); ?>">
                <button type="submit" class="btn-logout">Log Out</button>
            </form>
        </fieldset>
    </main>
</div>

<!-- File popup modal -->
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
        filePopupBackdrop.classList.remove('open');
        document.getElementById('filePopupFrame').src = 'about:blank';
    }

    filePopupBackdrop.addEventListener('click', e => {
        if (e.target === filePopupBackdrop) closeFilePopup();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeFilePopup();
    });
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>