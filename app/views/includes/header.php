<?php
include 'sprites.php';

/** @var array|null $user */
$first_name = $user['first_name'] ?? '';
$role = $user['role'] ?? '';
$hideHeaderAuth = $hideHeaderAuth ?? false;
$hideHeader     = $hideHeader     ?? false;
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

  <link rel="icon" href="<?= IMGPATH ?>/favicon.ico" type="image/x-icon">

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
      const SESSION_LOGIN_PATH = <?= json_encode(in_array($role, ['staff', 'reviewer'], true) ? 'personnel/login' : 'users/login') ?>;
    </script>
    <script src="<?= asset_js('notifications.js') ?>" defer></script>
    <script src="<?= asset_js('session-timeout.js') ?>" defer></script>
  <?php endif; ?>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Akt:wght@100..900&family=Alfa+Slab+One&family=Bitter:ital,wght@0,100..900;1,100..900&family=PT+Serif:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
</head>

<body>
  <a href="#main-content" class="skip-link">Skip to main content</a>

  <?php if (!$hideHeader): ?>
    <header>
      <div class="header-logo-cont">
        <a href="<?= ROOT ?>" class="header-logo">
          <div>
            <!-- <img src="<?= IMGPATH ?>/bsu.webp" alt=""> -->
            <!-- <img src="<?= IMGPATH ?>/ccard.webp" alt=""> -->
          </div>
          <div>BSU-<span>IACUC</span></div>
        </a>
      </div>

      <!-- DARK MODE TOGGLE -->
      <?php include 'theme-toggle.php'; ?>

      <!-- ACCOUNT DROPDOWN (LOGGED IN) -->
      <?php if (!$hideHeaderAuth): ?>
        <div class="header-auth">
          <?php if ($user) { ?>
            <button class="notif-bell"
              aria-expanded="false"
              aria-haspopup="true"
              aria-label="Show notifications"
              aria-controls="notif-dropdown">

              <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
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
              aria-label="Show account dropdown menu"
              aria-controls="account-dropdown">

              <img src="<?= IMGPATH ?>/scientist.webp" alt="">

              <span><?= htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8') ?></span>

              <span class="chev-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <use href="#chev-down-icon" />
                </svg>
              </span>
            </button>

            <div id="account-dropdown">
              <a href="<?= ROOT ?>/users/account">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <use href="#account-dropdown-icon" />
                </svg>
                My Profile</a>

              <?php if ($role === 'researcher'): ?>
                <form method="POST" action="<?= ROOT ?>/users/logout" data-confirm-message="Confirm to log out?" data-confirm-ok-text="Log Out">
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

            <!-- LOG IN/REGISTER (NOT LOGGED IN) -->
          <?php } else { ?>
            <a href="<?= ROOT ?>/users/login" id="headerLogin" class="auth-btn">Sign In</a>
            <a href="<?= ROOT ?>/users/register" id="headerRegister" class="auth-btn">Register</a>
          <?php } ?>

          <!-- MOBILE HAMBURGER ICON -->
          <button
            class="mobile-menu"
            aria-expanded="false"
            aria-controls="nav-sidebar"
            aria-label="Toggle navigation menu">
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
              <li><a href="<?= ROOT ?>/personnel/clearances"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#clearance-icon" />
                  </svg><span>Clearance Pool</span></a></li>
              <li><a href="<?= ROOT ?>/personnel/announcements"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#announcement-icon" />
                  </svg><span>Announcements</span></a></li>
              <li><a href="<?= ROOT ?>/personnel/accounts"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#accounts-icon" />
                  </svg><span>Manage Accounts</span></a></li>
            <?php endif; ?>
          </ul>
        </nav>
      <?php endif; ?>

    <?php else: ?>
      <!-- PUBLIC MOBILE NAVIGATION -->
      <nav id="mobileNav" aria-label="Mobile navigation" aria-hidden="true">
        <ul class="nav-sidebar" id="nav-sidebar" inert>
          <li><a href="<?= ROOT ?>/home"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#home-icon" />
              </svg><span>Home</span></a></li>
          <li><a href="<?= ROOT ?>/announcements"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#announcement-icon" />
              </svg><span>Announcements</span></a></li>
          <li><a href="<?= ROOT ?>/contact"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
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
        <form method="POST" action="<?= ROOT ?>/users/resend_verification" class="verify-banner-link-form"
          data-confirm-message="Send a verification link to <?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>?"
          data-confirm-ok-text="Send">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit" class="verify-banner-link">Verify my email</button>
        </form>
      </div>
      <button type="button" class="verify-banner-close" id="verifyEmailBannerClose" aria-label="Dismiss">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <use href="#close-icon" />
        </svg>
      </button>
    </div>
  <?php endif; ?>

  <?php if ($user && !empty($user['show_welcome'])): ?>
    <div class="modal-backdrop open" id="welcomeModal">
      <div class="modal-card welcome-modal-card">
        <button type="button" class="announcement-modal-close" id="welcomeModalClose" aria-label="Close">
          <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#close-icon" />
          </svg>
        </button>
        <h2>Welcome to BSU-IACUC!</h2>
        <p>We're glad to have you here. Explore your dashboard to get started.</p>
      </div>
    </div>
    <?php unset($_SESSION['user']['show_welcome']); ?>
  <?php endif; ?>

  <div id="sidebar-backdrop" aria-hidden="true"></div>