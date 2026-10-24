<?php

class Apply extends Controller
{
    private ProtocolModel $protocolModel;
    private DraftModel $draftModel;
    private RecordModel $recordModel;

    // ===== SETUP =====
    public function __construct()
    {
        parent::__construct();

        $this->protocolModel = new ProtocolModel();
        $this->draftModel    = new DraftModel();
        $this->recordModel   = new RecordModel();
    }

    // ===== HELPERS =====
    // ===== RESEARCHER-ONLY GUARD =====
    private function requireResearcher(bool $asPage = false): void
    {
        if ($this->isPersonnel()) {
            $asPage
                ? $this->redirect('personnel/home')
                : $this->jsonError(403, 'This action is for researchers only.');
        }
    }

    private function requireProtocolAccess(array $protocol, int $userId, string $role, bool $asPage = false): void
    {
        if ((int) $protocol['user_id'] !== $userId && !in_array($role, ['staff', 'reviewer'])) {
            if ($asPage) {
                $this->renderError(403, 'Access Denied', [
                    'You do not have permission to view this protocol.',
                ]);
            }
            $this->jsonError(403, 'Access denied.');
        }
    }

    // ===== OWNER LOOKUP =====
    private function ownerOf(array $protocol)
    {
        return $this->userModel->getUser((int) $protocol['user_id']);
    }

    private function actorDisplayName(array $actor): string
    {
        $user = $this->userModel->getUser($actor['id']);
        $full = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        return $full !== '' ? $full : $actor['name'];
    }

    private function protocolHighlightLink(int $protocolId, ?string $open = null): string
    {
        return 'submissions?highlight=' . $protocolId . ($open ? '&open=' . $open : '');
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'staff'    => 'Staff',
            'reviewer' => 'Reviewer',
            default    => 'Researcher',
        };
    }

    // ===== NOTIFICATION HELPERS =====
    private function notifyProtocolRenamed(array $protocol, string $oldTitle, string $newTitle, array $actor): void
    {
        $owner = $this->ownerOf($protocol);
        if (!$owner) {
            return;
        }

        $roleLabel = $this->roleLabel($actor['role']);

        Notifier::send(
            (int) $protocol['user_id'],
            'protocol_renamed',
            'Protocol Renamed',
            Notifier::boldTitle($oldTitle) . ' was renamed to ' . Notifier::boldTitle($newTitle) . " by $roleLabel - {$actor['name']}. " . Notifier::NEXT_STEPS['protocol_renamed'],
            $this->protocolHighlightLink((int) $protocol['protocol_id']),
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

    private function notifyPersonnelProtocolRenamed(array $protocol, string $oldTitle, string $newTitle, array $actor): void
    {
        $roleLabel = $this->roleLabel($actor['role']);

        foreach (['staff', 'reviewer'] as $personnelRole) {
            Notifier::sendToRole(
                $personnelRole,
                'protocol_renamed',
                'Protocol Renamed',
                "$roleLabel {$actor['name']} renamed " . Notifier::boldTitle($oldTitle) . ' to ' . Notifier::boldTitle($newTitle) . '. ' . Notifier::NEXT_STEPS['protocol_renamed'],
                'apply/viewer/' . $protocol['protocol_id'],
                [
                    'template' => 'protocol_renamed_personnel',
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

    private function notifyPersonnelProtocolResubmitted(array $protocol, array $actor): void
    {
        $title     = $protocol['research_title'] ?? 'Untitled Protocol';
        $roleLabel = $this->roleLabel($actor['role']);

        Notifier::sendToPersonnel(
            'protocol_resubmitted',
            'Protocol Re-submitted',
            "$roleLabel {$actor['name']} resubmitted " . Notifier::boldTitle($title) . ' for review.',
            'protocol_resubmitted',
            'apply/viewer/' . $protocol['protocol_id'],
            [
                'template' => 'protocol_resubmitted_personnel',
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

    private function notifyReviewersReturnedToReview(array $protocol, array $actor, string $reason): void
    {
        $title = $protocol['research_title'] ?? 'Untitled Protocol';

        Notifier::sendToRole(
            'reviewer',
            'protocol_returned_to_review',
            'Protocol Returned to Review',
            $this->roleLabel($actor['role']) . " {$actor['name']} returned " . Notifier::boldTitle($title) . ' to To Review after DA-CARFU or BAI sent it back.' . Notifier::reasonSentence($reason) . ' ' . Notifier::NEXT_STEPS['new_submission_reviewer'],
            'apply/viewer/' . $protocol['protocol_id']
        );
    }

    private function notifyPersonnelProtocolAmended(array $protocol, array $actor, string $note): void
    {
        $title = $protocol['research_title'] ?? 'Untitled Protocol';

        foreach (['staff', 'reviewer'] as $personnelRole) {
            Notifier::sendToRole(
                $personnelRole,
                'protocol_amended',
                'Protocol Amended',
                "{$actor['name']} submitted an amendment for " . Notifier::boldTitle($title) . '. ' . Notifier::NEXT_STEPS['protocol_amended'],
                'apply/viewer/' . $protocol['protocol_id'],
                [
                    'template' => 'protocol_amended_personnel',
                    'vars'     => [
                        'title'       => $title,
                        'actor_name'  => $actor['name'],
                        'note'        => $note,
                        'protocol_id' => $protocol['protocol_id'],
                    ],
                    'subject' => 'Protocol Amended',
                ]
            );
        }
    }

    private function notifyDeletionRequested(array $protocol, string $reason, array $actor): void
    {
        $roleLabel = $this->roleLabel($actor['role']);
        $title     = $protocol['research_title'] ?? 'Untitled Protocol';

        Notifier::sendToRole(
            'staff',
            'protocol_deletion_requested',
            'Deletion Requested',
            "$roleLabel {$actor['name']} requested deletion of " . Notifier::boldTitle($title) . '. ' . Notifier::NEXT_STEPS['protocol_deletion_requested'] . " Reason: $reason",
            'apply/viewer/' . $protocol['protocol_id'] . '?open=deletion_request',
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
        $owner = $this->ownerOf($protocol);
        if (!$owner) {
            return;
        }

        $title = $protocol['research_title'] ?? 'Untitled Protocol';

        Notifier::send(
            (int) $protocol['user_id'],
            'protocol_deleted',
            'Protocol Deleted',
            'Your protocol ' . Notifier::boldTitle($title) . ' was ' . Notifier::bold('deleted') . " by {$actor['name']}. " . Notifier::NEXT_STEPS['protocol_deleted'] . " Reason: $reason",
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

        $requester = $this->userModel->getUser($requesterId);
        if (!$requester) {
            return;
        }

        $title = $protocol['research_title'] ?? 'Untitled Protocol';

        Notifier::send(
            $requesterId,
            'protocol_deletion_rejected',
            'Deletion Request Rejected',
            'Your request to delete ' . Notifier::boldTitle($title) . ' was ' . Notifier::bold('rejected') . " by {$actor['name']}. " . Notifier::NEXT_STEPS['protocol_deletion_rejected'] . " Reason: $rejectionReason",
            'apply/viewer/' . $protocol['protocol_id'] . '?open=deletion_rejected',
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

    private function notifyStatusChange(array $protocol, string $newStatus, string $reason = ''): void
    {
        $owner = $this->ownerOf($protocol);
        if (!$owner) {
            return;
        }

        $title = $protocol['research_title'] ?? 'Untitled Protocol';

        $link = strtolower($newStatus) === 'needs revision'
            ? 'apply/viewer/' . $protocol['protocol_id']
            : $this->protocolHighlightLink((int) $protocol['protocol_id']);

        $noticeKey = strtolower($newStatus);
        if ($noticeKey === 'reviewed' && ($protocol['payment_status'] ?? '') === 'paid') {
            $noticeKey = 'reviewed paid';
        }

        $returned = $noticeKey === 'under review';
        [$noticeTitle, $nextStep] = $returned ? Notifier::returnedToReviewNotice($reason) : Notifier::STATUS_NOTICES[$noticeKey];

        Notifier::send(
            (int) $protocol['user_id'],
            $returned ? 'protocol_returned_to_review' : 'protocol_status_' . str_replace(' ', '_', strtolower($newStatus)),
            $noticeTitle,
            'Your protocol ' . Notifier::boldTitle($title) . ' ' . $nextStep,
            $link,
            [
                'template' => 'protocol_status_changed',
                'vars'     => ['first_name' => $owner['first_name'] ?? '', 'title' => $title, 'status' => $newStatus, 'next_step' => $nextStep, 'protocol_id' => $protocol['protocol_id']],
                'to'       => $owner['email'] ?? '',
                'name'     => $owner['first_name'] ?? '',
                'subject'  => $noticeTitle,
            ]
        );
    }

    private function notifyPaymentProofUploaded(array $protocol, array $actor, string $method): void
    {
        $title        = $protocol['research_title'] ?? 'Untitled Protocol';
        $researcher   = $this->actorDisplayName($actor);
        $paidInPerson = $method === 'in_person';

        Notifier::sendToRole(
            'staff',
            'payment_proof_uploaded',
            $paidInPerson ? 'Payment Confirmed' : 'Payment Proof Uploaded',
            $researcher . ($paidInPerson ? ' confirmed payment in person for ' : ' uploaded proof of payment for ') . Notifier::boldTitle($title) . '. ' . Notifier::NEXT_STEPS['payment_proof_uploaded'],
            'personnel/home?status=reviewed&open_payment=' . $protocol['protocol_id'],
            [
                'template' => 'payment_proof_uploaded',
                'vars'     => ['title' => $title, 'actor_name' => $researcher, 'protocol_id' => $protocol['protocol_id'], 'paid_in_person' => $paidInPerson],
                'subject'  => $paidInPerson ? 'Payment Confirmed' : 'Payment Proof Uploaded',
            ]
        );
    }

    private function notifyPaymentVerified(array $protocol, array $actor): void
    {
        $owner = $this->ownerOf($protocol);
        $title = $protocol['research_title'] ?? 'Untitled Protocol';

        if ($owner) {
            Notifier::send(
                (int) $protocol['user_id'],
                'payment_verified',
                'Payment Verified',
                'Your payment for ' . Notifier::boldTitle($title) . ' has been ' . Notifier::bold('verified') . '. ' . Notifier::PAYMENT_VERIFIED_NEXT_STEP,
                $this->protocolHighlightLink((int) $protocol['protocol_id']),
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
            'staff',
            'protocol_paid',
            'Protocol Ready for IPN',
            Notifier::boldTitle($title) . ' has been ' . Notifier::bold('paid') . '. Assign its IPN, then print and sign it.',
            'personnel/home?status=reviewed',
            [
                'template' => 'protocol_paid',
                'vars'     => ['title' => $title, 'protocol_id' => $protocol['protocol_id']],
                'subject'  => 'Protocol Ready for IPN',
            ]
        );
    }

    private function notifyPaymentRejected(array $protocol, string $reason, array $actor): void
    {
        $owner = $this->ownerOf($protocol);
        if (!$owner) {
            return;
        }

        $title = $protocol['research_title'] ?? 'Untitled Protocol';

        Notifier::send(
            (int) $protocol['user_id'],
            'payment_proof_rejected',
            'Payment Proof Rejected',
            'Your payment for ' . Notifier::boldTitle($title) . ' was ' . Notifier::bold('rejected') . ". Reason: $reason. " . Notifier::PAYMENT_REJECTED_NEXT_STEP,
            $this->protocolHighlightLink((int) $protocol['protocol_id'], 'payment'),
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
        $owner = $this->ownerOf($protocol);
        if (!$owner) {
            return;
        }

        $title = $protocol['research_title'] ?? 'Untitled Protocol';

        Notifier::send(
            (int) $protocol['user_id'],
            'signed_scan_uploaded',
            'Signed Protocol Uploaded',
            'View your protocol ' . Notifier::boldTitle($title) . ' signed by the IACUC chairperson. ' . Notifier::SIGNED_SCAN_NEXT_STEP,
            $this->protocolHighlightLink((int) $protocol['protocol_id'], 'signed_scan'),
            [
                'template' => 'signed_scan_uploaded',
                'vars'     => ['first_name' => $owner['first_name'] ?? '', 'title' => $title, 'protocol_id' => $protocol['protocol_id']],
                'to'       => $owner['email'] ?? '',
                'name'     => $owner['first_name'] ?? '',
                'subject'  => 'Signed Protocol Uploaded',
            ]
        );
    }

    // ===== FILE & PATH HELPERS =====
    private function addFileUrls(array $versions): array
    {
        return array_map(fn($v) => $v + ['file_url' => ROOT . '/apply/file/' . (int) $v['id']], $versions);
    }

    private function protocolDir(int $protocolId): string
    {
        return dirname(__DIR__, 2) . '/storage/uploads/protocols/' . $protocolId . '/';
    }

    private function certDir(int $userId): string
    {
        return dirname(__DIR__, 2) . '/storage/uploads/protocols/certs/' . $userId . '/';
    }

    private function notifyCertSubmitted(int $userId, string $submitterName): void
    {
        Notifier::sendToPersonnel(
            'cert_verification',
            'Training Certificate to Verify',
            $submitterName . ' submitted a training certificate for verification.',
            'cert_verification',
            'personnel/home?open_researcher=' . $userId,
            [
                'template' => 'cert_verification_personnel',
                'vars'     => ['submitter' => $submitterName, 'user_id' => $userId],
                'subject'  => 'Training Certificate to Verify',
            ]
        );
    }

    private function arNumberTaken(string $arNumber, ?array $protocol = null, ?array $record = null): bool
    {
        $onProtocols = ($protocol['ar_number'] ?? '') !== $arNumber && $this->protocolModel->arNumberExists($arNumber);
        $onRecords   = (!$record || ($record['ar_number'] ?? '') !== $arNumber) && $this->recordModel->arExists($arNumber);
        return $onProtocols || $onRecords;
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

    private function protocolDisplayFilename(?string $ipn, string $researcherName, string $title, string $ext): string
    {
        $researcherName = trim($researcherName) !== '' ? trim($researcherName) : 'Applicant';
        $title          = trim($title) !== '' ? trim($title) : 'Untitled Protocol';
        $ipn            = trim((string) $ipn);

        $illegal        = ['/', '\\', ':', '*', '?', '"', '<', '>', '|'];
        $researcherName = str_replace($illegal, '', $researcherName);
        $title          = str_replace($illegal, '', $title);
        $ipn            = str_replace($illegal, '', $ipn);

        $base = $ipn !== '' ? ($ipn . '_' . $researcherName) : ($researcherName . '_' . $title);

        return $base . '.' . strtolower($ext);
    }

    private function clearanceDisplayFilename(?string $ipn, string $researcherName, string $ext): string
    {
        $researcherName = mb_strtoupper(trim($researcherName) !== '' ? trim($researcherName) : 'Applicant');
        $ipn            = trim((string) $ipn);

        $illegal        = ['/', '\\', ':', '*', '?', '"', '<', '>', '|'];
        $researcherName = str_replace($illegal, '', $researcherName);
        $ipn            = str_replace($illegal, '', $ipn);

        $base = $ipn !== '' ? ($ipn . '-' . $researcherName) : $researcherName;

        return $base . '.' . strtolower($ext);
    }

    // ===== SAVE UPLOAD =====
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

        if (in_array($ext, ['doc', 'docx'], true)) {
            if (!$this->isWordFile($file['tmp_name'], $ext)) {
                $reason = 'That file isn\'t a valid Word document. Please make sure it opens correctly in Word, or export it to PDF and upload that instead.';
                return false;
            }
        } elseif (in_array($inputName, ['protocol_file', 'clearance_file', 'amendment_file', 'staged_file', 'signed_scan_file'], true)) {
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

    // ===== WORD FILE CHECK =====
    private function isWordFile(string $path, string $ext): bool
    {
        if ($ext === 'doc') {
            return (string) file_get_contents($path, false, null, 0, 8) === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }

        $isWord = $zip->locateName('word/document.xml') !== false && $zip->locateName('word/vbaProject.bin') === false;
        $zip->close();

        return $isWord;
    }

    // ===== SAVE PROTOCOL UPLOAD (PDF OR WORD, WORD IS CONVERTED) =====
    private function saveProtocolUpload(string $inputName, string $dir, bool $required, ?string &$reason = null, ?bool &$converted = null): array|false|null
    {
        $converted = false;
        $upload    = $this->saveUpload($inputName, $dir, ['pdf', 'doc', 'docx'], $required, $reason);

        if (!is_array($upload)) {
            return $upload;
        }

        [$sourcePath, $originalName] = $upload;
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            return $upload;
        }

        $actor = $this->actor();

        if ($this->protocolModel->countRecentActions($actor['id'], 'protocol_converted') >= PdfConverter::HOURLY_LIMIT) {
            @unlink($sourcePath);
            $reason = PdfConverter::LIMIT_REACHED;
            return false;
        }

        $pdfPath = substr($sourcePath, 0, -strlen($ext)) . 'pdf';
        $detail  = null;

        if (!PdfConverter::convert($sourcePath, $pdfPath, $reason, $detail)) {
            @unlink($sourcePath);
            $this->protocolModel->logAudit('protocol_conversion_failed', $actor['id'], $actor['name'], $actor['role'], 'user', $actor['id'], mb_substr($originalName . ': ' . $detail, 0, 500));
            return false;
        }

        $this->protocolModel->logAudit('protocol_converted', $actor['id'], $actor['name'], $actor['role'], 'user', $actor['id'], $originalName . ' converted to PDF');

        $converted = true;
        return [$pdfPath, substr($originalName, 0, -strlen($ext)) . 'pdf'];
    }

    // ===== ORIGINAL WORD FILE HELPERS =====
    private function originalFiles(string $pdfPath): array
    {
        $stem = preg_replace('/\.[^.\/]+$/', '', $pdfPath);
        return array_filter([$stem . '.docx', $stem . '.doc'], 'is_file');
    }

    private function deleteWithOriginal(string $pdfPath): void
    {
        @unlink($pdfPath);
        foreach ($this->originalFiles($pdfPath) as $original) {
            @unlink($original);
        }
    }

    private function moveOriginal(string $fromPdf, string $toPdf): void
    {
        $toStem = preg_replace('/\.[^.\/]+$/', '', $toPdf);
        foreach ($this->originalFiles($fromPdf) as $original) {
            rename($original, $toStem . '.' . pathinfo($original, PATHINFO_EXTENSION));
        }
    }

    // ===== STAGED PROTOCOL FILE HELPERS =====
    private function stagingDir(int $userId): string
    {
        return dirname(__DIR__, 2) . '/storage/uploads/staging/' . $userId . '/';
    }

    private function clearStagedProtocol(): void
    {
        $path = $_SESSION['staged_protocol']['path'] ?? null;
        if ($path) {
            $this->deleteWithOriginal($path);
        }
        unset($_SESSION['staged_protocol']);
    }

    private function takeStagedProtocol(string $dir, ?string &$reason = null): array|false
    {
        $staged = $_SESSION['staged_protocol'] ?? null;

        if (!$staged || !is_file($staged['path'])) {
            $reason = 'Your converted file is no longer available. Please upload it again.';
            return false;
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $destPath = $dir . basename($staged['path']);
        $this->moveOriginal($staged['path'], $destPath);
        rename($staged['path'], $destPath);
        unset($_SESSION['staged_protocol']);

        return [$destPath, $staged['name']];
    }

    // ===== STAGE PROTOCOL FILE  (POST /apply/stageprotocol) =====

    public function stageprotocol(): void
    {
        $this->requireLogin();
        $this->requireResearcher();
        header('Content-Type: application/json');
        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $actor = $this->actor();
        $this->clearStagedProtocol();

        $reason = null;
        $upload = $this->saveProtocolUpload('staged_file', $this->stagingDir($actor['id']), true, $reason);
        if ($upload === false) {
            $this->jsonError(422, $reason ?? 'Upload failed.');
        }
        [$absPath, $originalName] = $upload;

        $_SESSION['staged_protocol'] = ['path' => $absPath, 'name' => $originalName];

        echo json_encode([
            'ok'   => true,
            'name' => $originalName,
            'size' => filesize($absPath),
            'url'  => ROOT . '/apply/stagedfile',
        ]);
        exit;
    }

    // ===== STAGED FILE  (GET /apply/stagedfile) =====

    public function stagedfile(): void
    {
        $this->requireLogin();
        $this->requireResearcher();

        $path = $_SESSION['staged_protocol']['path'] ?? null;
        if (!$path || !is_file($path)) {
            $this->jsonError(404, 'File not found.');
        }

        $this->streamFile($path, $_SESSION['staged_protocol']['name']);
    }

    // ===== UPLOAD FORM  (GET /apply) =====

    public function index(): void
    {
        $this->requireLogin();
        $this->requireResearcher(true);
        $this->view('apply', [
            'previousDetails' => $this->protocolModel->lastDetailsByUser($this->actor()['id']),
            // Suggestions come from this researcher's own protocols only, never from other researchers' records
            'advisers'        => $this->protocolModel->distinctDetailsByUser($this->actor()['id'], 'research_adviser'),
            'veterinarians'   => $this->protocolModel->distinctDetailsByUser($this->actor()['id'], 'veterinarian'),
        ]);
    }

    // ===== FIRST UPLOAD  (POST /apply/submit) =====

    public function submit(): void
    {
        $this->requireLogin();
        $this->requireResearcher();
        header('Content-Type: application/json');
        $this->requirePostMethod();

        $actor      = $this->actor();

        // ===== LOAD DRAFT & TITLE =====
        $draft = $this->draftModel->getByUser($actor['id']);
        if (!$draft) {
            $this->jsonError(422, 'No draft found. Please fill out the application first.');
        }

        $title = trim($draft['title'] ?? '');
        if ($title === '') {
            $this->jsonError(422, 'Protocol title is required.');
        }
        $this->rejectDuplicateTitle($title);

        $details = [];
        foreach (ProtocolModel::DETAIL_FIELDS as $column => $label) {
            $details[$column] = trim((string) ($draft[$column] ?? ''));
            if ($details[$column] === '') {
                $this->jsonError(422, "$label is required.");
            }
        }
        if ((int) $details['animal_count'] < 1) {
            $this->jsonError(422, 'Animal count must be a whole number of 1 or more.');
        }
        $details['animal_count'] = (int) $details['animal_count'];

        // ===== CHECK DRAFT FILES =====
        $draftDirAbs = dirname(__DIR__, 2) . '/storage/uploads/drafts/';

        $docRelPath = $draft['protocol_file_path'] ?? null;
        if (!$docRelPath || !is_file($draftDirAbs . $docRelPath)) {
            $this->jsonError(422, 'Please upload your completed IACUC protocol form.');
        }
        $docOriginalName = $draft['protocol_file_name'];

        // ===== SUBMITTER & CERTIFICATE CHECK =====
        $submitter = $this->userModel->getUser($actor['id']);
        $docExt    = strtolower(pathinfo($docOriginalName, PATHINFO_EXTENSION)) ?: 'pdf';
        $researcherName = trim(($submitter['first_name'] ?? '') . ' ' . ($submitter['last_name'] ?? ''));
        $docOriginalName = $this->protocolDisplayFilename(null, $researcherName, $title, $docExt);

        $existingCert = $this->userModel->getCert($actor['id']);
        $certRelPath  = $draft['cert_file_path'] ?? null;
        if (!$existingCert && (!$certRelPath || !is_file($draftDirAbs . $certRelPath))) {
            $this->jsonError(422, 'Please upload your IACUC Training Certificate.');
        }
        if ($existingCert && $existingCert['cert_status'] !== 'verified') {
            $this->jsonError(422, $existingCert['cert_status'] === 'rejected'
                ? 'Your training certificate was rejected. Please upload a new one from your account before submitting a protocol.'
                : 'Your training certificate is still waiting for verification. You can submit protocols once it is verified.');
        }
        $certOriginalName = $draft['cert_file_name'];

        // ===== CREATE PROTOCOL & MOVE FILES =====
        $protocolId = $this->protocolModel->insertProtocol($actor['id'], $title, $details);
        if (!$protocolId) {
            $this->jsonError(500, 'Could not create protocol record. Please try again.');
        }

        $finalDir = $this->protocolDir($protocolId);
        if (!is_dir($finalDir)) {
            mkdir($finalDir, 0750, true);
        }

        $docFinal = $finalDir . basename($docRelPath);
        $this->moveOriginal($draftDirAbs . $docRelPath, $docFinal);
        rename($draftDirAbs . $docRelPath, $docFinal);
        $this->protocolModel->insertVersion($protocolId, $this->relPath($protocolId, $docFinal), $docOriginalName, $actor['id'], 'protocol');

        $certSubmitted = false;
        if (!$existingCert && $certRelPath && is_file($draftDirAbs . $certRelPath)) {
            $certFinal = $finalDir . basename($certRelPath);
            rename($draftDirAbs . $certRelPath, $certFinal);
            $relCert = $this->relPath($protocolId, $certFinal);
            $certSubmitted = $this->userModel->saveCert($actor['id'], $relCert, $certOriginalName);
        }

        // ===== CLEAR DRAFT & LOG =====
        $this->draftModel->clear($actor['id']);

        $this->protocolModel->logAudit('protocol_submitted', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Protocol submitted: $title");
        if ($certSubmitted) {
            $this->protocolModel->logAudit('cert_submitted', $actor['id'], $actor['name'], $actor['role'], 'user', $actor['id'], 'Training certificate submitted for verification');
        }

        // ===== NOTIFY SUBMITTER =====
        Notifier::send(
            $actor['id'],
            'protocol_submitted',
            'Protocol Submitted',
            'Your protocol ' . Notifier::boldTitle($title) . ' has been submitted and is now ' . Notifier::bold('under review') . '. ' . Notifier::NEXT_STEPS['protocol_submitted'],
            $this->protocolHighlightLink($protocolId),
            [
                'template' => 'protocol_submitted',
                'vars'     => ['first_name' => $submitter['first_name'] ?? '', 'title' => $title, 'protocol_id' => $protocolId],
                'to'       => $submitter['email'] ?? '',
                'name'     => $submitter['first_name'] ?? '',
                'subject'  => 'Protocol Submitted',
            ]
        );

        // ===== NOTIFY STAFF & REVIEWERS =====
        $submitterName = trim(($submitter['first_name'] ?? '') . ' ' . ($submitter['last_name'] ?? ''));

        Notifier::sendToPersonnel(
            'new_submission',
            'New Protocol Submitted',
            $submitterName . ' submitted ' . Notifier::boldTitle($title) . '.',
            'new_submission',
            'apply/viewer/' . $protocolId,
            [
                'template' => 'protocol_submitted_personnel',
                'vars'     => ['submitter' => $submitterName, 'title' => $title, 'protocol_id' => $protocolId],
                'subject'  => 'New Protocol Submission',
            ]
        );

        if ($certSubmitted) {
            $this->notifyCertSubmitted($actor['id'], $submitterName);
        }

        echo json_encode(['success' => true, 'protocolId' => $protocolId]);
        exit;
    }

    // ===== HAS-CERT CHECK  (GET /apply/hascert) =====

    public function hascert(): void
    {
        $this->requireLogin();
        $this->requireResearcher();
        header('Content-Type: application/json');

        $actor     = $this->actor();

        $cert = $this->userModel->getCert($actor['id']);

        echo json_encode(['has_cert' => $cert !== null, 'cert_status' => $cert['cert_status'] ?? null]);
        exit;
    }

    // ===== DRAFT  (GET /apply/draft) =====

    public function draft(): void
    {
        $this->requireLogin();
        $this->requireResearcher();
        header('Content-Type: application/json');

        $actor = $this->actor();
        $row   = $this->draftModel->getByUser($actor['id']);

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
                'name'      => $row[$key . '_file_name'],
                'size'      => is_file($abs) ? filesize($abs) : null,
                'url'       => ROOT . '/apply/draftfile/' . $key,
                'converted' => !empty($this->originalFiles($abs)),
            ];
        };

        echo json_encode([
            'exists'        => true,
            'step'          => (int) $row['step'],
            'agreedTerms'   => (bool) $row['agreed_terms'],
            'agreedPrivacy' => (bool) $row['agreed_privacy'],
            'title'         => $row['title'],
            'animalType'    => $row['animal_type'] ?? '',
            'animalCount'   => $row['animal_count'] ?? '',
            'researchAdviser' => $row['research_adviser'] ?? '',
            'veterinarian'  => $row['veterinarian'] ?? '',
            'protocol'      => $fileInfo('protocol'),
            'cert'          => $fileInfo('cert'),
        ]);
        exit;
    }

    // ===== DRAFT SAVE  (POST /apply/draftsave) =====

    public function draftsave(): void
    {
        $this->requireLogin();
        $this->requireResearcher();
        header('Content-Type: application/json');
        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $actor = $this->actor();
        $text  = fn(string $key): string => (string) ($body[$key] ?? '');

        $ok = $this->draftModel->saveFields(
            $actor['id'],
            (int) ($body['step'] ?? 0),
            (bool) ($body['agreedTerms'] ?? false),
            (bool) ($body['agreedPrivacy'] ?? false),
            $text('title'),
            [
                'animal_type'      => $text('animalType'),
                'animal_count'     => (string) ($body['animalCount'] ?? ''),
                'research_adviser' => $text('researchAdviser'),
                'veterinarian'     => $text('veterinarian'),
            ]
        );

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== DRAFT UPLOAD  (POST /apply/draftupload) =====

    public function draftupload(): void
    {
        $this->requireLogin();
        $this->requireResearcher();
        header('Content-Type: application/json');
        $this->requirePostMethod();

        $actor = $this->actor();
        $key   = $_POST['key'] ?? '';

        $fieldMap = ['protocol' => 'protocol_file', 'cert' => 'cert'];
        if (!isset($fieldMap[$key])) {
            $this->jsonError(400, 'Invalid file key.');
        }

        $reason    = null;
        $converted = false;
        $upload    = $key === 'protocol'
            ? $this->saveProtocolUpload($fieldMap[$key], $this->draftDir($actor['id']), true, $reason, $converted)
            : $this->saveUpload($fieldMap[$key], $this->draftDir($actor['id']), ['pdf', 'jpg', 'jpeg', 'png'], required: true, reason: $reason);
        if ($upload === false) {
            $this->jsonError(422, $reason ?? 'Upload failed.');
        }
        [$absPath, $originalName] = $upload;

        $old   = $this->draftModel->getByUser($actor['id']);
        $oldPath = $old[$key . '_file_path'] ?? null;

        $relPath = $this->relPath($actor['id'], $absPath);
        $this->draftModel->saveFile($actor['id'], $key, $relPath, $originalName);

        if ($oldPath) {
            $this->deleteWithOriginal(dirname(__DIR__, 2) . '/storage/uploads/drafts/' . $oldPath);
        }

        echo json_encode([
            'ok'        => true,
            'name'      => $originalName,
            'size'      => filesize($absPath),
            'url'       => ROOT . '/apply/draftfile/' . $key,
            'converted' => $converted,
        ]);
        exit;
    }

    // ===== DRAFT FILE  (GET /apply/draftfile/{key}) =====

    public function draftfile(string $key = ''): void
    {
        $this->requireLogin();
        $this->requireResearcher();

        $actor = $this->actor();
        $row   = $this->draftModel->getByUser($actor['id']);

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
        $this->requireResearcher();
        header('Content-Type: application/json');
        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $key  = $body['key'] ?? '';
        $actor = $this->actor();

        if (!in_array($key, ['protocol', 'cert'], true)) {
            $this->jsonError(400, 'Invalid file key.');
        }

        $row   = $this->draftModel->getByUser($actor['id']);
        $path  = $row[$key . '_file_path'] ?? null;

        $ok = $this->draftModel->removeFile($actor['id'], $key);

        if ($path) {
            $this->deleteWithOriginal(dirname(__DIR__, 2) . '/storage/uploads/drafts/' . $path);
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== DRAFT CLEAR  (POST /apply/draftclear) =====

    public function draftclear(): void
    {
        $this->requireLogin();
        $this->requireResearcher();
        header('Content-Type: application/json');
        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $actor = $this->actor();
        $row   = $this->draftModel->getByUser($actor['id']);

        if ($row) {
            foreach (['protocol', 'cert'] as $key) {
                $path = $row[$key . '_file_path'] ?? null;
                if ($path) {
                    $this->deleteWithOriginal(dirname(__DIR__, 2) . '/storage/uploads/drafts/' . $path);
                }
            }
        }

        $ok = $this->draftModel->clear($actor['id']);

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

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $actor = $this->actor();
        $this->requireProtocolAccess($protocol, $actor['id'], $actor['role']);

        $versions = $this->addFileUrls($this->protocolModel->getVersions($protocolId, 'protocol'));

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

        $isOwnCertificate = $this->isActor($userId);

        if (!$isOwnCertificate && !$this->isPersonnel()) {
            $this->renderError(403, 'Access Denied', [
                'You do not have permission to view this certificate.',
            ]);
        }

        if ($userId < 1) {
            $this->renderError(400, 'Missing User ID', [
                'No researcher was specified.',
            ]);
        }

        $cert      = $this->userModel->getCert($userId);

        if (!$cert) {
            $this->renderError(404, 'No Certificate Found', [
                'No certificate is on file for this researcher.',
            ]);
        }

        $filePath = dirname(__DIR__, 2) . '/storage/uploads/protocols/' . $cert['cert_path'];

        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->renderError(404, 'File Not Found', [
                'The certificate file could not be found on the server.',
            ]);
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

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $actor = $this->actor();

        if (!$this->isActor($protocol['user_id'])) {
            $this->jsonError(403, 'Access denied.');
        }
        if (strtolower($protocol['status']) !== 'needs revision') {
            $this->jsonError(422, 'This protocol is not awaiting revision.');
        }

        $docReason = null;
        $docUpload = ($_POST['use_staged'] ?? '') === '1'
            ? $this->takeStagedProtocol($this->protocolDir($protocolId), $docReason)
            : $this->saveProtocolUpload('protocol_file', $this->protocolDir($protocolId), false, $docReason);
        if ($docUpload === false) {
            $this->jsonError(422, $docReason ?? 'Upload failed. Please attach a PDF, DOC, or DOCX file (max 10 MB).');
        }

        if ($docUpload !== null) {
            [$docPath, $docOriginalName] = $docUpload;
            $docExt = strtolower(pathinfo($docOriginalName, PATHINFO_EXTENSION)) ?: 'pdf';
            $researcherName = trim(($protocol['submitter_first_name'] ?? '') . ' ' . ($protocol['submitter_last_name'] ?? ''));
            $docOriginalName = $this->protocolDisplayFilename($protocol['reference_no'] ?? null, $researcherName, $protocol['research_title'] ?? '', $docExt);
            $versionId = $this->protocolModel->insertVersion($protocolId, $this->relPath($protocolId, $docPath), $docOriginalName, $actor['id'], 'protocol');
            if (!$versionId) {
                $this->jsonError(500, 'Could not record new version.');
            }
        }

        $this->protocolModel->updateStatus($protocolId, 'Under Review');
        $this->notifyStatusChange($protocol, 'Under Review');
        $this->notifyPersonnelProtocolResubmitted($protocol, $actor);
        $this->protocolModel->logAudit('protocol_revised', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Protocol # $protocolId resubmitted");
        $_SESSION['flash_success'] = 'Your protocol has been resubmitted and is back under review.';

        echo json_encode(['success' => true]);
        exit;
    }

    // ===== VIEWER  (GET /apply/viewer/{protocolId}) =====

    public function viewer(int $protocolId = 0, int $versionId = 0): void
    {
        $this->requireLogin();

        if ($protocolId < 1) {
            $this->renderError(400, 'Missing Protocol ID', [
                'No protocol was specified.',
            ]);
        }

        // ===== LOAD PROTOCOL =====
        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->renderError(404, 'Protocol Not Found', [
                'The protocol you are looking for does not exist or may have been removed.',
            ]);
        }

        // ===== ACCESS CHECK =====
        $actor = $this->actor();
        $this->requireProtocolAccess($protocol, $actor['id'], $actor['role'], true);

        // ===== PICK VERSION TO SHOW =====
        // The switcher lists protocol versions, signed scans and amendments together.
        // Everyone opens whatever is newest.
        $timeline      = $this->protocolModel->getVersionTimeline($protocolId);
        $latestVersion = $this->protocolModel->getLatestVersion($protocolId, 'protocol');

        if ($versionId > 0) {
            $version = $this->protocolModel->getVersionById($versionId);

            if (
                !$version
                || (int) $version['protocol_id'] !== $protocolId
                || !in_array($version['file_type'], ['protocol', 'signed_scan', 'amendment'], true)
            ) {
                $this->renderError(404, 'Version Not Found', [
                    'That protocol version could not be found.',
                ]);
            }
        } elseif ($timeline) {
            $version = $this->protocolModel->getVersionById((int) $timeline[0]['id']);
        } else {
            $version = $latestVersion;
        }

        if (!$version) {
            $this->renderError(404, 'No File Found', [
                'No protocol file has been uploaded for this submission yet.',
                'If you believe this is a mistake, please contact the IACUC office or ask the researcher to re-upload their protocol file.',
            ]);
        }

        // A signed scan or amendment is read-only, and every permission check still runs
        // against the latest protocol version, so it counts as "latest".
        $isSignedScan    = $version['file_type'] === 'signed_scan';
        $isAmendment     = $version['file_type'] === 'amendment';
        $isLatestVersion = $isSignedScan
            || $isAmendment
            || ($latestVersion && (int) $version['id'] === (int) $latestVersion['id']);

        // ===== PERMISSIONS & BACK LINK =====
        $perm        = ProtocolPermissions::flags($protocol, $actor, $this->protocolModel);
        $isPersonnel = $perm['isPersonnel'];
        $backBase   = $isPersonnel ? ROOT . '/personnel/home' : ROOT . '/submissions';
        $fromFilter = isset($_GET['from']) ? preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $_GET['from'])) : '';
        $backUrl    = $fromFilter !== '' ? $backBase . '?status=' . $fromFilter : $backBase;

        $deletionRequested  = !empty($protocol['deletion_requested_at']);
        $deletionRequestedByYou = $this->isActor($protocol['deletion_requested_by'] ?? 0);
        $showTitleChangeBanner = !empty($protocol['previous_title'])
            && !$this->isActor($protocol['title_changed_by'] ?? 0)
            && !$this->isActor($protocol['title_change_seen_by'] ?? 0);

        $this->view('protocol', [
            'protocol'          => $protocol,
            'version'           => $version,
            'versions'          => $timeline,
            'fromFilter'        => $fromFilter,
            'isLatestVersion'   => $isLatestVersion,
            'isSignedScan'      => $isSignedScan,
            'isAmendment'       => $isAmendment,
            'csrf'              => $this->generateCsrfToken(),
            'permissions'       => $perm,
            'isPersonnel'           => $isPersonnel,
            'isStaff'           => $perm['isStaff'],
            'backUrl'           => $backUrl,
            'latestPaymentProofVersion' => $this->protocolModel->getLatestVersion($protocolId, 'payment_proof'),
            'latestSignedScanVersion'   => $this->protocolModel->getLatestVersion($protocolId, 'signed_scan'),
            'latestClearanceVersion'    => $this->protocolModel->getLatestVersion($protocolId, 'clearance'),
            'paymentRejection'  => $this->protocolModel->getLatestPaymentRejection($protocolId),
            'reviewerNote'      => $this->protocolModel->getReturnReasonForVersion((int) $version['id']),
            'canRename'         => $perm['canRename'],
            'canRequestDeletion' => $perm['canRequestDeletion'],
            'canDelete'         => $perm['canDelete'],
            'deletionRequested' => $deletionRequested,
            'deletionRequestedByYou' => $deletionRequestedByYou,
            'showTitleChangeBanner' => $showTitleChangeBanner,
            'hasRenames'        => $perm['hasRenames'],
            'hasPaymentHistory' => $perm['hasPaymentHistory'],
            'canAmend'          => $perm['canAmend'],
            'amendments'        => $this->addFileUrls($this->protocolModel->getVersions($protocolId, 'amendment')),
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
            $this->renderError(400, 'Missing Version ID', [
                'No file version was specified.',
            ]);
        }

        $version = $this->protocolModel->getVersionById($versionId);

        if (!$version) {
            $this->renderError(404, 'File Not Found', [
                'The file you are looking for does not exist or may have been removed.',
            ]);
        }


        if (!$this->isActor($version['owner_id']) && !$this->isPersonnel()) {
            $this->renderError(403, 'Access Denied', [
                'You do not have permission to view this file.',
            ]);
        }

        $filePath = dirname(__DIR__, 2) . '/storage/uploads/protocols/' . $version['file_path'];

        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->renderError(404, 'File Not Found', [
                'The file could not be found on the server.',
            ]);
        }

        $forceDownload = isset($_GET['download']);
        $displayName   = $version['original_name'] ?: basename($filePath);

        if ($version['file_type'] === 'protocol') {
            $owner          = $this->userModel->getUser((int) $version['owner_id']);
            $ext            = pathinfo($displayName, PATHINFO_EXTENSION) ?: 'pdf';
            $researcherName = trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));
            $displayName    = $this->protocolDisplayFilename($version['reference_no'] ?? null, $researcherName, $version['protocol_title'] ?? '', $ext);
        } elseif ($version['file_type'] === 'clearance') {
            $owner          = $this->userModel->getUser((int) $version['owner_id']);
            $ext            = pathinfo($displayName, PATHINFO_EXTENSION) ?: 'pdf';
            $researcherName = trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));
            $displayName    = $this->clearanceDisplayFilename($version['reference_no'] ?? null, $researcherName, $ext);
        }

        $this->streamFile($filePath, $displayName, $forceDownload);
    }

    // ===== DOWNLOAD SELECTED  (GET /apply/download_selected?ids=1,2,3) =====

    public function download_selected(): void
    {
        $this->requireLogin();

        $actor = $this->actor();
        if ($actor['role'] !== 'staff') {
            $this->renderError(403, 'Access Denied', [
                'This page is for administrative staff only.',
            ]);
        }

        if (!class_exists('ZipArchive')) {
            $this->renderError(500, 'Something Went Wrong', [
                'The PHP zip extension is not available on this server.',
            ]);
        }

        $rows = $this->protocolModel->getReviewedWithLatestFile();
        $ids  = array_map('intval', explode(',', (string) ($_GET['ids'] ?? '')));
        $rows = array_filter($rows, fn($r) => !empty($r['file_path']) && in_array((int) $r['id'], $ids, true));

        if (empty($rows)) {
            $this->renderError(404, 'Nothing To Download', [
                'None of the selected protocols are reviewed.',
            ]);
        }

        $baseDir = dirname(__DIR__, 2) . '/storage/uploads/protocols/';
        $zipPath = tempnam(sys_get_temp_dir(), 'reviewed_') . '.zip';

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

        $this->protocolModel->logAudit('bulk_download', $actor['id'], $actor['name'], $actor['role'], 'protocol', null, count($usedNames) . ' reviewed protocol(s) downloaded as ZIP');

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="reviewed_protocols.zip"');
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

        if ($this->actor()['role'] !== 'reviewer') {
            $this->jsonError(403, 'Reviewers only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];

        match ($body['action'] ?? '') {
            'save'   => $this->handleSaveAnnotation($body),
            'edit'   => $this->handleEditAnnotation($body),
            'delete' => $this->handleDeleteAnnotation($body),
            default  => $this->jsonError(400, 'Unknown action.'),
        };
    }

    private function handleGetAnnotations(): void
    {
        $versionId = (int) ($_GET['version_id'] ?? 0);
        if ($versionId < 1) {
            $this->jsonError(400, 'Missing version_id.');
        }

        $version = $this->protocolModel->getVersionById($versionId);

        if (!$version) {
            $this->jsonError(404, 'Version not found.');
        }


        if (!$this->isActor($version['owner_id']) && !$this->isPersonnel()) {
            $this->jsonError(403, 'Forbidden.');
        }

        if (!$this->isPersonnel()) {
            $latestVersion   = $this->protocolModel->getLatestVersion((int) $version['protocol_id'], 'protocol');
            $isLatestVersion = $latestVersion && (int) $latestVersion['id'] === $versionId;

            if ($isLatestVersion && strtolower($version['protocol_status'] ?? '') !== 'needs revision') {
                echo json_encode([]);
                exit;
            }
        }

        echo json_encode($this->protocolModel->getAnnotations($versionId, $this->isPersonnel()));
        exit;
    }

    private function handleSaveAnnotation(array $body): void
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
        $newId = $this->protocolModel->insertAnnotation($versionId, $pageNumber, $x, $y, $width, $height, $comment, $actor['id']);

        if (!$newId) {
            $this->jsonError(500, 'Could not save annotation.');
        }

        $this->protocolModel->logAudit('annotation_added', $actor['id'], $actor['name'], $actor['role'], 'protocol_version', $versionId, "Annotation added on page $pageNumber");

        echo json_encode(['id' => $newId, 'ok' => true, 'author' => $this->roleLabel($actor['role']) . ' - ' . $this->actorDisplayName($actor)]);
        exit;
    }

    private function handleEditAnnotation(array $body): void
    {
        $annotId = (int) ($body['id'] ?? 0);
        $comment = trim($body['comment'] ?? '');

        if ($annotId < 1 || $comment === '') {
            $this->jsonError(400, 'Missing or invalid fields.');
        }

        $actor = $this->actor();
        $ok    = $this->protocolModel->updateAnnotation($annotId, $comment);

        if ($ok) {
            $this->protocolModel->logAudit('annotation_edited', $actor['id'], $actor['name'], $actor['role'], 'annotation', $annotId, 'Annotation comment edited');
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    private function handleDeleteAnnotation(array $body): void
    {
        $annotId = (int) ($body['id'] ?? 0);
        if ($annotId < 1) {
            $this->jsonError(400, 'Missing annotation id.');
        }

        $actor = $this->actor();
        $ok    = $this->protocolModel->deleteAnnotation($annotId);

        if ($ok) {
            $this->protocolModel->logAudit('annotation_deleted', $actor['id'], $actor['name'], $actor['role'], 'annotation', $annotId, 'Annotation deleted');
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== STATUS UPDATE  (POST /apply/status) =====

    public function status(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if (!$this->isPersonnel()) {
            $this->jsonError(403, 'Administrative staff only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        $newStatus  = $body['status'] ?? '';

        if ($protocolId < 1 || $newStatus === '') {
            $this->jsonError(400, 'Invalid parameters.');
        }

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        // ===== ALLOWED STATUS CHANGES =====
        $allowedTransitions = [
            'reviewer' => ['under review' => ['Needs Revision', 'Reviewed']],
            'staff'    => [
                'reviewed' => ['Endorsed'],
                'endorsed' => ['Reviewed', 'Approved', 'Under Review'],
                'approved' => ['Endorsed'],
            ],
        ];

        $allowedTargets = $allowedTransitions[$actor['role']][strtolower($protocol['status'])] ?? [];
        if (!in_array($newStatus, $allowedTargets, true)) {
            $this->jsonError(422, "That status change isn't allowed from the protocol's current state.");
        }

        $isRevert = (in_array($newStatus, ['Reviewed', 'Under Review'], true) && strtolower($protocol['status']) === 'endorsed')
            || ($newStatus === 'Endorsed' && strtolower($protocol['status']) === 'approved');

        $reason = $newStatus === 'Under Review' ? mb_substr(trim((string) ($body['reason'] ?? '')), 0, 1000) : '';

        if ($newStatus === 'Endorsed' && $isRevert && !empty($protocol['hard_copy_released_at'])) {
            $this->jsonError(422, 'Undo the hard copy pickup before reverting this protocol.');
        }

        // ===== REQUIREMENTS BEFORE ENDORSING =====
        if ($newStatus === 'Endorsed' && !$isRevert) {
            if (($protocol['payment_status'] ?? 'unpaid') !== 'paid') {
                $this->jsonError(422, 'This protocol must be marked as paid before it can be endorsed.');
            }
            if (empty($protocol['reference_no'])) {
                $this->jsonError(422, 'Assign an IPN to this protocol before it can be endorsed.');
            }
            if (!$this->protocolModel->getLatestVersion($protocolId, 'signed_scan')) {
                $this->jsonError(422, 'A signed scan must be uploaded before this protocol can be endorsed.');
            }
        }

        // ===== CHECKS BEFORE REVIEWED / APPROVED =====
        $latestProtocolVersion = $this->protocolModel->getLatestVersion($protocolId, 'protocol');

        if ($newStatus === 'Reviewed' && !$isRevert) {
            $cert = $this->userModel->getCert((int) $protocol['user_id']);
            if (($cert['cert_status'] ?? '') !== 'verified') {
                $this->jsonError(422, "Verify the researcher's training certificate before marking this protocol as Reviewed. Press the researcher's name to open their profile and verify it.");
            }
            if ($latestProtocolVersion && $this->protocolModel->getAnnotations((int) $latestProtocolVersion['id'])) {
                $this->jsonError(422, 'This protocol still has comments. Either return for revision so the researcher can address them, or remove comments to confirm that your review is finished.');
            }
        }

        if ($newStatus === 'Approved' && !$this->protocolModel->getLatestVersion($protocolId, 'clearance')) {
            $this->jsonError(422, 'A clearance document must be attached before this protocol can be approved.');
        }

        // ===== SAVE NEW STATUS =====
        $ok = $this->protocolModel->updateStatus($protocolId, $newStatus);

        if ($ok) {
            $auditAction = $isRevert ? 'status_reverted' : 'status_updated';
            $this->protocolModel->logAudit($auditAction, $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Status changed to: $newStatus" . ($reason !== '' ? ". Reason: $reason" : ''));

            // ===== SNAPSHOT RECORD ON REVIEWED =====
            // Runs before notifications so a mail or file problem can never skip it.
            if ($newStatus === 'Reviewed' && !$isRevert) {
                try {
                    $this->recordModel->snapshotFromProtocol($protocol, $latestProtocolVersion);
                } catch (Throwable $e) {
                    error_log("Record snapshot failed (protocol #$protocolId): " . $e->getMessage());
                }
            }

            $this->notifyStatusChange($protocol, $newStatus, $reason);

            if ($newStatus === 'Under Review') {
                $this->notifyReviewersReturnedToReview($protocol, $actor, $reason);
            }

            // ===== SUCCESS MESSAGE =====
            $flashMessages = [
                'Under Review'   => 'Endorsement reverted. Protocol is back to To Review.',
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

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (!$this->isActor($protocol['user_id'])) {
            $this->jsonError(403, 'Access denied.');
        }
        if (strtolower($protocol['status']) !== 'reviewed') {
            $this->jsonError(422, 'Payment can only be confirmed once a protocol has been reviewed.');
        }

        $mustPayInPerson = UserModel::mustPayInPerson($protocol['submitter_school'] ?? '');
        $method = $mustPayInPerson ? 'in_person' : ($_POST['payment_method'] ?? '');
        if (!in_array($method, ['in_person', 'online'], true)) {
            $this->jsonError(400, 'Please choose a payment method.');
        }

        if ($method === 'in_person') {
            if (($_POST['confirm_in_person'] ?? '') !== '1') {
                $this->jsonError(422, 'Please confirm that you have paid in person.');
            }
        } else {
            $reason = null;
            $upload = $this->saveUpload('payment_proof_file', $this->protocolDir($protocolId), ['pdf', 'jpg', 'jpeg', 'png'], required: true, reason: $reason);
            if ($upload === false) {
                $this->jsonError(422, $reason ?? 'Upload failed. Please attach a photo or PDF of your proof of payment (max 10 MB).');
            }
            [$path, $originalName] = $upload;

            $versionId = $this->protocolModel->insertVersion($protocolId, $this->relPath($protocolId, $path), $originalName, $actor['id'], 'payment_proof');
            if (!$versionId) {
                $this->jsonError(500, 'Could not record the payment proof. Please try again.');
            }
        }

        $this->protocolModel->submitPaymentProof($protocolId, $method);
        $this->protocolModel->logAudit('payment_proof_uploaded', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, $method === 'in_person' ? 'Payment confirmed as paid in person' : 'Payment proof uploaded');
        $this->notifyPaymentProofUploaded($protocol, $actor, $method);

        $_SESSION['flash_success'] = $method === 'in_person'
            ? 'Payment confirmed. Administrative staff will verify it shortly.'
            : 'Proof of payment submitted. Administrative staff will verify it shortly.';
        echo json_encode(['success' => true]);
        exit;
    }

    // ===== VERIFY PAYMENT  (POST /apply/verify_payment) =====

    public function verify_payment(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'staff') {
            $this->jsonError(403, 'Administrative staff only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol_id.');
        }

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (($protocol['payment_status'] ?? 'unpaid') !== 'proof_submitted') {
            $this->jsonError(422, 'This protocol has no pending payment confirmation to verify.');
        }

        $ok = $this->protocolModel->markPaid($protocolId, $actor['id']);
        if ($ok) {
            $this->protocolModel->logAudit('payment_verified', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, 'Payment marked as paid');
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
        if ($actor['role'] !== 'staff') {
            $this->jsonError(403, 'Administrative staff only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol_id.');
        }

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (($protocol['payment_status'] ?? 'unpaid') !== 'paid') {
            $this->jsonError(422, 'This protocol is not currently marked as paid.');
        }
        if (strtolower($protocol['status']) !== 'reviewed') {
            $this->jsonError(422, "Payment can't be unmarked once the protocol has moved past this stage.");
        }

        $ok = $this->protocolModel->undoMarkPaid($protocolId);
        if ($ok) {
            $this->protocolModel->logAudit('payment_unmarked', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, 'Payment mark undone');
            $_SESSION['flash_success'] = 'Payment mark undone. The protocol is back to "proof submitted".';
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== HARD COPY PICKUP  (POST /apply/hard_copy) =====

    public function hard_copy(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'staff') {
            $this->jsonError(403, 'Administrative staff only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        $release    = ($body['action'] ?? '') === 'release';
        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol_id.');
        }

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (strtolower($protocol['status']) !== 'approved') {
            $this->jsonError(422, 'The hard copy pickup can only be recorded for approved protocols.');
        }
        if ($release === !empty($protocol['hard_copy_released_at'])) {
            $this->jsonError(422, $release ? 'The hard copy pickup was already recorded.' : 'The hard copy pickup has not been recorded.');
        }

        $ok = $this->protocolModel->setHardCopyReleased($protocolId, $release ? $actor['id'] : null);
        if ($ok) {
            $this->protocolModel->logAudit($release ? 'hard_copy_picked_up' : 'hard_copy_pickup_undone', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, $release ? 'Embossed hard copy picked up by the researcher' : 'Hard copy pickup undone');
            $_SESSION['flash_success'] = $release ? 'Hard copy pickup recorded.' : 'Hard copy pickup undone.';
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== HARD COPY CLAIMED BY RESEARCHER  (POST /apply/hard_copy_claim) =====

    public function hard_copy_claim(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor         = $this->actor();
        $actor['name'] = $this->actorDisplayName($actor);
        if ($actor['role'] !== 'researcher') {
            $this->jsonError(403, 'This action is for researchers only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol_id.');
        }

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (!$this->isActor($protocol['user_id'])) {
            $this->jsonError(403, 'Access denied.');
        }
        if (strtolower($protocol['status']) !== 'approved') {
            $this->jsonError(422, 'The hard copy can only be claimed for approved protocols.');
        }
        if (!empty($protocol['hard_copy_released_at'])) {
            $this->jsonError(422, 'The hard copy pickup was already recorded.');
        }

        $ok = $this->protocolModel->setHardCopyReleased($protocolId, $actor['id']);
        if ($ok) {
            $this->protocolModel->logAudit('hard_copy_claimed', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, 'Researcher marked the embossed hard copy as claimed');
            $_SESSION['flash_success'] = 'Hard copy marked as claimed.';
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
        if ($actor['role'] !== 'staff') {
            $this->jsonError(403, 'Administrative staff only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        $comment    = trim($body['comment'] ?? '');

        if ($protocolId < 1 || $comment === '') {
            $this->jsonError(400, 'Please explain why the payment is being rejected.');
        }

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (($protocol['payment_status'] ?? 'unpaid') !== 'proof_submitted') {
            $this->jsonError(422, 'This protocol has no pending payment to reject.');
        }

        $ok = $this->protocolModel->rejectPaymentProof($protocolId, $actor['id'], $comment);
        if ($ok) {
            $this->protocolModel->logAudit('payment_proof_rejected', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Payment proof rejected: $comment");
            $this->notifyPaymentRejected($protocol, $comment, $actor);
            $_SESSION['flash_success'] = 'Payment rejected. The researcher has been notified to resubmit.';
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
        if ($actor['role'] !== 'staff') {
            $this->jsonError(403, 'Administrative staff only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $protocolId = (int) ($_POST['protocol_id'] ?? 0);
        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol_id.');
        }

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (strtolower($protocol['status']) !== 'reviewed') {
            $this->jsonError(422, 'Only reviewed protocols can have a signed scan uploaded.');
        }
        if (($protocol['payment_status'] ?? 'unpaid') !== 'paid') {
            $this->jsonError(422, 'This protocol must be marked as paid before the signed scan can be uploaded.');
        }
        if (empty($protocol['reference_no'])) {
            $this->jsonError(422, 'Assign an IPN to this protocol before uploading the signed scan.');
        }

        $reason = null;
        $upload = $this->saveUpload('signed_scan_file', $this->protocolDir($protocolId), ['pdf'], required: true, reason: $reason);
        if ($upload === false) {
            $this->jsonError(422, $reason ?? 'Upload failed. Please attach the scanned, signed protocol (PDF, max 10 MB).');
        }
        [$path, $originalName] = $upload;

        $versionId = $this->protocolModel->insertVersion($protocolId, $this->relPath($protocolId, $path), $originalName, $actor['id'], 'signed_scan');
        if (!$versionId) {
            $this->jsonError(500, 'Could not record the signed scan. Please try again.');
        }

        $this->protocolModel->logAudit('signed_scan_uploaded', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, 'Signed scan uploaded');
        $this->notifySignedScanUploaded($protocol, $actor);

        $_SESSION['flash_success'] = 'Signed scan uploaded. Mark this protocol as endorsed once delivered to DA-CARFU.';
        echo json_encode(['success' => true]);
        exit;
    }

    // ===== AMENDMENT  (POST /apply/amendment_upload) =====

    public function amendment_upload(): void
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
        $note       = trim($_POST['note'] ?? '');

        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol_id.');
        }
        if ($note === '') {
            $this->jsonError(422, 'Please explain what changed in the protocol.');
        }
        if (mb_strlen($note) > 1000) {
            $this->jsonError(422, 'The explanation must be 1000 characters or fewer.');
        }

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (!$this->isActor($protocol['user_id'])) {
            $this->jsonError(403, 'Access denied.');
        }
        if (strtolower($protocol['status']) !== 'approved') {
            $this->jsonError(422, 'Amendments can only be submitted once the clearance has been released.');
        }

        $reason = null;
        $upload = ($_POST['use_staged'] ?? '') === '1'
            ? $this->takeStagedProtocol($this->protocolDir($protocolId), $reason)
            : $this->saveProtocolUpload('amendment_file', $this->protocolDir($protocolId), true, $reason);
        if ($upload === false) {
            $this->jsonError(422, $reason ?? 'Upload failed. Please attach the updated protocol (PDF, DOC, or DOCX, max 10 MB).');
        }
        [$path, $originalName] = $upload;

        $versionId = $this->protocolModel->insertVersion($protocolId, $this->relPath($protocolId, $path), $originalName, $actor['id'], 'amendment', $note);
        if (!$versionId) {
            $this->jsonError(500, 'Could not record the amendment. Please try again.');
        }

        $this->protocolModel->logAudit('protocol_amended', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, 'Amendment submitted');
        $this->notifyPersonnelProtocolAmended($protocol, $actor, $note);

        $_SESSION['flash_success'] = 'Amendment submitted. CCARD has been notified.';
        echo json_encode(['success' => true]);
        exit;
    }

    // ===== DUPLICATE TITLE GUARD (shared by submit and rename) =====

    private function rejectDuplicateTitle(string $title, ?int $excludeId = null): void
    {
        if ($this->protocolModel->titleExists($title, $excludeId)) {
            $this->jsonError(422, 'A protocol with this title has already been submitted. Please use a different title.');
        }
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
        $newTitle   = normalize_pasted_text(trim($body['title'] ?? ''));

        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol ID.');
        }
        if ($newTitle === '') {
            $this->jsonError(422, 'Please enter a research title.');
        }

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $actor         = $this->actor();
        $actor['name'] = $this->actorDisplayName($actor);
        $perm          = ProtocolPermissions::flags($protocol, $actor);

        if (!$perm['isOwner'] && !$perm['isPersonnel']) {
            $this->jsonError(403, 'Access denied.');
        }
        if (!$perm['canRename']) {
            $this->jsonError(422, 'This protocol can only be renamed while it is under review or needs revision.');
        }
        $this->rejectDuplicateTitle($newTitle, $protocolId);

        $oldTitle = $this->protocolModel->renameProtocol($protocolId, $newTitle, $actor['id'], $actor['name'], $actor['role']);

        if ($oldTitle === false) {
            $this->jsonError(422, 'Could not rename the protocol. Please choose a different title.');
        }

        $this->protocolModel->logAudit('protocol_renamed', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Renamed from \"$oldTitle\" to \"$newTitle\"");

        if ($perm['isPersonnel'] && !$perm['isOwner']) {
            $this->notifyProtocolRenamed($protocol, $oldTitle, $newTitle, $actor);
        } elseif ($perm['isOwner']) {
            $this->notifyPersonnelProtocolRenamed($protocol, $oldTitle, $newTitle, $actor);
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
        if ($actor['role'] !== 'researcher') {
            $this->jsonError(403, 'Administrative staff and reviewers delete protocols directly. Use the Delete button instead.');
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

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $isOwner = $this->isActor($protocol['user_id']);
        if ($actor['role'] === 'researcher' && !$isOwner) {
            $this->jsonError(403, 'Access denied.');
        }
        if (!empty($protocol['deletion_requested_at'])) {
            $this->jsonError(422, 'A deletion request has already been made for this protocol.');
        }

        $ok = $this->protocolModel->requestDeletion($protocolId, $actor['id'], $actor['name'], $actor['role'], $reason);

        if ($ok) {
            $this->protocolModel->logAudit('protocol_deletion_requested', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Requested deletion. Reason: $reason");
            $this->notifyDeletionRequested($protocol, $reason, $actor);
            $_SESSION['flash_success'] = 'Deletion request sent to administrative staff.';
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== DELETE  (POST /apply/delete): staff and reviewers only, any status =====

    public function delete(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $actor         = $this->actor();
        $actor['name'] = $this->actorDisplayName($actor);
        if (!$this->isPersonnel()) {
            $this->jsonError(403, 'Administrative staff or reviewer access only.');
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

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $ok = $this->protocolModel->softDelete($protocolId, $actor['id'], $actor['name'], $reason);

        if ($ok) {
            $this->protocolModel->logAudit('protocol_deleted', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Deleted. Reason: $reason");
            $this->notifyProtocolDeleted($protocol, $reason, $actor);
            $_SESSION['flash_success'] = 'Protocol deleted.';
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== APPROVE DELETION REQUEST  (POST /apply/approve_deletion): administrative staff only =====

    public function approve_deletion(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $actor         = $this->actor();
        $actor['name'] = $this->actorDisplayName($actor);
        if ($actor['role'] !== 'staff') {
            $this->jsonError(403, 'Administrative staff access only.');
        }

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);

        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol ID.');
        }

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (empty($protocol['deletion_requested_at'])) {
            $this->jsonError(422, 'There is no pending deletion request for this protocol.');
        }

        $reason = trim((string) $protocol['deletion_request_reason']) ?: 'Deletion request approved.';
        $ok     = $this->protocolModel->softDelete($protocolId, $actor['id'], $actor['name'], $reason);

        if ($ok) {
            $this->protocolModel->logAudit('protocol_deletion_approved', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Approved deletion request. Reason: $reason");
            $this->notifyProtocolDeleted($protocol, $reason, $actor);
            $_SESSION['flash_success'] = 'Deletion request approved. Protocol deleted.';
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ===== REJECT DELETION REQUEST  (POST /apply/reject_deletion): administrative staff only =====

    public function reject_deletion(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $actor         = $this->actor();
        $actor['name'] = $this->actorDisplayName($actor);
        if ($actor['role'] !== 'staff') {
            $this->jsonError(403, 'Administrative staff access only.');
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

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (empty($protocol['deletion_requested_at'])) {
            $this->jsonError(422, 'There is no pending deletion request for this protocol.');
        }

        $ok = $this->protocolModel->rejectDeletionRequest($protocolId, $reason);

        if ($ok) {
            $this->protocolModel->logAudit('protocol_deletion_rejected', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Rejected deletion request. Reason: $reason");
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
        if ($actor['role'] !== 'staff') {
            $this->jsonError(403, 'Administrative staff only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $protocolId = (int) ($_POST['protocol_id'] ?? 0);
        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol_id.');
        }

        $protocol = $this->protocolModel->getById($protocolId);

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

        $versionId = $this->protocolModel->insertVersion($protocolId, $this->relPath($protocolId, $docPath), $docOriginalName, $actor['id'], 'clearance');
        if (!$versionId) {
            $this->jsonError(500, 'Could not record the clearance file. Please try again.');
        }

        $this->protocolModel->logAudit('clearance_uploaded', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, 'Clearance uploaded');
        $_SESSION['flash_success'] = 'Clearance uploaded. You can now mark this protocol as Approved.';

        echo json_encode(['success' => true]);
        exit;
    }

    // ===== CLEARANCE FILE  (GET /apply/clearance/{protocolId}) =====

    public function clearance(int $protocolId = 0): void
    {
        $this->requireLogin();

        if ($protocolId < 1) {
            $this->renderError(400, 'Missing Protocol ID', [
                'No protocol was specified.',
            ]);
        }

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->renderError(404, 'Protocol Not Found', [
                'The protocol you are looking for does not exist or may have been removed.',
            ]);
        }

        $actor = $this->actor();
        $this->requireProtocolAccess($protocol, $actor['id'], $actor['role'], true);

        $version = $this->protocolModel->getLatestVersion($protocolId, 'clearance');
        if (!$version) {
            $dashboard = $this->isPersonnel() ? 'personnel/home' : 'submissions';
            $this->renderError(404, 'No Clearance File Found', [
                'No clearance document has been uploaded for this protocol yet.',
            ], [
                ['label' => '← Back to Dashboard', 'href' => ROOT . '/' . $dashboard],
            ]);
        }

        $target = 'apply/file/' . (int) $version['id'];
        if (isset($_GET['download'])) {
            $target .= '?download=1';

            if ($this->isActor($protocol['user_id'])) {
                if ($this->protocolModel->markClearanceClaimed($protocolId)) {
                    $this->protocolModel->logAudit('clearance_claimed', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, 'Clearance claimed');
                }
            }
        }

        $this->redirect($target);
    }

    // ===== CLEARANCE POOL UPLOAD  (POST /apply/clearance_pool_upload) =====

    public function clearance_pool_upload(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'staff') {
            $this->jsonError(403, 'Administrative staff only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $arNumber = trim($_POST['ar_number'] ?? '');
        if ($arNumber === '') {
            $this->jsonError(422, 'AR number is required.');
        }
        if (mb_strlen($arNumber) > 50) {
            $this->jsonError(422, 'AR number is too long.');
        }
        if ($this->arNumberTaken($arNumber) || $this->protocolModel->clearancePoolArExists($arNumber)) {
            $this->jsonError(422, 'That AR number already exists.');
        }

        $reason = null;
        $upload = $this->saveUpload('clearance_screenshot', $this->clearancePoolDir(), ['jpg', 'jpeg', 'png'], required: true, reason: $reason);
        if ($upload === false) {
            $this->jsonError(422, $reason ?? 'Please attach a JPG or PNG screenshot.');
        }
        [$path, $originalName] = $upload;

        if ($this->protocolModel->insertClearancePoolItem($path, $originalName, $arNumber, $actor['id']) === false) {
            @unlink($path);
            $this->jsonError(500, 'Could not save the screenshot. Please try again.');
        }

        $this->protocolModel->logAudit('clearance_pool_uploaded', $actor['id'], $actor['name'], $actor['role'], 'clearance_pool', null, "Screenshot uploaded with AR number $arNumber");

        echo json_encode(['success' => true]);
        exit;
    }

    // ===== CLEARANCE POOL LIST  (GET /apply/clearance_pool) =====

    public function clearance_pool(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'staff') {
            $this->jsonError(403, 'Administrative staff only.');
        }

        $unassigned = array_map(
            fn($item) => $item + ['file_url' => ROOT . '/apply/clearance_pool_file/' . (int) $item['id']],
            $this->protocolModel->getUnassignedClearancePool()
        );
        $staged = array_map(
            fn($item) => $item + ['file_url' => ROOT . '/apply/clearance_pool_file/' . (int) $item['id']],
            $this->protocolModel->getStagedClearanceItems()
        );

        echo json_encode([
            'unassigned' => $unassigned,
            'staged' => $staged,
            'endorsed_protocols' => $this->protocolModel->getEndorsedProtocols(),
        ]);
        exit;
    }

    // ===== CLEARANCE POOL FILE  (GET /apply/clearance_pool_file/{id}) =====

    public function clearance_pool_file(int $poolId = 0): void
    {
        $this->requireLogin();

        $actor = $this->actor();
        if ($actor['role'] !== 'staff') {
            $this->renderError(403, 'Access Denied', [
                'This page is for administrative staff only.',
            ]);
        }

        $item  = $this->protocolModel->getClearancePoolItem($poolId);

        if (!$item || !file_exists($item['file_path'])) {
            $this->renderError(404, 'File Not Found', [
                'The clearance file you are looking for does not exist or may have been removed.',
            ]);
        }

        $this->streamFile($item['file_path'], $item['original_name']);
    }

    // ===== CLEARANCE STAGE  (POST /apply/clearance_stage) =====

    public function clearance_stage(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'staff') {
            $this->jsonError(403, 'Administrative staff only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $poolId     = (int) ($body['pool_id'] ?? 0);
        $protocolId = (int) ($body['protocol_id'] ?? 0);

        if ($poolId < 1 || $protocolId < 1) {
            $this->jsonError(400, 'Invalid parameters.');
        }

        $item     = $this->protocolModel->getClearancePoolItem($poolId);
        $protocol = $this->protocolModel->getById($protocolId);

        if (!$item || $item['protocol_id'] !== null) {
            $this->jsonError(422, 'That screenshot is no longer available.');
        }
        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (strtolower($protocol['status']) !== 'endorsed') {
            $this->jsonError(422, 'Only endorsed protocols can receive a clearance.');
        }
        if (empty($item['ar_number'])) {
            $this->jsonError(422, 'That screenshot has no AR number. Delete it and upload it again.');
        }

        $ok = $this->protocolModel->stageClearancePoolItem($poolId, $protocolId, $actor['id']);
        if (!$ok) {
            $this->jsonError(422, 'That screenshot was just matched by someone else.');
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // ===== CLEARANCE UNSTAGE  (POST /apply/clearance_unstage) =====

    public function clearance_unstage(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'staff') {
            $this->jsonError(403, 'Administrative staff only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $poolId = (int) ($body['pool_id'] ?? 0);
        if ($poolId < 1) {
            $this->jsonError(400, 'Missing pool_id.');
        }

        $ok = $this->protocolModel->unstageClearancePoolItem($poolId);
        if (!$ok) {
            $this->jsonError(422, 'That screenshot has already been confirmed: use "detach" instead.');
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // ===== CLEARANCE DELETE  (POST /apply/clearance_delete) =====

    public function clearance_delete(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'staff') {
            $this->jsonError(403, 'Administrative staff only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $poolId = (int) ($body['pool_id'] ?? 0);
        if ($poolId < 1) {
            $this->jsonError(400, 'Missing pool_id.');
        }

        $item  = $this->protocolModel->getClearancePoolItem($poolId);

        if (!$item || $item['protocol_id'] !== null) {
            $this->jsonError(422, 'That screenshot is no longer available to delete.');
        }

        if (!$this->protocolModel->deleteClearancePoolItem($poolId)) {
            $this->jsonError(422, 'Could not delete that screenshot. It may have just been matched.');
        }

        if (!empty($item['file_path'])) {
            @unlink($item['file_path']);
        }

        $this->protocolModel->logAudit('clearance_pool_item_deleted', $actor['id'], $actor['name'], $actor['role'], 'clearance_pool', null, "Deleted pool screenshot: {$item['original_name']}");

        echo json_encode(['success' => true]);
        exit;
    }

    // ===== ASSIGN IPN  (POST /apply/assign_ipn) =====

    public function assign_ipn(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'staff') {
            $this->jsonError(403, 'Administrative staff only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        $refNo      = trim($body['reference_no'] ?? '');

        if ($protocolId < 1) {
            $this->jsonError(400, 'Invalid protocol.');
        }
        if ($refNo === '') {
            $this->jsonError(422, 'IPN is required.');
        }
        if (!preg_match('/^\d{6}$/', $refNo)) {
            $this->jsonError(422, 'IPN must be 6 digits, with the last 2 digits as the year (e.g. 000026).');
        }

        $protocol = $this->protocolModel->getById($protocolId);
        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (strtolower($protocol['status']) !== 'reviewed') {
            $this->jsonError(422, 'An IPN can only be assigned while the protocol is Reviewed.');
        }
        if (($protocol['payment_status'] ?? 'unpaid') !== 'paid') {
            $this->jsonError(422, 'Verify the payment before assigning an IPN.');
        }

        $existingRecord = $this->recordModel->getByProtocolId($protocolId);

        $refTakenOnProtocols = $protocol['reference_no'] !== $refNo && $this->protocolModel->referenceNoExists($refNo);
        $refTakenOnRecords   = (!$existingRecord || $existingRecord['reference_no'] !== $refNo) && $this->recordModel->refExists($refNo);
        if ($refTakenOnProtocols || $refTakenOnRecords) {
            $this->jsonError(422, 'That IPN already exists.');
        }

        if (!$this->protocolModel->setReferenceNo($protocolId, $refNo)) {
            $this->jsonError(500, 'Could not save the IPN. Please try again.');
        }
        if ($existingRecord) {
            $this->recordModel->setReferenceNoByProtocolId($protocolId, $refNo);
        }

        $previousIpn = $protocol['reference_no'] ?? '';
        $this->protocolModel->logAudit('protocol_ipn_assigned', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, $previousIpn !== '' ? "IPN changed from $previousIpn to $refNo" : "IPN assigned: $refNo");

        $_SESSION['flash_success'] = $previousIpn !== ''
            ? "IPN updated to $refNo."
            : "IPN $refNo assigned.";

        echo json_encode(['success' => true, 'reference_no' => $refNo]);
        exit;
    }

    // ===== CLEARANCE CONFIRM  (POST /apply/clearance_confirm) =====

    public function clearance_confirm(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'staff') {
            $this->jsonError(403, 'Administrative staff only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $poolId = (int) ($body['pool_id'] ?? 0);

        $staged = $this->protocolModel->getStagedClearanceItems();
        if ($poolId > 0) {
            $staged = array_filter($staged, fn($item) => (int) $item['id'] === $poolId);
        }

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

            $protocol = $this->protocolModel->getById($protocolId);
            $record   = $this->recordModel->getByProtocolId($protocolId);
            if ($this->arNumberTaken($item['ar_number'], $protocol, $record)) {
                $failures[] = $item['protocol_title'] . ': AR number ' . $item['ar_number'] . ' already exists: skipped.';
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

            $versionId = $this->protocolModel->insertVersion($protocolId, $this->relPath($protocolId, $dest), $item['original_name'], $actor['id'], 'clearance');
            if (!$versionId || !$this->protocolModel->confirmClearancePoolItem((int) $item['id'], $versionId)) {
                $failures[] = $item['protocol_title'] . ': could not record the clearance file.';
                continue;
            }

            $this->protocolModel->setArNumber($protocolId, $item['ar_number']);
            if ($record) {
                $this->recordModel->setArNumberByProtocolId($protocolId, $item['ar_number']);
            }
            $this->protocolModel->updateStatus($protocolId, 'Approved');
            $this->notifyStatusChange($protocol, 'Approved');
            $this->protocolModel->logAudit('clearance_confirmed', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Clearance pool item #{$item['id']} confirmed; marked Approved");
            $confirmed++;
        }

        $_SESSION['flash_success'] = "$confirmed protocol(s) approved." . ($failures ? ' Some items need another look.' : '');
        echo json_encode(['confirmed' => $confirmed, 'failures' => $failures]);
        exit;
    }

    // ===== CLEARANCE UNASSIGN  (POST /apply/clearance_unassign) =====

    public function clearance_unassign(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $actor = $this->actor();
        if ($actor['role'] !== 'staff') {
            $this->jsonError(403, 'Administrative staff only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $poolId = (int) ($body['pool_id'] ?? 0);
        if ($poolId < 1) {
            $this->jsonError(400, 'Missing pool_id.');
        }

        $item  = $this->protocolModel->getClearancePoolItem($poolId);

        if (!$item || $item['protocol_id'] === null) {
            $this->jsonError(422, 'That screenshot is not currently assigned.');
        }
        if (empty($item['version_id'])) {
            $this->jsonError(422, 'That screenshot hasn\'t been confirmed yet: use "unstage" instead.');
        }

        $protocolId = (int) $item['protocol_id'];
        $protocol   = $this->protocolModel->getById($protocolId);

        if ($protocol && strtolower($protocol['status']) === 'approved') {
            $this->protocolModel->updateStatus($protocolId, 'Endorsed');
            $this->notifyStatusChange($protocol, 'Endorsed');
        }

        if (!empty($item['version_id'])) {
            $this->protocolModel->deleteVersion((int) $item['version_id']);
        }

        $this->protocolModel->unassignClearancePoolItem($poolId);
        $this->protocolModel->logAudit('clearance_unassigned', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, "Clearance pool item #$poolId detached; reverted to Endorsed");
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
            $this->jsonError(403, 'Reviewers only.');
        }

        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);
        $comment    = trim($body['comment'] ?? '');

        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol ID.');
        }

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }
        if (strtolower($protocol['status']) !== 'under review') {
            $this->jsonError(422, 'Only protocols under review can be returned for revision.');
        }

        $currentVersion = $this->protocolModel->getLatestVersion($protocolId, 'protocol');
        $reasonSaved    = $this->protocolModel->insertReturnReason($protocolId, $actor['id'], $comment, $currentVersion['id'] ?? null);
        if (!$reasonSaved) {
            error_log("insertReturnReason failed for protocol $protocolId");
        }

        $ok = $this->protocolModel->updateStatus($protocolId, 'Needs Revision');

        if ($ok) {
            $detail = 'Returned for revision.'
                . ($comment !== '' ? ' Comment: ' . mb_substr($comment, 0, 200) : '');
            $this->protocolModel->logAudit('protocol_returned', $actor['id'], $actor['name'], $actor['role'], 'protocol', $protocolId, $detail);
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

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $actor = $this->actor();
        $this->requireProtocolAccess($protocol, $actor['id'], $actor['role']);

        echo json_encode(['reason' => $this->protocolModel->getLatestReturnReason($protocolId, $this->isPersonnel())]);
        exit;
    }

    // ===== RESUBMIT TRAINING CERTIFICATE  (POST /apply/reuploadcert) =====

    public function reuploadcert(): void
    {
        $this->requireLogin();
        $this->requireResearcher();
        $this->requirePostMethod();
        $this->verifyCsrfToken(false);

        $actor = $this->actor();
        $cert  = $this->userModel->getCert($actor['id']);
        $error = null;
        $upload = null;

        if (!$cert || $cert['cert_status'] !== 'rejected') {
            $error = 'You can upload a new certificate only after a reviewer requests a resubmission.';
        } else {
            $reason = null;
            $upload = $this->saveUpload('cert_file', $this->certDir($actor['id']), ['pdf', 'jpg', 'jpeg', 'png'], required: true, reason: $reason);
            if ($upload === false) {
                $error = $reason ?? 'Upload failed. Accepted formats: PDF, JPG, PNG (max 10 MB).';
            }
        }

        if (!$error) {
            [$certPath, $certOriginalName] = $upload;

            if ($this->userModel->saveCert($actor['id'], 'certs/' . $actor['id'] . '/' . basename($certPath), $certOriginalName)) {
                $this->userModel->logAudit('cert_reuploaded', $actor['id'], $actor['name'], $actor['role'], 'user', $actor['id'], 'Researcher resubmitted training certificate');
                $this->notifyCertSubmitted($actor['id'], $this->actorDisplayName($actor));
                $_SESSION['flash_success'] = 'Certificate submitted. You will be notified once it is reviewed.';
            } else {
                $error = 'Could not save your certificate. Please try again.';
            }
        }

        if ($error) {
            $_SESSION['flash_error'] = $error;
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? ROOT . '/submissions'));
        exit;
    }

    // ===== MARK TITLE CHANGE SEEN  (POST /apply/mark_title_seen) =====

    public function mark_title_seen(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');
        $this->requirePostMethod();
        $this->verifyCsrfHeader();

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $protocolId = (int) ($body['protocol_id'] ?? 0);

        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol_id.');
        }

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $actor = $this->actor();
        $this->requireProtocolAccess($protocol, $actor['id'], $actor['role']);

        $this->protocolModel->markTitleChangeSeen($protocolId, $actor['id']);

        echo json_encode(['success' => true]);
        exit;
    }

    // ===== PAYMENT EVENTS (proofs, rejections, verification), newest first =====

    private function paymentEvents(array $protocol, array $proofFiles): array
    {
        $events = [];
        foreach ($proofFiles as $file) {
            $events[] = ['type' => 'proof', 'at' => $file['uploaded_at'], 'original_name' => $file['original_name'], 'file_url' => $file['file_url']];
        }
        foreach ($this->protocolModel->getPaymentRejections((int) $protocol['protocol_id']) as $rejection) {
            $events[] = ['type' => 'rejected', 'at' => $rejection['created_at'], 'comment' => $rejection['comment']];
        }
        if (($protocol['payment_status'] ?? '') === 'paid' && !empty($protocol['paid_at'])) {
            $events[] = ['type' => 'paid', 'at' => $protocol['paid_at'], 'method' => $protocol['payment_method'] ?? ''];
        }
        usort($events, fn($a, $b) => strcmp($b['at'], $a['at']));
        return $events;
    }

    // ===== SHARED MODAL DATA  (GET /apply/modaldata/{protocolId}) =====
    // Feeds the shared Payment History and Amendments modals.

    public function modaldata(int $protocolId = 0): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        if ($protocolId < 1) {
            $this->jsonError(400, 'Missing protocol ID.');
        }

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $actor = $this->actor();
        $this->requireProtocolAccess($protocol, $actor['id'], $actor['role']);

        $proofFiles = $this->addFileUrls($this->protocolModel->getVersions($protocolId, 'payment_proof'));

        echo json_encode([
            'protocol_id'     => $protocolId,
            'title'           => $protocol['research_title'],
            'payment_history' => $this->paymentEvents($protocol, $proofFiles),
            'amendments'      => $this->addFileUrls($this->protocolModel->getVersions($protocolId, 'amendment')),
        ]);
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

        $protocol = $this->protocolModel->getById($protocolId);

        if (!$protocol) {
            $this->jsonError(404, 'Protocol not found.');
        }

        $actor = $this->actor();
        $this->requireProtocolAccess($protocol, $actor['id'], $actor['role']);

        // Protocol versions and signed scans, newest first. Amendments have their own modal.
        $versions = array_values(array_filter(
            $this->protocolModel->getVersionTimeline($protocolId),
            fn($v) => $v['file_type'] !== 'amendment'
        ));
        $versions = array_map(function ($v) use ($protocolId, $protocol) {
            if ($v['file_type'] === 'protocol') {
                $v['title_at_version'] = $this->protocolModel->getTitleAsOf($protocolId, $v['uploaded_at']) ?? $protocol['research_title'];
            }
            return $v;
        }, $versions);

        $titleHistory = $this->protocolModel->getTitleHistory($protocolId);

        echo json_encode([
            'protocol_id'         => $protocolId,
            'title'               => $protocol['research_title'],
            'status'              => $protocol['status'],
            'reference_no'        => $protocol['reference_no'] ?? '',
            'versions'            => $versions,
            'payment_proof_files' => $this->addFileUrls($this->protocolModel->getVersions($protocolId, 'payment_proof')),
            'title_history'       => count($titleHistory) > 1 ? $titleHistory : [],
        ]);
        exit;
    }
}
