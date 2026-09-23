<?php
// NEW FILE - CRUD for staff-managed Contact page offices

require_once dirname(__DIR__) . '/core/Model.php';

class ContactOfficeModel extends Model
{
  // Get all offices, in display order (used on the public Contact page)
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

  // logo_path isn't editable through the admin form yet, so it's only set
  // here on insert (new offices start with no logo) and left alone by update().
  public function insert(
    int $sortOrder,
    string $name,
    ?string $logoPath,
    ?string $address,
    ?string $phone,
    ?string $email,
    ?string $facebookUrl,
    ?string $facebookLabel,
    ?string $directorName,
    ?string $directorRole,
    ?string $directorEmail
  ): bool {
    $stmt = $this->connection->prepare(
      "INSERT INTO `contact_offices`
                (sort_order, name, logo_path, address, phone, email, facebook_url, facebook_label, director_name, director_role, director_email)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (! $stmt) return false;
    $types = 'i' . str_repeat('s', 10);
    $stmt->bind_param(
      $types,
      $sortOrder,
      $name,
      $logoPath,
      $address,
      $phone,
      $email,
      $facebookUrl,
      $facebookLabel,
      $directorName,
      $directorRole,
      $directorEmail
    );
    return $stmt->execute();
  }

  public function update(
    int $id,
    int $sortOrder,
    string $name,
    ?string $address,
    ?string $phone,
    ?string $email,
    ?string $facebookUrl,
    ?string $facebookLabel,
    ?string $directorName,
    ?string $directorRole,
    ?string $directorEmail
  ): bool {
    $stmt = $this->connection->prepare(
      "UPDATE `contact_offices` SET
                sort_order = ?, name = ?, address = ?, phone = ?, email = ?,
                facebook_url = ?, facebook_label = ?, director_name = ?, director_role = ?, director_email = ?
             WHERE id = ?"
    );
    if (! $stmt) return false;
    $types = 'i' . str_repeat('s', 9) . 'i';
    $stmt->bind_param(
      $types,
      $sortOrder,
      $name,
      $address,
      $phone,
      $email,
      $facebookUrl,
      $facebookLabel,
      $directorName,
      $directorRole,
      $directorEmail,
      $id
    );
    return $stmt->execute();
  }

  public function delete(int $id): bool
  {
    $stmt = $this->connection->prepare("DELETE FROM `contact_offices` WHERE id = ?");
    if (! $stmt) return false;
    $stmt->bind_param('i', $id);
    return $stmt->execute();
  }
}
