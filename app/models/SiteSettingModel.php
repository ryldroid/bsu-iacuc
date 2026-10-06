<?php

class SiteSettingModel extends Model
{
  // ===== READ SETTINGS =====
  public function getAll(): array
  {
    $result   = $this->connection->query("SELECT setting_key, setting_value FROM `site_settings`");
    $rows     = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $settings = [];
    foreach ($rows as $row) {
      $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
  }

  // ===== SAVE SETTINGS =====
  public function set(string $key, string $value): bool
  {
    $stmt = $this->connection->prepare(
      "INSERT INTO `site_settings` (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    if (! $stmt) return false;
    $stmt->bind_param('ss', $key, $value);
    return $stmt->execute();
  }

  public function setMany(array $pairs): bool
  {
    $ok = true;
    foreach ($pairs as $key => $value) {
      $ok = $this->set($key, $value) && $ok;
    }
    return $ok;
  }
}
