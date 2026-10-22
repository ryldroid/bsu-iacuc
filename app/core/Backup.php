<?php

class Backup
{
  // ===== SETTINGS =====
  private const PREFIX        = 'iacuc-backup-';
  private const NAME_PATTERN  = '/^iacuc-backup-\d{8}-\d{6}(-[a-z]+)?\.zip$/';
  private const JOB_PATTERN   = '/^[a-f0-9]{32}$/';
  private const STEP_SECONDS  = 12;
  private const ROW_CHUNK     = 200;
  private const ROWS_PER_SQL  = 50;
  private const MAX_SQL_BYTES = 262144;
  private const BATCH_BYTES   = 40 * 1024 * 1024;
  private const BATCH_FILES   = 300;
  private const KEEP          = 10;
  private const WARN_DAYS     = 3;
  private const AUTO_DAYS     = 1;
  private const STALE_SECONDS = 21600;
  private const BUSY_SECONDS  = 60;
  private const UPLOAD_PREFIX = 'upload-';
  private const MAX_UPLOAD    = 512 * 1024 * 1024;
  private const MAX_CHUNK     = 4 * 1024 * 1024;
  private const PHRASE_LENGTH = 12;
  private const SHADOW        = '_restore_';
  private const SCHEMA_MARKER = '/storage/.schema_checked';
  private const SKIP_TABLES   = ['login_attempts', 'password_resets', 'email_verifications', 'invite_tokens'];
  private const FILE_ROOTS    = ['storage/uploads', 'portal/assets/uploads'];
  private const SKIP_PREFIX   = 'storage/uploads/staging/';
  private const BINARY_TYPES  = [249, 250, 251, 252, 253, 254];
  private const HTACCESS      = "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n";
  private const MESSAGES      = [
    'stage_db'    => 'Checking and loading the backup data...',
    'stage_files' => 'Checking and unpacking uploaded files...',
    'database'    => 'Saving the database...',
    'files'       => 'Packing uploaded files...',
    'finish'      => 'Finishing up...',
    'swap'        => 'Restoring your data...',
    'cleanup'     => 'Cleaning up...',
    'done'        => 'Done.',
  ];

  private mysqli $db;
  private string $root;
  private string $dir;
  private string $work;
  private array $orderCache = [];

  // ===== SETUP =====
  public function __construct(mysqli $db)
  {
    $this->db   = $db;
    $this->root = dirname(__DIR__, 2);
    $this->dir  = $this->root . '/storage/backups';
    $this->work = $this->dir . '/.work';
  }

  // ===== START A BACKUP JOB =====
  public function start(string $by, string $label = '', bool $automatic = false): array
  {
    $state = $this->openJob($by, $label, $automatic);

    $this->prepareSnapshot($state);
    $this->save($state);

    return $this->status($state);
  }

  // ===== START A RESTORE JOB =====
  public function startRestore(string $name, string $by): array
  {
    $path = $this->path($name);
    if ($path === null) {
      throw new RuntimeException('That backup no longer exists.', 404);
    }

    $manifest = $this->manifest($path);
    if ($manifest === null) {
      throw new RuntimeException('This backup file is damaged or was not made by this portal.');
    }

    $this->checkRestorable($manifest);

    if (!empty($manifest['encrypted'])) {
      $this->checkPassphrase($path);
    }

    $state = $this->openJob($by, 'prerestore');

    $state['phase']   = 'stage_db';
    $state['restore'] = [
      'name'        => $name,
      'tables'      => array_fill_keys(array_keys($manifest['tables']), 0),
      'ready'       => false,
      'offset'      => 0,
      'sql_size'    => 0,
      'file'        => 0,
      'bytes_total' => array_sum(array_column($manifest['files'], 'size')),
      'bytes_done'  => 0,
    ];

    file_put_contents($this->work . '/' . $state['id'] . '/manifest.json', json_encode($manifest));

    $this->save($state);
    return $this->status($state);
  }

  // ===== RUN ONE STEP =====
  public function step(string $id): array
  {
    $job  = $this->jobDir($id);
    $lock = fopen($job . '/lock', 'c');

    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
      throw new RuntimeException('This backup is already being processed.', 409);
    }

    try {
      $state = $this->load($id);

      match ($state['phase']) {
        'stage_db'    => $this->stageDatabase($state, $job),
        'stage_files' => $this->stageFiles($state, $job),
        'database'    => $this->stepDatabase($state, $job),
        'files'       => $this->stepFiles($state, $job),
        'finish'      => $this->finish($state, $job),
        'swap'        => $this->swap($state, $job),
        default       => $this->cleanup($state),
      };

      $this->save($state);
    } finally {
      flock($lock, LOCK_UN);
      fclose($lock);
    }

    if ($state['phase'] === 'done') {
      $this->removeDir($job);
    }

    return $this->status($state);
  }

  // ===== CANCEL A JOB =====
  public function cancel(string $id): void
  {
    $job = $this->work . '/' . $id;

    if (!preg_match(self::JOB_PATTERN, $id) || !is_dir($job)) {
      return;
    }

    $state = json_decode((string) @file_get_contents($job . '/state.json'), true);

    if (isset($state['restore'])) {
      $this->dropShadows(array_keys($state['restore']['tables']));
    }

    $this->removeDir($job);
  }

  // ===== LIST BACKUPS =====
  public function all(): array
  {
    $items = [];

    foreach (glob($this->dir . '/' . self::PREFIX . '*.zip') ?: [] as $path) {
      $name = basename($path);
      if (!preg_match(self::NAME_PATTERN, $name)) {
        continue;
      }

      $manifest = $this->manifest($path);

      $items[] = [
        'name'       => $name,
        'size'       => (int) filesize($path),
        'created_at' => $manifest['created_at'] ?? date('Y-m-d H:i:s', (int) filemtime($path)),
        'created_by' => $manifest['created_by'] ?? '',
        'encrypted'  => !empty($manifest['encrypted']),
        'label'      => $manifest['label'] ?? '',
        'rows'       => array_sum(array_column($manifest['tables'] ?? [], 'rows')),
        'files'      => count($manifest['files'] ?? []),
        'valid'      => $manifest !== null,
      ];
    }

    usort($items, fn($a, $b) => strcmp($b['name'], $a['name']));
    return $items;
  }

  // ===== READ MANIFEST FROM A BACKUP =====
  public function manifest(string $path): ?array
  {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::RDONLY) !== true) {
      return null;
    }

    $json = $zip->getFromName('manifest.json');
    $zip->close();

    $data = $json === false ? null : json_decode($json, true);
    return is_array($data) && ($data['app'] ?? '') === 'bsu-iacuc' ? $data : null;
  }

  // ===== RESOLVE A BACKUP NAME TO A PATH =====
  public function path(string $name): ?string
  {
    $path = $this->dir . '/' . $name;
    return preg_match(self::NAME_PATTERN, $name) && is_file($path) ? $path : null;
  }

  // ===== DELETE A BACKUP =====
  public function delete(string $name): bool
  {
    $path = $this->path($name);
    return $path !== null && unlink($path);
  }

  // ===== AUTOMATIC BACKUP: ONE STEP PER CALL =====
  public function automatic(): array
  {
    $id = $this->automaticJob();

    if ($id === null) {
      $last = $this->lastBackupTime();

      if ($last !== null && time() - $last < self::AUTO_DAYS * 86400) {
        return ['idle' => true, 'done' => false];
      }

      $id = $this->start('Automatic', '', true)['id'];
    }

    return $this->step($id);
  }

  // ===== LAST BACKUP TIME =====
  public function lastBackupTime(): ?int
  {
    $newest = null;

    foreach (glob($this->dir . '/' . self::PREFIX . '*.zip') ?: [] as $path) {
      $name = basename($path);
      if (preg_match(self::NAME_PATTERN, $name) && ($newest === null || $name > $newest)) {
        $newest = $name;
      }
    }

    $date = $newest === null ? false : DateTime::createFromFormat('Ymd-His', substr($newest, strlen(self::PREFIX), 15));
    return $date ? $date->getTimestamp() : null;
  }

  // ===== WARNING WHEN THE LAST BACKUP IS TOO OLD =====
  public function ageWarning(): ?string
  {
    $last = $this->lastBackupTime();

    if ($last === null) {
      return 'No backup has been made yet. Create one and download a copy.';
    }

    $days = (int) floor((time() - $last) / 86400);
    return $days >= self::WARN_DAYS ? 'The last backup is ' . $days . ' days old. Create a new one and download a copy.' : null;
  }

  // ===== SERVER AND BACKUP HEALTH =====
  public function health(): array
  {
    $free = @disk_free_space($this->root);

    return [
      'zip'        => class_exists('ZipArchive'),
      'encryption' => $this->passphrase() !== '',
      'free_bytes' => $free === false ? null : (int) $free,
      'memory'     => (string) ini_get('memory_limit'),
      'time_limit' => (int) ini_get('max_execution_time'),
      'last'       => $this->lastBackupTime(),
      'warning'    => $this->ageWarning(),
    ];
  }

  // ===== VERIFY A BACKUP WITHOUT RESTORING =====
  public function verify(string $name, int $offset): array
  {
    $path = $this->path($name);
    if ($path === null) {
      throw new RuntimeException('That backup no longer exists.', 404);
    }

    $this->requireZip();

    $manifest = $this->manifest($path);
    if ($manifest === null) {
      throw new RuntimeException('This file is not a backup made by this portal.');
    }

    $encrypted = !empty($manifest['encrypted']);
    if ($encrypted && $offset < 1) {
      $this->checkPassphrase($path);
    }

    $entries = [['name' => 'database.sql', 'size' => null, 'sha256' => (string) ($manifest['database_sha256'] ?? '')]];
    foreach ($manifest['files'] ?? [] as $file) {
      $entries[] = ['name' => 'files/' . $file['path'], 'size' => (int) $file['size'], 'sha256' => (string) $file['sha256']];
    }

    $total  = count($entries);
    $offset = max(0, $offset);
    $until  = microtime(true) + self::STEP_SECONDS;
    $zip    = $this->openForRead($path, $encrypted);

    try {
      while ($offset < $total) {
        $this->checkEntry($zip, $entries[$offset]);
        $offset++;

        if (microtime(true) >= $until) {
          break;
        }
      }
    } finally {
      $zip->close();
    }

    return ['offset' => $offset, 'total' => $total, 'percent' => (int) floor($offset / $total * 100), 'done' => $offset >= $total];
  }

  // ===== START AN UPLOAD =====
  public function startUpload(int $size): string
  {
    if ($size < 1 || $size > self::MAX_UPLOAD) {
      throw new RuntimeException('Choose a backup file that is not empty and not larger than ' . (self::MAX_UPLOAD / 1048576) . ' MB.', 413);
    }

    $this->requireZip();
    $this->ensureDir($this->work);
    $this->clearStale();

    $id = bin2hex(random_bytes(16));
    $this->ensureDir($this->work . '/' . self::UPLOAD_PREFIX . $id);

    return $id;
  }

  // ===== ADD ONE PIECE TO AN UPLOAD =====
  public function addChunk(string $id, int $offset, string $tmpFile): int
  {
    $part = $this->uploadDir($id) . '/part';

    clearstatcache(true, $part);
    $size   = is_file($part) ? (int) filesize($part) : 0;
    $length = (int) filesize($tmpFile);

    if ($offset !== $size) {
      throw new RuntimeException('The upload got out of order. Please start again.', 409);
    }

    if ($length < 1 || $length > self::MAX_CHUNK || $size + $length > self::MAX_UPLOAD) {
      throw new RuntimeException('That upload piece is not allowed. Please start again.', 413);
    }

    if (file_put_contents($part, file_get_contents($tmpFile), FILE_APPEND | LOCK_EX) !== $length) {
      throw new RuntimeException('Could not save the upload. The disk may be full.');
    }

    return $size + $length;
  }

  // ===== CHECK AND KEEP AN UPLOADED BACKUP =====
  public function finishUpload(string $id): string
  {
    $dir  = $this->uploadDir($id);
    $part = $dir . '/part';

    try {
      $manifest = is_file($part) ? $this->manifest($part) : null;
      if ($manifest === null) {
        throw new RuntimeException('This file is not a backup made by this portal.');
      }

      $this->checkRestorable($manifest);

      $time = strtotime((string) ($manifest['created_at'] ?? ''));
      if ($time === false) {
        throw new RuntimeException('This backup does not say when it was created.');
      }

      $name = self::PREFIX . date('Ymd-His', $time) . '-uploaded.zip';
      if (is_file($this->dir . '/' . $name)) {
        throw new RuntimeException('This backup is already in the list.', 409);
      }

      if (!rename($part, $this->dir . '/' . $name)) {
        throw new RuntimeException('Could not save the backup file.');
      }

      return $name;
    } finally {
      $this->removeDir($dir);
    }
  }

  // ===== OPEN A JOB =====
  private function openJob(string $by, string $label, bool $automatic = false): array
  {
    $this->requireZip();
    $encrypted = $this->encryptionEnabled();
    $this->ensureDir($this->work);
    $this->clearStale();

    if ($this->isBusy()) {
      throw new RuntimeException('Another backup or restore is already running. Please try again in a minute.', 409);
    }

    $id = bin2hex(random_bytes(16));
    $this->ensureDir($this->work . '/' . $id);

    return [
      'id'          => $id,
      'by'          => $by,
      'automatic'   => $automatic,
      'label'       => preg_replace('/[^a-z]/', '', strtolower($label)),
      'created'     => date('Y-m-d H:i:s'),
      'encrypted'   => $encrypted,
      'phase'       => 'database',
      'tables'      => [],
      'table'       => 0,
      'offset'      => 0,
      'sql_bytes'   => 0,
      'rows_total'  => 0,
      'rows_done'   => 0,
      'file'        => 0,
      'bytes_total' => 0,
      'bytes_done'  => 0,
      'hashes'      => [],
    ];
  }

  // ===== PREPARE THE DATABASE AND FILE SNAPSHOT =====
  private function prepareSnapshot(array &$state): void
  {
    $job   = $this->work . '/' . $state['id'];
    $files = $this->collectFiles();

    file_put_contents($job . '/files.json', json_encode($files));
    file_put_contents($job . '/database.sql', "-- BSU-IACUC backup\nSET NAMES utf8mb4;\n");

    $tables = $this->describeTables();

    $state = array_merge($state, [
      'phase'       => 'database',
      'tables'      => $tables,
      'table'       => 0,
      'offset'      => 0,
      'sql_bytes'   => (int) filesize($job . '/database.sql'),
      'rows_total'  => array_sum(array_column($tables, 'expected')),
      'rows_done'   => 0,
      'file'        => 0,
      'bytes_total' => array_sum(array_column($files, 'size')),
      'bytes_done'  => 0,
      'hashes'      => [],
    ]);
  }

  // ===== CHECK A BACKUP CAN BE RESTORED HERE =====
  private function checkRestorable(array $manifest): void
  {
    if (($manifest['format'] ?? 0) !== 1 || empty($manifest['tables']) || !is_array($manifest['files'] ?? null)) {
      throw new RuntimeException('This backup was made by an unsupported version of the portal.');
    }

    foreach ($manifest['tables'] as $table => $info) {
      if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || in_array($table, self::SKIP_TABLES, true) || str_starts_with($table, self::SHADOW)) {
        throw new RuntimeException('This backup lists a table that cannot be restored: ' . $table . '.');
      }

      $live = $this->liveColumns($table);
      if (!$live) {
        throw new RuntimeException('This backup has a table this portal does not have: ' . $table . '.');
      }

      $unknown = array_diff($info['columns'] ?? [], $live);
      if ($unknown) {
        throw new RuntimeException('This backup has data this portal version cannot hold (' . $table . '.' . reset($unknown) . '). Update the portal first.');
      }
    }

    foreach ($manifest['files'] as $file) {
      if (!$this->safePath($file['path'] ?? '') || !isset($file['size'], $file['sha256'])) {
        throw new RuntimeException('This backup lists a file path that is not allowed.');
      }
    }
  }

  // ===== STAGE THE DATABASE INTO SCRATCH TABLES =====
  private function stageDatabase(array &$state, string $job): void
  {
    $until    = microtime(true) + self::STEP_SECONDS;
    $restore  = &$state['restore'];
    $manifest = json_decode((string) file_get_contents($job . '/manifest.json'), true);
    $source   = $job . '/source.sql';

    if (!$restore['ready']) {
      $this->extractSql($restore['name'], $manifest, $source);

      foreach (array_keys($manifest['tables']) as $table) {
        $this->exec('DROP TABLE IF EXISTS `' . self::SHADOW . $table . '`', 'Could not prepare the table ' . $table . '.');
        $this->exec('CREATE TABLE `' . self::SHADOW . $table . '` LIKE `' . $table . '`', 'Could not prepare the table ' . $table . '.');
      }

      $restore['ready']    = true;
      $restore['sql_size'] = (int) filesize($source);
    }

    $in       = fopen($source, 'rb');
    $finished = false;
    fseek($in, $restore['offset']);

    while (true) {
      $line = fgets($in);

      if ($line === false) {
        $finished = true;
        break;
      }

      $this->loadLine($line, $restore['tables']);
      $restore['offset'] = ftell($in);

      if (microtime(true) >= $until) {
        break;
      }
    }

    fclose($in);

    if ($finished) {
      foreach ($manifest['tables'] as $table => $info) {
        if ($restore['tables'][$table] !== $info['rows']) {
          throw new RuntimeException('The backup data for ' . $table . ' is incomplete.');
        }
      }

      $orphan = $this->findOrphans($restore['tables']);
      if ($orphan !== null) {
        throw new RuntimeException('This backup has data that points to missing records (' . $orphan . '). It was probably saved while the site was busy. Create a new backup and restore that one instead.');
      }

      $this->clearMissingTimestamps($manifest);

      if (isset($restore['tables']['users']) && !$this->hasActiveStaff()) {
        throw new RuntimeException('This backup has no active staff account, so restoring it would lock everyone out.');
      }

      $restore['offset'] = $restore['sql_size'];
      $state['phase']    = 'stage_files';
    }
  }

  // ===== STAFF ACCESS SURVIVES THE RESTORE =====
  private function hasActiveStaff(): bool
  {
    $result = $this->db->query("SELECT COUNT(*) FROM `" . self::SHADOW . "users` WHERE role = 'staff' AND status = 'active'");
    return $result && (int) $result->fetch_row()[0] > 0;
  }

  // ===== EXTRACT AND VERIFY THE SQL FILE =====
  private function extractSql(string $name, array $manifest, string $target): void
  {
    $zip = $this->openForRead($this->dir . '/' . $name, !empty($manifest['encrypted']));

    $in = $zip->getStream('database.sql');
    if (!$in) {
      throw new RuntimeException('The backup has no database data.');
    }

    $out = fopen($target, 'wb');
    [, $digest] = $this->readHashed($in, $out);
    fclose($out);
    $zip->close();

    if (!hash_equals((string) ($manifest['database_sha256'] ?? ''), $digest)) {
      throw new RuntimeException('The backup database data failed its integrity check.');
    }
  }

  // ===== LOAD ONE INSERT LINE INTO ITS SCRATCH TABLE =====
  private function loadLine(string $line, array &$counts): void
  {
    if (!preg_match('/^INSERT INTO `([A-Za-z0-9_]+)` /', $line, $match)) {
      return;
    }

    if (!isset($counts[$match[1]])) {
      throw new RuntimeException('The backup contains data for a table it does not list.');
    }

    $this->exec('INSERT INTO `' . self::SHADOW . $match[1] . '` ' . substr($line, strlen($match[0])), 'Could not load the data for ' . $match[1] . '.');
    $counts[$match[1]] += $this->db->affected_rows;
  }

  // ===== STAGE THE FILES INTO A SCRATCH FOLDER =====
  private function stageFiles(array &$state, string $job): void
  {
    $until    = microtime(true) + self::STEP_SECONDS;
    $restore  = &$state['restore'];
    $manifest = json_decode((string) file_get_contents($job . '/manifest.json'), true);
    $files    = $manifest['files'];

    $zip = $this->openForRead($this->dir . '/' . $restore['name'], !empty($manifest['encrypted']));

    while ($restore['file'] < count($files)) {
      $this->stageFile($zip, $files[$restore['file']], $job . '/stage');

      $restore['bytes_done'] += $files[$restore['file']]['size'];
      $restore['file']++;

      if (microtime(true) >= $until) {
        break;
      }
    }

    $zip->close();

    if ($restore['file'] >= count($files)) {
      $this->prepareSnapshot($state);
    }
  }

  // ===== STAGE ONE FILE AND VERIFY IT =====
  private function stageFile(ZipArchive $zip, array $file, string $dest): void
  {
    $in = $zip->getStream('files/' . $file['path']);
    if (!$in) {
      throw new RuntimeException('The backup is missing a file: ' . $file['path']);
    }

    $target = $dest . '/' . $file['path'];
    $this->ensureDir(dirname($target));

    $out = fopen($target, 'wb');
    [$size, $digest] = $this->readHashed($in, $out);
    fclose($out);

    if ($size !== $file['size'] || !hash_equals($file['sha256'], $digest)) {
      throw new RuntimeException('A file in this backup failed its integrity check: ' . $file['path']);
    }
  }

  // ===== SWAP THE RESTORED DATA INTO PLACE =====
  private function swap(array &$state, string $job): void
  {
    $tables = array_keys($state['restore']['tables']);
    $undo   = [];

    $this->exec('SET FOREIGN_KEY_CHECKS=0', 'Could not start the restore.');
    $this->db->begin_transaction();

    try {
      foreach (array_merge($tables, array_intersect(self::SKIP_TABLES, $this->liveTables())) as $table) {
        $this->exec('DELETE FROM `' . $table . '`', 'Could not clear the table ' . $table . '.');
      }

      foreach ($tables as $table) {
        $this->exec('INSERT INTO `' . $table . '` SELECT * FROM `' . self::SHADOW . $table . '`', 'Could not restore the table ' . $table . '.');
      }

      $this->swapFiles($job, $undo);

      if (!$this->db->commit()) {
        throw new RuntimeException('Could not save the restored data.');
      }
    } catch (Throwable $e) {
      $this->db->rollback();

      foreach ($undo as $revert) {
        $revert();
      }

      throw $e;
    } finally {
      $this->db->query('SET FOREIGN_KEY_CHECKS=1');
    }

    $state['phase'] = 'cleanup';
  }

  // ===== SWAP THE UPLOAD FOLDERS =====
  private function swapFiles(string $job, array &$undo): void
  {
    $move = function (string $from, string $to) use (&$undo): void {
      if (!@rename($from, $to)) {
        throw new RuntimeException('Could not move ' . basename($from) . ' into place.');
      }

      array_unshift($undo, fn() => @rename($to, $from));
    };

    foreach (self::FILE_ROOTS as $rel) {
      $live  = $this->root . '/' . $rel;
      $stage = $job . '/stage/' . $rel;
      $old   = $job . '/old/' . $rel;

      $this->ensureDir($stage);
      $this->ensureDir(dirname($old));
      $this->ensureDir(dirname($live));

      if (is_dir($live . '/staging')) {
        $move($live . '/staging', $stage . '/staging');
      }

      if (is_dir($live)) {
        $move($live, $old);
      }

      $move($stage, $live);
    }
  }

  // ===== DROP THE SCRATCH TABLES =====
  private function cleanup(array &$state): void
  {
    $this->dropShadows(array_keys($state['restore']['tables']));
    @unlink($this->root . self::SCHEMA_MARKER);
    $state['phase'] = 'done';
  }

  private function dropShadows(array $tables): void
  {
    foreach ($tables as $table) {
      $this->db->query('DROP TABLE IF EXISTS `' . self::SHADOW . $table . '`');
    }
  }

  // ===== DATABASE PHASE =====
  private function stepDatabase(array &$state, string $job): void
  {
    $until = microtime(true) + self::STEP_SECONDS;
    $names = array_keys($state['tables']);
    $out   = fopen($job . '/database.sql', 'c+');

    ftruncate($out, $state['sql_bytes']);
    fseek($out, 0, SEEK_END);

    while ($state['table'] < count($names)) {
      $table = $names[$state['table']];
      $count = $this->dumpChunk($out, $table, $state['tables'][$table]['columns'], $state['offset']);

      $state['tables'][$table]['rows'] += $count;
      $state['rows_done']              += $count;
      $state['offset']                 += $count;

      if ($count < self::ROW_CHUNK) {
        $state['table']++;
        $state['offset'] = 0;
      }

      if (microtime(true) >= $until) {
        break;
      }
    }

    fflush($out);
    $state['sql_bytes'] = (int) fstat($out)['size'];
    fclose($out);

    if ($state['table'] >= count($names)) {
      $state['phase'] = 'files';
    }
  }

  // ===== DUMP ONE CHUNK OF A TABLE =====
  private function dumpChunk($out, string $table, array $columns, int $offset): int
  {
    $list   = '`' . implode('`,`', $columns) . '`';
    $result = $this->db->query('SELECT ' . $list . ' FROM `' . $table . '`' . $this->orderBy($table)
      . ' LIMIT ' . self::ROW_CHUNK . ' OFFSET ' . $offset);

    if (!$result) {
      throw new RuntimeException('Could not read the table ' . $table . '.');
    }

    $fields = $result->fetch_fields();
    $group  = [];
    $bytes  = 0;
    $count  = 0;

    while ($row = $result->fetch_row()) {
      $values = [];
      foreach ($row as $i => $value) {
        $values[] = $this->literal($value, $fields[$i]);
      }

      $group[] = '(' . implode(',', $values) . ')';
      $bytes  += strlen(end($group));
      $count++;

      if (count($group) >= self::ROWS_PER_SQL || $bytes >= self::MAX_SQL_BYTES) {
        fwrite($out, $this->insertSql($table, $list, $group));
        $group = [];
        $bytes = 0;
      }
    }

    $result->free();

    if ($group) {
      fwrite($out, $this->insertSql($table, $list, $group));
    }

    return $count;
  }

  private function insertSql(string $table, string $list, array $group): string
  {
    return 'INSERT INTO `' . $table . '` (' . $list . ') VALUES ' . implode(',', $group) . ";\n";
  }

  // ===== SQL VALUE LITERAL =====
  private function literal(?string $value, object $field): string
  {
    if ($value === null) {
      return 'NULL';
    }

    if ($value !== '' && $field->charsetnr === 63 && in_array($field->type, self::BINARY_TYPES, true)) {
      return '0x' . bin2hex($value);
    }

    return "'" . $this->db->real_escape_string($value) . "'";
  }

  // ===== PRIMARY KEY ORDER =====
  private function orderBy(string $table): string
  {
    if (!isset($this->orderCache[$table])) {
      $keys   = [];
      $result = $this->db->query("SHOW KEYS FROM `" . $table . "` WHERE Key_name = 'PRIMARY'");

      while ($row = $result->fetch_assoc()) {
        $keys[(int) $row['Seq_in_index']] = '`' . $row['Column_name'] . '`';
      }

      ksort($keys);
      $this->orderCache[$table] = $keys ? ' ORDER BY ' . implode(',', $keys) : '';
    }

    return $this->orderCache[$table];
  }

  // ===== FILES PHASE =====
  private function stepFiles(array &$state, string $job): void
  {
    $files = json_decode((string) file_get_contents($job . '/files.json'), true) ?: [];
    $zip   = $this->openZip($job . '/backup.zip');
    $bytes = 0;
    $added = 0;

    while ($state['file'] < count($files) && $added < self::BATCH_FILES && $bytes < self::BATCH_BYTES) {
      $path = $files[$state['file']]['path'];
      $abs  = $this->root . '/' . $path;

      if (is_file($abs)) {
        $size = (int) filesize($abs);

        $zip->addFile($abs, 'files/' . $path);
        $zip->setCompressionName('files/' . $path, ZipArchive::CM_STORE);
        $this->protect($zip, 'files/' . $path, $state);

        $state['hashes'][$path] = ['path' => $path, 'size' => $size, 'sha256' => hash_file('sha256', $abs)];
        $state['bytes_done']   += $size;
        $bytes                 += $size;
        $added++;
      }

      $state['file']++;
    }

    if (!$zip->close()) {
      throw new RuntimeException('Could not write the backup file. The disk may be full.');
    }

    if ($state['file'] >= count($files)) {
      $state['phase'] = 'finish';
    }
  }

  // ===== FINISH: MANIFEST, DATABASE, FINAL NAME =====
  private function finish(array &$state, string $job): void
  {
    $manifest = [
      'format'          => 1,
      'app'             => 'bsu-iacuc',
      'created_at'      => $state['created'],
      'created_by'      => $state['by'],
      'label'           => $state['label'],
      'encrypted'       => !empty($state['encrypted']),
      'database_sha256' => hash_file('sha256', $job . '/database.sql'),
      'tables'          => array_map(fn($t) => ['rows' => $t['rows'], 'columns' => $t['columns']], $state['tables']),
      'files'           => array_values($state['hashes']),
    ];

    $zip = $this->openZip($job . '/backup.zip');
    $zip->addFile($job . '/database.sql', 'database.sql');
    $this->protect($zip, 'database.sql', $state);
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    if (!$zip->close()) {
      throw new RuntimeException('Could not finish the backup file. The disk may be full.');
    }

    $name = self::PREFIX . date('Ymd-His', strtotime($state['created'])) . ($state['label'] !== '' ? '-' . $state['label'] : '') . '.zip';

    if (!rename($job . '/backup.zip', $this->dir . '/' . $name)) {
      throw new RuntimeException('Could not save the backup file.');
    }

    $state['name']  = $name;
    $state['phase'] = isset($state['restore']) ? 'swap' : 'done';

    $this->prune($state['restore']['name'] ?? '');
  }

  // ===== KEEP ONLY THE NEWEST BACKUPS =====
  private function prune(string $protect): void
  {
    foreach (array_slice($this->all(), self::KEEP) as $old) {
      if ($old['name'] !== $protect) {
        $this->delete($old['name']);
      }
    }
  }

  // ===== TABLES TO BACK UP =====
  private function describeTables(): array
  {
    $tables = [];

    foreach ($this->liveTables() as $name) {
      if (in_array($name, self::SKIP_TABLES, true) || str_starts_with($name, self::SHADOW)) {
        continue;
      }

      $expected = $this->db->query('SELECT COUNT(*) FROM `' . $name . '`')->fetch_row()[0];

      $tables[$name] = ['columns' => $this->liveColumns($name), 'rows' => 0, 'expected' => (int) $expected];
    }

    return $this->childrenFirst($tables);
  }

  // ===== SAVE TABLES THAT POINT TO OTHERS BEFORE THE TABLES THEY POINT TO =====
  private function childrenFirst(array $tables): array
  {
    $parents = [];
    foreach ($this->foreignKeys() as $key) {
      if ($key['child'] !== $key['parent']) {
        $parents[$key['child']][$key['parent']] = true;
      }
    }

    $ordered = [];
    while ($tables) {
      $blocked = [];
      foreach (array_keys($tables) as $child) {
        $blocked += $parents[$child] ?? [];
      }

      $ready   = array_diff_key($tables, $blocked) ?: $tables;
      $ordered += $ready;
      $tables  = array_diff_key($tables, $ready);
    }

    return $ordered;
  }

  // ===== FOREIGN KEYS OF THE LIVE DATABASE =====
  private function foreignKeys(): array
  {
    $keys   = [];
    $result = $this->db->query('SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION');

    while ($result && $row = $result->fetch_assoc()) {
      $id = $row['TABLE_NAME'] . '.' . $row['CONSTRAINT_NAME'];

      $keys[$id]['child']     = $row['TABLE_NAME'];
      $keys[$id]['parent']    = $row['REFERENCED_TABLE_NAME'];
      $keys[$id]['columns'][] = [$row['COLUMN_NAME'], $row['REFERENCED_COLUMN_NAME']];
    }

    return array_values($keys);
  }

  // ===== FIND A RESTORED ROW THAT POINTS TO A MISSING ROW =====
  private function findOrphans(array $tables): ?string
  {
    foreach ($this->foreignKeys() as $key) {
      if (!isset($tables[$key['child']], $tables[$key['parent']])) {
        continue;
      }

      $joins   = array_map(fn($pair) => 'c.`' . $pair[0] . '` = p.`' . $pair[1] . '`', $key['columns']);
      $present = array_map(fn($pair) => 'c.`' . $pair[0] . '` IS NOT NULL', $key['columns']);
      $missing = $this->db->query(
        'SELECT 1 FROM `' . self::SHADOW . $key['child'] . '` c LEFT JOIN `' . self::SHADOW . $key['parent'] . '` p ON ' . implode(' AND ', $joins)
          . ' WHERE ' . implode(' AND ', $present) . ' AND p.`' . $key['columns'][0][1] . '` IS NULL LIMIT 1'
      );

      if ($missing && $missing->num_rows > 0) {
        return $key['child'] . '.' . $key['columns'][0][0];
      }
    }

    return null;
  }

  // ===== LEAVE DATE COLUMNS THE BACKUP DOES NOT HAVE AS UNKNOWN =====
  private function clearMissingTimestamps(array $manifest): void
  {
    foreach ($manifest['tables'] as $table => $info) {
      $result = $this->db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $table . "' AND IS_NULLABLE = 'YES' AND COLUMN_DEFAULT LIKE 'current\\_timestamp%'");

      while ($result && $row = $result->fetch_row()) {
        if (!in_array($row[0], $info['columns'] ?? [], true)) {
          $this->exec('UPDATE `' . self::SHADOW . $table . '` SET `' . $row[0] . '` = NULL', 'Could not prepare the table ' . $table . '.');
        }
      }
    }
  }

  private function liveTables(): array
  {
    $tables = [];
    $result = $this->db->query('SHOW TABLES');

    while ($row = $result->fetch_row()) {
      $tables[] = $row[0];
    }

    return $tables;
  }

  private function liveColumns(string $table): array
  {
    $columns = [];
    $result  = $this->db->query('SHOW COLUMNS FROM `' . $table . '`');

    while ($result && $column = $result->fetch_assoc()) {
      $columns[] = $column['Field'];
    }

    return $columns;
  }

  // ===== FILES TO BACK UP =====
  private function collectFiles(): array
  {
    $files = [];

    foreach (self::FILE_ROOTS as $rel) {
      $base = $this->root . '/' . $rel;
      if (!is_dir($base)) {
        continue;
      }

      $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));

      foreach ($items as $item) {
        $path = str_replace('\\', '/', $rel . substr($item->getPathname(), strlen($base)));

        if ($item->isFile() && !$item->isLink() && !str_starts_with($path, self::SKIP_PREFIX)) {
          $files[] = ['path' => $path, 'size' => $item->getSize()];
        }
      }
    }

    usort($files, fn($a, $b) => strcmp($a['path'], $b['path']));
    return $files;
  }

  // ===== ALLOWED FILE PATHS IN A BACKUP =====
  private function safePath(string $path): bool
  {
    return (bool) preg_match('#^(storage/uploads|portal/assets/uploads)/[^\x00\\\\]+$#', $path)
      && !preg_match('#(^|/)\.\.?(/|$)#', $path)
      && !str_starts_with($path, self::SKIP_PREFIX);
  }

  // ===== PROGRESS STATUS =====
  private function status(array $state): array
  {
    $restoring = isset($state['restore']);
    $phase     = $state['phase'];
    $dbShare   = $state['rows_total'] ? min(1, $state['rows_done'] / $state['rows_total']) : 1;
    $fileShare = $state['bytes_total'] ? min(1, $state['bytes_done'] / $state['bytes_total']) : 1;

    $snapshot = match ($phase) {
      'database' => (int) floor($dbShare * 40),
      'files'    => 40 + (int) floor($fileShare * 55),
      default    => 95,
    };

    $percent = match ($phase) {
      'stage_db'    => (int) floor(($restoring && $state['restore']['sql_size'] ? $state['restore']['offset'] / $state['restore']['sql_size'] : 0) * 30),
      'stage_files' => 30 + (int) floor(($state['restore']['bytes_total'] ? $state['restore']['bytes_done'] / $state['restore']['bytes_total'] : 1) * 25),
      'database', 'files', 'finish' => $restoring ? 55 + (int) floor($snapshot * 0.35) : $snapshot,
      'swap'        => 92,
      'cleanup'     => 97,
      default       => 100,
    };

    $message = $restoring && in_array($phase, ['database', 'files', 'finish'], true)
      ? 'Saving a safety backup of the current data...'
      : self::MESSAGES[$phase];

    return [
      'id'      => $state['id'],
      'phase'   => $phase,
      'percent' => $percent,
      'message' => $message,
      'done'    => $phase === 'done',
      'restore' => $restoring,
      'source'  => $state['restore']['name'] ?? null,
      'name'    => $state['name'] ?? null,
      'rows'    => $state['rows_done'],
      'files'   => count($state['hashes']),
    ];
  }

  // ===== JOB STATE =====
  private function jobDir(string $id): string
  {
    $job = $this->work . '/' . $id;

    if (!preg_match(self::JOB_PATTERN, $id) || !is_dir($job)) {
      throw new RuntimeException('This backup job no longer exists. Please start again.');
    }

    return $job;
  }

  private function load(string $id): array
  {
    return json_decode((string) file_get_contents($this->jobDir($id) . '/state.json'), true);
  }

  private function save(array $state): void
  {
    file_put_contents($this->work . '/' . $state['id'] . '/state.json', json_encode($state));
  }

  private function uploadDir(string $id): string
  {
    $dir = $this->work . '/' . self::UPLOAD_PREFIX . $id;

    if (!preg_match(self::JOB_PATTERN, $id) || !is_dir($dir)) {
      throw new RuntimeException('This upload no longer exists. Please start again.', 404);
    }

    return $dir;
  }

  // ===== ACTIVE AND STALE JOBS =====
  private function isBusy(): bool
  {
    foreach (glob($this->work . '/*/state.json') ?: [] as $state) {
      if (time() - (int) filemtime($state) < self::BUSY_SECONDS) {
        return true;
      }
    }

    return false;
  }

  private function clearStale(): void
  {
    foreach (glob($this->work . '/*', GLOB_ONLYDIR) ?: [] as $job) {
      $state = is_file($job . '/state.json') ? $job . '/state.json' : $job . '/part';
      $age   = time() - (int) @filemtime(is_file($state) ? $state : $job);

      if ($age > self::STALE_SECONDS) {
        str_starts_with(basename($job), self::UPLOAD_PREFIX) ? $this->removeDir($job) : $this->cancel(basename($job));
      }
    }
  }

  // ===== DATABASE AND FILESYSTEM HELPERS =====
  private function exec(string $sql, string $failure): void
  {
    if (!$this->db->query($sql)) {
      throw new RuntimeException($failure . ' (' . $this->db->error . ')');
    }
  }

  private function requireZip(): void
  {
    if (!class_exists('ZipArchive')) {
      throw new RuntimeException('The PHP zip extension is not available on this server.');
    }
  }

  private function ensureDir(string $path): void
  {
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
      throw new RuntimeException('Could not create the backup folder.');
    }

    $guard = $this->dir . '/.htaccess';
    if (!is_file($guard)) {
      file_put_contents($guard, self::HTACCESS);
    }
  }

  private function passphrase(): string
  {
    return (string) EnvLoader::get('BACKUP_PASSPHRASE', '');
  }

  private function encryptionEnabled(): bool
  {
    $passphrase = $this->passphrase();

    if ($passphrase === '') {
      return false;
    }

    if (strlen($passphrase) < self::PHRASE_LENGTH) {
      throw new RuntimeException('BACKUP_PASSPHRASE must be at least ' . self::PHRASE_LENGTH . ' characters long.');
    }

    if (!defined('ZipArchive::EM_AES_256') || !method_exists('ZipArchive', 'setEncryptionName')) {
      throw new RuntimeException('This server cannot make encrypted backups. It needs libzip 1.2 or newer.');
    }

    return true;
  }

  private function protect(ZipArchive $zip, string $entry, array $state): void
  {
    if (!empty($state['encrypted']) && !$zip->setEncryptionName($entry, ZipArchive::EM_AES_256, $this->passphrase())) {
      throw new RuntimeException('Could not encrypt the backup file.');
    }
  }

  private function checkPassphrase(string $path): void
  {
    if ($this->passphrase() === '') {
      throw new RuntimeException('This backup is encrypted. Set BACKUP_PASSPHRASE in your .env file to the passphrase it was made with.');
    }

    $zip     = $this->openForRead($path, true);
    $in      = $zip->getStream('database.sql');
    $matches = $in !== false;

    if ($matches) {
      fclose($in);
    }

    $zip->close();

    if (!$matches) {
      throw new RuntimeException('BACKUP_PASSPHRASE does not match the passphrase this backup was made with.');
    }
  }

  // ===== FIND THE UNFINISHED AUTOMATIC BACKUP =====
  private function automaticJob(): ?string
  {
    foreach (glob($this->work . '/*/state.json') ?: [] as $file) {
      $state = json_decode((string) @file_get_contents($file), true);

      if (!empty($state['automatic']) && !isset($state['restore'])) {
        return basename(dirname($file));
      }
    }

    return null;
  }

  // ===== CHECK ONE BACKUP ENTRY AGAINST ITS CHECKSUM =====
  private function checkEntry(ZipArchive $zip, array $entry): void
  {
    $in = $zip->getStream($entry['name']);
    if (!$in) {
      throw new RuntimeException('The backup is missing: ' . $entry['name']);
    }

    [$size, $digest] = $this->readHashed($in);

    if (($entry['size'] !== null && $size !== $entry['size']) || !hash_equals($entry['sha256'], $digest)) {
      throw new RuntimeException('This backup failed its integrity check at ' . $entry['name'] . '.');
    }
  }

  // ===== READ A STREAM, HASH IT AND OPTIONALLY COPY IT =====
  private function readHashed($in, $out = null): array
  {
    $hash = hash_init('sha256');
    $size = 0;

    while (!feof($in)) {
      $chunk = fread($in, 1048576);
      if ($chunk === false) {
        break;
      }
      hash_update($hash, $chunk);
      $size += strlen($chunk);
      if ($out) {
        fwrite($out, $chunk);
      }
    }

    fclose($in);
    return [$size, hash_final($hash)];
  }

  private function openForRead(string $path, bool $encrypted): ZipArchive
  {
    $zip = new ZipArchive();

    if ($zip->open($path, ZipArchive::RDONLY) !== true) {
      throw new RuntimeException('Could not open the backup file.');
    }

    if ($encrypted) {
      $zip->setPassword($this->passphrase());
    }

    return $zip;
  }

  private function openZip(string $path): ZipArchive
  {
    $zip = new ZipArchive();

    if ($zip->open($path, ZipArchive::CREATE) !== true) {
      throw new RuntimeException('Could not open the backup file for writing.');
    }

    return $zip;
  }

  private function removeDir(string $path): void
  {
    $items = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
      $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
  }
}
