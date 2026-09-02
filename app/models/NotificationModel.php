<?php

require_once dirname(__DIR__) . '/core/Model.php';

class NotificationModel extends Model
{
  public function create(int $userId, string $type, string $title, string $message = '', ?string $link = null): int | false
  {
    $stmt = $this->connection->prepare(
      "INSERT INTO `notifications` (user_id, type, title, message, link)
             VALUES (?, ?, ?, ?, ?)"
    );
    if (! $stmt) {
      return false;
    }

    $stmt->bind_param('issss', $userId, $type, $title, $message, $link);
    if (! $stmt->execute()) {
      return false;
    }

    return $this->connection->insert_id;
  }

  public function createForRole(string $role, string $type, string $title, string $message = '', ?string $link = null): array
  {
    $stmt = $this->connection->prepare(
      "SELECT id, email, first_name, email_verified FROM `users` WHERE role = ? AND status = 'active'"
    );
    if (! $stmt) {
      return [];
    }

    $stmt->bind_param('s', $role);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($users as $u) {
      $this->create((int) $u['id'], $type, $title, $message, $link);
    }

    return $users;
  }

  public function getForUser(int $userId, int $limit = 20): array
  {
    $stmt = $this->connection->prepare(
      "SELECT id, type, title, message, link, is_read, created_at
             FROM `notifications`
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ?"
    );
    if (! $stmt) {
      return [];
    }

    $stmt->bind_param('ii', $userId, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  public function getUnreadCount(int $userId): int
  {
    $stmt = $this->connection->prepare(
      "SELECT COUNT(*) AS c FROM `notifications` WHERE user_id = ? AND is_read = 0"
    );
    if (! $stmt) {
      return 0;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['c'];
  }

  public function markRead(int $id, int $userId): bool
  {
    $stmt = $this->connection->prepare(
      "UPDATE `notifications` SET is_read = 1 WHERE id = ? AND user_id = ?"
    );
    if (! $stmt) {
      return false;
    }

    $stmt->bind_param('ii', $id, $userId);
    return $stmt->execute();
  }

  public function markAllRead(int $userId): bool
  {
    $stmt = $this->connection->prepare(
      "UPDATE `notifications` SET is_read = 1 WHERE user_id = ? AND is_read = 0"
    );
    if (! $stmt) {
      return false;
    }

    $stmt->bind_param('i', $userId);
    return $stmt->execute();
  }
}
