<?php

class NotificationModel extends Model
{
  // ===== ICON & COLOR BY TYPE =====
  private const DEFAULT_STYLE = ['icon' => 'bell-icon', 'variant' => 'info'];
  private const BACKUP_REMINDER_DAYS = 7;
  private const BACKUP_DOWNLOAD_DAYS = 14;

  private const TYPE_STYLES = [
    'account_verified'               => ['icon' => 'shield-check-icon', 'variant' => 'success'],
    'personnel_application'          => ['icon' => 'clock-icon', 'variant' => 'warning'],
    'protocol_submitted'             => ['icon' => 'upload-icon', 'variant' => 'info'],
    'new_submission'                 => ['icon' => 'upload-icon', 'variant' => 'info'],
    'new_submission_staff'           => ['icon' => 'upload-icon', 'variant' => 'info'],
    'new_submission_reviewer'        => ['icon' => 'upload-icon', 'variant' => 'info'],
    'protocol_resubmitted'           => ['icon' => 'upload-icon', 'variant' => 'warning'],
    'protocol_amended'               => ['icon' => 'upload-icon', 'variant' => 'teal'],
    'protocol_renamed'               => ['icon' => 'edit-icon', 'variant' => 'purple'],
    'protocol_status_changed'        => ['icon' => 'review-icon', 'variant' => 'info'],
    'protocol_status_under_review'   => ['icon' => 'clock-icon', 'variant' => 'info'],
    'protocol_status_needs_revision' => ['icon' => 'alert-triangle-icon', 'variant' => 'warning'],
    'protocol_status_reviewed'       => ['icon' => 'checkbox-icon', 'variant' => 'purple'],
    'protocol_status_endorsed'       => ['icon' => 'shield-check-icon', 'variant' => 'teal'],
    'protocol_status_approved'       => ['icon' => 'check-circle-icon', 'variant' => 'success'],
    'payment_proof_uploaded'         => ['icon' => 'upload-icon', 'variant' => 'warning'],
    'cert_verification'              => ['icon' => 'upload-icon', 'variant' => 'warning'],
    'cert_verified'                  => ['icon' => 'check-icon', 'variant' => 'success'],
    'cert_rejected'                  => ['icon' => 'close-icon', 'variant' => 'danger'],
    'payment_verified'               => ['icon' => 'check-icon', 'variant' => 'success'],
    'payment_proof_rejected'         => ['icon' => 'close-icon', 'variant' => 'danger'],
    'protocol_paid'                  => ['icon' => 'download-icon', 'variant' => 'teal'],
    'signed_scan_uploaded'           => ['icon' => 'edit-icon', 'variant' => 'purple'],
    'clearance_pool_uploaded'        => ['icon' => 'clearance-icon', 'variant' => 'teal'],
    'protocol_deletion_requested'    => ['icon' => 'alert-triangle-icon', 'variant' => 'warning'],
    'protocol_deletion_rejected'     => ['icon' => 'close-icon', 'variant' => 'danger'],
    'protocol_deleted'               => ['icon' => 'trash-icon', 'variant' => 'danger'],
    'protocol_returned_to_review'    => ['icon' => 'alert-triangle-icon', 'variant' => 'warning'],
    'clearance_expiring'             => ['icon' => 'alert-triangle-icon', 'variant' => 'warning'],
    'backup_warning'                 => ['icon' => 'alert-triangle-icon', 'variant' => 'danger'],
    'backup_download'                => ['icon' => 'download-icon', 'variant' => 'warning'],
    'email_updates_stopped'          => ['icon' => 'alert-triangle-icon', 'variant' => 'warning'],
  ];

  // ===== CREATE NOTIFICATIONS =====
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
      "SELECT id, email, first_name, email_verified, email_notifications FROM `users` WHERE role = ? AND status = 'active'"
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

  // ===== BACKUP WARNING FOR ALL STAFF: AT MOST ONE A WEEK WHILE THE BACKUP IS STALE =====
  public function warnStaffAboutBackup(Backup $backup): void
  {
    $message = $backup->ageWarning();
    if (! $message) {
      return;
    }

    $since = date('Y-m-d H:i:s', max($backup->lastBackupTime() ?? 0, time() - self::BACKUP_REMINDER_DAYS * 86400));

    $stmt = $this->connection->prepare(
      "SELECT u.id FROM `users` u
             WHERE u.role = 'staff' AND u.status = 'active'
             AND NOT EXISTS (
               SELECT 1 FROM `notifications` n
               WHERE n.user_id = u.id AND n.type = 'backup_warning' AND n.created_at > ?
             )"
    );
    if (! $stmt) {
      return;
    }

    $stmt->bind_param('s', $since);
    $stmt->execute();

    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $staff) {
      $this->create((int) $staff['id'], 'backup_warning', 'Backup Needed', $message, 'personnel/accounts?tab=backups');
    }
  }

  // ===== REMIND STAFF TO DOWNLOAD A BACKUP: AT MOST ONE EVERY 2 WEEKS =====
  public function remindStaffToDownloadBackup(Backup $backup): void
  {
    if ($backup->lastBackupTime() === null) {
      return;
    }

    $limit = date('Y-m-d H:i:s', time() - self::BACKUP_DOWNLOAD_DAYS * 86400);

    $stmt = $this->connection->prepare("SELECT MAX(created_at) FROM `audit_logs` WHERE action = 'backup_downloaded'");
    if (! $stmt) {
      return;
    }

    $stmt->execute();
    $lastDownload = $stmt->get_result()->fetch_row()[0] ?? null;

    if ($lastDownload !== null && $lastDownload > $limit) {
      return;
    }

    $stmt = $this->connection->prepare(
      "SELECT u.id FROM `users` u
             WHERE u.role = 'staff' AND u.status = 'active'
             AND NOT EXISTS (
               SELECT 1 FROM `notifications` n
               WHERE n.user_id = u.id AND n.type = 'backup_download' AND n.created_at > ?
             )"
    );
    if (! $stmt) {
      return;
    }

    $stmt->bind_param('s', $limit);
    $stmt->execute();

    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $staff) {
      $this->create((int) $staff['id'], 'backup_download', 'Download a Backup', 'No backup has been downloaded in the last 2 weeks. Press Save a copy on a backup and keep the file on your computer or Google Drive.', 'personnel/accounts?tab=backups');
    }
  }

  // ===== READ NOTIFICATIONS =====
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
    return $this->withDisplay($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
  }

  public function getForUserPaginated(int $userId, int $limit, int $offset): array
  {
    $stmt = $this->connection->prepare(
      "SELECT id, type, title, message, link, is_read, created_at
             FROM `notifications`
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?"
    );
    if (! $stmt) {
      return [];
    }

    $stmt->bind_param('iii', $userId, $limit, $offset);
    $stmt->execute();
    return $this->withDisplay($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
  }

  private function withDisplay(array $items): array
  {
    return array_map(function (array $item): array {
      $style = self::TYPE_STYLES[$item['type']] ?? self::DEFAULT_STYLE;

      return $item + $style + ['message_html' => Notifier::toHtml((string) $item['message'])];
    }, $items);
  }

  // ===== COUNTS =====
  public function countForUser(int $userId): int
  {
    $stmt = $this->connection->prepare(
      "SELECT COUNT(*) AS c FROM `notifications` WHERE user_id = ?"
    );
    if (! $stmt) {
      return 0;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['c'];
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

  // ===== MARK AS READ / UNREAD =====
  public function markRead(int $id, int $userId, bool $read = true): bool
  {
    $stmt = $this->connection->prepare(
      "UPDATE `notifications` SET is_read = ? WHERE id = ? AND user_id = ?"
    );
    if (! $stmt) {
      return false;
    }

    $isRead = (int) $read;
    $stmt->bind_param('iii', $isRead, $id, $userId);
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
