<?php

// One place for what "deactivated" means: who gets deactivated and when, who can come back on their own, and what is recorded
//
//   Soft (expired)  a researcher whose last clearance expired and who has nothing pending. They sign in, confirm, and are back.
//   Hard (dormant)  personnel with no login for PERSONNEL_DORMANT_MONTHS. Locked out until administrative staff reactivate them.
//   Hard (manual)   any account deactivated by administrative staff. Locked out until administrative staff reactivate it.
//
// Deactivating never touches protocols, records or files. Only deleting anonymizes the person.
class AccountLifecycle
{
  public const REASON_EXPIRED = 'expired';
  public const REASON_DORMANT = 'dormant';
  public const REASON_MANUAL  = 'manual';

  public const PERSONNEL_DORMANT_MONTHS = 6;

  // ===== WHO MAY COME BACK ON THEIR OWN =====
  public static function canSelfReactivate(array $user): bool
  {
    return ($user['status'] ?? '') === 'deactivated'
      && ($user['role'] ?? '') === 'researcher'
      && ($user['deactivation_reason'] ?? self::REASON_EXPIRED) === self::REASON_EXPIRED;
  }

  // ===== SIGN-IN GATE =====
  // The message to show when a deactivated account must not sign in, or null when nothing blocks it
  public static function signInBlock(array $user): ?string
  {
    if (($user['status'] ?? '') !== 'deactivated' || self::canSelfReactivate($user)) {
      return null;
    }

    return ($user['deactivation_reason'] ?? '') === self::REASON_DORMANT
      ? 'Your account was deactivated after ' . self::PERSONNEL_DORMANT_MONTHS . ' months of not signing in. Please ask an administrative staff member to reactivate your account.'
      : 'Your account has been deactivated. Please contact BSU-CCARD to have it reactivated.';
  }

  // Shown when a signed-in session is ended because the account is no longer active
  public static function sessionEndedMessage(array $account): string
  {
    if (($account['status'] ?? '') !== 'deactivated') {
      return 'Your session has ended. Please log in again.';
    }

    return self::canSelfReactivate($account)
      ? 'Your account is now inactive because your clearance has ended. Log in again to reactivate it.'
      : (string) self::signInBlock($account);
  }

  // ===== LABEL FOR STAFF-FACING SCREENS =====
  public static function statusLabel(array $user): string
  {
    if (($user['status'] ?? '') !== 'deactivated') {
      return ucfirst((string) ($user['status'] ?? ''));
    }

    return match ($user['deactivation_reason'] ?? '') {
      self::REASON_DORMANT => 'Deactivated (no login for ' . self::PERSONNEL_DORMANT_MONTHS . ' months)',
      self::REASON_MANUAL  => 'Deactivated by staff',
      default              => 'Deactivated (latest clearance expired)',
    };
  }

  // ===== DEACTIVATE =====
  // $actor is the staff member who did it ('id', 'name', 'role'), or null when the system did
  public static function deactivate(int $id, string $reason, ?array $actor = null): bool
  {
    $users = new UserModel();
    $user  = $users->getUser($id);

    if (!$user || !$users->deactivateUser($id, $reason)) {
      return false;
    }

    $description = match (true) {
      $actor !== null                  => "Deactivated by {$actor['name']}",
      $reason === self::REASON_DORMANT => 'Auto-deactivated: no login for ' . self::PERSONNEL_DORMANT_MONTHS . ' months',
      default                          => 'Auto-deactivated: latest clearance expired with no protocols pending',
    };

    $users->logAudit(
      'account_deactivated',
      $actor['id'] ?? null,
      $actor['name'] ?? 'System',
      $actor['role'] ?? 'system',
      'user',
      $id,
      $description,
      'active',
      'deactivated'
    );

    if (!empty($user['email_verified'])) {
      Mailer::sendTemplate('account_deactivated', [
        'first_name'      => $user['first_name'],
        'self_reactivate' => $reason === self::REASON_EXPIRED,
        'reason'          => $reason,
        'months'          => self::PERSONNEL_DORMANT_MONTHS,
        'login_url'       => ROOT . '/' . ($user['role'] === 'researcher' ? 'user/login' : 'personnel/login'),
      ], $user['email'], $user['first_name'], 'Account Deactivated');
    }

    return true;
  }

  // ===== REACTIVATE =====
  // $actor is the staff member who did it, or null when the account owner confirmed it themselves
  public static function reactivate(int $id, ?array $actor = null): bool
  {
    $users = new UserModel();
    $user  = $users->getUser($id);

    if (!$user || !$users->reactivateUser($id)) {
      return false;
    }

    $users->logAudit(
      'account_reactivated',
      $actor['id'] ?? $id,
      $actor['name'] ?? $user['username'],
      $actor['role'] ?? $user['role'],
      'user',
      $id,
      $actor ? "Reactivated by {$actor['name']}" : 'Reactivated by the account owner after confirming',
      'deactivated',
      'active'
    );

    if ($actor && !empty($user['email_verified'])) {
      Mailer::sendTemplate('account_reactivated', [
        'first_name' => $user['first_name'],
        'login_url'  => ROOT . '/' . ($user['role'] === 'researcher' ? 'user/login' : 'personnel/login'),
      ], $user['email'], $user['first_name'], 'Account Reactivated');
    }

    return true;
  }

  // ===== SIGN-IN CHECK FOR DORMANT PERSONNEL =====
  // The sweeps only run when someone opens a dashboard or the pinger fires, so a dormant account could still sign in
  // in the meantime. Called at sign-in (after the password is verified): deactivates the account first if it is due,
  // and returns the fresh row so signInBlock() sees the new status.
  public static function enforceDormancy(array $user): array
  {
    $id = (int) ($user['id'] ?? 0);

    if (!in_array($user['role'] ?? '', ['staff', 'reviewer'], true) || ($user['status'] ?? '') !== 'active') {
      return $user;
    }

    $users = new UserModel();

    if (!$users->getDormantPersonnelIds(self::PERSONNEL_DORMANT_MONTHS, 0, $id)) {
      return $user;
    }

    if ($user['role'] === 'staff' && $users->isLastActiveStaff($id)) {
      return $user;
    }

    return self::deactivate($id, self::REASON_DORMANT) ? ($users->getUser($id) ?? $user) : $user;
  }

  // ===== SWEEPS =====
  // Safe to call on any page load: each sweep only touches accounts that are due
  public static function runSweeps(): array
  {
    return [
      'expired' => (new RecordModel())->runExpiryDeactivationSweep(),
      'dormant' => self::runDormantPersonnelSweep((int) ($_SESSION['user']['user_id'] ?? 0)),
    ];
  }

  // Personnel with no login for PERSONNEL_DORMANT_MONTHS. The person running the page and the last active staff account are never touched.
  private static function runDormantPersonnelSweep(int $exceptId): int
  {
    $users       = new UserModel();
    $deactivated = 0;

    foreach ($users->getDormantPersonnelIds(self::PERSONNEL_DORMANT_MONTHS, $exceptId) as $id) {
      $user = $users->getUser($id);

      if (!$user || ($user['role'] === 'staff' && $users->isLastActiveStaff($id))) {
        continue;
      }

      if (self::deactivate($id, self::REASON_DORMANT)) {
        $deactivated++;
      }
    }

    return $deactivated;
  }
}
