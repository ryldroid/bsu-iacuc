<?php
include 'sprites.php';

/** @var array|null $user */
$first_name = $user['first_name'] ?? '';
$role = $user['role'] ?? '';
$hideHeaderAuth = $hideHeaderAuth ?? false;
$hideHeader     = $hideHeader     ?? false;
$previewMode     = $previewMode     ?? false;
$pubRoot         = $previewMode ? ROOT . '/preview' : ROOT;
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="google" content="notranslate">

  <?php $default = "BSU-IACUC"; ?>
  <title><?= isset($title) ? "$title - $default" : $default ?></title>

  <script>
    (function() {
      var mode = localStorage.getItem('theme') || 'auto';
      var systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      var resolvedTheme = (mode === 'light' || mode === 'dark') ? mode : (systemPrefersDark ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme', resolvedTheme);
      document.documentElement.setAttribute('data-theme-mode', mode);
    })();
  </script>

  <link rel="stylesheet" href="<?= asset_css('header.css') ?>">
  <link rel="stylesheet" href="<?= asset_css('body.css') ?>">
  <link rel="stylesheet" href="<?= asset_css('modals.css') ?>">
  <link rel="stylesheet" href="<?= asset_css('action-queue.css') ?>">
  <?php if ($user): ?>
    <link rel="stylesheet" href="<?= asset_css('notifications.css') ?>">
  <?php endif; ?>

  <?php include __DIR__ . '/icons.php'; ?>

  <script src="<?= asset_js('utils.js') ?>"></script>
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
        <a href="<?= $previewMode ? $pubRoot . '/home' : ROOT ?>" class="header-logo">
          <div>
            <!-- <img src="<?= IMGPATH ?>/bsu.webp" alt=""> -->
            <!-- <img src="<?= IMGPATH ?>/ccard.webp" alt=""> -->
          </div>
          <div>BSU-<span>IACUC</span></div>
        </a>
      </div>

      <?php if (!$hideHeaderAuth): ?>
        <div class="header-auth">
          <?php if ($user) { ?>
            <?php if ($role === 'staff' || $role === 'reviewer'): ?>
              <!-- PREVIEW LIVE SITE -->
              <a href="<?= ROOT ?>/preview/home" class="header-preview-link" target="_blank" rel="noopener" aria-label="Preview public site" data-tooltip="Preview public site">
                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <use href="#eye-icon" />
                </svg>
              </a>
            <?php endif; ?>
          <?php } ?>
        </div>
      <?php endif; ?>

      <!-- DARK MODE TOGGLE -->
      <?php include 'theme-toggle.php'; ?>

      <!-- ACCOUNT DROPDOWN (LOGGED IN) -->
      <?php if (!$hideHeaderAuth): ?>
        <div class="header-auth">
          <?php if ($user) { ?>
            <button class="notif-bell"
              aria-expanded="false"
              aria-haspopup="true"
              aria-label="Notifications"
              data-tooltip="Notifications"
              aria-controls="notif-dropdown">

              <svg width="21" height="21" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
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

            <!-- <span class="greeting">Hello, </span> -->
            <button class="my-account-dropdown"
              aria-expanded="false"
              aria-haspopup="true"
              aria-label="My account"
              data-tooltip="My account"
              aria-controls="account-dropdown">

              <!-- <img src="<?= IMGPATH ?>/scientist.webp" alt=""> -->
              <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#account-icon" />
              </svg>

              <span><?= htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8') ?></span>

              <span class="chev-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <use href="#chev-down-icon" />
                </svg>
              </span>
            </button>

            <div id="account-dropdown">
              <a href="<?= ROOT ?>/user/account">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <use href="#account-dropdown-icon" />
                </svg>
                My Profile</a>

              <button type="button" data-welcome-replay>
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <use href="#info-icon" />
                </svg>
                Take the Tour
              </button>
              <?php if ($role === 'researcher'): ?>
                <form method="POST" action="<?= ROOT ?>/user/logout" data-confirm-message="Confirm to log out?" data-confirm-ok-text="Log Out">
                <?php elseif ($role === 'staff' || $role === 'reviewer'): ?>
                  <form method="POST" action="<?= ROOT ?>/personnel/logout" data-confirm-message="Confirm to log out?" data-confirm-ok-text="Log Out">
                  <?php endif; ?>
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                  <button type="submit" class="btn-header-logout">
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                      <use href="#log-out-icon" />
                    </svg>
                    Log Out
                  </button>
                  </form>
            </div>

            <!-- PREVIEW (PERSONNEL VIEWING THE PUBLIC SITE) -->
          <?php } elseif ($previewMode) { ?>
            <span class="preview-tag">[Preview]</span>

            <!-- LOG IN/REGISTER (NOT LOGGED IN) -->
          <?php } else { ?>
            <button type="button" data-welcome-replay aria-label="Take the tour" data-tooltip="Take the tour">
              <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#info-icon" />
              </svg>
              <span>Tour</span>
            </button>
            <a href="<?= ROOT ?>/user/login" id="headerLogin" class="auth-btn">Sign In</a>
            <a href="<?= ROOT ?>/user/register" id="headerRegister" class="auth-btn">Register</a>
          <?php } ?>

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
        </div>
      <?php endif; ?>
    </header>
    <!-- Mobile nav lives outside <header> so header's scroll-hide transform never traps it as a fixed-position containing block -->
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

  <?php if ($user && empty($user['email_verified'])): ?>
    <div class="verify-banner" id="verifyEmailBanner">
      <div class="verify-banner-text">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#info-icon" />
        </svg>
        <span>Verify your email to receive notification updates via email and keep up to date on your protocols' status.</span>
        <form method="POST" action="<?= ROOT ?>/user/resend_verification" class="verify-banner-link-form"
          data-confirm-message="Send a verification link to <?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>?"
          data-confirm-ok-text="Send">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit" class="verify-banner-link">Verify my email</button>
        </form>
      </div>
      <button type="button" class="verify-banner-close" id="verifyEmailBannerClose" aria-label="Dismiss">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#close-icon" />
        </svg>
      </button>
    </div>
  <?php endif; ?>

  <?php
  $showWelcome = $user && !empty($user['show_welcome']);
  // Every audience gets a spotlight tour of the website (replayable from the account menu or header help button).
  // Step format: [title, text, desktop target selector, mobile target selector (optional)]
  $navLink = fn($path) => ["aside a[href$='" . $path . "']", "#mobileNav a[href$='" . $path . "']"];
  $tourWelcome = ['Welcome to BSU-IACUC!', 'Let us show you around the website. It only takes a minute, and you can replay it anytime from your account menu.'];
  $tourAnnouncements = ['Announcements', 'Read the latest news and updates from the CCARD office.', ...$navLink('/announcements')];
  $tourContact = ['Contact Us', 'Find the CCARD office details and how to reach them.', ...$navLink('/contact')];
  $tourPreview = ['Preview public site', 'See the website the way visitors see it. It opens in a new tab.', '.header-preview-link'];
  $tourNotifications = ['Notifications', 'The bell shows a badge when there is something new. Open it to read your updates or see all notifications.', '.notif-bell svg'];
  $tourTheme = ['Theme', 'Switch between Light, Dark, and Auto to match your device.', '#theme-toggle'];
  $tourAccount = ['Your account', 'Open this to view My Profile, replay this tour, or log out.', '.my-account-dropdown'];
  $welcomeSteps = [];
  if ($role === 'researcher') {
    $welcomeSteps = [
      $tourWelcome,
      ['Page menu', 'These links take you between Home, My Protocols, Announcements, and Contact Us. On a phone, tap the menu icon at the top right to open them.', 'aside nav ul', '.mobile-menu'],
      ['My Protocols', 'Apply for review here, then track each protocol, answer reviewer comments, pay the processing fee, and download your clearance.', ...$navLink('/submissions')],
      $tourAnnouncements,
      $tourContact,
      $tourNotifications,
      $tourTheme,
      $tourAccount,
    ];
  } elseif ($role === 'staff') {
    $welcomeSteps = [
      $tourWelcome,
      ['Page menu', 'These links take you between the Dashboard, Records, Site Content, and Administration. On a phone, tap the menu icon at the top right to open them.', 'aside nav ul', '.mobile-menu'],
      ['Dashboard', 'The Protocol Inbox lists submitted protocols. Open one to review it or return it for revision, and upload released clearances here.', ...$navLink('/personnel/home')],
      ['Records', 'Browse the full table of protocol records and see statistics for a chosen period.', ...$navLink('/personnel/records')],
      ['Site Content', 'Edit what visitors see: post announcements and update the homepage text, FAQs, and contact page offices.', ...$navLink('/personnel/announcements')],
      ['Administration', 'Approve pending personnel applications and download audit logs.', ...$navLink('/personnel/accounts')],
      $tourPreview,
      $tourNotifications,
      $tourTheme,
      $tourAccount,
    ];
  } elseif ($role === 'reviewer') {
    $welcomeSteps = [
      $tourWelcome,
      ['Page menu', 'These links take you between the Dashboard and Records. On a phone, tap the menu icon at the top right to open them.', 'aside nav ul', '.mobile-menu'],
      ['Dashboard', 'The Protocol Inbox lists protocols for review. Open one to read it and draw annotation boxes on the document.', ...$navLink('/personnel/home')],
      ['Records', 'Browse the full table of protocol records and see statistics for a chosen period.', ...$navLink('/personnel/records')],
      $tourPreview,
      $tourNotifications,
      $tourTheme,
      $tourAccount,
    ];
  } elseif (!$user && !$previewMode && !$hideHeader && !$hideHeaderAuth) {
    $welcomeSteps = [
      ['Welcome to BSU-IACUC!', 'Let us show you around the website. It only takes a minute.'],
      ['Sign in or register', 'Already have an account? Press Sign In. New here? Press Register to create one. You need an account to apply for protocol review.', '#headerLogin, #headerRegister'],
      ['Page menu', 'These links take you between Home, Announcements, and Contact Us. On a phone, tap the menu icon at the top right to open them.', 'aside nav ul', '.mobile-menu'],
      $tourAnnouncements,
      $tourContact,
      $tourTheme,
    ];
  }
  ?>

  <?php if ($welcomeSteps): ?>
    <div class="modal-backdrop<?= $showWelcome ? ' open' : '' ?>" id="welcomeModal" <?= !$user ? ' data-show-once' : '' ?>>
      <div class="tour-spotlight" id="tourSpotlight" aria-hidden="true"></div>
      <div class="modal-card welcome-modal-card">
        <button type="button" class="modal-close" id="welcomeModalClose" aria-label="Close">
          <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#close-icon" />
          </svg>
        </button>
        <div class="welcome-dots" id="welcomeDots" aria-hidden="true"></div>
        <?php foreach ($welcomeSteps as $i => $step): ?>
          <?php [$stepTitle, $stepText, $stepTarget, $stepMobileTarget] = array_pad($step, 4, ''); ?>
          <section class="welcome-step" data-welcome-step<?= $i > 0 ? ' hidden' : '' ?> data-tour-target="<?= htmlspecialchars($stepTarget, ENT_QUOTES, 'UTF-8') ?>" <?= $stepMobileTarget ? ' data-tour-target-mobile="' . htmlspecialchars($stepMobileTarget, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
            <span class="welcome-step-label">Step <?= $i + 1 ?> of <?= count($welcomeSteps) ?></span>
            <h3><?= htmlspecialchars($stepTitle, ENT_QUOTES, 'UTF-8') ?></h3>
            <p><?= htmlspecialchars($stepText, ENT_QUOTES, 'UTF-8') ?></p>
          </section>
        <?php endforeach; ?>
        <div class="modal-actions welcome-actions">
          <button type="button" class="button" id="welcomeBack" hidden>Back</button>
          <button type="button" class="button welcome-next" id="welcomeNext">Next</button>
        </div>
      </div>
    </div>
  <?php endif; ?>
  <?php unset($_SESSION['user']['show_welcome']); ?>

  <div id="sidebar-backdrop" aria-hidden="true"></div>