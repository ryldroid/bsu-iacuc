<?php

class ContactOfficeModel extends Model
{
  // ===== READ OFFICES =====
  public function getAll(): array
  {
    $result = $this->connection->query(
      "SELECT * FROM `contact_offices` ORDER BY sort_order ASC, id ASC"
    );
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
  }

  public function getById(int $id): ?array
  {
    $stmt = $this->connection->prepare("SELECT * FROM `contact_offices` WHERE id = ?");
    if (! $stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }

  // ===== ADD OFFICE =====
  public function insert(
    string $name,
    ?string $logoPath,
    ?string $address,
    ?string $phone,
    ?string $email,
    ?string $facebookUrl,
    ?string $facebookLabel,
    ?string $websiteUrl,
    ?string $websiteLabel,
    ?string $directorName,
    ?string $directorRole,
    ?string $directorEmail
  ): bool {
    $stmt = $this->connection->prepare(
      "INSERT INTO `contact_offices`
                (sort_order, name, logo_path, address, phone, email, facebook_url, facebook_label, website_url, website_label, director_name, director_role, director_email)
             SELECT COALESCE(MAX(sort_order), -1) + 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
             FROM `contact_offices`"
    );
    if (! $stmt) return false;
    $stmt->bind_param(
      str_repeat('s', 12),
      $name,
      $logoPath,
      $address,
      $phone,
      $email,
      $facebookUrl,
      $facebookLabel,
      $websiteUrl,
      $websiteLabel,
      $directorName,
      $directorRole,
      $directorEmail
    );
    return $stmt->execute();
  }

  // ===== EDIT OFFICE =====
  public function update(
    int $id,
    string $name,
    ?string $address,
    ?string $phone,
    ?string $email,
    ?string $facebookUrl,
    ?string $facebookLabel,
    ?string $websiteUrl,
    ?string $websiteLabel,
    ?string $directorName,
    ?string $directorRole,
    ?string $directorEmail
  ): bool {
    $stmt = $this->connection->prepare(
      "UPDATE `contact_offices` SET
                name = ?, address = ?, phone = ?, email = ?,
                facebook_url = ?, facebook_label = ?, website_url = ?, website_label = ?,
                director_name = ?, director_role = ?, director_email = ?
             WHERE id = ?"
    );
    if (! $stmt) return false;
    $stmt->bind_param(
      str_repeat('s', 11) . 'i',
      $name,
      $address,
      $phone,
      $email,
      $facebookUrl,
      $facebookLabel,
      $websiteUrl,
      $websiteLabel,
      $directorName,
      $directorRole,
      $directorEmail,
      $id
    );
    return $stmt->execute();
  }

  // ===== MOVE OFFICE =====
  public function move(int $id, string $direction): bool
  {
    return $this->moveInOrder('contact_offices', $id, $direction);
  }

  // ===== DELETE OFFICE =====
  public function delete(int $id): bool
  {
    $stmt = $this->connection->prepare("DELETE FROM `contact_offices` WHERE id = ?");
    if (! $stmt) return false;
    $stmt->bind_param('i', $id);
    return $stmt->execute();
  }
}
