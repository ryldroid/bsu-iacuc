<?php

class Controller
{
  public function view(string $name, array $data = []): void
  {
    $filename = VIEWSPATH . $name . '.view.php';

    if (file_exists($filename)) {
      $data['user'] = $_SESSION['user'] ?? null;
      extract($data);
      require_once $filename;
    } else {
      $this->renderError(404, 'Page Not Found', [
        'Page could not be found. It may have been removed, had its name changed, or is temporarily unavailable.',
      ]);
    }
  }

  public function redirect(string $path): void
  {
    header("Location: " . ROOT . "/" . $path);
    exit;
  }

  protected function renderError(
    int $code,
    string $heading,
    array $lines = [],
    array $actions = []
  ): void {
    ErrorPage::render($code, $heading, $lines, $actions);
  }

  protected function actor(): array
  {
    return [
      'id'   => (int) ($_SESSION['user']['user_id'] ?? 0),
      'name' => $_SESSION['user']['username'] ?? '',
      'role' => $_SESSION['user']['role'] ?? '',
    ];
  }

  protected function jsonError(int $code, string $message): void
  {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message]);
    exit;
  }

  protected function verifyCsrfHeader(): void
  {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
      $this->jsonError(403, 'Invalid CSRF token.');
    }
  }

  protected function requirePostMethod(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->jsonError(405, 'Method not allowed.');
    }
  }

  protected function isLoggedIn()
  {
    return isset($_SESSION['user']['user_id']);
  }

  protected function requireLogin()
  {
    if (!$this->isLoggedIn()) {
      $this->redirect('users/login');
    }

    require_once dirname(__DIR__) . '/models/UserModel.php';
    $userModel = new UserModel();
    $fresh = $userModel->getUser($_SESSION['user']['user_id']);

    if ($fresh && $fresh['role'] !== $_SESSION['user']['role']) {
      $_SESSION['user']['role'] = $fresh['role'];
    }
    if ($fresh) {
      $_SESSION['user']['email_verified'] = (bool) $fresh['email_verified'];
      $_SESSION['user']['email']          = $fresh['email'];
    }
  }

  protected function generateCsrfToken(): string
  {
    if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
  }

  protected function verifyCsrfToken(bool $rotateAfter = false): void
  {
    $token        = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $expired      = $sessionToken === '';

    if (!hash_equals($sessionToken, $token)) {
      $wantsJson = isset($_SERVER['HTTP_ACCEPT']) &&
        str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

      if ($wantsJson) {
        http_response_code($expired ? 401 : 403);
        header('Content-Type: application/json');
        echo json_encode([
          'error' => $expired
            ? 'Your session has expired. Please reload the page and try again.'
            : 'Invalid or missing CSRF token.',
          'code' => $expired ? 401 : 403,
        ]);
        exit;
      }

      if ($expired) {
        $_SESSION['flash_error'] = 'Your session expired due to inactivity. Please try again.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? (ROOT . '/')));
        exit;
      }

      $this->renderError(403, '403 - Forbidden', [
        'Invalid or missing security token. Please reload the page and try again.',
      ], [
        ['label' => '← Go Back', 'href' => 'javascript:history.back()'],
      ]);
      exit;
    }

    if ($rotateAfter) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
  }

  protected function validatePasswordRequirements(string $password): array
  {
    $missing = [];
    if (strlen($password) < 8)                   $missing[] = 'at least 8 characters';
    if (!preg_match('/[A-Z]/', $password))        $missing[] = 'an uppercase letter';
    if (!preg_match('/[a-z]/', $password))        $missing[] = 'a lowercase letter';
    if (!preg_match('/[0-9]/', $password))        $missing[] = 'a number';
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) $missing[] = 'a special character';
    return $missing;
  }

  protected function handleForgotPassword(string $forgotRoute, string $resetRoute, string $loginRoute): void
  {
    require_once dirname(__DIR__) . '/models/UserModel.php';
    $userModel = new UserModel();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      $this->view('users/forgot_password', [
        'csrf'  => $this->generateCsrfToken(),
        'route' => $forgotRoute,
      ]);
      return;
    }

    $this->verifyCsrfToken();
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);

    $success = 'Please check your email inbox and click on the link to reset your password.';

    $user = $userModel->getUserByEmail($email);
    if ($user) {
      $token = bin2hex(random_bytes(32));
      $userModel->createPasswordReset((int) $user['id'], $token);

      $reset_url = ROOT . '/' . $resetRoute . '?token=' . $token;

      Mailer::sendTemplate('password_reset', [
        'first_name' => $user['first_name'],
        'reset_url'  => $reset_url,
      ], $user['email'], $user['first_name'], 'Password Reset');
    }

    $this->view('users/forgot_password', [
      'csrf'    => $this->generateCsrfToken(),
      'route'   => $forgotRoute,
      'success' => $success,
    ]);
  }

  protected function handleResetPassword(string $resetRoute, string $loginRoute): void
  {
    require_once dirname(__DIR__) . '/models/UserModel.php';
    $userModel = new UserModel();

    $token = $_GET['token'] ?? $_POST['token'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      $reset = $userModel->getPasswordReset($token);

      if (!$reset) {
        $this->view('users/reset_password', [
          'csrf'   => $this->generateCsrfToken(),
          'token'  => '',
          'route'  => $resetRoute,
          'errors' => ['This reset link is invalid or has expired.'],
        ]);
        return;
      }

      $this->view('users/reset_password', [
        'csrf'   => $this->generateCsrfToken(),
        'token'  => $token,
        'route'  => $resetRoute,
        'errors' => [],
      ]);
      return;
    }

    $this->verifyCsrfToken();

    $reset = $userModel->getPasswordReset($token);
    if (!$reset) {
      $this->view('users/reset_password', [
        'csrf'   => $this->generateCsrfToken(),
        'token'  => '',
        'route'  => $resetRoute,
        'errors' => ['This reset link is invalid or has expired.'],
      ]);
      return;
    }

    $password     = $_POST['password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';
    $errors       = [];

    $pw_reqs = $this->validatePasswordRequirements($password);
    if (!empty($pw_reqs)) {
      $errors[] = 'Password must contain: ' . implode(', ', $pw_reqs) . '.';
    }
    if ($password !== $confirm_pass) {
      $errors[] = 'Passwords do not match.';
    }

    if (!empty($errors)) {
      $this->view('users/reset_password', [
        'csrf'   => $this->generateCsrfToken(),
        'token'  => $token,
        'route'  => $resetRoute,
        'errors' => $errors,
      ]);
      return;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ok   = $userModel->updatePassword((int) $reset['user_id'], $hash);

    if (!$ok) {
      $this->view('users/reset_password', [
        'csrf'   => $this->generateCsrfToken(),
        'token'  => $token,
        'route'  => $resetRoute,
        'errors' => ['Something went wrong resetting your password. Please try again.'],
      ]);
      return;
    }

    $userModel->markResetUsed($token);

    $_SESSION['flash_success'] = 'Password reset successfully. Please log in.';
    $this->redirect($loginRoute);
  }

  protected function sendEmailVerification(array $user): void
  {
    require_once dirname(__DIR__) . '/models/UserModel.php';
    $userModel = new UserModel();

    $token = bin2hex(random_bytes(32));
    $userModel->createEmailVerification((int) $user['id'], $token);

    $verify_url = ROOT . '/users/verify_email?token=' . $token;

    Mailer::sendTemplate('verify_email', [
      'first_name' => $user['first_name'],
      'verify_url' => $verify_url,
    ], $user['email'], $user['first_name'], 'Verify Your Email');
  }
}
