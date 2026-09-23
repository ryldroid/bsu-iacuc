<?php

class Webhooks extends Controller
{
  public UserModel $model;

  public function __construct()
  {
    require_once "../app/models/UserModel.php";
    $this->model = new UserModel();
  }

  // POST /webhooks/brevo
  // Configure this exact URL as the webhook target for the "unsubscribed"
  // transactional event in the Brevo dashboard, with Token auth (Bearer).
  public function brevo(): void
  {
    // TEMP DEBUG: unconditionally record every request that reaches this
    // method, before any other logic runs, so we can see if this code is
    // even executing. Web-readable at /portal/webhook_debug.txt (no secret
    // values are written). Remove this block (and delete the file) once done.
    $authHeaderPeek = $_SERVER['HTTP_AUTHORIZATION']
      ?? (function_exists('getallheaders') ? (getallheaders()['Authorization'] ?? '') : '');
    @file_put_contents(
      __DIR__ . '/../../portal/webhook_debug.txt',
      date('c') . ' | method=' . ($_SERVER['REQUEST_METHOD'] ?? '?') .
        ' | authHeaderPresent=' . ($authHeaderPeek !== '' ? 'yes' : 'no') .
        ' | authHeaderLen=' . strlen($authHeaderPeek) .
        ' | contentType=' . ($_SERVER['CONTENT_TYPE'] ?? '?') . PHP_EOL,
      FILE_APPEND
    );

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->jsonError(405, 'Method not allowed.');
    }

    $authHeader = $authHeaderPeek;
    $token = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';

    if (empty(BREVO_WEBHOOK_SECRET) || !hash_equals(BREVO_WEBHOOK_SECRET, $token)) {
      $this->jsonError(401, 'Invalid webhook secret.');
    }

    $raw     = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    @file_put_contents(
      __DIR__ . '/../../portal/webhook_debug.txt',
      date('c') . ' | AUTH OK | event=' . ($payload['event'] ?? '?') .
        ' | email=' . ($payload['email'] ?? '?') . PHP_EOL,
      FILE_APPEND
    );

    if (!is_array($payload)) {
      $this->jsonError(400, 'Invalid payload.');
    }

    $event = $payload['event'] ?? '';
    $email = $payload['email'] ?? '';

    if ($event !== 'unsubscribed' || empty($email)) {
      // Not an event we act on (or Brevo is sending us something unexpected).
      // Acknowledge with 200 so Brevo doesn't keep retrying.
      http_response_code(200);
      header('Content-Type: application/json');
      echo json_encode(['ok' => true, 'ignored' => true]);
      return;
    }

    $user = $this->model->getUserByEmail($email);

    if ($user) {
      $this->model->markEmailProviderUnsubscribed((int) $user['id']);
      $this->model->logAudit(
        'email_provider_unsubscribed',
        null,
        'Brevo',
        'system',
        'user',
        (int) $user['id'],
        'Brevo reported an unsubscribe for this address; email notifications turned off automatically.'
      );
    }

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
  }
}
