<?php

/**
 * Central access policy (default deny).
 *
 * Every routable page/endpoint (controller/method) MUST be listed in self::RULES.
 * A route that is not listed is treated as "not found" for everyone, so a newly
 * added controller method stays closed until someone deliberately opens it here.
 *
 * Rules:
 *   public     anyone, logged in or not
 *   auth       any logged-in user (researcher, staff or reviewer)
 *   researcher researchers only
 *   personnel  staff or reviewer
 *   reviewer   reviewers only
 *   staff      administrative staff only
 *
 * Controllers keep their own checks too (defense in depth); this table is the
 * single place to see who can reach what.
 */
class Access
{
  private const RULES = [
    'home'          => ['index' => 'public'],
    'announcements' => ['index' => 'public'],
    'contact'       => ['index' => 'public'],
    'webhooks'      => ['brevo' => 'public'], // authenticated by its own bearer secret

    'preview' => [
      'index' => 'personnel',
      'home' => 'personnel',
      'announcements' => 'personnel',
      'contact' => 'personnel',
    ],

    'notifications' => [
      'index' => 'auth',
      'page' => 'auth',
      'markread' => 'auth',
      'markallread' => 'auth',
    ],

    'submissions' => [
      'index' => 'researcher',
      'checkupdates' => 'researcher',
    ],

    'user' => [
      // signed out
      'login' => 'public',
      'login_process' => 'public',
      'register' => 'public',
      'register_process' => 'public',
      'forgot_password' => 'public',
      'reset_password' => 'public',
      'email_preferences' => 'public', // signed email link
      'verify_email' => 'public',      // emailed token
      'logout' => 'public',
      // any signed-in user (own account only)
      'account' => 'auth',
      'update' => 'auth',
      'notifications' => 'auth',
      'ping' => 'auth',
      'resend_verification' => 'auth',
      'school_options' => 'auth',
      // researchers only
      'delete' => 'researcher',
    ],

    'personnel' => [
      // signed out
      'login' => 'public',
      'login_process' => 'public',
      'logout' => 'public',
      'register' => 'public',
      'register_process' => 'public',
      'forgot_password' => 'public',
      'reset_password' => 'public',
      // staff + reviewer
      'home' => 'personnel',
      'checkupdates' => 'personnel',
      'researcher_details' => 'personnel',
      'records' => 'personnel',
      'records_file' => 'personnel',
      'records_export' => 'personnel',
      // administrative staff only
      'clearances' => 'staff',
      'records_add' => 'staff',
      'records_get' => 'staff',
      'records_edit' => 'staff',
      'records_delete' => 'staff',
      'announcements' => 'staff',
      'announcements_add' => 'staff',
      'announcements_get' => 'staff',
      'announcements_edit' => 'staff',
      'announcements_delete' => 'staff',
      'site_content' => 'staff',
      'site_content_settings' => 'staff',
      'site_content_office_add' => 'staff',
      'site_content_office_get' => 'staff',
      'site_content_office_edit' => 'staff',
      'site_content_office_move' => 'staff',
      'site_content_office_delete' => 'staff',
      'site_content_faq_add' => 'staff',
      'site_content_faq_get' => 'staff',
      'site_content_faq_edit' => 'staff',
      'site_content_faq_delete' => 'staff',
      'accounts' => 'staff',
      'auditlogresults' => 'staff',
      'downloadauditlogs' => 'staff',
      'generate_invite' => 'staff',
      'approve' => 'staff',
      'reject' => 'staff',
      'update_role' => 'staff',
    ],

    'apply' => [
      // researcher application flow
      'index' => 'researcher',
      'submit' => 'researcher',
      'stageprotocol' => 'researcher',
      'stagedfile' => 'researcher',
      'hascert' => 'researcher',
      'draft' => 'researcher',
      'draftsave' => 'researcher',
      'draftupload' => 'researcher',
      'draftfile' => 'researcher',
      'draftremovefile' => 'researcher',
      'draftclear' => 'researcher',
      'reupload' => 'researcher',
      'reuploadcert' => 'researcher',
      'payment_proof' => 'researcher',
      'amendment_upload' => 'researcher',
      'request_deletion' => 'researcher',
      'hard_copy_claim' => 'researcher',
      // signed in; each also checks protocol ownership / personnel inside
      'versions' => 'auth',
      'allversions' => 'auth',
      'modaldata' => 'auth',
      'viewer' => 'auth',
      'file' => 'auth',
      'cert' => 'auth',
      'clearance' => 'auth',
      'returnreason' => 'auth',
      'rename' => 'auth',
      'annotate' => 'auth',
      'mark_title_seen' => 'auth',
      // staff + reviewer
      'status' => 'personnel',
      'delete' => 'personnel',
      'return_revision' => 'reviewer',
      // administrative staff only
      'download_selected' => 'staff',
      'verify_payment' => 'staff',
      'undo_payment' => 'staff',
      'reject_payment' => 'staff',
      'hard_copy' => 'staff',
      'signed_scan_upload' => 'staff',
      'approve_deletion' => 'staff',
      'reject_deletion' => 'staff',
      'clearance_upload' => 'staff',
      'clearance_pool_upload' => 'staff',
      'clearance_pool' => 'staff',
      'clearance_pool_file' => 'staff',
      'clearance_stage' => 'staff',
      'clearance_unstage' => 'staff',
      'clearance_delete' => 'staff',
      'clearance_confirm' => 'staff',
      'clearance_unassign' => 'staff',
      'assign_ipn' => 'staff',
    ],
  ];

  // Returns the rule for a route, or null when the route is not listed
  public static function ruleFor(string $controller, string $method): ?string
  {
    return self::RULES[strtolower($controller)][strtolower($method)] ?? null;
  }

  // Enforces the policy. Returns normally only when access is allowed.
  public static function enforce(string $controller, string $method): void
  {
    $rule = self::ruleFor($controller, $method);

    if ($rule === null) {
      self::deny(404);
    }

    if ($rule === 'public') {
      return;
    }

    $loggedIn = isset($_SESSION['user']['user_id']);
    $role     = (string) ($_SESSION['user']['role'] ?? '');

    if (!$loggedIn) {
      self::deny(401, strtolower($controller) === 'personnel' || $rule === 'staff' || $rule === 'personnel');
    }

    $allowed = match ($rule) {
      'auth'       => true,
      'researcher' => $role === 'researcher',
      'personnel'  => in_array($role, ['staff', 'reviewer'], true),
      'reviewer'   => $role === 'reviewer',
      'staff'      => $role === 'staff',
      default      => false, // unknown rule name: fail closed
    };

    if (!$allowed) {
      self::deny(403, false, $role);
    }
  }

  private static function wantsHtml(): bool
  {
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html');
  }

  private static function deny(int $code, bool $personnelArea = false, string $role = ''): void
  {
    if (!self::wantsHtml()) {
      http_response_code($code);
      header('Content-Type: application/json');
      echo json_encode(['ok' => false, 'error' => match ($code) {
        401 => 'Your session has expired. Please log in again.',
        403 => 'You do not have permission to access this.',
        default => 'Not found.',
      }]);
      exit;
    }

    if ($code === 401) {
      header('Location: ' . ROOT . '/' . ($personnelArea ? 'personnel/login' : 'user/login'));
      exit;
    }

    if ($code === 403 && in_array($role, ['staff', 'reviewer'], true)) {
      // Personnel who wander into a page that is not theirs go back to their dashboard
      header('Location: ' . ROOT . '/personnel/home');
      exit;
    }

    if ($code === 403) {
      ErrorPage::render(403, 'Access Denied', [
        'You do not have permission to view this page.',
      ]);
      exit;
    }

    ErrorPage::render(404, 'Page Not Found', [
      'Page could not be found. It may have been removed, had its name changed, or is temporarily unavailable.',
    ]);
    exit;
  }
}
