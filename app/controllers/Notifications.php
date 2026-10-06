<?php

class Notifications extends Controller
{
  private const HIGHLIGHT_TYPES = [
    'protocol_renamed',
    'signed_scan_uploaded',
    'protocol_status_under_review',
    'protocol_status_reviewed',
    'protocol_status_endorsed',
    'protocol_status_approved',
  ];

  private NotificationModel $notificationModel;

  // ===== SETUP =====
  public function __construct()
  {
    parent::__construct();

    $this->notificationModel = new NotificationModel();
  }

  // ===== NOTIFICATIONS PAGE =====
  public function index(): void
  {
    $this->requireLogin();
    header('Content-Type: application/json');

    $userId = (int) $_SESSION['user']['user_id'];

    echo json_encode([
      'unread_count' => $this->notificationModel->getUnreadCount($userId),
      'items'        => $this->withHighlightLinks($this->notificationModel->getForUser($userId)),
    ]);
    exit;
  }

  // ===== LOAD MORE (PAGINATION) =====
  public function page(): void
  {
    $this->requireLogin();

    $userId  = (int) $_SESSION['user']['user_id'];
    $perPage = 20;
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $offset  = ($page - 1) * $perPage;

    $total      = $this->notificationModel->countForUser($userId);
    $items      = $this->withHighlightLinks($this->notificationModel->getForUserPaginated($userId, $perPage, $offset));
    $totalPages = max(1, (int) ceil($total / $perPage));

    $this->view('notifications', [
      'user'       => $_SESSION['user'],
      'csrf'       => $this->generateCsrfToken(),
      'items'      => $items,
      'total'      => $total,
      'page'       => $page,
      'totalPages' => $totalPages,
      'perPage'    => $perPage,
    ]);
  }

  // ===== HIGHLIGHT LINKS =====
  private function withHighlightLinks(array $items): array
  {
    if ($this->isPersonnel()) {
      return $items;
    }

    return array_map(function (array $item): array {
      if (in_array($item['type'], self::HIGHLIGHT_TYPES, true) && preg_match('#^apply/viewer/(\d+)$#', (string) $item['link'], $match)) {
        $item['link'] = 'submissions?highlight=' . $match[1];
      }
      return $item;
    }, $items);
  }

  // ===== MARK AS READ =====
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
    $ok     = $this->notificationModel->markRead($id, $userId);

    echo json_encode(['ok' => $ok]);
    exit;
  }

  // ===== MARK ALL AS READ =====
  public function markallread(): void
  {
    $this->requireLogin();
    header('Content-Type: application/json');
    $this->requirePostMethod();
    $this->verifyCsrfHeader();

    $userId = (int) $_SESSION['user']['user_id'];
    $ok     = $this->notificationModel->markAllRead($userId);

    echo json_encode(['ok' => $ok]);
    exit;
  }
}
