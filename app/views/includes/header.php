<?php
include 'sprites.php';

/** @var array|null $user */
$first_name = $user['first_name'] ?? '';
$role = $user['role'] ?? '';
$hideHeaderAuth = $hideHeaderAuth ?? false;
$hideHeader     = $hideHeader     ?? false;
$previewMode     = $previewMode     ?? false;
$pubRoot         = $previewMode ? ROOT . '/preview' : ROOT;

// One list of theme options, rendered in the account dropdown and in the visitor theme menu
$themeModes = [
  ['light', 'Light', 'sun-icon'],
  ['dark', 'Dark', 'moon-icon'],
  ['auto', 'System', 'monitor-icon'],
];

$showWelcome = $user && !empty($user['show_welcome']);
// Every audience gets a spotlight guide of the website (replayable from the header help button).
// Step format: [title, text, desktop target selector, mobile target selector (optional)]
$navLink = fn($path) => ["aside a[href$='" . $path . "']", "#mobileNav a[href$='" . $path . "']"];
$guideWelcome = ['Welcome to BSU-IACUC!', 'Let us show you around the website. It only takes a minute, and you can replay it anytime from **Guide** in the three dots menu in the header.'];
$guideHome = ['Homepage', 'Go here to read about IACUC, how to apply for protocol review, the frequently asked questions, and more.', ...$navLink('/home')];
$guideAnnouncements = ['Announcements', 'Read the latest news and updates from the CCARD office.', ...$navLink('/announcements')];
$guideContact = ['Contact Us', 'Find CCARD\'s and related offices\' details and how to reach them.', ...$navLink('/contact')];
$guideNotifications = ['Notifications', 'The bell shows a badge when there is something new. Open it to read your updates or see all notifications.', '.notif-bell svg'];
$guideTheme = ['Theme', 'Switch between Light, Dark, and System to match your device.', '#theme-toggle'];
$guideHelp = ['Guide', 'Replay this tour anytime with the question mark button.', '.header-help'];
$guideAccount = ['Your account', 'Open this to view and update **My Account**.', '.header-account'];
$guideMore = ['More options', 'Open this menu to ' . ($role === 'researcher' ? '' : 'preview the public site, ') . 'replay this guide, switch between Light, Dark, and System theme, or log out.', '.header-more'];
$welcomeSteps = [];
if ($role === 'researcher') {
  $welcomeSteps = [
    $guideWelcome,
    ['Page menu', 'These links take you between **Home**, **My Protocols**, **Announcements**, and **Contact Us**. On a phone, tap the menu icon at the top left to open them.', 'aside nav ul', '.mobile-menu'],
    $guideHome,
    ['My Protocols', 'Apply for review here, then track each protocol, answer reviewer comments, pay the processing fee, and download your clearance.', ...$navLink('/submissions')],
    $guideAnnouncements,
    $guideContact,
    $guideNotifications,
    $guideAccount,
    $guideMore,
  ];
} elseif ($role === 'staff') {
  $welcomeSteps = [
    $guideWelcome,
    ['Page menu', 'These links take you between the **Dashboard**, **Records**, **Site Content**, and **Administration**. On a phone, tap the menu icon at the top left to open them.', 'aside nav ul', '.mobile-menu'],
    ['Dashboard', 'The Protocol Inbox lists submitted protocols. Open one to review it or return it for revision, and upload released clearances here.', ...$navLink('/personnel/home')],
    ['Records', 'Browse the full table of protocol records and see statistics for a chosen period.', ...$navLink('/personnel/records')],
    ['Site Content', 'Edit what visitors see: post announcements and update the homepage text, FAQs, and contact page offices.', ...$navLink('/personnel/announcements')],
    ['Administration', 'Approve pending personnel applications and download audit logs.', ...$navLink('/personnel/accounts')],
    $guideNotifications,
    $guideAccount,
    $guideMore,
  ];
} elseif ($role === 'reviewer') {
  $welcomeSteps = [
    $guideWelcome,
    ['Page menu', 'These links take you between the **Dashboard** and **Records**. On a phone, tap the menu icon at the top left to open them.', 'aside nav ul', '.mobile-menu'],
    ['Dashboard', 'The Protocol Inbox lists protocols for review. Open one to read it and draw annotation boxes on the document.', ...$navLink('/personnel/home')],
    ['Records', 'Browse the full table of protocol records and see statistics for a chosen period.', ...$navLink('/personnel/records')],
    $guideNotifications,
    $guideAccount,
    $guideMore,
  ];
} elseif (!$user && !$previewMode && !$hideHeader && !$hideHeaderAuth) {
  $welcomeSteps = [
    ['Welcome to BSU-IACUC!', 'Let us show you around the website. It only takes a minute.'],
    ['Sign in or register', 'Already have an account? Press **Sign In**. New here? Press **Register** to create one. You need an account to apply for protocol review.', '#headerLogin, #headerRegister'],
    ['Page menu', 'These links take you between the **homepage**, **announcements page**, and **contact page**. On a phone, tap the menu icon at the top left to open them.', 'aside nav ul', '.mobile-menu'],
    $guideHome,
    $guideAnnouncements,
    $guideContact,
    $guideTheme,
    $guideHelp,
  ];
}
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
            <!-- <img src="<?= IMGPATH ?>/bsu.webp" alt=""> -->
            <!-- <img src="<?= IMGPATH ?>/ccard.webp" alt=""> -->
          </div>
          <div>BSU-<span>IACUC</span></div>
        </a>
      </div>

      <?php if (!$hideHeaderAuth): ?>
        <!-- THEME (VISITORS ONLY: logged-in users switch theme from the account dropdown) -->
        <?php if (!$user) include 'theme-toggle.php'; ?>

        <div class="header-auth">
          <!-- GUIDE (HELP, VISITORS ONLY: logged-in users open it from the more menu) -->
          <?php if ($welcomeSteps && !$user): ?>
            <button type="button" class="header-help" data-guide-start="welcome" aria-label="View guide on website navigation" data-tooltip="View guide on website navigation">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#question-info-icon" />
              </svg>
              <span>Guide</span>
            </button>
          <?php endif; ?>

          <?php if ($user) { ?>
            <button class="notif-bell"
              aria-expanded="false"
              aria-haspopup="true"
              aria-label="Notifications"
              data-tooltip="Show notifications"
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

            <!-- MY ACCOUNT (OPENS THE SLIDE-IN) -->
            <button class="header-account"
              aria-label="My account"
              data-tooltip="Show account settings">

              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#account-icon" />
              </svg>

              <span><?= htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8') ?></span>
            </button>

            <!-- MORE OPTIONS -->
            <button class="header-more"
              aria-expanded="false"
              aria-haspopup="true"
              aria-label="More options"
              data-tooltip="More options"
              aria-controls="header-menu">

              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#more-icon" />
              </svg>
            </button>

            <div id="header-menu">
              <?php if ($role === 'staff' || $role === 'reviewer'): ?>
                <a href="<?= ROOT ?>/preview/home" target="_blank" rel="noopener">
                  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#eye-icon" />
                  </svg>
                  Preview public site</a>
              <?php endif; ?>

              <?php if ($welcomeSteps): ?>
                <button type="button" data-guide-start="welcome">
                  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#question-info-icon" />
                  </svg>
                  Guide
                </button>
              <?php endif; ?>

              <div class="dropdown-theme" role="radiogroup" aria-label="Theme">
                <span>Theme</span>
                <div class="theme-options">
                  <?php foreach ($themeModes as [$themeMode, $themeLabel, $themeIcon]): ?>
                    <button type="button" role="radio" data-mode="<?= $themeMode ?>" aria-label="<?= $themeLabel ?>" title="<?= $themeLabel ?>">
                      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#<?= $themeIcon ?>" />
                      </svg>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>

              <form method="POST" action="<?= ROOT ?><?= $role === 'researcher' ? '/user/logout' : '/personnel/logout' ?>" data-confirm-message="Confirm to log out?" data-confirm-ok-text="Log Out">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn-header-logout">
                  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
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
            <a href="<?= ROOT ?>/user/login" id="headerLogin" class="auth-btn">Sign In</a>
            <a href="<?= ROOT ?>/user/register" id="headerRegister" class="auth-btn">Register</a>
          <?php } ?>
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

  <?php if ($welcomeSteps) {
    $guideId       = 'welcome';
    $guideSteps    = $welcomeSteps;
    $guideOpen     = $showWelcome;
    $guideShowOnce = !$user;
    include __DIR__ . '/guide.php';
  } ?>
  <?php unset($_SESSION['user']['show_welcome']); ?>

  <div id="sidebar-backdrop" aria-hidden="true"></div>

  <?php if ($user && !$hideHeader && !$hideHeaderAuth): ?>
    <!-- ===== Account drawer ===== -->
    <div id="account-drawer" role="dialog" aria-modal="true" aria-label="My Account" inert>
      <iframe title="My Account" data-src="<?= ROOT ?>/user/account"></iframe>
    </div>
  <?php endif; ?>