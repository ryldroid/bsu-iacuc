<?php
// NEW FILE SPM - CRUD for admin-managed "From Our Office" announcements

require_once dirname(__DIR__) . '/core/Model.php';

class AnnouncementModel extends Model
{
  // Get all announcements, newest first (used on the public Announcements page)
  public function getAll(): array
  {
    $result = $this->connection->query(
      "SELECT * FROM `announcements` ORDER BY created_at DESC"
    );
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
  }

  public function getById(int $id): ?array
  {
    $stmt = $this->connection->prepare("SELECT * FROM `announcements` WHERE id = ?");
    if (! $stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }

  public function exists(int $id): bool
  {
    $stmt = $this->connection->prepare("SELECT 1 FROM `announcements` WHERE id = ?");
    if (! $stmt) return false;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_row();
  }

  public function insert(string $title, string $body, ?int $postedBy): bool
  {
    $stmt = $this->connection->prepare(
      "INSERT INTO `announcements` (title, body, posted_by) VALUES (?, ?, ?)"
    );
    if (! $stmt) return false;
    $stmt->bind_param('ssi', $title, $body, $postedBy);
    return $stmt->execute();
  }

  public function update(int $id, string $title, string $body): bool
  {
    $stmt = $this->connection->prepare(
      "UPDATE `announcements` SET title = ?, body = ? WHERE id = ?"
    );
    if (! $stmt) return false;
    $stmt->bind_param('ssi', $title, $body, $id);
    return $stmt->execute();
  }

  public function delete(int $id): bool
  {
    $stmt = $this->connection->prepare("DELETE FROM `announcements` WHERE id = ?");
    if (! $stmt) return false;
    $stmt->bind_param('i', $id);
    return $stmt->execute();
  }
}
// END NEW FILE
