<?php

class Webhooks extends Controller
{
  // ===== BREVO UNSUBSCRIBE WEBHOOK =====
  public function brevo(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->jsonError(405, 'Method not allowed.');
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? (getallheaders()['Authorization'] ?? '');
    $token = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';
    if (empty(BREVO_WEBHOOK_SECRET) || !hash_equals(BREVO_WEBHOOK_SECRET, $token)) {
      $this->jsonError(401, 'Invalid webhook secret.');
    }

    $raw     = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    if (!is_array($payload)) {
      $this->jsonError(400, 'Invalid payload.');
    }

    $event = $payload['event'] ?? '';
    $email = $payload['email'] ?? '';

    if (!isset(self::EMAIL_STOP_NOTICES[$event]) || empty($email)) {
      http_response_code(200);
      header('Content-Type: application/json');
      echo json_encode(['ok' => true, 'ignored' => true]);
      return;
    }

    $user = $this->userModel->getUserByEmail($email);

    if ($user) {
      $this->stopEmailUpdates($user, $event);
    }

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
  }

  // ===== CLEARANCE EXPIRY SWEEP (EXTERNAL PINGER) =====
  public function expiry(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->jsonError(405, 'Method not allowed.');
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? (getallheaders()['Authorization'] ?? '');
    $token      = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';

    if (strlen(EXPIRY_CRON_SECRET) < 32 || !hash_equals(EXPIRY_CRON_SECRET, $token)) {
      $this->jsonError(401, 'Invalid expiry secret.');
    }

    $recordModel = new RecordModel();

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
      'ok'          => true,
      'warned'      => $recordModel->sendExpiryWarnings(),
      'deactivated' => $recordModel->runExpiryDeactivationSweep(),
    ]);
  }
}
