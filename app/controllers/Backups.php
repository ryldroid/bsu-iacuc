<?php

class Backups extends Controller
{
  private Backup $backup;

  // ===== SETUP =====
  public function __construct()
  {
    parent::__construct();
    $this->backup = new Backup($this->userModel->connection);
  }

  // ===== LIST BACKUPS =====
  public function all(): void
  {
    $this->jsonResponse([
      'ok'     => true,
      'html'   => $this->render('backup-results', ['backups' => $this->backup->all()]),
      'health' => $this->render('backup-health', ['health' => $this->backup->health()]),
    ]);
  }

  // ===== START A BACKUP =====
  public function start(): void
  {
    $this->guardPost();

    try {
      $status = $this->backup->start($this->actor()['name']);
    } catch (RuntimeException $e) {
      $this->jsonError($e->getCode() === 409 ? 409 : 500, $e->getMessage());
    }

    $this->jsonResponse(['ok' => true, 'status' => $status]);
  }

  // ===== START A RESTORE =====
  public function restore(): void
  {
    $this->guardPost();

    $actor  = $this->actor();
    $record = $this->userModel->getUser($actor['id']);

    if (($_POST['confirm'] ?? '') !== 'RESTORE' || !$record || !password_verify($_POST['password'] ?? '', $record['password'])) {
      $this->userModel->logAudit('backup_restore_denied', $actor['id'], $actor['name'], $actor['role'], 'backup', null, 'Incorrect password or confirmation while attempting a restore');
      $this->jsonError(403, 'Incorrect password or confirmation text.');
    }

    try {
      $status = $this->backup->startRestore((string) ($_POST['name'] ?? ''), $actor['name']);
    } catch (RuntimeException $e) {
      $this->jsonError(in_array($e->getCode(), [404, 409], true) ? $e->getCode() : 500, $e->getMessage());
    }

    $this->jsonResponse(['ok' => true, 'status' => $status]);
  }

  // ===== RUN THE NEXT STEP =====
  public function step(): void
  {
    $this->guardPost();

    $actor = $this->actor();
    $id    = (string) ($_POST['id'] ?? '');

    session_write_close();
    ignore_user_abort(true);
    @set_time_limit(60);

    try {
      $status = $this->backup->step($id);
    } catch (Throwable $e) {
      if ($e->getCode() !== 409) {
        $this->backup->cancel($id);
      }
      error_log('Backup step failed: ' . $e->getMessage());
      $this->jsonError($e->getCode() === 409 ? 409 : 500, $e instanceof RuntimeException ? $e->getMessage() : 'The backup failed. Please try again.');
    }

    if ($status['done']) {
      $restored = $status['restore'];
      $this->userModel->logAudit(
        $restored ? 'backup_restored' : 'backup_created',
        $actor['id'],
        $actor['name'],
        $actor['role'],
        'backup',
        null,
        $restored ? 'Restored backup ' . $status['source'] . ' (safety backup ' . $status['name'] . ')' : 'Created backup ' . $status['name']
      );
    }

    $this->jsonResponse(['ok' => true, 'status' => $status]);
  }

  // ===== AUTOMATIC BACKUP (EXTERNAL PINGER) =====
  public function auto(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->jsonError(405, 'Method not allowed.');
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? (getallheaders()['Authorization'] ?? '');
    $token      = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';

    if (strlen(BACKUP_CRON_SECRET) < 32 || !hash_equals(BACKUP_CRON_SECRET, $token)) {
      $this->jsonError(401, 'Invalid backup secret.');
    }

    session_write_close();
    ignore_user_abort(true);
    @set_time_limit(60);

    try {
      $status = $this->backup->automatic();
    } catch (Throwable $e) {
      error_log('Automatic backup failed: ' . $e->getMessage());
      (new NotificationModel())->warnStaffAboutBackup($this->backup);
      $this->jsonError($e->getCode() === 409 ? 409 : 500, 'The automatic backup could not continue.');
    }

    if (!empty($status['done'])) {
      $this->userModel->logAudit('backup_created', null, 'Automatic', 'system', 'backup', null, 'Created backup ' . $status['name']);
    }

    $this->jsonResponse(['ok' => true, 'status' => $status]);
  }

  // ===== VERIFY A BACKUP =====
  public function verify(): void
  {
    $this->guardPost();

    $actor = $this->actor();
    $name  = (string) ($_POST['name'] ?? '');

    session_write_close();
    ignore_user_abort(true);
    @set_time_limit(60);

    try {
      $status = $this->backup->verify($name, (int) ($_POST['offset'] ?? 0));
    } catch (RuntimeException $e) {
      $this->jsonError($e->getCode() === 404 ? 404 : 500, $e->getMessage());
    }

    if ($status['done']) {
      $this->userModel->logAudit('backup_verified', $actor['id'], $actor['name'], $actor['role'], 'backup', null, 'Verified backup ' . $name);
    }

    $this->jsonResponse(['ok' => true, 'status' => $status]);
  }

  // ===== DOWNLOAD A BACKUP =====
  public function download(string $name = ''): void
  {
    $path = $this->backup->path($name);

    if ($path === null) {
      $this->renderError(404, 'Backup Not Found', ['That backup no longer exists.']);
      return;
    }

    $actor = $this->actor();
    $this->userModel->logAudit('backup_downloaded', $actor['id'], $actor['name'], $actor['role'], 'backup', null, 'Downloaded backup ' . $name);

    session_write_close();
    @set_time_limit(0);

    while (ob_get_level() > 0) {
      ob_end_clean();
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
  }

  // ===== DELETE A BACKUP =====
  public function delete(): void
  {
    $this->guardPost();

    $name = (string) ($_POST['name'] ?? '');

    if (!$this->backup->delete($name)) {
      $this->jsonError(404, 'That backup no longer exists.');
    }

    $actor = $this->actor();
    $this->userModel->logAudit('backup_deleted', $actor['id'], $actor['name'], $actor['role'], 'backup', null, 'Deleted backup ' . $name);

    $this->jsonResponse(['ok' => true]);
  }

  // ===== UPLOAD A BACKUP: START =====
  public function uploadStart(): void
  {
    $this->guardPost();

    try {
      $id = $this->backup->startUpload((int) ($_POST['size'] ?? 0));
    } catch (RuntimeException $e) {
      $this->uploadError($e);
    }

    $this->jsonResponse(['ok' => true, 'id' => $id]);
  }

  // ===== UPLOAD A BACKUP: ONE PIECE =====
  public function uploadChunk(): void
  {
    $this->guardPost();
    session_write_close();

    $chunk = $_FILES['chunk'] ?? null;

    if (!$chunk || $chunk['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($chunk['tmp_name'])) {
      $this->jsonError(400, 'The upload piece did not arrive. Please try again.');
    }

    try {
      $size = $this->backup->addChunk((string) ($_POST['id'] ?? ''), (int) ($_POST['offset'] ?? 0), $chunk['tmp_name']);
    } catch (RuntimeException $e) {
      $this->uploadError($e);
    }

    $this->jsonResponse(['ok' => true, 'size' => $size]);
  }

  // ===== UPLOAD A BACKUP: FINISH =====
  public function uploadFinish(): void
  {
    $this->guardPost();

    try {
      $name = $this->backup->finishUpload((string) ($_POST['id'] ?? ''));
    } catch (RuntimeException $e) {
      $this->uploadError($e);
    }

    $actor = $this->actor();
    $this->userModel->logAudit('backup_uploaded', $actor['id'], $actor['name'], $actor['role'], 'backup', null, 'Uploaded backup ' . $name);

    $this->jsonResponse(['ok' => true, 'name' => $name]);
  }

  // ===== UPLOAD ERROR RESPONSE =====
  private function uploadError(RuntimeException $e): void
  {
    $this->jsonError(in_array($e->getCode(), [404, 409, 413], true) ? $e->getCode() : 500, $e->getMessage());
  }

  // ===== RENDER A PARTIAL TO HTML =====
  private function render(string $partial, array $data): string
  {
    extract($data);
    ob_start();
    include dirname(__DIR__) . '/views/personnel/partials/' . $partial . '.view.php';
    return ob_get_clean();
  }

  // ===== POST AND CSRF GUARD =====
  private function guardPost(): void
  {
    $this->requirePostMethod();
    $this->verifyCsrfToken(false);
  }
}
