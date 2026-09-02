<?php

require_once dirname(__DIR__) . '/core/Model.php';

class DraftModel extends Model
{
  // ===== GET DRAFT =====

  public function getByUser(int $userId): ?array
  {
    $stmt = $this->connection->prepare(
      "SELECT * FROM `protocol_drafts` WHERE user_id = ? LIMIT 1"
    );
    if (! $stmt) {
      return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
  }

  // ===== SAVE FIELDS =====

  public function saveFields(
    int $userId,
    int $step,
    bool $agreedTerms,
    bool $agreedPrivacy,
    ?bool $isPi,
    string $title
  ): bool {
    $title       = mb_substr(trim($title), 0, 255);
    $agreedT     = $agreedTerms ? 1 : 0;
    $agreedP     = $agreedPrivacy ? 1 : 0;
    $isPiVal     = $isPi === null ? null : ($isPi ? 1 : 0);

    $stmt = $this->connection->prepare(
      "INSERT INTO `protocol_drafts` (user_id, step, agreed_terms, agreed_privacy, is_pi, title)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                step = VALUES(step),
                agreed_terms = VALUES(agreed_terms),
                agreed_privacy = VALUES(agreed_privacy),
                is_pi = VALUES(is_pi),
                title = VALUES(title)"
    );
    if (! $stmt) {
      return false;
    }

    $stmt->bind_param('iiiiis', $userId, $step, $agreedT, $agreedP, $isPiVal, $title);
    return $stmt->execute();
  }

  // ===== SAVE FILE =====

  public function saveFile(int $userId, string $key, string $path, string $originalName): bool
  {
    if (! in_array($key, ['protocol', 'cert', 'auth'], true)) {
      return false;
    }

    $pathCol = $key . '_file_path';
    $nameCol = $key . '_file_name';

    $stmt = $this->connection->prepare(
      "INSERT INTO `protocol_drafts` (user_id, `$pathCol`, `$nameCol`)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                `$pathCol` = VALUES(`$pathCol`),
                `$nameCol` = VALUES(`$nameCol`)"
    );
    if (! $stmt) {
      return false;
    }

    $stmt->bind_param('iss', $userId, $path, $originalName);
    return $stmt->execute();
  }

  // ===== REMOVE FILE =====

  public function removeFile(int $userId, string $key): bool
  {
    if (! in_array($key, ['protocol', 'cert', 'auth'], true)) {
      return false;
    }

    $pathCol = $key . '_file_path';
    $nameCol = $key . '_file_name';

    $stmt = $this->connection->prepare(
      "UPDATE `protocol_drafts` SET `$pathCol` = NULL, `$nameCol` = NULL WHERE user_id = ?"
    );
    if (! $stmt) {
      return false;
    }

    $stmt->bind_param('i', $userId);
    return $stmt->execute();
  }

  // ===== CLEAR DRAFT =====

  public function clear(int $userId): bool
  {
    $stmt = $this->connection->prepare(
      "DELETE FROM `protocol_drafts` WHERE user_id = ?"
    );
    if (! $stmt) {
      return false;
    }

    $stmt->bind_param('i', $userId);
    return $stmt->execute();
  }
}
