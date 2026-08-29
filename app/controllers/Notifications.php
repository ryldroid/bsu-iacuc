<?php

class Notifications extends Controller
{
  public NotificationModel $model;

  public function __construct()
  {
    require_once "../app/models/NotificationModel.php";
    $this->model = new NotificationModel();
  }

  public function index(): void
  {
    $this->requireLogin();
    header('Content-Type: application/json');

    $userId = (int) $_SESSION['user']['user_id'];

    echo json_encode([
      'unread_count' => $this->model->getUnreadCount($userId),
      'items'        => $this->model->getForUser($userId),
    ]);
    exit;
  }

  public function markread(): void
  {
    $this->requireLogin();
    header('Content-Type: application/json');
    $this->requirePostMethod();
    $this->verifyCsrfHeader();

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int) ($body['id'] ?? 0);

    if ($id < 1) {
      $this->jsonError(400, 'Missing notification id.');
    }

    $userId = (int) $_SESSION['user']['user_id'];
    $ok     = $this->model->markRead($id, $userId);

    echo json_encode(['ok' => $ok]);
    exit;
  }

  public function markallread(): void
  {
    $this->requireLogin();
    header('Content-Type: application/json');
    $this->requirePostMethod();
    $this->verifyCsrfHeader();

    $userId = (int) $_SESSION['user']['user_id'];
    $ok     = $this->model->markAllRead($userId);

    echo json_encode(['ok' => $ok]);
    exit;
  }
}
