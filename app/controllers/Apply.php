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
 *   POST /apply/payment_proof          → researcher uploads proof of payment (JSON)
 *   POST /apply/verify_payment         → reviewer marks a protocol paid (JSON)
 *   POST /apply/undo_payment           → reviewer undoes a "mark as paid" (JSON)
 *   POST /apply/reject_payment         → reviewer rejects a payment proof with a reason (JSON)
 *   POST /apply/signed_scan_upload     → admin uploads the wet-signed scan (JSON)
 *   POST /apply/clearance_pool_upload  → reviewer uploads a BAI clearance screenshot to the pool (JSON)
 *   GET  /apply/clearance_pool         → list unassigned + staged clearance pool items (JSON, admin)
 *   GET  /apply/clearance_pool_file/{id} → stream a clearance pool item
 *   POST /apply/clearance_stage        → admin drags a screenshot onto a protocol (provisional, JSON)
 *   POST /apply/clearance_unstage      → admin undoes a stage before confirming (JSON)
 *   POST /apply/clearance_confirm      → admin commits all staged matches at once, marks Approved (JSON)
 *   POST /apply/clearance_unassign     → admin detaches an already-confirmed wrong match (JSON)
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

    private function actorDisplayName(array $actor): string
    {
        $user = (new UserModel())->getUser($actor['id']);
        $full = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        return $full !== '' ? $full : $actor['name'];
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'admin'    => 'Admin',
            'reviewer' => 'Reviewer',
            default    => 'Researcher',
        };
    }

    private function notifyProtocolRenamed(array $protocol, string $oldTitle, string $newTitle, array $actor): void
    {
        $owner = (new UserModel())->getUser((int) $protocol['user_id']);
        if (!$owner) {
            return;
        }

        $roleLabel = $this->roleLabel($actor['role']);

        Notifier::send(
            (int) $protocol['user_id'],
            'protocol_renamed',
            'Protocol Renamed',
            "\"$oldTitle\" was renamed to \"$newTitle\" by $roleLabel - {$actor['name']}.",
            'apply/viewer/' . $protocol['protocol_id'],
            [
                'template' => 'protocol_renamed',
                'vars'     => [
                    'first_name'  => $owner['first_name'] ?? '',
                    'old_title'   => $oldTitle,
                    'new_title'   => $newTitle,
                    'role_label'  => $roleLabel,
                    'actor_name'  => $actor['name'],
                    'protocol_id' => $protocol['protocol_id'],
                ],
                'to'      => $owner['email'] ?? '',
                'name'    => $owner['first_name'] ?? '',
                'subject' => 'Protocol Renamed',
            ]
        );
    }

    private function notifyStaffProtocolRenamed(array $protocol, string $oldTitle, string $newTitle, array $actor): void
    {
        $roleLabel = $this->roleLabel($actor['role']);

        foreach (['admin', 'reviewer'] as $staffRole) {
            Notifier::sendToRole(
                $staffRole,
                'protocol_renamed',
                'Protocol Renamed',
                "$roleLabel {$actor['name']} renamed \"$oldTitle\" to \"$newTitle\".",
                'apply/viewer/' . $protocol['protocol_id'],
                [
                    'template' => 'protocol_renamed_staff',
                    'vars'     => [
                        'old_title'   => $oldTitle,
                        'new_title'   => $newTitle,
                        'role_label'  => $roleLabel,
                        'actor_name'  => $actor['name'],
                        'protocol_id' => $protocol['protocol_id'],
                    ],
                    'subject' => 'Protocol Renamed',
                ]
            );
        }
    }

    private function notifyStaffProtocolResubmitted(array $protocol, array $actor): void
    {
        $title     = $protocol['research_title'] ?? 'Untitled Protocol';
        $roleLabel = $this->roleLabel($actor['role']);

        foreach (['admin', 'reviewer'] as $staffRole) {
            Notifier::sendToRole(
                $staffRole,
                'protocol_resubmitted',
                'Protocol Re-submitted',
                "$roleLabel {$actor['name']} resubmitted \"$title\" for review.",
                'apply/viewer/' . $protocol['protocol_id'],
                [
                    'template' => 'protocol_resubmitted_staff',
                    'vars'     => [
                        'title'       => $title,
                        'role_label'  => $roleLabel,
                        'actor_name'  => $actor['name'],
                        'protocol_id' => $protocol['protocol_id'],
                    ],
                    'subject' => 'Protocol Re-submitted',
                ]
            );
        }
    }

    private function notifyDeletionRequested(array $protocol, string $reason, array $actor): void
    {
        $roleLabel = $this->roleLabel($actor['role']);
        $title     = $protocol['research_title'] ?? 'Untitled Protocol';

        Notifier::sendToRole(
            'reviewer',
            'protocol_deletion_requested',
            'Deletion Requested',
            "$roleLabel {$actor['name']} requested deletion of \"$title\". Reason: $reason",
            'apply/viewer/' . $protocol['protocol_id'],
            [
                'template' => 'protocol_deletion_requested',
                'vars'     => [
                    'title'       => $title,
                    'role_label'  => $roleLabel,
                    'actor_name'  => $actor['name'],
                    'reason'      => $reason,
                    'protocol_id' => $protocol['protocol_id'],
                ],
                'subject' => 'Protocol Deletion Requested',
            ]
        );
    }

    private function notifyProtocolDeleted(array $protocol, string $reason, array $actor): void
    {
        $owner = (new UserModel())->getUser((int) $protocol['user_id']);
        if (!$owner) {
            return;
        }

        $title = $protocol['research_title'] ?? 'Untitled Protocol';

        Notifier::send(
            (int) $protocol['user_id'],
            'protocol_deleted',
            'Protocol Deleted',
            "Your protocol \"$title\" was deleted by {$actor['name']}. Reason: $reason",
            'submissions',
            [
                'template' => 'protocol_deleted',
                'vars'     => [
                    'first_name' => $owner['first_name'] ?? '',
                    'title'      => $title,
                    'actor_name' => $actor['name'],
                    'reason'     => $reason,
                ],
                'to'      => $owner['email'] ?? '',
                'name'    => $owner['first_name'] ?? '',
                'subject' => 'Protocol Deleted',
            ]
        );
    }

    private function notifyDeletionRejected(array $protocol, string $rejectionReason, array $actor): void
    {
        $requesterId = (int) ($protocol['deletion_requested_by'] ?? 0);
        if ($requesterId < 1) {
            return;
        }

        $requester = (new UserModel())->getUser($requesterId);
        if (!$requester) {
            return;
        }

        $title = $protocol['research_title'] ?? 'Untitled Protocol';

        Notifier::send(
            $requesterId,
            'protocol_deletion_rejected',
            'Deletion Request Rejected',
            "Your request to delete \"$title\" was rejected by {$actor['name']}. Reason: $rejectionReason",
            'apply/viewer/' . $protocol['protocol_id'],
            [
                'template' => 'protocol_deletion_rejected',
                'vars'     => [
                    'first_name'  => $requester['first_name'] ?? '',
                    'title'       => $title,
                    'actor_name'  => $actor['name'],
                    'reason'      => $rejectionReason,
                    'protocol_id' => $protocol['protocol_id'],
                ],
                'to'      => $requester['email'] ?? '',
                'name'    => $requester['first_name'] ?? '',
                'subject' => 'Protocol Deletion Request Rejected',
            ]
        );
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
            'apply/viewer/' . $protocol['protocol_id'],
            [
                'template' => 'protocol_status_changed',
                'vars'     => ['first_name' => $owner['first_name'] ?? '', 'title' => $title, 'status' => $newStatus, 'protocol_id' => $protocol['protocol_id']],
                'to'       => $owner['email'] ?? '',
                'name'     => $owner['first_name'] ?? '',
                'subject'  => 'Protocol Status Updated',
            ]
        );
    }

    private function notifyPaymentProofUploaded(array $protocol, array $actor): void
    {
        $title = $protocol['research_title'] ?? 'Untitled Protocol';

        Notifier::sendToRole(
            'reviewer',
            'payment_proof_uploaded',
            'Payment Proof Uploaded',
            "{$actor['name']} uploaded proof of payment for \"$title\".",
            'apply/viewer/' . $protocol['protocol_id'],
            [
                'template' => 'payment_proof_uploaded',
                'vars'     => ['title' => $title, 'actor_name' => $actor['name'], 'protocol_id' => $protocol['protocol_id']],
                'subject'  => 'Payment Proof Uploaded',
            ]
        );
    }

    private function notifyPaymentVerified(array $protocol, array $actor): void
    {
        $owner = (new UserModel())->getUser((int) $protocol['user_id']);
        $title = $protocol['research_title'] ?? 'Untitled Protocol';

        if ($owner) {
            Notifier::send(
                (int) $protocol['user_id'],
                'payment_verified',
                'Payment Verified',
                "Your payment for \"$title\" has been verified.",
                'apply/viewer/' . $protocol['protocol_id'],
                [
                    'template' => 'payment_verified',
                    'vars'     => ['first_name' => $owner['first_name'] ?? '', 'title' => $title, 'protocol_id' => $protocol['protocol_id']],
                    'to'       => $owner['email'] ?? '',
                    'name'     => $owner['first_name'] ?? '',
                    'subject'  => 'Payment Verified',
                ]
            );
        }

        Notifier::sendToRole(
            'admin',
            'protocol_paid',
            'Protocol Ready for Printing',
            "\"$title\" has been paid and is ready to print and sign.",
            'apply/viewer/' . $protocol['protocol_id'],
            [
                'template' => 'protocol_paid',
                'vars'     => ['title' => $title, 'protocol_id' => $protocol['protocol_id']],
                'subject'  => 'Protocol Ready for Printing',
            ]
        );
    }

    private function notifyPaymentRejected(array $protocol, string $reason, array $actor): void
    {
        $owner = (new UserModel())->getUser((int) $protocol['user_id']);
        if (!$owner) {
            return;
        }

        $title = $protocol['research_title'] ?? 'Untitled Protocol';

        Notifier::send(
            (int) $protocol['user_id'],
            'payment_proof_rejected',
            'Payment Proof Rejected',
            "Your payment proof for \"$title\" was rejected. Reason: $reason. Please upload a new one.",
            'apply/viewer/' . $protocol['protocol_id'],
            [
                'template' => 'payment_proof_rejected',
                'vars'     => ['first_name' => $owner['first_name'] ?? '', 'title' => $title, 'reason' => $reason, 'protocol_id' => $protocol['protocol_id']],
                'to'       => $owner['email'] ?? '',
                'name'     => $owner['first_name'] ?? '',
                'subject'  => 'Payment Proof Rejected',
            ]
        );
    }

    private function notifySignedScanUploaded(array $protocol, array $actor): void
    {
        $owner = (new UserModel())->getUser((int) $protocol['user_id']);
        if (!$owner) {
            return;
        }

        $title = $protocol['research_title'] ?? 'Untitled Protocol';

        Notifier::send(
            (int) $protocol['user_id'],
            'signed_scan_uploaded',
            'Signed Protocol Uploaded',
            "The signed scan of \"$title\" has been uploaded.",
            'apply/viewer/' . $protocol['protocol_id'],
            [
                'template' => 'signed_scan_uploaded',
                'vars'     => ['first_name' => $owner['first_name'] ?? '', 'title' => $title, 'protocol_id' => $protocol['protocol_id']],
                'to'       => $owner['email'] ?? '',
                'name'     => $owner['first_name'] ?? '',
                'subject'  => 'Signed Protocol Uploaded',
            ]
        );
    }

    private function notifyClearancePoolUploaded(array $actor, int $count): void
    {
        Notifier::sendToRole(
            'admin',
            'clearance_pool_uploaded',
            'New Clearance Screenshots',
            "{$actor['name']} added $count clearance screenshot(s) for sorting.",
            'admin/clearances',
            [
                'template' => 'clearance_pool_uploaded',
                'vars'     => ['actor_name' => $actor['name'], 'count' => $count],
                'subject'  => 'New Clearance Screenshots',
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

    private function clearancePoolDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/uploads/clearance_pool/';
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

    private function streamFile(string $filePath, string $displayName, bool $forceDownload = false): void
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

        $disposition = $forceDownload ? 'attachment' : 'inline';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($displayName) . '"');
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
            'apply/viewer/' . $protocolId,
            [
                'template' => 'protocol_submitted',
                'vars'     => ['first_name' => $submitter['first_name'] ?? '', 'title' => $title, 'protocol_id' => $protocolId],
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
            'apply/viewer/' . $protocolId,
            [
                'template' => 'protocol_submitted_admin',
                'vars'     => ['submitter' => trim(($submitter['first_name'] ?? '') . ' ' . ($submitter['last_name'] ?? '')), 'title' => $title, 'protocol_id' => $protocolId],
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
        $this->notifyStaffProtocolResubmitted($protocol, $actor);
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

        $isOwner            = (int) $protocol['user_id'] === $actor['id'];
        $statusKeyForAccess = strtolower($protocol['status']);
        $canRename          = ($isOwner || $isStaff)
            && in_array($statusKeyForAccess, ['under review', 'needs revision'], true);
        $canRequestDeletion = ($isOwner && !$isStaff) || $actor['role'] === 'admin';
        $canDelete          = $actor['role'] === 'reviewer';
        $deletionRequested  = !empty($protocol['deletion_requested_at']);
        $showTitleChangeBanner = !empty($protocol['previous_title'])
            && (int) ($protocol['title_changed_by'] ?? 0) !== $actor['id'];

        $titleHistory = $model->getTitleHistory($protocolId);
        $titleHistory = count($titleHistory) > 1 ? $titleHistory : [];

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
            'latestPaymentProofVersion' => $model->getLatestVersion($protocolId, 'payment_proof'),
            'latestSignedScanVersion'   => $model->getLatestVersion($protocolId, 'signed_scan'),
            'latestClearanceVersion'    => $model->getLatestVersion($protocolId, 'clearance'),
            'paymentRejection'  => $model->getLatestPaymentRejection($protocolId),
            'returnReason'      => $model->getLatestReturnReason($protocolId),
            'hasCertOnFile'     => $hasCertOnFile,
            'canRename'         => $canRename,
            'canRequestDeletion' => $canRequestDeletion,
            'canDelete'         => $canDelete,
            'deletionRequested' => $deletionRequested,
            'showTitleChangeBanner' => $showTitleChangeBanner,
            'titleHistory'      => $titleHistory,
            'flashSuccess'      => $_SESSION['flash_success'] ?? '',
            'flashError'        => $_SESSION['flash_error'] ?? '',
        ]);
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
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

        $forceDownload = isset($_GET['download']);

        $displayName = $version['original_name'] ?: basename($filePath);
        if ($forceDownload && !empty($version['protocol_title'])) {
            $ext         = pathinfo($filePath, PATHINFO_EXTENSION);
            $safeTitle   = preg_replace('/[^\w.\-]+/', '_', trim($version['protocol_title']));
            $displayName = $safeTitle . ($ext ? '.' . $ext : '');
        }

        $this->streamFile($filePath, $displayName, $forceDownload);
    }

    // ===== DOWNLOAD ALL PAID  (GET /apply/download_all_paid) =====
    // Zips the latest protocol PDF for every Reviewed + paid protocol, for
    // batch printing.

    public function download_all_paid(): void
    {
        $this->requireLogin();

        $actor = $this->actor();
        if ($actor['role'] !== 'admin') {
            http_response_code(403);
            echo 'Admin only.';
            exit;
        }

        if (!class_exists('ZipArchive')) {
            http_response_code(500);
            echo 'The PHP zip extension is not available on this server.';
            exit;
        }

        $rows = (new ProtocolModel())->getReviewedPaidWithLatestFile();
        $rows = array_filter($rows, fn($r) => !empty($r['file_path']));

        if (empty($rows)) {
            http_response_code(404);
            echo 'No paid, reviewed protocols to download.';
            exit;
        }

        $baseDir = dirname(__DIR__, 2) . '/storage/uploads/protocols/';
        $zipPath = tempnam(sys_get_temp_dir(), 'reviewed_paid_') . '.zip';

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $usedNames = [];
        foreach ($rows as $row) {
            $absPath = $baseDir . $row['file_path'];
            if (!file_exists($absPath)) {
                continue;
            }

            $ext       = pathinfo($absPath, PATHINFO_EXTENSION);
            $safeTitle = preg_replace('/[^\w.\-]+/', '_', trim($row['research_title'] ?: $row['reference_no'] ?: ('protocol_' . $row['id'])));
            $entryName = $safeTitle . '.' . $ext;

            $suffix = 1;
            while (in_array($entryName, $usedNames, true)) {
                $entryName = $safeTitle . '_' . (++$suffix) . '.' . $ext;
            }
            $usedNames[] = $entryName;

            $zip->addFile($absPath, $entryName);
        }
        $zip->close();

        (new ProtocolModel())->logAudit('bulk_download', $actor['id'], $actor['name'], $actor['role'], 'protocol', null, count($usedNames) . ' reviewed & paid protocol(s) downloaded as ZIP');

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="reviewed_paid_protocols.zip"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        unlink($zipPath);
        exit;
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
            'admin'    => [
                'reviewed' => ['Endorsed'],
                'endorsed' => ['Reviewed', 'Approved'],
                'approved' => ['Endorsed'],
            ],
        ];

        $allowedTargets = $allowedTransitions[$actor['role']][strtolower($protocol['status'])] ?? [];
        if (!in_array($newStatus, $allowedTargets, true)) {
            $this->jsonError(422, "That status change isn't allowed from the protocol's current state.");
        }

        $isRevert = ($newStatus === 'Reviewed' && strtolower($protocol['status']) === 'endorsed')
            || ($newStatus === 'Endorsed' && strtolower($protocol['status']) === 'approved');

        if ($newStatus === 'Endorsed' && !$isRevert) {
            if (($protocol['payment_status'] ?? 'unpaid') !== 'paid') {
                $this->jsonError(422, 'This protocol must be marked as paid before it can be endorsed.');
            }
            if (!$model->getLatestVersion($protocolId, 'signed_scan')) {
                $this->jsonError(422, 'A signed scan must be uploaded before this protocol can be endorsed.');
            }
        }

        if ($newStatus === 'Approved' && !$model->getLatestVersion($protocolId, 'clearance')) {
            $this->jsonError(422, 'A clearance document must be attached before this protocol can be approved.');
        }

        $ok = $model->updateStatus($protocolId, $newStatus);

        if ($ok) {
            $auditAction = $isRevert ? 'status_reverted' : 'status_updated';
            $model->logAudit($auditAction, $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Status changed to: $newStatus");
            $this->notifyStatusChange($protocol, $newStatus);

            if ($newStatus === 'Reviewed' && !$isRevert) {
                require_once dirname(__DIR__) . '/models/RecordModel.php';
                $pi = trim(($protocol['submitter_first_name'] ?? '') . ' ' . ($protocol['submitter_last_name'] ?? ''));
                (new RecordModel())->insertFromProtocol($protocol['reference_no'] ?? '', $protocol['research_title'] ?? '', $pi);
            }

            $flashMessages = [
                'Needs Revision' => 'Protocol returned for revision. The researcher will be notified to make changes.',
                'Reviewed'       => $isRevert ? 'Endorsement reverted. Protocol is back to Reviewed.' : 'Review finished. Protocol details have been added to the records.',
                'Endorsed'       => $isRevert ? 'Approval reverted. Protocol is back to Endorsed.' : 'Protocol marked as endorsed.',
                'Approved'       => 'Protocol marked as approved.',
            ];
            $_SESSION['flash_success'] = $flashMessages[$newStatus] ?? 'Protocol status updated.';
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== PAYMENT PROOF  (POST /apply/payment_proof) =====

    public function payment_proof(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'researcher') {
            $this->jsonError(403, 'Researcher only.');
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
        if ((int) $protocol['user_id'] !== $actor['id']) {
            $this->jsonError(403, 'Access denied.');
        }
        if (strtolower($protocol['status']) !== 'reviewed') {
            $this->jsonError(422, 'Payment proof can only be submitted once a protocol has been reviewed.');
        }

        $reason = null;
        $upload = $this->saveUpload('payment_proof_file', $this->protocolDir($protocolId), ['pdf', 'jpg', 'jpeg', 'png'], required: true, reason: $reason);
        if ($upload === false) {
            $this->jsonError(422, $reason ?? 'Upload failed. Please attach a photo or PDF of your proof of payment (max 10 MB).');
        }
        [$path, $originalName] = $upload;

        $versionId = $model->insertVersion($protocolId, $this->relPath($protocolId, $path), $originalName, $actor['id'], 'payment_proof');
        if (!$versionId) {
            $this->jsonError(500, 'Could not record the payment proof. Please try again.');
        }

        $model->submitPaymentProof($protocolId);
        $model->logAudit('payment_proof_uploaded', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, 'Payment proof uploaded');
        $this->notifyPaymentProofUploaded($protocol, $actor);

        $_SESSION['flash_success'] = 'Proof of payment submitted. A reviewer will verify it shortly.';
        echo json_encode(['success' => true]);
        exit;
    }

    // ===== VERIFY PAYMENT  (POST /apply/verify_payment) =====

    public function verify_payment(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'reviewer') {
            $this->jsonError(403, 'Reviewer only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol_id.');
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (($protocol['payment_status'] ?? 'unpaid') !== 'proof_submitted') {
            $this->jsonError(422, 'This protocol has no pending payment proof to verify.');
        }

        $ok = $model->markPaid($protocolId, $actor['id']);
        if ($ok) {
            $model->logAudit('payment_verified', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, 'Payment marked as paid');
            $this->notifyPaymentVerified($protocol, $actor);
            $_SESSION['flash_success'] = 'Payment marked as paid.';
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== UNDO PAYMENT  (POST /apply/undo_payment) =====

    public function undo_payment(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'reviewer') {
            $this->jsonError(403, 'Reviewer only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol_id.');
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (($protocol['payment_status'] ?? 'unpaid') !== 'paid') {
            $this->jsonError(422, 'This protocol is not currently marked as paid.');
        }
        if (strtolower($protocol['status']) !== 'reviewed') {
            $this->jsonError(422, "Payment can't be unmarked once the protocol has moved past this stage.");
        }

        $ok = $model->undoMarkPaid($protocolId);
        if ($ok) {
            $model->logAudit('payment_unmarked', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, 'Payment mark undone');
            $_SESSION['flash_success'] = 'Payment mark undone. The protocol is back to "proof submitted".';
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== REJECT PAYMENT PROOF  (POST /apply/reject_payment) =====

    public function reject_payment(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'reviewer') {
            $this->jsonError(403, 'Reviewer only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        $comment    = trim($body['comment'] ?? '');

        if ($protocolId < 1 || $comment === '') {
            $this->jsonError(400, 'Please explain why the payment proof is being rejected.');
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (($protocol['payment_status'] ?? 'unpaid') !== 'proof_submitted') {
            $this->jsonError(422, 'This protocol has no pending payment proof to reject.');
        }

        $ok = $model->rejectPaymentProof($protocolId, $actor['id'], $comment);
        if ($ok) {
            $model->logAudit('payment_proof_rejected', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Payment proof rejected: $comment");
            $this->notifyPaymentRejected($protocol, $comment, $actor);
            $_SESSION['flash_success'] = 'Payment proof rejected. The researcher has been notified to re-upload.';
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== SIGNED SCAN UPLOAD  (POST /apply/signed_scan_upload) =====

    public function signed_scan_upload(): void
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
        if (strtolower($protocol['status']) !== 'reviewed') {
            $this->jsonError(422, 'Only reviewed protocols can have a signed scan uploaded.');
        }
        if (($protocol['payment_status'] ?? 'unpaid') !== 'paid') {
            $this->jsonError(422, 'This protocol must be marked as paid before the signed scan can be uploaded.');
        }

        $reason = null;
        $upload = $this->saveUpload('signed_scan_file', $this->protocolDir($protocolId), ['pdf', 'jpg', 'jpeg', 'png'], required: true, reason: $reason);
        if ($upload === false) {
            $this->jsonError(422, $reason ?? 'Upload failed. Please attach the scanned, signed protocol (PDF or image, max 10 MB).');
        }
        [$path, $originalName] = $upload;

        $versionId = $model->insertVersion($protocolId, $this->relPath($protocolId, $path), $originalName, $actor['id'], 'signed_scan');
        if (!$versionId) {
            $this->jsonError(500, 'Could not record the signed scan. Please try again.');
        }

        $model->logAudit('signed_scan_uploaded', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, 'Signed scan uploaded');
        $this->notifySignedScanUploaded($protocol, $actor);

        $_SESSION['flash_success'] = 'Signed scan uploaded. This protocol can now be marked as endorsed.';
        echo json_encode(['success' => true]);
        exit;
    }

    // ===== RENAME  (POST /apply/rename) =====

    public function rename(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        $newTitle   = trim($body['title'] ?? '');

        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol ID.');
        }
        if ($newTitle === '') {
            $this->jsonError(422, 'Please enter a research title.');
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $actor         = $this->actor();
        $actor['name'] = $this->actorDisplayName($actor);
        $isOwner       = (int) $protocol['user_id'] === $actor['id'];
        $isStaff       = in_array($actor['role'], ['admin', 'reviewer']);

        if (!$isOwner && !$isStaff) {
            $this->jsonError(403, 'Access denied.');
        }
        if (!in_array(strtolower($protocol['status']), ['under review', 'needs revision'], true)) {
            $this->jsonError(422, 'This protocol can only be renamed while it is under review or needs revision.');
        }

        $oldTitle = $model->renameProtocol($protocolId, $newTitle, $actor['id'], $actor['name'], $actor['role']);

        if ($oldTitle === false) {
            $this->jsonError(422, 'Could not rename the protocol. Please choose a different title.');
        }

        $model->logAudit('protocol_renamed', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Renamed from \"$oldTitle\" to \"$newTitle\"");

        if ($isStaff && !$isOwner) {
            $this->notifyProtocolRenamed($protocol, $oldTitle, $newTitle, $actor);
        } elseif ($isOwner) {
            $this->notifyStaffProtocolRenamed($protocol, $oldTitle, $newTitle, $actor);
        }

        $_SESSION['flash_success'] = 'Protocol renamed.';

        echo json_encode(['ok' => true, 'title' => $newTitle]);
        exit;
    }

    // ===== REQUEST DELETION  (POST /apply/request_deletion) =====

    public function request_deletion(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $actor         = $this->actor();
        $actor['name'] = $this->actorDisplayName($actor);
        if (!in_array($actor['role'], ['researcher', 'admin'], true)) {
            $this->jsonError(403, 'Reviewers delete protocols directly. Use the Delete button instead.');
        }

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        $reason     = trim($body['reason'] ?? '');

        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol ID.');
        }
        if ($reason === '') {
            $this->jsonError(422, 'Please provide a reason for the deletion request.');
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $isOwner = (int) $protocol['user_id'] === $actor['id'];
        if ($actor['role'] === 'researcher' && !$isOwner) {
            $this->jsonError(403, 'Access denied.');
        }
        if (!empty($protocol['deletion_requested_at'])) {
            $this->jsonError(422, 'A deletion request has already been made for this protocol.');
        }

        $ok = $model->requestDeletion($protocolId, $actor['id'], $actor['name'], $actor['role'], $reason);

        if ($ok) {
            $model->logAudit('protocol_deletion_requested', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Requested deletion. Reason: $reason");
            $this->notifyDeletionRequested($protocol, $reason, $actor);
            $_SESSION['flash_success'] = 'Deletion request sent to the reviewer.';
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== DELETE  (POST /apply/delete): reviewer only, any status =====

    public function delete(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $actor         = $this->actor();
        $actor['name'] = $this->actorDisplayName($actor);
        if ($actor['role'] !== 'reviewer') {
            $this->jsonError(403, 'Reviewer access only.');
        }

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        $reason     = trim($body['reason'] ?? '');

        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol ID.');
        }
        if ($reason === '') {
            $this->jsonError(422, 'Please provide a reason for deleting this protocol.');
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $ok = $model->softDelete($protocolId, $actor['id'], $actor['name'], $reason);

        if ($ok) {
            $model->logAudit('protocol_deleted', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Deleted. Reason: $reason");
            $this->notifyProtocolDeleted($protocol, $reason, $actor);
            $_SESSION['flash_success'] = 'Protocol deleted.';
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== APPROVE DELETION REQUEST  (POST /apply/approve_deletion): reviewer only =====

    public function approve_deletion(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $actor         = $this->actor();
        $actor['name'] = $this->actorDisplayName($actor);
        if ($actor['role'] !== 'reviewer') {
            $this->jsonError(403, 'Reviewer access only.');
        }

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);

        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol ID.');
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (empty($protocol['deletion_requested_at'])) {
            $this->jsonError(422, 'There is no pending deletion request for this protocol.');
        }

        $reason = trim((string) $protocol['deletion_request_reason']) ?: 'Deletion request approved.';
        $ok     = $model->softDelete($protocolId, $actor['id'], $actor['name'], $reason);

        if ($ok) {
            $model->logAudit('protocol_deletion_approved', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Approved deletion request. Reason: $reason");
            $this->notifyProtocolDeleted($protocol, $reason, $actor);
            $_SESSION['flash_success'] = 'Deletion request approved. Protocol deleted.';
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== REJECT DELETION REQUEST  (POST /apply/reject_deletion): reviewer only =====

    public function reject_deletion(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $actor         = $this->actor();
        $actor['name'] = $this->actorDisplayName($actor);
        if ($actor['role'] !== 'reviewer') {
            $this->jsonError(403, 'Reviewer access only.');
        }

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        $reason     = trim($body['reason'] ?? '');

        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol ID.');
        }
        if ($reason === '') {
            $this->jsonError(422, 'Please explain why this deletion request is being rejected.');
        }

        $model    = new ProtocolModel();
        $protocol = $model->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (empty($protocol['deletion_requested_at'])) {
            $this->jsonError(422, 'There is no pending deletion request for this protocol.');
        }

        $ok = $model->rejectDeletionRequest($protocolId);

        if ($ok) {
            $model->logAudit('protocol_deletion_rejected', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Rejected deletion request. Reason: $reason");
            $this->notifyDeletionRejected($protocol, $reason, $actor);
            $_SESSION['flash_success'] = 'Deletion request rejected.';
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
            $this->jsonError(422, 'Only endorsed protocols can receive a clearance.');
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

        $model->logAudit('clearance_uploaded', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, 'Clearance uploaded');
        $_SESSION['flash_success'] = 'Clearance uploaded. You can now mark this protocol as Approved.';

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

    // ===== CLEARANCE POOL UPLOAD  (POST /apply/clearance_pool_upload) =====

    public function clearance_pool_upload(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'reviewer') {
            $this->jsonError(403, 'Reviewer only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $files = $_FILES['clearance_screenshots'] ?? null;
        if (!$files || empty($files['name'][0])) {
            $this->jsonError(400, 'Please attach at least one screenshot.');
        }

        $model     = new ProtocolModel();
        $dir       = $this->clearancePoolDir();
        $inserted  = 0;
        $failures  = [];

        foreach ($files['name'] as $i => $name) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $single = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
            $_FILES['__clearance_pool_single'] = $single;

            $reason = null;
            $upload = $this->saveUpload('__clearance_pool_single', $dir, ['jpg', 'jpeg', 'png'], required: true, reason: $reason);
            if ($upload === false) {
                $failures[] = $name . ($reason ? " ($reason)" : '');
                continue;
            }
            [$path, $originalName] = $upload;

            if ($model->insertClearancePoolItem($path, $originalName, $actor['id']) !== false) {
                $inserted++;
            }
        }
        unset($_FILES['__clearance_pool_single']);

        if ($inserted > 0) {
            $model->logAudit('clearance_pool_uploaded', $actor['id'], $actor['name'], $actor['role'], 'clearance_pool', null, "$inserted screenshot(s) uploaded");
            $this->notifyClearancePoolUploaded($actor, $inserted);
        }

        $_SESSION['flash_success'] = $inserted . ' clearance screenshot(s) uploaded for the admins to view' . ($failures ? '; some files were skipped.' : '.');
        echo json_encode(['success' => $inserted > 0, 'inserted' => $inserted, 'failures' => $failures]);
        exit;
    }

    // ===== CLEARANCE POOL LIST  (GET /apply/clearance_pool) =====

    public function clearance_pool(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'admin') {
            $this->jsonError(403, 'Admin only.');
        }

        $model      = new ProtocolModel();
        $unassigned = array_map(
            fn($item) => $item + ['file_url' => ROOT . '/apply/clearance_pool_file/' . (int) $item['id']],
            $model->getUnassignedClearancePool()
        );
        $staged = array_map(
            fn($item) => $item + ['file_url' => ROOT . '/apply/clearance_pool_file/' . (int) $item['id']],
            $model->getStagedClearanceItems()
        );

        echo json_encode([
            'unassigned' => $unassigned,
            'staged' => $staged,
            'confirmed' => $model->getConfirmedClearanceItems(),
            'endorsed_protocols' => $model->getEndorsedProtocols(),
        ]);
        exit;
    }

    // ===== CLEARANCE POOL FILE  (GET /apply/clearance_pool_file/{id}) =====

    public function clearance_pool_file(int $poolId = 0): void
    {
        $this->requireLogin();

        $actor = $this->actor();
        if (!in_array($actor['role'], ['admin', 'reviewer'])) {
            http_response_code(403);
            echo 'Access denied.';
            exit;
        }

        $model = new ProtocolModel();
        $item  = $model->getClearancePoolItem($poolId);

        if (!$item || !file_exists($item['file_path'])) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }

        $this->streamFile($item['file_path'], $item['original_name']);
    }

    // ===== CLEARANCE STAGE  (POST /apply/clearance_stage) =====
    // Drags a pool screenshot onto a protocol card. Nothing is committed yet:
    // this only marks a provisional match, reversible via clearance_unstage.

    public function clearance_stage(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'admin') {
            $this->jsonError(403, 'Admin only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $poolId     = (int) ($body['pool_id'] ?? 0);
        $protocolId = (int) ($body['protocol_id'] ?? 0);

        if ($poolId < 1 || $protocolId < 1) {
            $this->jsonError(400, 'Invalid parameters.');
        }

        $model    = new ProtocolModel();
        $item     = $model->getClearancePoolItem($poolId);
        $protocol = $model->getById($protocolId);

        if (!$item || $item['protocol_id'] !== null) {
            $this->jsonError(422, 'That screenshot is no longer available.');
        }
        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (strtolower($protocol['status']) !== 'endorsed') {
            $this->jsonError(422, 'Only endorsed protocols can receive a clearance.');
        }

        $ok = $model->stageClearancePoolItem($poolId, $protocolId, $actor['id']);
        if (!$ok) {
            $this->jsonError(422, 'That screenshot was just matched by someone else.');
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // ===== CLEARANCE UNSTAGE  (POST /apply/clearance_unstage) =====
    // Drags a screenshot back off a protocol card, before confirming.

    public function clearance_unstage(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'admin') {
            $this->jsonError(403, 'Admin only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $poolId = (int) ($body['pool_id'] ?? 0);
        if ($poolId < 1) {
            $this->jsonError(400, 'Missing pool_id.');
        }

        $ok = (new ProtocolModel())->unstageClearancePoolItem($poolId);
        if (!$ok) {
            $this->jsonError(422, 'That screenshot has already been confirmed: use "detach" instead.');
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // ===== CLEARANCE CONFIRM  (POST /apply/clearance_confirm) =====
    // Commits every currently-staged match in one batch: moves each file,
    // records the clearance version, and marks each protocol Approved.

    public function clearance_confirm(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'admin') {
            $this->jsonError(403, 'Admin only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $model  = new ProtocolModel();
        $staged = $model->getStagedClearanceItems();

        if (empty($staged)) {
            $this->jsonError(422, 'Nothing is staged to confirm.');
        }

        $confirmed = 0;
        $failures  = [];

        foreach ($staged as $item) {
            $protocolId = (int) $item['protocol_id'];

            if (strtolower($item['protocol_status']) !== 'endorsed') {
                $failures[] = $item['protocol_title'] . ' is no longer Endorsed: skipped.';
                continue;
            }

            $dir = $this->protocolDir($protocolId);
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
            $ext      = strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION));
            $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
            $dest     = $dir . $safeName;

            if (!rename($item['file_path'], $dest)) {
                $failures[] = $item['protocol_title'] . ': could not move the file.';
                continue;
            }

            $versionId = $model->insertVersion($protocolId, $this->relPath($protocolId, $dest), $item['original_name'], $actor['id'], 'clearance');
            if (!$versionId || !$model->confirmClearancePoolItem((int) $item['id'], $versionId)) {
                $failures[] = $item['protocol_title'] . ': could not record the clearance file.';
                continue;
            }

            $protocol = $model->getById($protocolId);
            $model->updateStatus($protocolId, 'Approved');
            $this->notifyStatusChange($protocol, 'Approved');
            $model->logAudit('clearance_confirmed', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Clearance pool item #{$item['id']} confirmed; marked Approved");
            $confirmed++;
        }

        $_SESSION['flash_success'] = "$confirmed protocol(s) approved." . ($failures ? ' Some items need another look.' : '');
        echo json_encode(['confirmed' => $confirmed, 'failures' => $failures]);
        exit;
    }

    // ===== CLEARANCE UNASSIGN  (POST /apply/clearance_unassign) =====
    // Detaches an already-confirmed clearance from the wrong protocol: reverts
    // that protocol to Endorsed, deletes the specific clearance version, and
    // puts the screenshot back in the unassigned pool.

    public function clearance_unassign(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'admin') {
            $this->jsonError(403, 'Admin only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $poolId = (int) ($body['pool_id'] ?? 0);
        if ($poolId < 1) {
            $this->jsonError(400, 'Missing pool_id.');
        }

        $model = new ProtocolModel();
        $item  = $model->getClearancePoolItem($poolId);

        if (!$item || $item['protocol_id'] === null) {
            $this->jsonError(422, 'That screenshot is not currently assigned.');
        }
        if (empty($item['version_id'])) {
            $this->jsonError(422, 'That screenshot hasn\'t been confirmed yet: use "unstage" instead.');
        }

        $protocolId = (int) $item['protocol_id'];
        $protocol   = $model->getById($protocolId);

        if ($protocol && strtolower($protocol['status']) === 'approved') {
            $model->updateStatus($protocolId, 'Endorsed');
            $this->notifyStatusChange($protocol, 'Endorsed');
        }

        if (!empty($item['version_id'])) {
            $model->deleteVersion((int) $item['version_id']);
        }

        $model->unassignClearancePoolItem($poolId);
        $model->logAudit('clearance_unassigned', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Clearance pool item #$poolId detached; reverted to Endorsed");
        $_SESSION['flash_success'] = 'Clearance detached and returned to the pool.';

        echo json_encode(['success' => true]);
        exit;
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

        $files = $this->addFileUrls($model->getVersions($protocolId, 'protocol'));
        $files = array_map(function ($v) use ($model, $protocolId, $protocol) {
            $v['title_at_version'] = $model->getTitleAsOf($protocolId, $v['uploaded_at']) ?? $protocol['research_title'];
            return $v;
        }, $files);

        $titleHistory = $model->getTitleHistory($protocolId);

        echo json_encode([
            'protocol_id'    => $protocolId,
            'title'          => $protocol['research_title'],
            'status'         => $protocol['status'],
            'protocol_files' => $files,
            'payment_proof_files' => $this->addFileUrls($model->getVersions($protocolId, 'payment_proof')),
            'signed_scan_files'   => $this->addFileUrls($model->getVersions($protocolId, 'signed_scan')),
            'clearance_files'     => $this->addFileUrls($model->getVersions($protocolId, 'clearance')),
            'return_reason'  => $model->getLatestReturnReason($protocolId),
            'title_history'  => count($titleHistory) > 1 ? $titleHistory : [],
        ]);
        exit;
    }
}
