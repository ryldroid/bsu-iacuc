<?php
$title = 'My Account';
$hideHeader = true;
$hideHeaderAuth = true;

include dirname(__DIR__) . '/includes/header.php';

$current_role = $old['role'] ?? '';
$is_personnel = in_array($old['role'] ?? '', ['staff', 'reviewer']);
?>

<!-- PDF.js from CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<link rel="stylesheet" href="<?= asset_css('account.css') ?>">
<link rel="stylesheet" href="<?= asset_css('form.css') ?>">

<div class="body">
    <main class="main-content wide main-content--pinned-nav" id="main-content" tabindex="-1">
        <?php $themeToggleExtraClass = 'theme-toggle--card theme-toggle--floating'; ?>
        <?php include dirname(__DIR__) . '/includes/theme-toggle.php'; ?>

        <a class="btn-back button btn-back--pinned" id="account-back" href="<?= ROOT ?>/<?= $is_personnel ? 'personnel/home' : 'home' ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#back-icon">
            </svg>
            Back
        </a>

        <?php if (empty($email_verified)): ?>
            <form id="resend-verification-form" class="hidden-form" method="POST" action="<?= ROOT ?>/user/resend_verification"
                data-confirm-message="Send a verification link to <?= htmlspecialchars($old['email'] ?? '') ?>?"
                data-confirm-ok-text="Send">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? $_SESSION['csrf_token'] ?? ''); ?>">
            </form>
        <?php endif; ?>

        <form class="account-form" method="POST" action="<?= ROOT ?>/user/update"
            data-confirm-message="Save changes to your account?"
            data-confirm-ok-text="Save Changes">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? $_SESSION['csrf_token'] ?? ''); ?>">

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

            <h1>My Account</h1>

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
                    <label for="first_name" id="first_name_label">First Name <span class="required-asterisk">*</span></label>
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
                        <span class="email-verify-badge email-badge">Unverified</span>
                    <?php else: ?>
                        <span class="email-verified-badge email-badge">Verified</span>
                    <?php endif; ?>
                </label>
            </div>

            <?php if (empty($email_verified)): ?>
                <button type="submit" form="resend-verification-form" class="email-verify-resend">Verify email</button>
            <?php endif; ?>

            <div class="input-group">
                <div class="phone-field-wrap">
                    <span class="phone-prefix">+63</span>
                    <input type="tel" id="phone_number" name="phone_number" placeholder=" "
                        inputmode="numeric" pattern="9[0-9]{9}" maxlength="10"
                        value="<?= htmlspecialchars(preg_replace('/^\+63/', '', $old['phone_number'] ?? '')); ?>" required>
                </div>
                <label for="phone_number">Phone Number <span class="required-asterisk">*</span></label>
            </div>

            <div class="input-group">
                <select id="sex" name="sex" required>
                    <option value="" disabled <?= empty($old['sex']) ? 'selected' : '' ?>>— select —</option>
                    <option value="Male" <?= ($old['sex'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= ($old['sex'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
                <label for="sex">Sex <span class="required-asterisk">*</span></label>
            </div>

            <?php if (($old['role'] ?? '') === 'researcher'): ?>
                <div class="input-group">
                    <input type="text" id="school" name="school" list="school-options" placeholder=" "
                        value="<?= htmlspecialchars($old['school'] ?? ''); ?>" required>
                    <label for="school">School <span class="required-asterisk">*</span></label>
                </div>
                <?php include __DIR__ . '/../includes/school-options.php'; ?>
            <?php endif; ?>

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

        <section class="settings-section">
            <div class="popup-wrap">
                <h2>Email Notifications</h2>
                <div class="info-wrapper">
                    <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#info-icon" />
                    </svg>
                    <span class="info-popup">If you unsubscribed directly through your email client and wish to receive emails again, turn this toggle off and then on again. The system cannot detect the unsubscribe automatically.</span>
                </div>
            </div>

            <form class="notifications-form" method="POST" action="<?= ROOT ?>/user/notifications" id="notifications-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? $_SESSION['csrf_token'] ?? ''); ?>">
                <label class="switch-toggle" for="email_notifications">
                    <span class="switch-toggle-label">Email me about protocol updates</span>
                    <span class="switch-toggle-control">
                        <input type="checkbox" id="email_notifications" name="email_notifications" value="1"
                            <?= !empty($email_notifications) ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span class="switch-toggle-track"></span>
                    </span>
                </label>
            </form>

            <?php if (!empty($email_provider_blocked)): ?>
                <p class="helper email-provider-blocked-note">You previously unsubscribed via a link in one of our emails. Turning this back on will also ask our email provider to unblock your address.</p>
            <?php endif; ?>
        </section>

        <section class="settings-section">
            <h2>Account Actions</h2>

            <p class="helper account-deactivation-note">Accounts are deactivated automatically once your animal research clearance expires and you have no other protocols being processed. Your info, protocols, and training certificate will be kept, but your protocols will be hidden from CCARD personnel.</p>

            <form method="POST" action="<?= ROOT ?>/user/delete"
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
                        <span class="info-popup">Deleting your account permanently removes your personal account details and training certificate from our records. Your submitted protocols and files are kept, as required by IACUC recordkeeping rules, but will no longer be linked to your account. This action cannot be undone, though you may register again afterward using the same details.</span>
                    </div>
                </div>
            </form>

            <form class="logout-form" data-confirm-message="Confirm to log out?" data-confirm-ok-text="Log Out"
                action="<?= ROOT ?>/<?= $is_personnel ? 'personnel/logout' : 'user/logout' ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? $_SESSION['csrf_token'] ?? ''); ?>">
                <button type="submit" class="btn-logout">Log Out</button>
            </form>
        </section>
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
        <div class="file-popup-frame" id="filePopupFrame">
            <div id="filePopupPdfPages" class="file-popup-pdf-pages" hidden></div>
            <img id="filePopupImg" alt="">
            <p id="filePopupMessage" class="helper" style="padding:2rem">Loading…</p>
        </div>
    </div>
</div>

<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    const filePopupBackdrop = document.getElementById('filePopupBackdrop');
    const filePopupFrame = document.getElementById('filePopupFrame');
    const filePopupPdfPages = document.getElementById('filePopupPdfPages');
    const filePopupImg = document.getElementById('filePopupImg');
    const filePopupMessage = document.getElementById('filePopupMessage');
    let filePopupObjectUrl = null;

    // Exactly one of these three stays visible at a time.
    function showFilePopupState(state) {
        filePopupPdfPages.hidden = state !== 'pdf';
        filePopupImg.hidden = state !== 'img';
        filePopupMessage.hidden = state !== 'message';
    }

    async function renderPopupPdf(fileUrl) {
        filePopupPdfPages.innerHTML = '';
        const frameWidth = filePopupFrame.clientWidth;
        const doc = await pdfjsLib.getDocument(fileUrl).promise;

        for (let p = 1; p <= doc.numPages; p++) {
            const page = await doc.getPage(p);
            const scale = Math.min(1.5, (frameWidth - 48) / page.getViewport({
                scale: 1
            }).width);
            const vp = page.getViewport({
                scale
            });

            const canvas = document.createElement('canvas');
            const outputScale = window.devicePixelRatio || 1;
            canvas.width = Math.floor(vp.width * outputScale);
            canvas.height = Math.floor(vp.height * outputScale);
            canvas.style.width = vp.width + 'px';
            canvas.style.height = vp.height + 'px';
            filePopupPdfPages.appendChild(canvas);

            await page.render({
                canvasContext: canvas.getContext('2d'),
                viewport: vp,
                transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : undefined
            }).promise;
        }
    }

    async function openFilePopup(fileUrl, title) {
        document.getElementById('filePopupTitle').textContent = title;
        filePopupBackdrop.classList.add('open');
        filePopupFrame.scrollTop = 0;

        filePopupMessage.textContent = 'Loading…';
        showFilePopupState('message');

        try {
            const res = await fetch(fileUrl);
            if (!res.ok) throw new Error('Failed to load file');

            const contentType = res.headers.get('content-type') || '';

            if (contentType.includes('pdf')) {
                await renderPopupPdf(fileUrl);
                showFilePopupState('pdf');
                filePopupFrame.scrollTop = 0;
                return;
            }

            const blob = await res.blob();

            if (filePopupObjectUrl) URL.revokeObjectURL(filePopupObjectUrl);
            filePopupObjectUrl = URL.createObjectURL(blob);

            filePopupImg.src = filePopupObjectUrl;
            filePopupImg.alt = title;
            showFilePopupState('img');
        } catch (err) {
            filePopupMessage.textContent = 'Could not load this file.';
            showFilePopupState('message');
        }
    }

    function closeFilePopup() {
        filePopupBackdrop.classList.remove('open');
        filePopupPdfPages.innerHTML = '';
        filePopupImg.removeAttribute('src');
        if (filePopupObjectUrl) {
            URL.revokeObjectURL(filePopupObjectUrl);
            filePopupObjectUrl = null;
        }
    }

    filePopupBackdrop.addEventListener('click', e => {
        if (e.target === filePopupBackdrop) closeFilePopup();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeFilePopup();
    });

    const accountSuccessMessage = document.querySelector('.account-form .success-message');
    if (accountSuccessMessage) {
        setTimeout(() => {
            accountSuccessMessage.style.transition = 'opacity 0.4s ease';
            accountSuccessMessage.style.opacity = '0';
            setTimeout(() => accountSuccessMessage.remove(), 400);
        }, 3000);
    }
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>