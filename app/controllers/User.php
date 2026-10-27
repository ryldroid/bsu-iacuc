<?php

class User extends Controller
{
  // ===== INPUT HELPERS =====
  private function normalizePhoneNumber(string $raw): string
  {
    $digits = preg_replace('/\D/', '', $raw);

    if ($digits === '') {
      return '';
    }

    if (str_starts_with($digits, '63') && strlen($digits) > 10) {
      $digits = substr($digits, 2);
    }

    if ($digits[0] === '0') {
      $digits = substr($digits, 1);
    }

    return '+63' . $digits;
  }

  private function sanitizeInputs(array $post): array
  {
    return [
      'username'     => trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $post['username'] ?? '')),
      'first_name'   => ucfirst(mb_strtolower(trim(preg_replace('/[^\p{L}\p{M}\s\-\']/u', '', $post['first_name'] ?? '')))),
      'last_name'    => trim(preg_replace('/[^\p{L}\p{M}\s\-\']/u', '', $post['last_name'] ?? '')),
      'email'        => filter_var(trim($post['email'] ?? ''), FILTER_SANITIZE_EMAIL),
      'phone_number' => $this->normalizePhoneNumber($post['phone_number'] ?? ''),
      'school'       => trim(preg_replace('/[^\p{L}\p{M}\p{N}\s\-\'\.,()&]/u', '', $post['school'] ?? '')),
      'sex'          => in_array($post['sex'] ?? '', ['Male', 'Female'], true) ? $post['sex'] : '',
      'researcher_type' => in_array($post['researcher_type'] ?? '', UserModel::RESEARCHER_TYPES, true) ? $post['researcher_type'] : '',
      'password'     => $post['password'] ?? '',
      'confirm_pass' => $post['confirm_password'] ?? '',
      'role'         => $post['role'] ?? '',
    ];
  }

  private function validateUserFields(
    string $username,
    string $first_name,
    string $last_name,
    string $email,
    string $phone_number,
    string $password,
    string $confirm_pass,
    string $role,
    bool $password_required = true,
    string $sex = '',
    string $researcher_type = ''
  ): array {
    $errors = [];

    if ($role === 'researcher' && empty($researcher_type)) {
      $errors[] = 'Please select your researcher type.';
    }

    if (empty($sex)) {
      $errors[] = 'Please select your sex.';
    }

    if (empty($username)) {
      $errors[] = 'Username is required.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
      $errors[] = 'Username must be 3–50 characters (letters, numbers, _ or -).';
    }

    if (preg_match('/[^\p{L}\p{M}\s\-\']/u', $first_name)) {
      $errors[] = 'First name contains invalid characters. Only letters from any language, spaces, hyphens, and apostrophes are allowed.';
    } elseif (empty($first_name) || strlen($first_name) < 2 || strlen($first_name) > 100) {
      $errors[] = 'First name must be at least 2 characters.';
    }

    if (preg_match('/[^\p{L}\p{M}\s\-\']/u', $last_name)) {
      $errors[] = 'Last name contains invalid characters. Only letters from any language, spaces, hyphens, and apostrophes are allowed.';
    } elseif (empty($last_name) || strlen($last_name) < 2 || strlen($last_name) > 100) {
      $errors[] = 'Last name must be at least 2 characters.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = 'A valid email address is required.';
    }

    if (empty($phone_number)) {
      $errors[] = 'Phone number is required.';
    } else {
      if (preg_match('/[a-zA-Z]/', $phone_number)) {
        $errors[] = 'Phone number cannot contain letters. Please enter numbers only.';
      } elseif (!preg_match('/^\+639\d{9}$/', $phone_number)) {
        $errors[] = 'Enter a valid Philippine phone number (e.g., +639171234567)';
      }
    }

    if ($password_required || !empty($password) || !empty($confirm_pass)) {
      $pw_reqs = $this->validatePasswordRequirements($password);
      if (!empty($pw_reqs)) {
        $errors[] = 'Password must contain: ' . implode(', ', $pw_reqs) . '.';
      } elseif ($password !== $confirm_pass) {
        $errors[] = 'Passwords do not match.';
      }
    }

    $valid_roles = ['researcher', 'staff', 'reviewer'];
    if (!in_array($role, $valid_roles)) {
      $errors[] = 'Please select a valid role.';
    }

    return $errors;
  }

  // ===== LOG IN =====

  public function login()
  {
    if ($this->isLoggedIn()) {
      $this->redirect('submissions');
    }

    $prefill = $_SESSION['new_username'] ?? $_SESSION['new_email'] ?? '';
    unset($_SESSION['new_username'], $_SESSION['new_email']);

    if (isset($_GET['switch'])) {
      unset($_SESSION['pending_verification']);
      $this->redirect('user/login');
    }

    $pending = !empty($_SESSION['pending_verification']) ? $this->userModel->getUser((int) $_SESSION['pending_verification']) : null;
    if (!$pending || !empty($pending['email_verified'])) {
      unset($_SESSION['pending_verification']);
      $pending = null;
    }

    $this->view('user/login', [
      'prefill' => $prefill,
      'pendingEmail' => $pending['email'] ?? '',
      'error'   => $_SESSION['flash_error'] ?? '',
      'csrf'    => $this->generateCsrfToken(),
    ]);
    unset($_SESSION['flash_error']);
  }

  public function login_process()
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->redirect('user/login');
    }

    $this->verifyCsrfToken(true);
    unset($_SESSION['pending_verification']);

    // ===== READ INPUT =====
    $input    = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // ===== LOGIN ATTEMPT LIMIT =====
    $MAX_ATTEMPTS = 5;
    $WINDOW       = 900;

    $ipAttempts   = $this->userModel->countRecentAttempts($ip, $WINDOW);
    $userAttempts = $this->userModel->countRecentAttempts($input, $WINDOW);

    if ($ipAttempts >= $MAX_ATTEMPTS || $userAttempts >= $MAX_ATTEMPTS) {
      $this->userModel->logAudit('login_locked', null, $input, '', 'user', null, 'Login temporarily locked after repeated failed attempts');
      $_SESSION['flash_error'] = 'Too many failed login attempts. Please wait 15 minutes before trying again.';
      $this->redirect('user/login');
    }

    if (empty($input) || empty($password)) {
      $_SESSION['flash_error'] = 'Please fill in all fields.';
      $this->redirect('user/login');
    }

    // ===== FIND USER & CHECK PASSWORD =====
    $user = $this->userModel->getUserByUsername($input)
      ?? $this->userModel->getUserByEmail($input);

    if (!$user) {
      $this->userModel->recordLoginAttempt($ip);
      $this->userModel->recordLoginAttempt($input);
      $this->userModel->logAudit('login_failed', null, $input, '', '', null, 'Failed login attempt (no account found)');
      $_SESSION['flash_error'] = 'No researcher account found with that username or email.';
      $this->redirect('user/login');
    }

    if (!password_verify($password, $user['password'])) {
      $this->userModel->recordLoginAttempt($ip);
      $this->userModel->recordLoginAttempt($input);
      $_SESSION['flash_error'] = 'Invalid password. Please try again.';

      $this->userModel->logAudit(
        event: 'login_failed',
        actorId: null,
        actorUsername: $input,
        actorRole: '',
        targetType: '',
        targetId: null,
        description: 'Failed login attempt (wrong password)'
      );

      $this->redirect('user/login');
    }

    // ===== ACCOUNT CHECKS =====
    $this->userModel->clearLoginAttempts($ip);
    $this->userModel->clearLoginAttempts($input);

    if ($user['role'] !== 'researcher') {
      $_SESSION['flash_error'] = 'No researcher account found with that username or email.';
      $this->redirect('user/login');
    }

    // ===== EMAIL MUST BE VERIFIED FIRST =====
    if (empty($user['email_verified'])) {
      $_SESSION['pending_verification'] = (int) $user['id'];
      $this->userModel->logAudit('login_blocked_unverified', (int) $user['id'], $user['username'], $user['role'], 'user', (int) $user['id'], 'Login blocked until the email address is verified');
      $_SESSION['flash_error'] = 'Please verify your email before logging in. Use the link we emailed you, or request a new one below.';
      $this->redirect('user/login');
    }

    $this->startResearcherSession($user);

    $this->redirect('submissions');
  }

  // Signs a researcher in: reactivates a deactivated account, starts the session, queues the welcome guide, and logs it
  private function startResearcherSession(array $user): void
  {
    $reactivated = false;
    if (($user['status'] ?? 'active') === 'deactivated') {
      $this->userModel->reactivateUser((int) $user['id']);
      $reactivated = true;
    }

    // ===== START SESSION =====
    session_regenerate_id(true);
    $_SESSION['user'] = [
      'user_id'        => $user['id'],
      'first_name'     => $user['first_name'],
      'username'       => $user['username'],
      'email'          => $user['email'],
      'role'           => $user['role'],
      'email_verified' => (bool) $user['email_verified'],
    ];

    // ===== WELCOME POPUP =====
    if (!empty($user['sex']) && !empty($user['researcher_type'])) {
      $this->showWelcomeOnce($user);
    }

    // ===== LOG LOGIN & REDIRECT =====
    $this->userModel->logAudit(
      event: 'login_success',
      actorId: (int)$user['id'],
      actorUsername: $user['username'],
      actorRole: $user['role'],
      targetType: 'user',
      targetId: (int)$user['id'],
      description: 'Researcher logged in'
    );

    if ($reactivated) {
      $this->userModel->logAudit('account_reactivated', (int) $user['id'], $user['username'], $user['role'], 'user', (int) $user['id'], 'Account reactivated by logging in');
      $_SESSION['flash_success'] = 'Welcome back! Your account has been reactivated.';
    }
  }

  // Welcome guide shows once, after the researcher has set sex and researcher type and verified their email
  private function showWelcomeOnce(array $user): void
  {
    if (empty($user['welcome_seen']) && !empty($user['email_verified'])) {
      $_SESSION['user']['show_welcome'] = true;
      $this->userModel->markWelcomeSeen((int) $user['id']);
    }
  }

  // ===== REGISTER =====

  public function register()
  {
    if ($this->isLoggedIn()) {
      $this->redirect('submissions');
    }

    $this->view('user/register', [
      'errors' => [],
      'old'    => [],
      'csrf'   => $this->generateCsrfToken(),
    ]);
  }

  public function register_process()
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->redirect('user/register');
    }

    $this->verifyCsrfToken();

    extract($this->sanitizeInputs($_POST));
    $school = trim($school ?? '');
    $old = compact('username', 'first_name', 'last_name', 'email', 'phone_number', 'school', 'sex', 'researcher_type');

    $errors = $this->validateUserFields($username, $first_name, $last_name, $email, $phone_number, $password, $confirm_pass, 'researcher', true, $sex, $researcher_type);

    if ($school === '') {
      $errors[] = 'Please enter your school.';
    } elseif (mb_strlen($school) > 150) {
      $errors[] = 'School name is too long.';
    }

    if (empty($errors)) {
      if ($this->userModel->getUserByEmail($email)) {
        $errors[] = 'That email is already taken.';
      }
      if ($this->userModel->getUserByUsername($username)) {
        $errors[] = 'That username is already taken.';
      }
    }

    if (!empty($errors)) {
      $this->view('user/register', [
        'errors' => $errors,
        'old'    => $old,
        'csrf'   => $this->generateCsrfToken(),
      ]);
      return;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ok   = $this->userModel->insertUser($username, $first_name, $last_name, $email, $hash, 'researcher', 'active', $phone_number, $school, $sex, $researcher_type);

    if ($ok) {
      // No email goes out yet: the verify page lets the person send the link when they are ready
      $newUser = $this->userModel->getUserByEmail($email);
      $_SESSION['pending_verification'] = (int) $newUser['id'];
      $_SESSION['flash_success']        = 'Account created! Activate your account through email verification.';
      $this->redirect('user/login');
    } else {
      $errors[] = 'Registration failed. Please try again.';
      $this->view('user/register', [
        'errors' => $errors,
        'old'    => $old,
        'csrf'   => $this->generateCsrfToken(),
      ]);
    }
  }

  // ===== LOG OUT =====

  public function logout()
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->redirect('home');
    }

    $uid      = $_SESSION['user']['user_id'] ?? null;
    $uname    = $_SESSION['user']['username'] ?? '';
    $urole    = $_SESSION['user']['role'] ?? '';

    $this->userModel->logAudit('logout', $uid, $uname, $urole, 'user', $uid, 'User logged out');

    session_destroy();
    session_start();
    $this->redirect('home');
  }

  // ===== SESSION PING  (POST /user/ping) =====

  public function ping(): void
  {
    $this->requireLogin();
    header('Content-Type: application/json');
    $this->requirePostMethod();
    $this->verifyCsrfHeader();

    echo json_encode(['ok' => true]);
    exit;
  }

  // ===== VERIFY EMAIL: REMIND ME LATER (POST /user/snooze_verification) =====
  // Lets the person browse for a while; the gate in Controller asks again once this runs out
  public function snooze_verification(): void
  {
    $this->requireLogin();
    $this->requirePostMethod();
    $this->verifyCsrfHeader();

    $_SESSION['verify_snoozed_until'] = time() + EMAIL_VERIFY_SNOOZE;

    $this->jsonResponse(['ok' => true]);
  }

  // ===== MANAGE ACCOUNT =====

  public function account()
  {
    $this->requireLogin();
    $this->redirect(($_SESSION['user']['role'] ?? '') === 'researcher' ? 'home?account=1' : 'personnel/home?account=1');
  }

  // ===== EMAIL NOTIFICATIONS TOGGLE (POST /user/notifications) =====

  public function notifications(): void
  {
    $this->requireLogin();
    $this->requirePostMethod();
    $this->verifyCsrfToken();

    $id    = (int) $_SESSION['user']['user_id'];
    $email = $_SESSION['user']['email'];
    session_write_close();

    [$ok, $message] = $this->saveEmailNotifications($id, $email, !empty($_POST['email_notifications']));

    if (!$ok) {
      $this->jsonError(502, $message);
    }

    $this->jsonResponse([
      'message'          => $message,
      'provider_blocked' => !empty($this->userModel->getUser($id)['email_provider_blocked']),
    ]);
  }

  // ===== SCHOOL SUGGESTIONS (LOADED WHEN THE ACCOUNT PANEL FIRST OPENS) =====

  public function school_options(): void
  {
    $this->requireLogin();
    header('Cache-Control: private, max-age=86400');
    include dirname(__DIR__) . '/views/includes/school-options.php';
  }

  // ===== EMAIL PREFERENCES (SIGNED EMAIL LINK, NO LOGIN) =====

  public function email_preferences(): void
  {
    $email = trim((string) ($_GET['email'] ?? ''));
    $token = (string) ($_GET['token'] ?? '');
    $user  = hash_equals(Mailer::preferencesToken($email), $token) ? $this->userModel->getUserByEmail($email) : null;

    if (!$user) {
      $this->renderError(403, 'Invalid Link', ['This email preferences link is invalid or no longer works.']);
      return;
    }

    $enabled = (bool) $user['email_notifications'];
    $success = '';
    $error   = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $this->verifyCsrfToken();
      $wanted = ($_POST['email_notifications'] ?? '') === '1';
      [$ok, $message] = $this->saveEmailNotifications((int) $user['id'], $user['email'], $wanted);
      $enabled = $ok ? $wanted : $enabled;
      $success = $ok ? $message : '';
      $error   = $ok ? '' : $message;
    }

    $this->view('user/email_preferences', [
      'csrf'    => $this->generateCsrfToken(),
      'action'  => ROOT . '/user/email_preferences?email=' . rawurlencode($email) . '&token=' . $token,
      'enabled' => $enabled,
      'success' => $success,
      'error'   => $error,
    ]);
  }

  // Saves the preference and, when turning it on, asks Brevo to unblock the address first
  private function saveEmailNotifications(int $id, string $email, bool $enabled): array
  {
    if ($enabled && !Mailer::resubscribe($email, $failedStatus)) {
      $user = $this->userModel->getUser($id);
      $this->userModel->logAudit('email_provider_unblock_failed', $id, $user['username'], $user['role'], 'user', $id, 'Brevo could not unblock this address (status ' . $failedStatus . ').');

      // Status 0 means Brevo was never reached, so the toggle still saves
      if ($failedStatus !== 0) {
        return [false, 'We could not reach our email provider to unblock your address. Please try again shortly.'];
      }
    }

    $this->userModel->updateEmailNotifications($id, $enabled);

    if ($enabled) {
      $this->userModel->updateEmailProviderBlocked($id, false);
    }

    return [true, $enabled
      ? 'You will now receive email updates about your protocols.'
      : 'Email updates about your protocols have been turned off.'];
  }

  // ===== UPDATE ACCOUNT =====
  public function update()
  {
    $this->requireLogin();
    $this->requirePostMethod();
    $this->verifyCsrfToken();

    $id = (int) $_SESSION['user']['user_id'];

    // ===== CLEAN INPUT & VALIDATE =====
    extract($this->sanitizeInputs($_POST));

    $current_user = $this->userModel->getUser($id);

    // The role is NEVER taken from the form. Roles are only changed by staff through personnel/update_role.
    $role = $current_user['role'];

    $school = trim($school ?? '');

    $errors = $this->validateUserFields($username, $first_name, $last_name, $email, $phone_number, $password, $confirm_pass, $role, false, $sex, $researcher_type);

    if ($role === 'researcher') {
      if ($school === '') {
        $errors[] = 'Please enter your school.';
      } elseif (mb_strlen($school) > 150) {
        $errors[] = 'School name is too long.';
      }
    }

    if (empty($errors)) {
      $existing_username = $this->userModel->getUserByUsername($username);

      // Only a changed email needs the uniqueness check, so an unchanged one never blocks saving
      $emailChanged = strcasecmp($email, $current_user['email']) !== 0;
      if ($emailChanged && $this->userModel->isEmailTakenByAnother($email, $id)) {
        $errors[] = 'That email is already taken.';
      }
      if ($existing_username && (int) $existing_username['id'] !== $id) {
        $errors[] = 'That username is already taken.';
      }
    }

    // ===== SHOW ERRORS =====
    if (!empty($errors)) {
      $this->jsonResponse(['errors' => $errors], 422);
    }

    // ===== SAVE CHANGES =====
    $input = compact('username', 'first_name', 'last_name', 'email', 'phone_number', 'school', 'sex', 'researcher_type', 'role');
    if (!empty($password)) {
      $input['password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $profileWasIncomplete = $role === 'researcher' && (empty($current_user['sex']) || empty($current_user['researcher_type']));

    if (!$this->userModel->updateUser($id, $input)) {
      $this->jsonError(500, 'Update failed. Please try again.');
    }

    // ===== AFTER SAVE =====
    if ($role === 'researcher') {
      (new RecordModel())->fillBlankDemographics($id, $sex, $researcher_type);
      if ($profileWasIncomplete) {
        $this->showWelcomeOnce($current_user);
      }
    }
    $_SESSION['user']['first_name'] = $first_name;
    $_SESSION['user']['username']   = $username;
    $_SESSION['user']['email']      = $email;
    $_SESSION['user']['role'] = $role;

    if (!empty($password)) {
      $this->userModel->logAudit('password_changed', $id, $username, $role, 'user', $id, 'Password changed via account settings');
    }

    $message = 'Account updated successfully!';

    if ($email !== $current_user['email']) {
      $this->userModel->markEmailUnverified($id);
      $verificationSent = $this->sendEmailVerification(['id' => $id, 'first_name' => $first_name, 'email' => $email]);
      $_SESSION['user']['email_verified'] = false;

      $this->userModel->logAudit('email_changed', $id, $username, $role, 'user', $id, 'Email changed, re-verification sent');

      $message = $verificationSent
        ? 'Account updated successfully! A verification link was sent to your email.'
        : 'Account updated successfully! Use "Verify email" to get a verification link for your new email.';
    }

    if ($profileWasIncomplete) {
      $_SESSION['flash_success'] = $message;
    }

    $this->jsonResponse([
      'message'  => $message,
      'redirect' => $profileWasIncomplete ? ROOT . '/home' : null,
      'user'     => [
        'first_name'     => $first_name,
        'last_name'      => $last_name,
        'username'       => $username,
        'email'          => $email,
        'email_verified' => !empty($_SESSION['user']['email_verified']),
      ],
    ]);
  }

  // ===== DELETE ACCOUNT =====
  public function delete()
  {
    $this->requireLogin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->redirect('user/account');
    }

    $this->verifyCsrfToken();

    $id       = (int) $_SESSION['user']['user_id'];
    $username = $_SESSION['user']['username'] ?? '';
    $role     = $_SESSION['user']['role'] ?? '';

    $account = $this->userModel->getUser($id);
    if (!$account || !password_verify($_POST['password'] ?? '', $account['password'])) {
      $this->userModel->logAudit('account_delete_denied', $id, $username, $role, 'user', $id, 'Incorrect password entered while attempting to delete own account');
      $_SESSION['flash_error'] = 'Incorrect password. Account not deleted.';
      $this->redirect('user/account');
    }

    if ($role === 'researcher') {
      $protocolModel = new ProtocolModel();

      if ($protocolModel->hasActiveProtocols($id)) {
        $_SESSION['flash_error'] = 'You cannot delete your account while you have protocols that are still being processed.';
        $this->redirect('user/account');
      }

      $protocolModel->softDeleteAllForUser($id, 'Deleted User', 'Account deleted by owner');

      (new DraftModel())->clear($id);
      $draftDir = dirname(__DIR__, 2) . '/storage/uploads/drafts/' . $id . '/';
      if (is_dir($draftDir)) {
        $this->rrmdir($draftDir);
      }
    } elseif ($role === 'staff' && $this->userModel->isLastActiveStaff($id)) {
      $_SESSION['flash_error'] = 'You are the only active Administrative Staff account. Give another account the staff role before deleting yours.';
      $this->redirect('user/account');
    }

    $ok = $this->userModel->deleteUser($id);

    if ($ok) {
      $this->userModel->logAudit('account_deleted', $id, $username, $role, 'user', $id, 'User deleted their account');
      Mailer::sendTemplate('account_deleted', ['first_name' => $account['first_name'], 'deleted_by_admin' => false], $account['email'], $account['first_name'], 'Account Deleted');

      session_destroy();
      session_start();
      $_SESSION['flash_success'] = 'Your account has been deleted.';
      $this->redirect($role === 'researcher' ? 'user/login' : 'personnel/login');
    }

    $_SESSION['flash_error'] = 'Account deletion failed. Please try again or contact support.';
    $this->redirect('user/account');
  }

  // ===== FOLDER CLEANUP HELPER =====
  private function rrmdir(string $dir): void
  {
    $items = @scandir($dir);
    if ($items === false) {
      return;
    }

    foreach ($items as $item) {
      if ($item === '.' || $item === '..') {
        continue;
      }
      $path = $dir . $item;
      if (is_dir($path)) {
        $this->rrmdir($path);
      } else {
        @unlink($path);
      }
    }

    @rmdir($dir);
  }

  // ===== FORGOT / RESET PASSWORD =====
  public function forgot_password(): void
  {
    if ($this->isLoggedIn()) {
      $this->redirect('submissions');
    }

    $this->handleForgotPassword(
      'user/forgot_password',
      'user/reset_password',
      'user/login'
    );
  }

  public function reset_password(): void
  {
    $this->handleResetPassword(
      'user/reset_password',
      'user/login'
    );
  }

  // ===== EMAIL VERIFICATION =====
  public function verify_email(): void
  {
    $token  = $_GET['token'] ?? '';
    $record = $this->userModel->getEmailVerification($token);

    if (!$record) {
      $_SESSION['flash_error'] = 'This verification link is invalid or has expired.';
      $this->redirect($this->isLoggedIn() ? 'user/account' : 'user/login');
      return;
    }

    $userId = (int) $record['user_id'];

    $this->userModel->markEmailVerified($userId);
    $this->userModel->markEmailVerificationUsed($token);
    $this->userModel->logAudit('email_verified', $userId, '', '', 'user', $userId, 'Email verified');

    $verified = $this->userModel->getUser($userId);
    $isSelf   = $this->isLoggedIn() && (int) $_SESSION['user']['user_id'] === $userId;
    if ($isSelf && !empty($verified['sex']) && !empty($verified['researcher_type'])) {
      $this->showWelcomeOnce($verified);
    }

    // The browser that registered (or was blocked at login) is signed in as soon as the link is opened
    if (!$this->isLoggedIn() && (int) ($_SESSION['pending_verification'] ?? 0) === $userId && $verified['role'] === 'researcher') {
      unset($_SESSION['pending_verification']);
      $this->startResearcherSession($verified);
    }

    // The tab that asked for the link reloads itself and shows this message
    $_SESSION['flash_success'] = $this->isLoggedIn() ? 'Your email has been verified!' : 'Your email has been verified! You can now log in.';
    $home = ($_SESSION['user']['role'] ?? '') === 'researcher' ? 'home' : 'personnel/home';

    $this->view('user/email_verified', [
      'user'        => null,
      'continueUrl' => ROOT . '/' . ($this->isLoggedIn() ? $home : 'user/login'),
    ]);
  }

  // Polled by the tab waiting on a verification link (signed in, or blocked at login until verified)
  public function verification_status(): void
  {
    $id   = (int) ($_SESSION['user']['user_id'] ?? $_SESSION['pending_verification'] ?? 0);
    $user = $id ? $this->userModel->getUser($id) : null;
    $ok   = !empty($user['email_verified']);

    // Verified on another device or browser: this one is the one that registered, so it signs in here
    if ($ok && !$this->isLoggedIn()) {
      unset($_SESSION['pending_verification']);
      if ($user['role'] === 'researcher') {
        $this->startResearcherSession($user);
        $_SESSION['flash_success'] = 'Your email has been verified!';
      }
    }

    $this->jsonResponse(['verified' => $ok]);
  }

  public function resend_verification(): void
  {
    if ($this->isLoggedIn()) {
      $this->requireLogin();
    } elseif (empty($_SESSION['pending_verification'])) {
      $this->redirect('user/login');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->redirect($this->isLoggedIn() ? 'user/account' : 'user/login');
    }

    $this->verifyCsrfToken();

    $id   = (int) ($_SESSION['user']['user_id'] ?? $_SESSION['pending_verification']);
    $user = $this->userModel->getUser($id);

    if (!$user) {
      unset($_SESSION['pending_verification']);
      $this->redirect('user/login');
    }

    if (!empty($user['email_verified'])) {
      unset($_SESSION['pending_verification']);
      $this->verificationResult(true, 'Your email is already verified.');
    }

    // The account panel's verify step lets people correct the address first, so a new one is saved before the link goes out
    $email        = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $emailChanged = $email !== '' && strcasecmp($email, $user['email']) !== 0;

    if ($emailChanged) {
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->verificationResult(false, 'A valid email address is required.', 422);
      }
      if ($this->userModel->isEmailTakenByAnother($email, $id)) {
        $this->verificationResult(false, 'That email is already taken.', 422);
      }

      $this->userModel->updateEmail($id, $email);
      $this->userModel->logAudit('email_changed', $id, $user['username'], $user['role'], 'user', $id, 'Email changed, re-verification sent');
      if ($this->isLoggedIn()) {
        $_SESSION['user']['email'] = $email;
      }
      $user['email'] = $email;
    }

    // The cooldown only guards repeat sends to the same address
    $cooldown  = 45;
    $elapsed   = $this->userModel->secondsSinceLastVerificationEmail($id);

    if (!$emailChanged && $elapsed !== null && $elapsed < $cooldown) {
      $this->verificationResult(false, 'Please wait ' . ($cooldown - $elapsed) . ' seconds before requesting another verification link.', 429);
    }

    if ($this->sendEmailVerification($user)) {
      $this->verificationResult(true, 'Verification link sent to your email. Please check your inbox.');
    }

    $this->verificationResult(false, $emailChanged
      ? 'Your email was saved, but we could not send the verification link. Please try again.'
      : 'Could not send the verification link. Please try again later.', 500);
  }

  // Answers fetch requests with JSON and normal form posts with a flash message and a redirect back
  private function verificationResult(bool $ok, string $message, int $errorCode = 400): void
  {
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
      $this->jsonResponse($ok ? ['message' => $message] : ['error' => $message], $ok ? 200 : $errorCode);
    }

    $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $message;
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? (ROOT . ($this->isLoggedIn() ? '/user/account' : '/user/login'))));
    exit;
  }
}
