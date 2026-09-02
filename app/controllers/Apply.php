<?php

/**
 * Routes:
 *   GET  /apply                        → upload form (researcher)
 *   POST /apply/submit                 → save new protocol + upload file
 *   POST /apply/reupload               → upload a new version (status must be Needs Revision)
 *   GET  /apply/viewer/{id}/{versionId?} → PDF.js viewer for a protocol (versionId optional; defaults to latest, used by "Show History" to open a specific past version read-only)
 *   GET  /apply/file/{vid}             → stream a file by version id
 *   GET  /apply/hascert                → check if researcher has cert on file
 *   GET  /apply/versions/{id}          → protocol file version history (JSON)
 *   GET  /apply/allversions/{id}       → all file versions (JSON)
 *   GET  /apply/cert/{userId}          → stream a researcher's stored certificate
 *   GET  /apply/clearance/{id}         → redirect to latest clearance file
 *   POST /apply/clearance_upload       → attach clearance doc and mark Approved (admin)
 *   GET|POST /apply/annotate           → get/save/edit/delete annotations (JSON)
 *   POST /apply/status                 → update protocol status (JSON)
 *   POST /apply/return_revision        → return for revision with reasons (JSON)
 *   GET  /apply/returnreason/{id}      → get latest return reason (JSON)
 *   POST /apply/reuploadcert           → replace researcher's stored certificate
 *   POST /apply/reuploadauth           → replace authorization letter
 *   GET  /apply/draft                  → load the current user's in-progress draft (JSON)
 *   POST /apply/draftsave              → save draft step/checkboxes/title (JSON)
 *   POST /apply/draftupload            → upload a draft file (protocol/cert/auth)
 *   GET  /apply/draftfile/{key}        → stream a draft file belonging to the current user
 *   POST /apply/draftremovefile        → remove a single draft file
 *   POST /apply/draftclear             → discard the current user's draft entirely
 */

class Apply extends Controller
{
    public function __construct()
    {
        require_once dirname(__DIR__) . '/models/ProtocolModel.php';
        require_once dirname(__DIR__) . '/models/UserModel.php';
        require_once dirname(__DIR__) . '/models/DraftModel.php';
    }

    // ===== HELPERS =====

    private function requireProtocolAccess(array $protocol, int $userId, string $role): void
    {
        if ((int) $protocol['user_id'] !== $userId && !in_array($role, ['admin', 'reviewer'])) {
            $this->jsonError(403, 'Access denied.');
        }
    }

    private function notifyStatusChange(array $protocol, string $newStatus): void
    {
        $owner = (new UserModel())->getUser((int) $protocol['user_id']);
        if (!$owner) {
            return;
        }

        $title = $protocol['research_title'] ?? 'Untitled Protocol';

        Notifier::send(
            (int) $protocol['user_id'],
            'protocol_status_changed',
            'Protocol Status Updated',
            "Your protocol \"$title\" is now: $newStatus.",
            'submissions',
            [
                'template' => 'protocol_status_changed',
                'vars'     => ['first_name' => $owner['first_name'] ?? '', 'title' => $title, 'status' => $newStatus],
                'to'       => $owner['email'] ?? '',
                'name'     => $owner['first_name'] ?? '',
                'subject'  => 'Protocol Status Updated',
            ]
        );
    }

    private function addFileUrls(array $versions): array
    {
        return array_map(fn($v) => $v + ['file_url' => ROOT . '/apply/file/' . (int) $v['id']], $versions);
    }

    private function protocolDir(int $protocolId): string
    {
        return dirname(__DIR__, 2) . '/storage/uploads/protocols/' . $protocolId . '/';
    }

    private function draftDir(int $userId): string
    {
        return dirname(__DIR__, 2) . '/storage/uploads/drafts/' . $userId . '/';
    }

    private function relPath(int $protocolId, string $absPath): string
    {
        return $protocolId . '/' . basename($absPath);
    }

    private function describeMime(string $mime): string
    {
        $known = [
            'image/webp'    => 'a WebP image',
            'image/gif'     => 'a GIF image',
            'image/bmp'     => 'a BMP image',
            'image/heic'    => 'a HEIC image',
            'image/heif'    => 'a HEIF image',
            'image/tiff'    => 'a TIFF image',
            'image/svg+xml' => 'an SVG image',
            'application/pdf' => 'a PDF',
        ];
        return $known[$mime] ?? "a \"$mime\" file";
    }

    private function saveUpload(string $inputName, string $dir, array $allowedExts, bool $required, ?string &$reason = null): array|false|null
    {
        if (empty($_FILES[$inputName]['tmp_name']) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
            return $required ? false : null;
        }

        $file = $_FILES[$inputName];

        if ($file['size'] > 10 * 1024 * 1024) {
            $reason = 'That file is larger than the 10 MB limit.';
            return false;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            $reason = 'That file type isn\'t supported.';
            return false;
        }

        $finfo   = new finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($file['tmp_name']);

        $mimeMap = [
            'pdf'  => ['application/pdf'],
            'jpg'  => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'png'  => ['image/png', 'image/x-png'],
        ];

        if ($inputName === 'protocol_file' || $inputName === 'clearance_file') {
            if ($ext !== 'pdf' || !in_array($mime, $mimeMap['pdf'], true)) {
                $reason = 'That file isn\'t actually a PDF (it looks like it\'s ' . $this->describeMime($mime) . '), even though it\'s named .pdf.';
                return false;
            }
        } else {
            if (!isset($mimeMap[$ext]) || !in_array($mime, $mimeMap[$ext], true)) {
                $reason = 'Invalid file format: '
                    . 'The file extension does not match the actual file format. '
                    . 'This can happen with images downloaded from Facebook or Messenger. '
                    . 'Please save or export the file as PNG or JPG, then upload it again. '
                    . 'PDF files are also accepted.';
                return false;
            }
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $safeName      = bin2hex(random_bytes(8)) . '.' . $ext;
        $dest          = $dir . $safeName;
        $cleanOriginal = preg_replace('/[^\w.\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME)) . '.' . $ext;

        return move_uploaded_file($file['tmp_name'], $dest) ? [$dest, $cleanOriginal] : false;
    }

    private function streamFile(string $filePath, string $displayName): void
    {
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        $normalize = [
            'image/x-png' => 'image/png',
            'image/pjpeg' => 'image/jpeg',
        ];
        $mimeType = $normalize[$mimeType] ?? $mimeType;

        if (!in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            $this->jsonError(403, 'File type not permitted.');
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . addslashes($displayName) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($filePath);
        exit;
    }

    // ===== UPLOAD FORM  (GET /apply) =====

    public function index(): void
    {
        $this->requireLogin();
        $this->view('apply');
    }

    // ===== FIRST UPLOAD  (POST /apply/submit) =====

    public function submit(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');
        $this->requirePostMethod();

        $model      = new ProtocolModel();
        $userModel  = new UserModel();
        $draftModel = new DraftModel();
        $actor      = $this->actor();

        $draft = $draftModel->getByUser($actor['id']);
        if (!$draft) {
            $this->jsonError(422, 'No draft found. Please fill out the application first.');
        }

        $title = trim($draft['title'] ?? '');
        if ($title === '') {
            $this->jsonError(422, 'Protocol title is required.');
        }

        $isPi = (bool) $draft['is_pi'];

        $draftDirAbs = dirname(__DIR__, 2) . '/storage/uploads/drafts/';

        $docRelPath = $draft['protocol_file_path'] ?? null;
        if (!$docRelPath || !is_file($draftDirAbs . $docRelPath)) {
            $this->jsonError(422, 'Please upload your completed protocol form.');
        }
        $docOriginalName = $draft['protocol_file_name'];

        $existingCert = $userModel->getCert($actor['id']);
        $certRelPath  = $draft['cert_file_path'] ?? null;
        if (!$existingCert && (!$certRelPath || !is_file($draftDirAbs . $certRelPath))) {
            $this->jsonError(422, 'Please upload your IACUC Training Certificate.');
        }
        $certOriginalName = $draft['cert_file_name'];

        $authRelPath = $draft['auth_file_path'] ?? null;
        if (!$isPi && (!$authRelPath || !is_file($draftDirAbs . $authRelPath))) {
            $this->jsonError(422, 'Please upload the Authorization Letter, or confirm that you are the Principal Investigator.');
        }
        $authOriginalName = $draft['auth_file_name'];

        $protocolId = $model->insertProtocol($actor['id'], $title, $isPi);
        if (!$protocolId) {
            $this->jsonError(500, 'Could not create protocol record. Please try again.');
        }

        $finalDir = $this->protocolDir($protocolId);
        if (!is_dir($finalDir)) {
            mkdir($finalDir, 0750, true);
        }

        $docFinal = $finalDir . basename($docRelPath);
        rename($draftDirAbs . $docRelPath, $docFinal);
        $model->insertVersion($protocolId, $this->relPath($protocolId, $docFinal), $docOriginalName, $actor['id'], 'protocol');

        if ($certRelPath && is_file($draftDirAbs . $certRelPath)) {
            $certFinal = $finalDir . basename($certRelPath);
            rename($draftDirAbs . $certRelPath, $certFinal);
            $relCert = $this->relPath($protocolId, $certFinal);
            $model->insertVersion($protocolId, $relCert, $certOriginalName, $actor['id'], 'cert');
            $userModel->saveCert($actor['id'], $relCert, $certOriginalName);
        }

        if ($authRelPath && is_file($draftDirAbs . $authRelPath)) {
            $authFinal = $finalDir . basename($authRelPath);
            rename($draftDirAbs . $authRelPath, $authFinal);
            $model->insertVersion($protocolId, $this->relPath($protocolId, $authFinal), $authOriginalName, $actor['id'], 'auth');
        }

        $draftModel->clear($actor['id']);

        $model->logAudit('protocol_submitted', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Protocol submitted: $title");

        $submitter = $userModel->getUser($actor['id']);

        Notifier::send(
            $actor['id'],
            'protocol_submitted',
            'Protocol Submitted',
            "Your protocol \"$title\" has been submitted and is now under review.",
            'submissions',
            [
                'template' => 'protocol_submitted',
                'vars'     => ['first_name' => $submitter['first_name'] ?? '', 'title' => $title],
                'to'       => $submitter['email'] ?? '',
                'name'     => $submitter['first_name'] ?? '',
                'subject'  => 'Protocol Submitted',
            ]
        );

        Notifier::sendToRole(
            'admin',
            'new_submission_admin',
            'New Protocol Submitted',
            ($submitter['first_name'] ?? '') . ' ' . ($submitter['last_name'] ?? '') . " submitted \"$title\".",
            'admin/records',
            [
                'template' => 'protocol_submitted_admin',
                'vars'     => ['submitter' => trim(($submitter['first_name'] ?? '') . ' ' . ($submitter['last_name'] ?? '')), 'title' => $title],
                'subject'  => 'New Protocol Submission',
            ]
        );

        echo json_encode(['success' => true, 'protocolId' => $protocolId]);
        exit;
    }

    // ===== HAS-CERT CHECK  (GET /apply/hascert) =====

    public function hascert(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $userModel = new UserModel();
        $actor     = $this->actor();

        echo json_encode(['has_cert' => $userModel->hasCert($actor['id'])]);
        exit;
    }

    // ===== DRAFT  (GET /apply/draft) =====

    public function draft(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        $row   = (new DraftModel())->getByUser($actor['id']);

        if (!$row) {
            echo json_encode(['exists' => false]);
            exit;
        }

        $fileInfo = function (string $key) use ($actor, $row): ?array {
            $path = $row[$key . '_file_path'] ?? null;
            if (!$path) {
                return null;
            }
            $abs = dirname(__DIR__, 2) . '/storage/uploads/drafts/' . $path;
            return [
                'name' => $row[$key . '_file_name'],
                'size' => is_file($abs) ? filesize($abs) : null,
                'url'  => ROOT . '/apply/draftfile/' . $key,
            ];
        };

        echo json_encode([
            'exists'        => true,
            'step'          => (int) $row['step'],
            'agreedTerms'   => (bool) $row['agreed_terms'],
            'agreedPrivacy' => (bool) $row['agreed_privacy'],
            'isPi'          => $row['is_pi'] === null ? null : (bool) $row['is_pi'],
            'title'         => $row['title'],
            'protocol'      => $fileInfo('protocol'),
            'cert'          => $fileInfo('cert'),
            'auth'          => $fileInfo('auth'),
        ]);
        exit;
    }

    // ===== DRAFT SAVE  (POST /apply/draftsave) =====

    public function draftsave(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');
        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $actor = $this->actor();

        $isPi = array_key_exists('isPi', $body) ? $body['isPi'] : null;

        $ok = (new DraftModel())->saveFields(
            $actor['id'],
            (int) ($body['step'] ?? 0),
            (bool) ($body['agreedTerms'] ?? false),
            (bool) ($body['agreedPrivacy'] ?? false),
            $isPi === null ? null : (bool) $isPi,
            (string) ($body['title'] ?? '')
        );

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== DRAFT UPLOAD  (POST /apply/draftupload) =====

    public function draftupload(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');
        $this->requirePostMethod();

        $actor = $this->actor();
        $key   = $_POST['key'] ?? '';

        $fieldMap = ['protocol' => 'protocol_file', 'cert' => 'cert', 'auth' => 'auth'];
        if (!isset($fieldMap[$key])) {
            $this->jsonError(400, 'Invalid file key.');
        }

        $allowedExts = $key === 'protocol' ? ['pdf'] : ['pdf', 'jpg', 'jpeg', 'png'];

        $reason = null;
        $upload = $this->saveUpload($fieldMap[$key], $this->draftDir($actor['id']), $allowedExts, required: true, reason: $reason);
        if ($upload === false) {
            $this->jsonError(422, $reason ?? 'Upload failed.');
        }
        [$absPath, $originalName] = $upload;

        $model = new DraftModel();
        $old   = $model->getByUser($actor['id']);
        $oldPath = $old[$key . '_file_path'] ?? null;

        $relPath = $this->relPath($actor['id'], $absPath);
        $model->saveFile($actor['id'], $key, $relPath, $originalName);

        if ($oldPath) {
            @unlink(dirname(__DIR__, 2) . '/storage/uploads/drafts/' . $oldPath);
        }

        echo json_encode([
            'ok'   => true,
            'name' => $originalName,
            'size' => filesize($absPath),
            'url'  => ROOT . '/apply/draftfile/' . $key,
        ]);
        exit;
    }

    // ===== DRAFT FILE  (GET /apply/draftfile/{key}) =====

    public function draftfile(string $key = ''): void
    {
        $this->requireLogin();

        $actor = $this->actor();
        $row   = (new DraftModel())->getByUser($actor['id']);

        $path = $row[$key . '_file_path'] ?? null;
        if (!$row || !$path) {
            $this->jsonError(404, 'File not found.');
        }

        $abs = dirname(__DIR__, 2) . '/storage/uploads/drafts/' . $path;
        if (!is_file($abs)) {
            $this->jsonError(404, 'File not found.');
        }

        $this->streamFile($abs, $row[$key . '_file_name']);
    }

    // ===== DRAFT REMOVE FILE  (POST /apply/draftremovefile) =====

    public function draftremovefile(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');
        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $key  = $body['key'] ?? '';
        $actor = $this->actor();

        if (!in_array($key, ['protocol', 'cert', 'auth'], true)) {
            $this->jsonError(400, 'Invalid file key.');
        }

        $model = new DraftModel();
        $row   = $model->getByUser($actor['id']);
        $path  = $row[$key . '_file_path'] ?? null;

        $ok = $model->removeFile($actor['id'], $key);

        if ($path) {
            @unlink(dirname(__DIR__, 2) . '/storage/uploads/drafts/' . $path);
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== DRAFT CLEAR  (POST /apply/draftclear) =====

    public function draftclear(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');
        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $actor = $this->actor();
        $model = new DraftModel();
        $row   = $model->getByUser($actor['id']);

        if ($row) {
            foreach (['protocol', 'cert', 'auth'] as $key) {
                $path = $row[$key . '_file_path'] ?? null;
                if ($path) {
                    @unlink(dirname(__DIR__, 2) . '/storage/uploads/drafts/' . $path);
                }
            }
        }

        $ok = $model->clear($actor['id']);

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== VERSION HISTORY  (GET /apply/versions/{protocolId}) =====

    public function versions(int $protocolId = 0): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol ID.');
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $actor = $this->actor();
        $this->requireProtocolAccess($protocol, $actor['id'], $actor['role']);

        $versions = $this->addFileUrls($model->getVersions($protocolId, 'protocol'));

        echo json_encode([
            'protocol_id' => $protocolId,
            'title'       => $protocol['research_title'],
            'versions'    => $versions,
        ]);
        exit;
    }

    // ===== VIEW RESEARCHER'S STORED CERTIFICATE  (GET /apply/cert/{userId}) =====

    public function cert(int $userId = 0): void
    {
        $this->requireLogin();

        $actor            = $this->actor();
        $isOwnCertificate = $userId === $actor['id'];

        if (!$isOwnCertificate && !in_array($actor['role'], ['admin', 'reviewer'])) {
            http_response_code(403);
            echo 'Access denied.';
            exit;
        }

        if ($userId < 1) {
            http_response_code(400);
            echo 'Missing user ID.';
            exit;
        }

        $userModel = new UserModel();
        $cert      = $userModel->getCert($userId);

        if (!$cert) {
            http_response_code(404);
            echo 'No certificate on file for this researcher.';
            exit;
        }

        $filePath = dirname(__DIR__, 2) . '/storage/uploads/protocols/' . $cert['cert_path'];

        if (!file_exists($filePath) || !is_readable($filePath)) {
            http_response_code(404);
            echo 'Certificate file not found on server.';
            exit;
        }

        $this->streamFile($filePath, $cert['cert_original_name'] ?: basename($filePath));
    }

    // ===== RE-UPLOAD  (POST /apply/reupload) =====

    public function reupload(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');
        $this->requirePostMethod();

        $protocolId = (int) ($_POST['protocol_id'] ?? 0);
        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol_id.');
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $actor = $this->actor();

        if ((int) $protocol['user_id'] !== $actor['id']) {
            $this->jsonError(403, 'Access denied.');
        }
        if (strtolower($protocol['status']) !== 'needs revision') {
            $this->jsonError(422, 'This protocol is not awaiting revision.');
        }

        $docReason = null;
        $docUpload = $this->saveUpload('protocol_file', $this->protocolDir($protocolId), ['pdf'], required: false, reason: $docReason);
        if ($docUpload === false) {
            $this->jsonError(422, $docReason ?? 'Upload failed. Only PDF files are accepted (max 10 MB).');
        }

        if ($docUpload !== null) {
            [$docPath, $docOriginalName] = $docUpload;
            $versionId = $model->insertVersion($protocolId, $this->relPath($protocolId, $docPath), $docOriginalName, $actor['id'], 'protocol');
            if (!$versionId) {
                $this->jsonError(500, 'Could not record new version.');
            }
        }

        $model->updateStatus($protocolId, 'Under Review');
        $this->notifyStatusChange($protocol, 'Under Review');
        $model->logAudit('protocol_revised', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Protocol # $protocolId resubmitted");
        $_SESSION['flash_success'] = 'Your protocol has been resubmitted and is back under review.';

        echo json_encode(['success' => true]);
        exit;
    }

    // ===== VIEWER  (GET /apply/viewer/{protocolId}) =====

    public function viewer(int $protocolId = 0, int $versionId = 0): void
    {
        $this->requireLogin();

        if ($protocolId < 1) {
            http_response_code(400);
            echo 'Missing protocol ID.';
            exit;
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            http_response_code(404);
            echo 'Protocol not found.';
            exit;
        }

        $actor = $this->actor();
        $this->requireProtocolAccess($protocol, $actor['id'], $actor['role']);

        $latestVersion = $model->getLatestVersion($protocolId, 'protocol');

        if ($versionId > 0) {
            $version = $model->getVersionById($versionId);

            if (
                !$version
                || (int) $version['protocol_id'] !== $protocolId
                || $version['file_type'] !== 'protocol'
            ) {
                http_response_code(404);
                echo 'That protocol version could not be found.';
                exit;
            }
        } else {
            $version = $latestVersion;
        }

        if (!$version) {
            $this->renderError(404, 'No File Found', [
                'No protocol file has been uploaded for this submission yet.',
                'If you believe this is a mistake, please contact the IACUC office or ask the researcher to re-upload their protocol file.',
            ]);
        }

        $isLatestVersion = $latestVersion && (int) $version['id'] === (int) $latestVersion['id'];

        $isStaff    = in_array($actor['role'], ['admin', 'reviewer']);
        $backBase   = $isStaff ? ROOT . '/admin/home' : ROOT . '/submissions';
        $fromFilter = isset($_GET['from']) ? preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $_GET['from'])) : '';
        $backUrl    = $fromFilter !== '' ? $backBase . '?status=' . $fromFilter : $backBase;

        $userModel     = new UserModel();
        $hasCertOnFile = $isStaff ? false : $userModel->hasCert($actor['id']);

        $this->view('protocol', [
            'protocol'          => $protocol,
            'version'           => $version,
            'versions'          => $model->getVersions($protocolId, 'protocol'),
            'fromFilter'        => $fromFilter,
            'isLatestVersion'   => $isLatestVersion,
            'csrf'              => $this->generateCsrfToken(),
            'isStaff'           => $isStaff,
            'isAdmin'           => $actor['role'] === 'admin',
            'isReviewer'        => $actor['role'] === 'reviewer',
            'backUrl'           => $backUrl,
            'latestCertVersion' => $model->getLatestVersionAsOf($protocolId, 'cert', $version['uploaded_at']),
            'latestAuthVersion' => $model->getLatestVersionAsOf($protocolId, 'auth', $version['uploaded_at']),
            'returnReason'      => $model->getLatestReturnReason($protocolId),
            'hasCertOnFile'     => $hasCertOnFile,
        ]);
    }

    // ===== FILE SERVER  (GET /apply/file/{versionId}) =====

    public function file(int $versionId = 0): void
    {
        $this->requireLogin();

        if ($versionId < 1) {
            http_response_code(400);
            echo 'Missing version ID.';
            exit;
        }

        $model   = new ProtocolModel();
        $version = $model->getVersionById($versionId);

        if (!$version) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }

        $actor = $this->actor();

        if ((int) $version['owner_id'] !== $actor['id'] && !in_array($actor['role'], ['admin', 'reviewer'])) {
            http_response_code(403);
            echo 'Access denied.';
            exit;
        }

        $filePath = dirname(__DIR__, 2) . '/storage/uploads/protocols/' . $version['file_path'];

        if (!file_exists($filePath) || !is_readable($filePath)) {
            http_response_code(404);
            echo 'File not found on server.';
            exit;
        }

        $this->streamFile($filePath, $version['original_name'] ?: basename($filePath));
    }

    // ===== ANNOTATION API  (GET|POST /apply/annotate) =====

    public function annotate(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->handleGetAnnotations();
            return;
        }

        $actor = $this->actor();
        if (!in_array($actor['role'], ['admin', 'reviewer'])) {
            $this->jsonError(403, 'Staff only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $model  = new ProtocolModel();

        match ($body['action'] ?? '') {
            'save'   => $this->handleSaveAnnotation($model, $body),
            'edit'   => $this->handleEditAnnotation($model, $body),
            'delete' => $this->handleDeleteAnnotation($model, $body),
            default  => $this->jsonError(400, 'Unknown action.'),
        };
    }

    private function handleGetAnnotations(): void
    {
        $versionId = (int) ($_GET['version_id'] ?? 0);
        if ($versionId < 1) {
            $this->jsonError(400, 'Missing version_id.');
        }

        $model   = new ProtocolModel();
        $version = $model->getVersionById($versionId);

        if (!$version) {
            $this->jsonError(404, 'Version not found.');
        }

        $actor = $this->actor();

        if ((int) $version['owner_id'] !== $actor['id'] && !in_array($actor['role'], ['admin', 'reviewer'])) {
            $this->jsonError(403, 'Forbidden.');
        }

        if (!in_array($actor['role'], ['admin', 'reviewer'])) {
            $latestVersion   = $model->getLatestVersion((int) $version['protocol_id'], 'protocol');
            $isLatestVersion = $latestVersion && (int) $latestVersion['id'] === $versionId;

            if ($isLatestVersion && strtolower($version['protocol_status'] ?? '') !== 'needs revision') {
                echo json_encode([]);
                exit;
            }
        }

        echo json_encode($model->getAnnotations($versionId));
        exit;
    }

    private function handleSaveAnnotation(ProtocolModel $model, array $body): void
    {
        $versionId  = (int) ($body['version_id']  ?? 0);
        $pageNumber = (int) ($body['page_number']  ?? 0);
        $x          = (float) ($body['x']          ?? -1);
        $y          = (float) ($body['y']          ?? -1);
        $width      = (float) ($body['width']      ?? -1);
        $height     = (float) ($body['height']     ?? -1);
        $comment    = trim($body['comment']        ?? '');

        if ($versionId < 1 || $pageNumber < 1 || $x < 0 || $y < 0 || $width <= 0 || $height <= 0 || $comment === '') {
            $this->jsonError(400, 'Missing or invalid fields.');
        }

        $actor = $this->actor();
        $newId = $model->insertAnnotation($versionId, $pageNumber, $x, $y, $width, $height, $comment, $actor['id']);

        if (!$newId) {
            $this->jsonError(500, 'Could not save annotation.');
        }

        echo json_encode(['id' => $newId, 'ok' => true]);
        exit;
    }

    private function handleEditAnnotation(ProtocolModel $model, array $body): void
    {
        $annotId = (int) ($body['id'] ?? 0);
        $comment = trim($body['comment'] ?? '');

        if ($annotId < 1 || $comment === '') {
            $this->jsonError(400, 'Missing or invalid fields.');
        }

        echo json_encode(['ok' => $model->updateAnnotation($annotId, $comment)]);
        exit;
    }

    private function handleDeleteAnnotation(ProtocolModel $model, array $body): void
    {
        $annotId = (int) ($body['id'] ?? 0);
        if ($annotId < 1) {
            $this->jsonError(400, 'Missing annotation id.');
        }

        echo json_encode(['ok' => $model->deleteAnnotation($annotId)]);
        exit;
    }

    // ===== STATUS UPDATE  (POST /apply/status) =====

    public function status(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if (!in_array($actor['role'], ['admin', 'reviewer'])) {
            $this->jsonError(403, 'Staff only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        $newStatus  = $body['status'] ?? '';

        if ($protocolId < 1 || $newStatus === '') {
            $this->jsonError(400, 'Invalid parameters.');
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $allowedTransitions = [
            'reviewer' => ['under review' => ['Needs Revision', 'Reviewed']],
            'admin'    => ['reviewed'     => ['Endorsed']],
        ];

        $allowedTargets = $allowedTransitions[$actor['role']][strtolower($protocol['status'])] ?? [];
        if (!in_array($newStatus, $allowedTargets, true)) {
            $this->jsonError(422, "That status change isn't allowed from the protocol's current state.");
        }

        $ok = $model->updateStatus($protocolId, $newStatus);

        if ($ok) {
            $model->logAudit('status_updated', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Status changed to: $newStatus");
            $this->notifyStatusChange($protocol, $newStatus);

            if ($newStatus === 'Reviewed') {
                require_once dirname(__DIR__) . '/models/RecordModel.php';
                $pi = trim(($protocol['submitter_first_name'] ?? '') . ' ' . ($protocol['submitter_last_name'] ?? ''));
                (new RecordModel())->insertFromProtocol($protocol['reference_no'] ?? '', $protocol['research_title'] ?? '', $pi);
            }

            $flashMessages = [
                'Needs Revision' => 'Protocol returned for revision. The researcher will be notified to make changes.',
                'Reviewed'       => 'Review finished. Protocol details have been added to the records.',
                'Endorsed'       => 'Protocol marked as endorsed.',
            ];
            $_SESSION['flash_success'] = $flashMessages[$newStatus] ?? 'Protocol status updated.';
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== CLEARANCE UPLOAD  (POST /apply/clearance_upload) =====

    public function clearance_upload(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'admin') {
            $this->jsonError(403, 'Admin only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $protocolId = (int) ($_POST['protocol_id'] ?? 0);
        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol_id.');
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (strtolower($protocol['status']) !== 'endorsed') {
            $this->jsonError(422, 'Only endorsed protocols can be marked approved.');
        }

        $docReason = null;
        $docUpload = $this->saveUpload('clearance_file', $this->protocolDir($protocolId), ['pdf'], required: true, reason: $docReason);
        if ($docUpload === false) {
            $this->jsonError(422, $docReason ?? 'Upload failed. Only PDF files are accepted (max 10 MB).');
        }
        [$docPath, $docOriginalName] = $docUpload;

        $versionId = $model->insertVersion($protocolId, $this->relPath($protocolId, $docPath), $docOriginalName, $actor['id'], 'clearance');
        if (!$versionId) {
            $this->jsonError(500, 'Could not record the clearance file. Please try again.');
        }

        $model->updateStatus($protocolId, 'Approved');
        $this->notifyStatusChange($protocol, 'Approved');
        $model->logAudit('clearance_uploaded', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Clearance uploaded for protocol # $protocolId; marked Approved");
        $_SESSION['flash_success'] = 'Clearance uploaded. The protocol has been marked as Approved.';

        echo json_encode(['success' => true]);
        exit;
    }

    // ===== CLEARANCE FILE  (GET /apply/clearance/{protocolId}) =====

    public function clearance(int $protocolId = 0): void
    {
        $this->requireLogin();

        if ($protocolId < 1) {
            http_response_code(400);
            echo 'Missing protocol ID.';
            exit;
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            http_response_code(404);
            echo 'Protocol not found.';
            exit;
        }

        $actor = $this->actor();
        $this->requireProtocolAccess($protocol, $actor['id'], $actor['role']);

        $version = $model->getLatestVersion($protocolId, 'clearance');
        if (!$version) {
            $dashboard = in_array($actor['role'], ['admin', 'reviewer']) ? 'admin/home' : 'submissions';
            $this->renderError(404, 'No Clearance File Found', [
                'No clearance document has been uploaded for this protocol yet.',
            ], [
                ['label' => '← Back to Dashboard', 'href' => ROOT . '/' . $dashboard],
            ]);
        }

        $this->redirect('apply/file/' . (int) $version['id']);
    }

    // ===== RETURN FOR REVISION WITH REASONS  (POST /apply/return_revision) =====

    public function return_revision(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'reviewer') {
            $this->jsonError(403, 'Reviewer access only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        $comment    = trim($body['comment'] ?? '');

        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol ID.');
        }

        $allowedReasons  = ['wrong_cert', 'wrong_auth'];
        $filteredReasons = array_values(array_filter($body['reasons'] ?? [], fn($r) => in_array($r, $allowedReasons, true)));

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (strtolower($protocol['status']) !== 'under review') {
            $this->jsonError(422, 'Only protocols under review can be returned for revision.');
        }

        $reasonSaved = $model->insertReturnReason($protocolId, $actor['id'], $filteredReasons, $comment);
        if (!$reasonSaved) {
            error_log("insertReturnReason failed for protocol $protocolId");
        }

        $ok = $model->updateStatus($protocolId, 'Needs Revision');

        if ($ok) {
            $detail = 'Returned for revision.'
                . ($filteredReasons ? ' Reasons: ' . implode(', ', $filteredReasons) : '')
                . ($comment !== '' ? ' Comment: ' . mb_substr($comment, 0, 200) : '');
            $model->logAudit('protocol_returned', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, $detail);
            $this->notifyStatusChange($protocol, 'Needs Revision');
            $_SESSION['flash_success'] = 'Protocol returned for revision. The researcher will be notified to make corrections.';
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== RETURN REASON  (GET /apply/returnreason/{protocolId}) =====

    public function returnreason(int $protocolId = 0): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol ID.');
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $actor = $this->actor();
        $this->requireProtocolAccess($protocol, $actor['id'], $actor['role']);

        echo json_encode(['reason' => $model->getLatestReturnReason($protocolId)]);
        exit;
    }

    // ===== REUPLOAD CERTIFICATE  (POST /apply/reuploadcert) =====

    public function reuploadcert(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');
        $this->requirePostMethod();

        $protocolId = (int) ($_POST['protocol_id'] ?? 0);
        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol_id.');
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $actor = $this->actor();

        if ((int) $protocol['user_id'] !== $actor['id']) {
            $this->jsonError(403, 'Access denied.');
        }
        if (strtolower($protocol['status']) !== 'needs revision') {
            $this->jsonError(422, 'Certificate can only be reuploaded when the protocol needs revision.');
        }

        $certReason = null;
        $certUpload = $this->saveUpload('cert_file', $this->protocolDir($protocolId), ['pdf', 'jpg', 'jpeg', 'png'], required: true, reason: $certReason);
        if ($certUpload === false) {
            $this->jsonError(422, $certReason ?? 'Upload failed. Accepted formats: PDF, JPG, PNG (max 10 MB).');
        }
        [$certPath, $certOriginalName] = $certUpload;

        $relCert   = $this->relPath($protocolId, $certPath);
        $userModel = new UserModel();
        $model->insertVersion($protocolId, $relCert, $certOriginalName, $actor['id'], 'cert');
        $userModel->saveCert($actor['id'], $relCert, $certOriginalName);

        $model->logAudit('cert_reuploaded', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Researcher reuploaded training certificate for protocol # $protocolId");

        echo json_encode(['success' => true]);
        exit;
    }

    // ===== REUPLOAD AUTH LETTER  (POST /apply/reuploadauth) =====

    public function reuploadauth(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');
        $this->requirePostMethod();

        $protocolId = (int) ($_POST['protocol_id'] ?? 0);
        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol_id.');
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $actor = $this->actor();

        if ((int) $protocol['user_id'] !== $actor['id']) {
            $this->jsonError(403, 'Access denied.');
        }
        if (strtolower($protocol['status']) !== 'needs revision') {
            $this->jsonError(422, 'Authorization letter can only be reuploaded when the protocol needs revision.');
        }

        $authReason = null;
        $authUpload = $this->saveUpload('auth_file', $this->protocolDir($protocolId), ['pdf', 'jpg', 'jpeg', 'png'], required: true, reason: $authReason);
        if ($authUpload === false) {
            $this->jsonError(422, $authReason ?? 'Upload failed. Accepted formats: PDF, JPG, PNG (max 10 MB).');
        }
        [$authPath, $authOriginalName] = $authUpload;

        $model->insertVersion($protocolId, $this->relPath($protocolId, $authPath), $authOriginalName, $actor['id'], 'auth');
        $model->logAudit('auth_reuploaded', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Researcher reuploaded authorization letter for protocol # $protocolId");

        echo json_encode(['success' => true]);
        exit;
    }

    // ===== ALL VERSIONS  (GET /apply/allversions/{protocolId}) =====

    public function allversions(int $protocolId = 0): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol ID.');
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $actor = $this->actor();
        $this->requireProtocolAccess($protocol, $actor['id'], $actor['role']);

        echo json_encode([
            'protocol_id'    => $protocolId,
            'title'          => $protocol['research_title'],
            'status'         => $protocol['status'],
            'protocol_files' => $this->addFileUrls($model->getVersions($protocolId, 'protocol')),
            'return_reason'  => $model->getLatestReturnReason($protocolId),
        ]);
        exit;
    }
}
