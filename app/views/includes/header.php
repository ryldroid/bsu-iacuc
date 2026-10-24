<?php
include 'sprites.php';

/** @var array|null $user */
$first_name = $user['first_name'] ?? '';
$role = $user['role'] ?? '';
$hideHeaderAuth = $hideHeaderAuth ?? false;
$hideHeader     = $hideHeader     ?? false;
$previewMode     = $previewMode     ?? false;
$pubRoot         = $previewMode ? ROOT . '/preview' : ROOT;

$accountUser     = $user ? ($accountUser ?? (new UserModel())->getUser((int) $user['user_id'])) : null;
$accountRequired = isset($_GET['required']);
$needsProfile    = $role === 'researcher' && (empty($accountUser['sex']) || empty($accountUser['researcher_type']));
$showVerifyModal = $accountRequired && !$needsProfile && empty($accountUser['email_verified']);

$themeModes = [
  ['light', 'Light', 'sun-icon'],
  ['dark', 'Dark', 'moon-icon'],
  ['auto', 'System', 'monitor-icon'],
];

$showWelcome = $user && !empty($user['show_welcome']);
$navLink = fn($path) => ["aside a[href$='" . $path . "']", "#mobileNav a[href$='" . $path . "']"];
$guideWelcome = ['Welcome to BSU-IACUC!', 'Let us show you around the website. It only takes a minute, and you can replay it anytime with the question mark button at the top of the page.'];
$guideHome = ['Homepage', 'Go here to read about IACUC, how to apply for protocol review, the frequently asked questions, and more.', ...$navLink('/home')];
$guideAnnouncements = ['Announcements', 'Read the latest news and updates from the CCARD office.', ...$navLink('/announcements')];
$guideContact = ['Contact Us', 'Find CCARD\'s and related offices\' details and how to reach them.', ...$navLink('/contact')];
$guideSidebar = ['Expand the sidebar', 'On a computer, use this button to show the page names beside the icons, or hide them again.', '#sidebar-toggle', '', true];
$guideNotifications = ['Notifications', 'The bell shows a badge when there is something new. Open it to read your updates or see all notifications.', '.notif-bell svg'];
$guideTheme = ['Theme', 'Switch between Light, Dark, and System to match your device.', '#theme-toggle'];
$guideHelp = ['Guide', 'Replay this tour anytime with the question mark button.', '.header-help'];
$guideAccount = ['Your account', 'Open this to update **My Account**, ' . ($role === 'researcher' ? '' : 'preview the public site, ') . 'switch between Light, Dark, and System theme, or log out.', '.header-account'];
$welcomeSteps = [];
if ($role === 'researcher') {
  $welcomeSteps = [
    $guideWelcome,
    ['Page menu', 'These links take you between **Home**, **My Protocols**, **Announcements**, and **Contact Us**. On a phone, tap the menu icon at the top left to open them.', 'aside nav ul', '.mobile-menu'],
    $guideSidebar,
    $guideHome,
    ['My Protocols', 'Apply for review here, then track each protocol, answer reviewer comments, pay the processing fee, and download your clearance.', ...$navLink('/submissions')],
    $guideAnnouncements,
    $guideContact,
    $guideNotifications,
    $guideAccount,
  ];
} elseif ($role === 'staff') {
  $welcomeSteps = [
    $guideWelcome,
    ['Page menu', 'These links take you between the **Dashboard**, **Records**, **Site Content**, and **Administration**. On a phone, tap the menu icon at the top left to open them.', 'aside nav ul', '.mobile-menu'],
    $guideSidebar,
    ['Dashboard', 'The Protocol Inbox lists submitted protocols. Open one to review it or return it for revision, and upload released clearances here.', ...$navLink('/personnel/home')],
    ['Records', 'Browse the full table of protocol records and see statistics for a chosen period.', ...$navLink('/personnel/records')],
    ['Site Content', 'Edit what visitors see: post announcements and update the homepage text, FAQs, and contact page offices.', ...$navLink('/personnel/announcements')],
    ['Administration', 'Approve pending personnel applications and download audit logs.', ...$navLink('/personnel/accounts')],
    $guideNotifications,
    $guideAccount,
  ];
} elseif ($role === 'reviewer') {
  $welcomeSteps = [
    $guideWelcome,
    ['Page menu', 'These links take you between the **Dashboard** and **Records**. On a phone, tap the menu icon at the top left to open them.', 'aside nav ul', '.mobile-menu'],
    $guideSidebar,
    ['Dashboard', 'The Protocol Inbox lists protocols for review. Open one to read it and draw annotation boxes on the document.', ...$navLink('/personnel/home')],
    ['Records', 'Browse the full table of protocol records and see statistics for a chosen period.', ...$navLink('/personnel/records')],
    $guideNotifications,
    $guideAccount,
  ];
} elseif (!$user && !$previewMode && !$hideHeader && !$hideHeaderAuth) {
  $welcomeSteps = [
    ['Welcome to BSU-IACUC!', 'Let us show you around the website. It only takes a minute.'],
    ['Sign in or register', 'Already have an account? Press **Sign In**. New here? Press **Register** to create one. You need an account to apply for protocol review.', '#headerLogin, #headerRegister'],
    ['Page menu', 'These links take you between the **homepage**, **announcements page**, and **contact page**. On a phone, tap the menu icon at the top left to open them.', 'aside nav ul', '.mobile-menu'],
    $guideSidebar,
    $guideHome,
    $guideAnnouncements,
    $guideContact,
    $guideTheme,
    $guideHelp,
  ];
}
$canPreviewSite = $role === 'staff' || $role === 'reviewer';
$canReplayGuide = $welcomeSteps && !$accountRequired;
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="google" content="notranslate">
  <meta name="darkreader-lock">

  <?php $default = "BSU-IACUC"; ?>
  <title><?= isset($title) ? "$title - $default" : $default ?></title>

  <script>
    (function() {
      var mode = localStorage.getItem('theme') || 'auto';
      var systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      var resolvedTheme = (mode === 'light' || mode === 'dark') ? mode : (systemPrefersDark ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme', resolvedTheme);
      document.documentElement.setAttribute('data-theme-mode', mode);
      if (localStorage.getItem('sidebar') === 'expanded') document.documentElement.dataset.sidebar = 'expanded';
    })();
  </script>

  <link rel="stylesheet" href="<?= asset_css('header.css') ?>">
  <link rel="stylesheet" href="<?= asset_css('body.css') ?>">
  <link rel="stylesheet" href="<?= asset_css('modals.css') ?>">
  <link rel="stylesheet" href="<?= asset_css('action-queue.css') ?>">
  <?php if ($user): ?>
    <link rel="stylesheet" href="<?= asset_css('notifications.css') ?>">
    <link rel="stylesheet" href="<?= asset_css('form.css') ?>">
    <link rel="stylesheet" href="<?= asset_css('account-panel.css') ?>">
  <?php endif; ?>

  <?php include __DIR__ . '/icons.php'; ?>

  <script src="<?= asset_js('utils.js') ?>"></script>
  <script src="<?= asset_js('protocol-sort.js') ?>"></script>
  <script src="<?= asset_js('header.js') ?>" defer></script>
  <script src="<?= asset_js('theme-toggle.js') ?>" defer></script>
  <script src="<?= asset_js('modals.js') ?>" defer></script>
  <script src="<?= asset_js('action-queue.js') ?>" defer></script>
  <script src="<?= asset_js('sw-register.js') ?>" data-root="<?= ROOT ?>" defer></script>
  <script src="<?= asset_js('password-toggle.js') ?>" defer></script>
  <script src="<?= asset_js('password-strength.js') ?>" defer></script>
  <script src="<?= asset_js('phone-input.js') ?>" defer></script>
  <?php if ($user):
    if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
  ?>
    <script>
      const NOTIF_CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']) ?>;
      const NOTIF_ROOT = <?= json_encode(ROOT) ?>;
      const CURRENT_USER_ID = <?= json_encode((int) ($user['user_id'] ?? 0)) ?>;
      const SESSION_IDLE_LIMIT_MS = <?= json_encode(SESSION_TIMEOUT * 1000) ?>;
      const SESSION_LOGIN_PATH = <?= json_encode(in_array($role, ['staff', 'reviewer'], true) ? 'personnel/login' : 'user/login') ?>;
    </script>
    <script src="<?= asset_js('notifications.js') ?>" defer></script>
    <script src="<?= asset_js('session-timeout.js') ?>" defer></script>
  <?php endif; ?>

</head>

<body>
  <a href="#main-content" class="skip-link">Skip to main content</a>

  <?php if (!$hideHeader): ?>
    <header>
      <div class="header-logo-cont">
        <?php if (!$hideHeaderAuth): ?>
          <!-- MOBILE HAMBURGER ICON -->
          <button
            class="mobile-menu"
            aria-expanded="false"
            aria-controls="nav-sidebar"
            aria-label="Menu"
            data-tooltip="Menu">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <use href="#menu-icon" />
            </svg>
          </button>
        <?php endif; ?>
        <a href="<?= $previewMode ? $pubRoot . '/home' : ROOT ?>" class="header-logo">
          <div>
          </div>
          <div>BSU-<span>IACUC</span></div>
        </a>
      </div>

      <?php if (!$hideHeaderAuth): ?>
        <!-- THEME (VISITORS ONLY) -->
        <?php if (!$user) include 'theme-toggle.php'; ?>

        <div class="header-auth">
          <!-- GUIDE -->
          <?php if ($canReplayGuide): ?>
            <button type="button" class="header-help" data-guide-start="welcome" aria-label="View guide on website navigation" data-tooltip="View guide on website navigation">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#question-info-icon" />
              </svg>
              <?php if (!$user): ?><span>Guide</span><?php endif; ?>
            </button>
          <?php endif; ?>

          <?php if ($user) { ?>
            <button class="notif-bell"
              aria-expanded="false"
              aria-haspopup="true"
              aria-label="Notifications"
              data-tooltip="Notifications"
              aria-controls="notif-dropdown">

              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#bell-icon" />
              </svg>
              <span class="notif-badge" hidden>0</span>
            </button>

            <div id="notif-dropdown">
              <div class="notif-dropdown-header">
                <span>Notifications</span>
                <button type="button" class="notif-mark-all">Mark all as read</button>
              </div>
              <div class="notif-list"></div>
              <div class="notif-dropdown-footer">
                <a href="<?= ROOT ?>/notifications/page">See all notifications</a>
              </div>
            </div>

            <!-- MY ACCOUNT -->
            <button class="header-account"
              aria-expanded="false"
              aria-haspopup="dialog"
              aria-label="My account"
              data-tooltip="Account settings"
              aria-controls="account-drawer">

              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#account-icon" />
              </svg>

              <span><?= htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8') ?></span>
            </button>

            <!-- MY ACCOUNT PANEL -->
            <div id="account-drawer" role="dialog" aria-label="My Account" tabindex="-1" inert>
              <div class="account-panel-header">
                <span>My Account</span>
                <?php if (!$accountRequired): ?>
                  <button type="button" class="modal-close account-close" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                      <use href="#close-icon" />
                    </svg>
                  </button>
                <?php endif; ?>
              </div>

              <div class="account-panel-body">
                <?php if ($accountRequired && $needsProfile): ?>
                  <p class="info-notice">Complete the required fields to continue.<?= empty($accountUser['email_verified']) ? ' You will be asked to verify your email next.' : '' ?></p>
                <?php endif; ?>


                <!-- ===== Summary ===== -->
                <div class="account-summary">
                  <span class="account-avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($accountUser['first_name'] ?? '', 0, 1) . mb_substr($accountUser['last_name'] ?? '', 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                  <div>
                    <p class="account-summary-name"><?= htmlspecialchars(trim(($accountUser['first_name'] ?? '') . ' ' . ($accountUser['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="helper">@<?= htmlspecialchars($accountUser['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                  </div>
                </div>

                <?php if ($role === 'researcher' && !empty($accountUser['cert_path'])): ?>
                  <?php
                  $certStatus      = $accountUser['cert_status'] ?? 'pending';
                  $certStatusLabel = ['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'][$certStatus] ?? 'Pending';
                  ?>
                  <!-- ===== Training certificate ===== -->
                  <section class="account-card account-card-row">
                    <div>
                      <h2>IACUC Training Certificate <span class="email-badge <?= $certStatus === 'verified' ? 'email-badge-verified' : 'email-badge-unverified' ?>"><?= $certStatusLabel ?></span></h2>
                      <?php if (!empty($accountUser['cert_uploaded_at'])): ?>
                        <p class="helper">Uploaded <?= htmlspecialchars(date(DATE_FORMAT, strtotime($accountUser['cert_uploaded_at'])), ENT_QUOTES, 'UTF-8') ?></p>
                      <?php endif; ?>
                      <?php if ($certStatus === 'rejected' && !empty($accountUser['cert_note'])): ?>
                        <p class="helper">Reviewer note: <?= htmlspecialchars($accountUser['cert_note'], ENT_QUOTES, 'UTF-8') ?></p>
                      <?php endif; ?>
                    </div>
                    <a class="button" href="<?= ROOT ?>/apply/cert/<?= (int) $accountUser['id'] ?>" target="_blank" rel="noopener">
                      <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#open-mail-icon" />
                      </svg>
                      Open
                    </a>
                  </section>

                  <?php if ($certStatus === 'rejected'): ?>
                    <!-- ===== Resubmit training certificate ===== -->
                    <form class="account-card account-card-row" method="POST" action="<?= ROOT ?>/apply/reuploadcert" enctype="multipart/form-data"
                      data-confirm-message="Submit this certificate for verification?" data-confirm-ok-text="Submit">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                      <p class="helper">Upload a new certificate (PDF, JPG or PNG, max 10 MB).</p>
                      <input type="file" id="cert-reupload-input" name="cert_file" accept=".pdf,.jpg,.jpeg,.png" hidden onchange="this.form.requestSubmit()">
                      <button type="button" class="button" onclick="document.getElementById('cert-reupload-input').click()">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                          <use href="#upload-icon" />
                        </svg>
                        Upload new
                      </button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>

                <!-- ===== Resend verification form ===== -->
                <form id="resend-verification-form" method="POST" action="<?= ROOT ?>/user/resend_verification" hidden
                  data-confirm-message="Send a verification link to <?= htmlspecialchars($accountUser['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>?"
                  data-confirm-ok-text="Send">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </form>

                <!-- ===== Account form ===== -->
                <form id="account-form" class="account-form" method="POST" action="<?= ROOT ?>/user/update"
                  data-confirm-message="Save changes to your account?"
                  data-confirm-ok-text="Save Changes">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                  <h2>Personal Details</h2>
                  <div class="account-field-row">
                    <div class="input-group">
                      <input type="text" id="first_name" name="first_name" placeholder=" " autocomplete="given-name"
                        value="<?= htmlspecialchars($accountUser['first_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                      <label for="first_name">First Name <span class="required-asterisk">*</span></label>
                    </div>
                    <div class="input-group">
                      <input type="text" id="last_name" name="last_name" placeholder=" " autocomplete="family-name"
                        value="<?= htmlspecialchars($accountUser['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                      <label for="last_name">Last Name <span class="required-asterisk">*</span></label>
                    </div>
                  </div>

                  <div class="account-field-row">
                    <div class="input-group">
                      <select id="sex" name="sex" required>
                        <option value="" disabled <?= empty($accountUser['sex']) ? 'selected' : '' ?>>Select</option>
                        <?php foreach (['Male', 'Female'] as $sexOption): ?>
                          <option value="<?= $sexOption ?>" <?= ($accountUser['sex'] ?? '') === $sexOption ? 'selected' : '' ?>><?= $sexOption ?></option>
                        <?php endforeach; ?>
                      </select>
                      <label for="sex">Sex <span class="required-asterisk">*</span></label>
                    </div>
                    <?php if ($role === 'researcher'): ?>
                      <div class="input-group">
                        <select id="researcher_type" name="researcher_type" required>
                          <option value="" disabled <?= empty($accountUser['researcher_type']) ? 'selected' : '' ?>>Select</option>
                          <?php $selectedResearcherType = $accountUser['researcher_type'] ?? '';
                          include __DIR__ . '/researcher-type-options.php'; ?>
                        </select>
                        <label for="researcher_type">Researcher Type <span class="required-asterisk">*</span></label>
                      </div>
                    <?php endif; ?>
                  </div>

                  <?php if ($role === 'researcher'): ?>
                    <div class="input-group">
                      <input type="text" id="school" name="school" list="school-options" placeholder=" " autocomplete="organization"
                        value="<?= htmlspecialchars($accountUser['school'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                      <label for="school">School <span class="required-asterisk">*</span></label>
                    </div>
                  <?php endif; ?>

                  <div class="input-group">
                    <div class="phone-field-wrap">
                      <span class="phone-prefix">+63</span>
                      <input type="tel" id="phone_number" name="phone_number" placeholder=" " autocomplete="tel-national"
                        inputmode="numeric"
                        value="<?= htmlspecialchars(preg_replace('/^\+63/', '', $accountUser['phone_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <label for="phone_number">Phone Number <span class="required-asterisk">*</span></label>
                  </div>

                  <h2>Sign-in Details</h2>
                  <div class="input-group">
                    <input type="text" id="username" name="username" placeholder=" " autocomplete="username"
                      value="<?= htmlspecialchars($accountUser['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    <label for="username">Username <span class="required-asterisk">*</span></label>
                  </div>

                  <div class="input-group">
                    <input type="email" id="email" name="email" placeholder=" " autocomplete="email"
                      value="<?= htmlspecialchars($accountUser['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    <label for="email">
                      Email <span class="required-asterisk">*</span>
                      <span class="email-badge <?= empty($accountUser['email_verified']) ? 'email-badge-unverified' : 'email-badge-verified' ?>"><?= empty($accountUser['email_verified']) ? 'Unverified' : 'Verified' ?></span>
                    </label>
                  </div>

                  <button type="submit" form="resend-verification-form" class="email-verify-resend"
                    data-saved-email="<?= htmlspecialchars($accountUser['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    <?= empty($accountUser['email_verified']) ? '' : 'hidden' ?>>Verify email</button>

                  <details class="account-password">
                    <summary>
                      Change password
                      <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#chev-down-icon" />
                      </svg>
                    </summary>
                    <div class="account-password-fields">
                      <p class="helper">Leave the password fields blank to keep your current password.</p>
                      <div class="input-group">
                        <input type="password" id="password" name="password" placeholder=" " autocomplete="new-password">
                        <label for="password">New Password</label>
                      </div>
                      <div class="input-group">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder=" " autocomplete="new-password">
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
                    </div>
                  </details>
                </form>

                <!-- ===== Preferences ===== -->
                <form id="email-notifications-form" class="account-card" method="POST" action="<?= ROOT ?>/user/notifications">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                  <h2>Preferences</h2>
                  <div class="dropdown-theme" role="radiogroup" aria-label="Theme">
                    <span>Theme</span>
                    <div class="theme-options">
                      <?php foreach ($themeModes as [$themeMode, $themeLabel, $themeIcon]): ?>
                        <button type="button" role="radio" data-mode="<?= $themeMode ?>">
                          <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#<?= $themeIcon ?>" />
                          </svg>
                          <?= $themeLabel ?>
                        </button>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <label class="switch-toggle" for="email_notifications">
                    <span>Email me about protocol updates</span>
                    <span class="switch-toggle-control">
                      <input type="checkbox" id="email_notifications" name="email_notifications" value="1"
                        <?= !empty($accountUser['email_notifications']) ? 'checked' : '' ?>>
                      <span class="switch-toggle-track"></span>
                    </span>
                  </label>
                  <p class="helper">
                    <span class="provider-blocked-note" <?= empty($accountUser['email_provider_blocked']) ? 'hidden' : '' ?>>You previously unsubscribed via a link in one of our emails. Turning this back on will also ask our email provider to unblock your address.</span>
                  </p>
                  <?php if ($canPreviewSite): ?>
                    <div class="account-actions">
                      <a class="button" href="<?= ROOT ?>/preview/home" target="_blank" rel="noopener">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                          <use href="#eye-icon" />
                        </svg>
                        Preview public site
                      </a>
                    </div>
                  <?php endif; ?>
                </form>

                <!-- ===== Account actions ===== -->
                <section class="account-card">
                  <h2>Account Actions</h2>
                  <div class="account-actions">
                    <?php if ($role === 'researcher'): ?>
                      <form method="POST" action="<?= ROOT ?>/user/delete"
                        data-confirm-message="Deleting your account permanently removes your personal details and training certificate. Your submitted protocols and their files stay in an archive for internal recordkeeping and are no longer linked to your account, though they may still contain personal information you provided. This cannot be undone."
                        data-confirm-ok-text="Delete Account"
                        data-confirm-danger="true">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="button btn-account-delete">
                          <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#trash-icon" />
                          </svg>
                          Delete Account
                        </button>
                      </form>
                    <?php endif; ?>
                    <form method="POST" action="<?= ROOT ?><?= $role === 'researcher' ? '/user/logout' : '/personnel/logout' ?>"
                      data-confirm-message="Confirm to log out?" data-confirm-ok-text="Log Out">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                      <button type="submit" class="button">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                          <use href="#log-out-icon" />
                        </svg>
                        Log Out
                      </button>
                    </form>
                  </div>
                </section>
              </div>

              <div class="account-panel-footer">
                <button type="submit" form="account-form" class="account-save">Save Changes</button>
              </div>
            </div>

            <!-- PREVIEW (PERSONNEL VIEWING THE PUBLIC SITE) -->
          <?php } elseif ($previewMode) { ?>
            <span class="preview-tag">[Preview]</span>

            <!-- LOG IN/REGISTER (NOT LOGGED IN) -->
          <?php } else { ?>
            <a href="<?= ROOT ?>/user/login" id="headerLogin" class="auth-btn">Sign In</a>
            <a href="<?= ROOT ?>/user/register" id="headerRegister" class="auth-btn">Register</a>
          <?php } ?>
        </div>
      <?php endif; ?>
    </header>

    <?php if ($showVerifyModal): ?>
      <!-- ===== Verify email modal (saves the address, then sends the link) ===== -->
      <div class="modal-backdrop open" id="verify-email-modal" data-static role="dialog" aria-modal="true" aria-labelledby="verify-email-title">
        <form class="modal-card panel-modal-card" id="verify-email-form" method="POST" action="<?= ROOT ?>/user/resend_verification">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <div class="modal-header">
            <span class="modal-header-icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                <use href="#email-icon" />
              </svg>
            </span>
            <div>
              <p class="modal-label">Required</p>
              <p class="modal-title" id="verify-email-title">Verify your email to continue</p>
            </div>
          </div>
          <div class="panel-modal-body">
            <p class="panel-modal-intro">Verify your email address to receive important updates about your account and IACUC protocol submissions.</p>
            <div class="input-group">
              <input type="email" id="verify_email" name="email" placeholder=" " autocomplete="email" autofocus
                value="<?= htmlspecialchars($accountUser['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
              <label for="verify_email">Email <span class="required-asterisk">*</span></label>
            </div>
            <p class="panel-modal-intro verify-email-sent" hidden></p>
            <div class="panel-modal-actions">
              <button type="submit" class="confirm-modal-btn confirm-modal-ok">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <use href="#email-icon" />
                </svg>
                Send verification link
              </button>
              <button type="button" class="confirm-modal-btn confirm-modal-ok verify-email-continue" data-href="<?= ROOT ?>/<?= $role === 'researcher' ? 'home' : 'personnel/home' ?>" hidden>I have verified my email</button>
            </div>
          </div>
        </form>
      </div>
    <?php endif; ?>
    <?php if ($user): ?>

      <!-- RESEARCHER MOBILE NAVIGATION -->
      <?php if ($role === 'researcher'): ?>
        <nav id="mobileNav" aria-label="Mobile navigation" aria-hidden="true">
          <ul class="nav-sidebar" id="nav-sidebar" inert>
            <li><a href="<?= ROOT ?>/home"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <use href="#home-icon" />
                </svg><span>Home</span></a></li>
            <li><a href="<?= ROOT ?>/submissions"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <use href="#protocols-icon" />
                </svg><span>My Protocols</span></a></li>
            <li><a href="<?= ROOT ?>/announcements"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <use href="#announcement-icon" />
                </svg><span>Announcements</span></a></li>
            <li><a href="<?= ROOT ?>/contact"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <use href="#contact-icon" />
                </svg><span>Contact</span></a></li>
          </ul>
        </nav>

        <!-- PERSONNEL (STAFF / REVIEWER) MOBILE NAVIGATION -->
      <?php elseif ($role === 'staff' || $role === 'reviewer'): ?>
        <nav id="mobileNav" aria-label="Mobile navigation" aria-hidden="true">
          <ul class="nav-sidebar" id="nav-sidebar" inert>
            <li><a href="<?= ROOT ?>/personnel/home"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <use href="#home-icon" />
                </svg><span>Dashboard</span></a></li>
            <li><a href="<?= ROOT ?>/personnel/records"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <use href="#protocols-icon" />
                </svg><span>Records</span></a></li>
            <?php if ($role === 'staff'): ?>
              <li><a href="<?= ROOT ?>/personnel/announcements"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#announcement-icon" />
                  </svg><span>Announcements</span></a></li>
              <li><a href="<?= ROOT ?>/personnel/site_content"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#edit-icon" />
                  </svg><span>Site Content</span></a></li>
            <?php endif; ?>
            <?php if ($role === 'staff'): ?>
              <li><a href="<?= ROOT ?>/personnel/accounts"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#accounts-icon" />
                  </svg><span>Administration</span></a></li>
            <?php endif; ?>
          </ul>
        </nav>
      <?php endif; ?>

    <?php else: ?>
      <!-- PUBLIC MOBILE NAVIGATION -->
      <nav id="mobileNav" aria-label="Mobile navigation" aria-hidden="true">
        <ul class="nav-sidebar" id="nav-sidebar" inert>
          <li><a href="<?= $pubRoot ?>/home"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#home-icon" />
              </svg><span>Home</span></a></li>
          <li><a href="<?= $pubRoot ?>/announcements"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#announcement-icon" />
              </svg><span>Announcements</span></a></li>
          <li><a href="<?= $pubRoot ?>/contact"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#contact-icon" />
              </svg><span>Contact</span></a></li>
        </ul>
      </nav>
    <?php endif; ?>

  <?php endif; ?>

  <?php if ($welcomeSteps) {
    $guideId       = 'welcome';
    $guideSteps    = $welcomeSteps;
    $guideOpen     = $showWelcome;
    $guideShowOnce = !$user;
    include __DIR__ . '/guide.php';
  } ?>
  <?php unset($_SESSION['user']['show_welcome']); ?>

  <div id="sidebar-backdrop" aria-hidden="true"></div>