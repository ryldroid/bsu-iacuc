<?php

class Users extends Controller
{
  public UserModel $model;

  public function __construct()
  {
    require_once "../app/models/UserModel.php";
    $this->model = new UserModel();
  }

  private function sanitizeInputs(array $post): array
  {
    return [
      'username'     => trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $post['username'] ?? '')),
      'first_name'   => ucfirst(mb_strtolower(trim(preg_replace('/[^\p{L}\p{M}\s\-\']/u', '', $post['first_name'] ?? '')))),
      'last_name'    => trim(preg_replace('/[^\p{L}\p{M}\s\-\']/u', '', $post['last_name'] ?? '')),
      'email'        => filter_var(trim($post['email'] ?? ''), FILTER_SANITIZE_EMAIL),
      'phone_number' => trim(preg_replace('/[^0-9+]/', '', $post['phone_number'] ?? '')),
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
    bool $password_required = true
  ): array {
    $errors = [];

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
    } elseif (!preg_match('/^\+63\d{9,10}$/', $phone_number)) {
      $errors[] = 'Enter a valid Philippine phone number (e.g., +63xxxxxxxxxx).';
    }

    if ($password_required || !empty($password) || !empty($confirm_pass)) {
      $pw_reqs = $this->validatePasswordRequirements($password);
      if (!empty($pw_reqs)) {
        $errors[] = 'Password must contain: ' . implode(', ', $pw_reqs) . '.';
      }
      if ($password !== $confirm_pass) {
        $errors[] = 'Passwords do not match.';
      }
    }

    $valid_roles = ['researcher', 'admin', 'reviewer'];
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

    $this->view('users/login', [
      'prefill' => $prefill,
      'error'   => $_SESSION['flash_error'] ?? '',
      'csrf'    => $this->generateCsrfToken(),
    ]);
    unset($_SESSION['flash_error']);
  }

  public function login_process()
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->redirect('users/login');
    }

    $this->verifyCsrfToken(true);

    $input    = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $MAX_ATTEMPTS = 5;
    $WINDOW       = 900;

    $ipAttempts   = $this->model->countRecentAttempts($ip, $WINDOW);
    $userAttempts = $this->model->countRecentAttempts($input, $WINDOW);

    if ($ipAttempts >= $MAX_ATTEMPTS || $userAttempts >= $MAX_ATTEMPTS) {
      $_SESSION['flash_error'] = 'Too many failed login attempts. Please wait 15 minutes before trying again.';
      $this->redirect('users/login');
    }

    if (empty($input) || empty($password)) {
      $_SESSION['flash_error'] = 'Please fill in all fields.';
      $this->redirect('users/login');
    }

    $user = $this->model->getUserByUsername($input)
      ?? $this->model->getUserByEmail($input);

    if (!$user) {
      $this->model->recordLoginAttempt($ip);
      $this->model->recordLoginAttempt($input);
      $_SESSION['flash_error'] = 'No researcher account found with that username or email.';
      $this->redirect('users/login');
    }

    if (!password_verify($password, $user['password'])) {
      $this->model->recordLoginAttempt($ip);
      $this->model->recordLoginAttempt($input);
      $_SESSION['flash_error'] = 'Invalid password. Please try again.';

      $this->model->logAudit(
        event: 'login_failed',
        actorId: null,
        actorUsername: $input,
        actorRole: '',
        targetType: '',
        targetId: null,
        description: 'Failed login attempt (wrong password)'
      );

      $this->redirect('users/login');
    }

    $this->model->clearLoginAttempts($ip);
    $this->model->clearLoginAttempts($input);

    if ($user['role'] !== 'researcher') {
      $_SESSION['flash_error'] = 'No researcher account found with that username or email.';
      $this->redirect('users/login');
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
      'user_id'        => $user['id'],
      'first_name'     => $user['first_name'],
      'username'       => $user['username'],
      'role'           => $user['role'],
      'email_verified' => (bool) $user['email_verified'],
    ];

    $this->model->logAudit(
      event: 'login_success',
      actorId: (int)$user['id'],
      actorUsername: $user['username'],
      actorRole: $user['role'],
      targetType: 'user',
      targetId: (int)$user['id'],
      description: 'Researcher logged in'
    );

    $this->redirect('submissions');
  }

  // ===== REGISTER =====

  public function register()
  {
    if ($this->isLoggedIn()) {
      $this->redirect('submissions');
    }

    $this->view('users/register', [
      'errors' => [],
      'old'    => [],
      'csrf'   => $this->generateCsrfToken(),
    ]);
  }

  public function register_process()
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->redirect('users/register');
    }

    $this->verifyCsrfToken();

    extract($this->sanitizeInputs($_POST));
    $old = compact('username', 'first_name', 'last_name', 'email', 'phone_number');

    $errors = $this->validateUserFields($username, $first_name, $last_name, $email, $phone_number, $password, $confirm_pass, 'researcher');

    if (empty($errors)) {
      if ($this->model->getUserByEmail($email)) {
        $errors[] = 'That email is already taken.';
      }
      if ($this->model->getUserByUsername($username)) {
        $errors[] = 'That username is already taken.';
      }
    }

    if (!empty($errors)) {
      $this->view('users/register', [
        'errors' => $errors,
        'old'    => $old,
        'csrf'   => $this->generateCsrfToken(),
      ]);
      return;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ok   = $this->model->insertUser($username, $first_name, $last_name, $email, $hash, 'researcher', 'active', $phone_number);

    if ($ok) {
      $this->sendEmailVerification([
        'id'         => $this->model->connection->insert_id,
        'first_name' => $first_name,
        'email'      => $email,
      ]);

      $_SESSION['new_username'] = $username;
      $this->view('users/register', [
        'errors'  => [],
        'old'     => [],
        'csrf'    => $this->generateCsrfToken(),
        'success' => true,
      ]);
    } else {
      $errors[] = 'Registration failed. Please try again.';
      $this->view('users/register', [
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

    $this->model->logAudit('logout', $uid, $uname, $urole, 'user', $uid, 'User logged out');

    session_destroy();
    session_start();
    $this->redirect('home');
  }

  // ===== MANAGE ACCOUNT =====

  public function account()
  {
    $this->requireLogin();

    $user = $this->model->getUser($_SESSION['user']['user_id']);

    $certificate = ($user['role'] ?? '') === 'researcher'
      ? $this->model->getCert((int) $user['id'])
      : null;

    $this->view('users/account', [
      'csrf'           => $this->generateCsrfToken(),
      'errors'         => !empty($_SESSION['flash_error']) ? [$_SESSION['flash_error']] : [],
      'certificate'    => $certificate,
      'email_verified' => (bool) $user['email_verified'],
      'old'         => [
        'first_name'   => $user['first_name'],
        'last_name'    => $user['last_name'],
        'username'     => $user['username'],
        'email'        => $user['email'],
        'phone_number' => $user['phone_number'] ?? '+63',
        'role'         => $user['role'],
      ],
    ]);
    unset($_SESSION['flash_error']);
  }

  public function update()
  {
    $this->requireLogin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->redirect('users/account');
    }

    $this->verifyCsrfToken();

    $id = (int) $_SESSION['user']['user_id'];

    extract($this->sanitizeInputs($_POST));

    $current_user = $this->model->getUser($id);

    if ($current_user['role'] === 'researcher') {
      $role = $current_user['role'];
    } else {
      $role = in_array($role, ['admin', 'reviewer']) ? $role : $current_user['role'];
    }

    $old = compact('username', 'first_name', 'last_name', 'email', 'phone_number', 'role');

    $errors = $this->validateUserFields($username, $first_name, $last_name, $email, $phone_number, $password, $confirm_pass, $role, false);

    if (empty($errors)) {
      $existing_email    = $this->model->getUserByEmail($email);
      $existing_username = $this->model->getUserByUsername($username);

      if ($existing_email && (int) $existing_email['id'] !== $id) {
        $errors[] = 'That email is already taken.';
      }
      if ($existing_username && (int) $existing_username['id'] !== $id) {
        $errors[] = 'That username is already taken.';
      }
    }

    if (!empty($errors)) {
      $certificate = $current_user['role'] === 'researcher'
        ? $this->model->getCert($id)
        : null;

      $this->view('users/account', [
        'csrf'        => $this->generateCsrfToken(),
        'errors'      => $errors,
        'certificate' => $certificate,
        'old'         => $old,
      ]);
      return;
    }

    $input = compact('username', 'first_name', 'last_name', 'email', 'phone_number', 'role');
    if (!empty($password)) {
      $input['password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $ok = $this->model->updateUser($id, $input);

    if ($ok) {
      $_SESSION['user']['first_name'] = $first_name;
      $_SESSION['user']['username']   = $username;
      $_SESSION['user']['role'] = $role;

      if ($email !== $current_user['email']) {
        $this->model->markEmailUnverified($id);
        $this->sendEmailVerification(['id' => $id, 'first_name' => $first_name, 'email' => $email]);
        $_SESSION['user']['email_verified'] = false;

        $this->model->logAudit('email_changed', $id, $username, $role, 'user', $id, 'Email changed, re-verification sent');

        $_SESSION['flash_success'] = 'Account updated successfully! Verify your new email to keep getting email notifications.';
      } else {
        $_SESSION['flash_success'] = 'Account updated successfully!';
      }
    } else {
      $_SESSION['flash_error'] = 'Update failed. Please try again.';
    }

    $this->redirect('users/account');
  }

  public function delete()
  {
    $this->requireLogin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->redirect('users/account');
    }

    $this->verifyCsrfToken();

    $id = (int) $_SESSION['user']['user_id'];
    $ok = $this->model->deleteUser($id);

    if ($ok) {
      session_destroy();
      session_start();
      $_SESSION['flash_success'] = 'Your account has been deleted.';
    }

    $this->redirect('users/login');
  }

  public function forgot_password(): void
  {
    if ($this->isLoggedIn()) {
      $this->redirect('submissions');
    }

    $this->handleForgotPassword(
      'users/forgot_password',
      'users/reset_password',
      'users/login'
    );
  }

  public function reset_password(): void
  {
    $this->handleResetPassword(
      'users/reset_password',
      'users/login'
    );
  }

  public function verify_email(): void
  {
    $token  = $_GET['token'] ?? '';
    $record = $this->model->getEmailVerification($token);

    if (!$record) {
      $_SESSION['flash_error'] = 'This verification link is invalid or has expired.';
      $this->redirect($this->isLoggedIn() ? 'users/account' : 'users/login');
      return;
    }

    $userId = (int) $record['user_id'];

    $this->model->markEmailVerified($userId);
    $this->model->markEmailVerificationUsed($token);
    $this->model->logAudit('email_verified', $userId, '', '', 'user', $userId, 'Email verified');

    $_SESSION['flash_success'] = 'Your email has been verified!';
    $this->redirect($this->isLoggedIn() ? 'users/account' : 'users/login');
  }

  public function resend_verification(): void
  {
    $this->requireLogin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->redirect('users/account');
    }

    $this->verifyCsrfToken();

    $id   = (int) $_SESSION['user']['user_id'];
    $user = $this->model->getUser($id);

    $back = $_SERVER['HTTP_REFERER'] ?? (ROOT . '/users/account');

    if (!empty($user['email_verified'])) {
      header('Location: ' . $back);
      exit;
    }

    $cooldown  = 45;
    $elapsed   = $this->model->secondsSinceLastVerificationEmail($id);

    if ($elapsed !== null && $elapsed < $cooldown) {
      $_SESSION['flash_error'] = 'Please wait ' . ($cooldown - $elapsed) . ' seconds before requesting another verification link.';
      header('Location: ' . $back);
      exit;
    }

    $this->sendEmailVerification($user);
    $_SESSION['flash_success'] = 'Verification link sent to your email. Please check your inbox.';
    header('Location: ' . $back);
    exit;
  }
}
