<?php
// NEW FILE - CRUD for staff-managed homepage FAQ entries

require_once dirname(__DIR__) . '/core/Model.php';

class FaqModel extends Model
{
  // Get all FAQ entries, in display order (used on the public homepage)
  public function getAll(): array
  {
    $result = $this->connection->query(
      "SELECT * FROM `faqs` ORDER BY sort_order ASC, id ASC"
    );
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
  }

  public function getById(int $id): ?array
  {
    $stmt = $this->connection->prepare("SELECT * FROM `faqs` WHERE id = ?");
    if (! $stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }

  public function insert(int $sortOrder, string $question, string $answer): bool
  {
    $stmt = $this->connection->prepare(
      "INSERT INTO `faqs` (sort_order, question, answer) VALUES (?, ?, ?)"
    );
    if (! $stmt) return false;
    $stmt->bind_param('iss', $sortOrder, $question, $answer);
    return $stmt->execute();
  }

  public function update(int $id, int $sortOrder, string $question, string $answer): bool
  {
    $stmt = $this->connection->prepare(
      "UPDATE `faqs` SET sort_order = ?, question = ?, answer = ? WHERE id = ?"
    );
    if (! $stmt) return false;
    $stmt->bind_param('issi', $sortOrder, $question, $answer, $id);
    return $stmt->execute();
  }

  public function delete(int $id): bool
  {
    $stmt = $this->connection->prepare("DELETE FROM `faqs` WHERE id = ?");
    if (! $stmt) return false;
    $stmt->bind_param('i', $id);
    return $stmt->execute();
  }
}
