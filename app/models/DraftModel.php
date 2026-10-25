<?php

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
    string $title,
    array $details
  ): bool {
    $title       = mb_substr(normalize_pasted_text($title), 0, 255);
    $animalType  = mb_substr(normalize_pasted_text($details['animal_type'] ?? ''), 0, 100);
    $adviser     = mb_substr(normalize_pasted_text($details['research_adviser'] ?? ''), 0, 255);
    $veterinarian = mb_substr(normalize_pasted_text($details['veterinarian'] ?? ''), 0, 255);
    $durationStart = $this->monthToDate($details['research_duration_start'] ?? '', false);
    $durationEnd   = $this->monthToDate($details['research_duration_end'] ?? '', true);
    $countText   = trim((string) ($details['animal_count'] ?? ''));
    $animalCount = ctype_digit($countText) ? min((int) $countText, 1000000) : null;
    $agreedT     = $agreedTerms ? 1 : 0;
    $agreedP     = $agreedPrivacy ? 1 : 0;

    $stmt = $this->connection->prepare(
      "INSERT INTO `protocol_drafts` (user_id, step, agreed_terms, agreed_privacy, title, animal_type, animal_count, research_adviser, veterinarian, research_duration_start, research_duration_end)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                step = VALUES(step),
                agreed_terms = VALUES(agreed_terms),
                agreed_privacy = VALUES(agreed_privacy),
                title = VALUES(title),
                animal_type = VALUES(animal_type),
                animal_count = VALUES(animal_count),
                research_adviser = VALUES(research_adviser),
                veterinarian = VALUES(veterinarian),
                research_duration_start = VALUES(research_duration_start),
                research_duration_end = VALUES(research_duration_end)"
    );
    if (! $stmt) {
      return false;
    }

    $stmt->bind_param('iiiississss', $userId, $step, $agreedT, $agreedP, $title, $animalType, $animalCount, $adviser, $veterinarian, $durationStart, $durationEnd);
    return $stmt->execute();
  }

  private function monthToDate(string $value, bool $lastDay): ?string
  {
    $date = DateTime::createFromFormat('!Y-m', $value);
    if (! $date || $date->format('Y-m') !== $value) {
      return null;
    }
    return $date->format($lastDay ? 'Y-m-t' : 'Y-m-01');
  }

  // ===== SAVE FILE =====

  public function saveFile(int $userId, string $key, string $path, string $originalName): bool
  {
    if (! in_array($key, ['protocol', 'cert'], true)) {
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
    if (! in_array($key, ['protocol', 'cert'], true)) {
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
