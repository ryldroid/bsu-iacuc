<?php

class UserModel extends Model
{
  public $table = 'users';

  // ===== GETTING USERS =====

  public function getUserByEmail($email)
  {
    $stmt = $this->connection->prepare("SELECT * FROM $this->table WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
  }

  public function getUserByUsername($username)
  {
    $stmt = $this->connection->prepare("SELECT * FROM $this->table WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
  }

  public function getUser($id)
  {
    $stmt = $this->connection->prepare("SELECT * FROM $this->table WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
  }

  // ===== IACUC TRAINING CERTIFICATE =====

  public function getCert(int $userId): ?array
  {
    $stmt = $this->connection->prepare(
      "SELECT cert_path, cert_original_name, cert_uploaded_at
      FROM $this->table WHERE id = ? AND cert_path IS NOT NULL"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
  }

  public function hasCert(int $userId): bool
  {
    return $this->getCert($userId) !== null;
  }

  public function saveCert(int $userId, string $relPath, string $originalName): bool
  {
    $stmt = $this->connection->prepare(
      "UPDATE $this->table
      SET cert_path = ?, cert_original_name = ?, cert_uploaded_at = NOW()
      WHERE id = ?"
    );
    $stmt->bind_param('ssi', $relPath, $originalName, $userId);
    return $stmt->execute();
  }

  // ===== CREATE UPDATE DELETE =====

  public function insertUser(
    string $username,
    string $first_name,
    string $last_name,
    string $email,
    string $passwordHash,
    string $role = 'researcher',
    string $status = 'active',
    ?string $phone_number = null
  ): bool {
    $stmt = $this->connection->prepare(
      "INSERT INTO `$this->table`
      (username, first_name, last_name, email, phone_number, password, role, status)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param('ssssssss', $username, $first_name, $last_name, $email, $phone_number, $passwordHash, $role, $status);
    return $stmt->execute();
  }

  public function updateUser(int $id, array $input): bool
  {
    $username     = $input['username'];
    $first_name   = $input['first_name'];
    $last_name    = $input['last_name'];
    $email        = $input['email'];
    $phone_number = $input['phone_number'];
    $role         = $input['role'];

    if (!empty($input['password'])) {
      $password = $input['password'];
      $stmt = $this->connection->prepare(
        "UPDATE $this->table 
          SET username=?, first_name=?, last_name=?, email=?, phone_number=?, role=?, password=? 
          WHERE id=?"
      );

      $stmt->bind_param('sssssssi', $username, $first_name, $last_name, $email, $phone_number, $role, $password, $id);
    } else {
      $stmt = $this->connection->prepare(
        "UPDATE $this->table 
          SET username=?, first_name=?, last_name=?, email=?, phone_number=?, role=? 
          WHERE id=?"
      );
      $stmt->bind_param('ssssssi', $username, $first_name, $last_name, $email, $phone_number, $role, $id);
    }

    return $stmt->execute();
  }

  public function deleteUser(int $id): bool
  {
    $stmt = $this->connection->prepare("DELETE FROM $this->table WHERE id = ?");
    $stmt->bind_param('i', $id);
    return $stmt->execute();
  }

  // ===== OTHER =====

  public function getPendingUsers(): array
  {
    $stmt = $this->connection->prepare(
      "SELECT id, username, first_name, last_name, email, role, created_at
      FROM $this->table WHERE status = 'pending' ORDER BY created_at ASC"
    );
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  public function approveUser(int $id): bool
  {
    $stmt = $this->connection->prepare(
      "UPDATE $this->table SET status = 'active' WHERE id = ? AND status = 'pending'"
    );
    $stmt->bind_param('i', $id);
    return $stmt->execute();
  }

  public function rejectUser(int $id): bool
  {
    $stmt = $this->connection->prepare(
      "DELETE FROM $this->table WHERE id = ? AND status = 'pending'"
    );
    $stmt->bind_param('i', $id);
    return $stmt->execute();
  }

  // ===== FORGOT PASSWORD =====

  public function createPasswordReset(int $user_id, string $token): bool
  {
    $stmt = $this->connection->prepare(
      "DELETE FROM password_resets WHERE user_id = ?"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();

    $stmt = $this->connection->prepare(
      "INSERT INTO password_resets (user_id, token, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))"
    );
    $stmt->bind_param('is', $user_id, $token);
    return $stmt->execute();
  }

  public function getPasswordReset(string $token): ?array
  {
    $stmt = $this->connection->prepare(
      "SELECT * FROM password_resets
        WHERE token = ? AND used = 0 AND expires_at > NOW()"
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
  }

  public function markResetUsed(string $token): bool
  {
    $stmt = $this->connection->prepare(
      "UPDATE password_resets SET used = 1 WHERE token = ?"
    );
    $stmt->bind_param('s', $token);
    return $stmt->execute();
  }

  public function updatePassword(int $id, string $hash): bool
  {
    $stmt = $this->connection->prepare(
      "UPDATE users SET password = ? WHERE id = ?"
    );
    $stmt->bind_param('si', $hash, $id);
    return $stmt->execute();
  }

  // ===== EMAIL VERIFICATION =====

  public function createEmailVerification(int $user_id, string $token): bool
  {
    $stmt = $this->connection->prepare(
      "DELETE FROM email_verifications WHERE user_id = ?"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();

    $stmt = $this->connection->prepare(
      "INSERT INTO email_verifications (user_id, token, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))"
    );
    $stmt->bind_param('is', $user_id, $token);
    return $stmt->execute();
  }

  public function getEmailVerification(string $token): ?array
  {
    $stmt = $this->connection->prepare(
      "SELECT * FROM email_verifications
        WHERE token = ? AND used = 0 AND expires_at > NOW()"
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
  }

  public function markEmailVerificationUsed(string $token): bool
  {
    $stmt = $this->connection->prepare(
      "UPDATE email_verifications SET used = 1 WHERE token = ?"
    );
    $stmt->bind_param('s', $token);
    return $stmt->execute();
  }

  public function markEmailVerified(int $user_id): bool
  {
    $stmt = $this->connection->prepare(
      "UPDATE $this->table SET email_verified = 1 WHERE id = ?"
    );
    $stmt->bind_param('i', $user_id);
    return $stmt->execute();
  }

  public function markEmailUnverified(int $user_id): bool
  {
    $stmt = $this->connection->prepare(
      "UPDATE $this->table SET email_verified = 0 WHERE id = ?"
    );
    $stmt->bind_param('i', $user_id);
    return $stmt->execute();
  }

  public function secondsSinceLastVerificationEmail(int $user_id): ?int
  {
    $stmt = $this->connection->prepare(
      "SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS elapsed
        FROM email_verifications WHERE user_id = ?
        ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int) $row['elapsed'] : null;
  }

  // ===== LOGIN ATTEMPTS =====

  public function countRecentAttempts(string $identifier, int $windowSeconds = 900): int
  {
    $stmt = $this->connection->prepare(
      "SELECT COUNT(*) AS cnt FROM login_attempts
        WHERE identifier = ?
        AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)"
    );
    $stmt->bind_param('si', $identifier, $windowSeconds);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['cnt'];
  }

  public function recordLoginAttempt(string $identifier): void
  {
    $stmt = $this->connection->prepare(
      "INSERT INTO login_attempts (identifier) VALUES (?)"
    );
    $stmt->bind_param('s', $identifier);
    $stmt->execute();
  }

  public function clearLoginAttempts(string $identifier): void
  {
    $stmt = $this->connection->prepare(
      "DELETE FROM login_attempts WHERE identifier = ?"
    );
    $stmt->bind_param('s', $identifier);
    $stmt->execute();
  }

  // ===== ADMIN INVITE TOKENS =====

  public function createInviteToken(string $role = 'reviewer', int $hoursValid = 48): string
  {
    $token = bin2hex(random_bytes(32));

    $stmt = $this->connection->prepare(
      "INSERT INTO invite_tokens (token, role, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))"
    );
    $stmt->bind_param('ssi', $token, $role, $hoursValid);
    $stmt->execute();

    return $token;
  }

  public function getValidInviteToken(string $token): ?array
  {
    $stmt = $this->connection->prepare(
      "SELECT * FROM invite_tokens
        WHERE token = ? AND used = 0 AND expires_at > NOW()"
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
  }

  public function consumeInviteToken(string $token, int $userId): void
  {
    $stmt = $this->connection->prepare(
      "UPDATE invite_tokens SET used = 1, used_by = ? WHERE token = ?"
    );
    $stmt->bind_param('is', $userId, $token);
    $stmt->execute();
  }
}
