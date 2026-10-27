<?php

class ProtocolModel extends Model
{
    private const AUTHOR_SQL = "CONCAT(u.first_name, ' ', u.last_name)";

    // ===== CREATE PROTOCOL =====
    // Details the researcher enters on submission (column => label), all required
    public const DETAIL_FIELDS = [
        'animal_type'      => 'Animal type',
        'animal_count'     => 'Animal count',
        'research_adviser' => 'Research adviser',
        'veterinarian'     => 'Veterinarian',
        'research_duration_start' => 'Research start month',
        'research_duration_end'   => 'Research end month',
    ];

    // ===== PAYMENT STATUS LABELS =====
    public const PAYMENT_LABELS = [
        'unpaid'          => 'Unpaid',
        'proof_submitted' => 'Verifying Payment',
        'rejected'        => 'Payment Rejected',
        'paid'            => 'Paid',
    ];

    // ===== ENDORSEMENT STEPS (Reviewed status, staff view) =====
    public const ENDORSEMENT_STEPS = [
        1 => 'Researcher uploads the payment proof',
        2 => 'Staff verifies the payment proof',
        3 => 'Staff assigns an IPN',
        4 => 'Print for the chairperson\'s signature, then upload the signed scan',
        5 => 'Staff marks the protocol as endorsed',
    ];

    // Returns [step number, label, waiting on researcher]
    public static function nextStep(string $paymentStatus, bool $hasIpn, bool $hasSignedScan): array
    {
        return match (true) {
            $paymentStatus === 'proof_submitted' => [2, 'Verify the payment proof', false],
            $paymentStatus === 'rejected'        => [1, 'Waiting for the researcher to resubmit payment proof', true],
            $paymentStatus !== 'paid'            => [1, 'Waiting for the researcher to upload payment proof', true],
            !$hasIpn                             => [3, 'Assign an IPN', false],
            !$hasSignedScan                      => [4, 'Print for the chairperson\'s signature, then upload the signed scan', false],
            default                              => [5, 'Ready to endorse', false],
        };
    }

    public function insertProtocol(int $userId, string $title, array $details): int | false
    {
        $title        = mb_substr(normalize_pasted_text($title), 0, 255) ?: 'Untitled Protocol';
        $animalType   = normalize_pasted_text($details['animal_type']);
        $adviser      = normalize_pasted_text($details['research_adviser']);
        $veterinarian = normalize_pasted_text($details['veterinarian']);

        $stmt = $this->connection->prepare(
            "INSERT INTO `protocols` (user_id, title, animal_type, animal_count, research_adviser, veterinarian, research_duration_start, research_duration_end, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Under Review')"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('ississss', $userId, $title, $animalType, $details['animal_count'], $adviser, $veterinarian, $details['research_duration_start'], $details['research_duration_end']);
        if (! $stmt->execute()) {
            return false;
        }

        $protocolId = $this->connection->insert_id;
        $this->insertTitleHistory($protocolId, $title, $userId, null, 'researcher');

        return $protocolId;
    }

    // ===== DUPLICATE TITLE CHECK =====

    // Case-insensitive, ignores surrounding spaces. Soft-deleted protocols don't count.
    // Pass $excludeId when renaming so a protocol never clashes with itself.
    public function titleExists(string $title, ?int $excludeId = null): bool
    {
        $title     = mb_substr(trim($title), 0, 255);
        $excludeId = $excludeId ?? 0;

        $stmt = $this->connection->prepare(
            "SELECT 1 FROM `protocols`
             WHERE LOWER(TRIM(title)) = LOWER(?) AND id != ? AND deleted_at IS NULL
             LIMIT 1"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('si', $title, $excludeId);
        $stmt->execute();

        return $stmt->get_result()->num_rows > 0;
    }

    // ===== RENAME =====

    private function insertTitleHistory(
        int $protocolId,
        string $title,
        ?int $changedBy,
        ?string $changedByName,
        ?string $changedByRole
    ): void {
        $stmt = $this->connection->prepare(
            "INSERT INTO `protocol_title_history`
                (protocol_id, title, changed_by, changed_by_name, changed_by_role)
             VALUES (?, ?, ?, ?, ?)"
        );
        if (! $stmt) {
            return;
        }

        $stmt->bind_param('isiss', $protocolId, $title, $changedBy, $changedByName, $changedByRole);
        $stmt->execute();
    }

    public function renameProtocol(
        int $protocolId,
        string $newTitle,
        int $actorId,
        string $actorName,
        string $actorRole
    ): string | false {
        $newTitle = mb_substr(trim($newTitle), 0, 255);
        if ($newTitle === '') {
            return false;
        }

        $current = $this->getById($protocolId);
        if (! $current) {
            return false;
        }

        $oldTitle = $current['research_title'];
        if ($oldTitle === $newTitle) {
            return false;
        }

        $stmt = $this->connection->prepare(
            "UPDATE `protocols`
             SET title = ?, previous_title = ?, title_changed_by = ?,
                 title_changed_by_name = ?, title_changed_by_role = ?, title_changed_at = NOW(),
                 title_change_seen_by = NULL
             WHERE id = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('ssissi', $newTitle, $oldTitle, $actorId, $actorName, $actorRole, $protocolId);
        if (! $stmt->execute()) {
            return false;
        }

        $this->insertTitleHistory($protocolId, $newTitle, $actorId, $actorName, $actorRole);

        return $oldTitle;
    }

    public function markTitleChangeSeen(int $protocolId, int $userId): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE `protocols` SET title_change_seen_by = ? WHERE id = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('ii', $userId, $protocolId);
        return $stmt->execute();
    }

    public function getTitleAsOf(int $protocolId, string $asOf): ?string
    {
        $stmt = $this->connection->prepare(
            "SELECT title FROM `protocol_title_history`
             WHERE protocol_id = ? AND changed_at <= ?
             ORDER BY changed_at DESC, id DESC
             LIMIT 1"
        );
        if (! $stmt) {
            return null;
        }

        $stmt->bind_param('is', $protocolId, $asOf);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row['title'] ?? null;
    }

    public function getTitleHistory(int $protocolId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT title, changed_by_name, changed_by_role, changed_at
             FROM `protocol_title_history`
             WHERE protocol_id = ?
             ORDER BY changed_at DESC, id DESC"
        );
        if (! $stmt) {
            return [];
        }

        $stmt->bind_param('i', $protocolId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ===== DELETE / REQUEST DELETION =====

    public function requestDeletion(
        int $protocolId,
        int $actorId,
        string $actorName,
        string $actorRole,
        string $reason
    ): bool {
        $reason = mb_substr(trim($reason), 0, 1000);
        if ($reason === '') {
            return false;
        }

        $stmt = $this->connection->prepare(
            "UPDATE `protocols`
             SET deletion_requested_at = NOW(), deletion_requested_by = ?,
                 deletion_requested_by_name = ?, deletion_requested_by_role = ?,
                 deletion_request_reason = ?
             WHERE id = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('isssi', $actorId, $actorName, $actorRole, $reason, $protocolId);
        return $stmt->execute();
    }

    public function softDelete(
        int $protocolId,
        int $actorId,
        string $actorName,
        string $reason
    ): bool {
        $reason = mb_substr(trim($reason), 0, 1000);
        if ($reason === '') {
            return false;
        }

        $stmt = $this->connection->prepare(
            "UPDATE `protocols`
             SET deleted_at = NOW(), deleted_by = ?, deleted_by_name = ?, deletion_reason = ?
             WHERE id = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('issi', $actorId, $actorName, $reason, $protocolId);
        return $stmt->execute();
    }

    public function rejectDeletionRequest(int $protocolId, string $reason): bool
    {
        $reason = mb_substr(trim($reason), 0, 1000);

        $stmt = $this->connection->prepare(
            "UPDATE `protocols`
             SET deletion_requested_at = NULL, deletion_requested_by = NULL,
                 deletion_requested_by_name = NULL, deletion_requested_by_role = NULL,
                 deletion_request_reason = NULL, deletion_rejection_reason = ?
             WHERE id = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('si', $reason, $protocolId);
        return $stmt->execute();
    }

    // ===== PAYMENT =====

    public function submitPaymentProof(int $protocolId, string $method): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE `protocols` SET payment_status = 'proof_submitted', payment_method = ? WHERE id = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('si', $method, $protocolId);
        return $stmt->execute();
    }

    public function markPaid(int $protocolId, int $verifiedBy): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE `protocols`
             SET payment_status = 'paid', paid_at = NOW(), paid_by = ?
             WHERE id = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('ii', $verifiedBy, $protocolId);
        return $stmt->execute();
    }

    public function markClearanceClaimed(int $protocolId): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE `protocols`
             SET clearance_claimed_at = NOW(), updated_at = updated_at
             WHERE id = ? AND clearance_claimed_at IS NULL"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('i', $protocolId);
        return $stmt->execute() && $stmt->affected_rows > 0;
    }

    public function setHardCopyReleased(int $protocolId, ?int $releasedBy): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE `protocols`
             SET hard_copy_released_at = IF(? IS NULL, NULL, NOW()), hard_copy_released_by = ?, updated_at = updated_at
             WHERE id = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('iii', $releasedBy, $releasedBy, $protocolId);
        return $stmt->execute();
    }

    public function undoMarkPaid(int $protocolId): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE `protocols`
             SET payment_status = 'proof_submitted', paid_at = NULL, paid_by = NULL
             WHERE id = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('i', $protocolId);
        return $stmt->execute();
    }

    public function rejectPaymentProof(int $protocolId, int $reviewerId, string $comment): bool
    {
        $comment = mb_substr(trim($comment), 0, 1000);
        if ($comment === '') {
            return false;
        }

        $stmt = $this->connection->prepare(
            "INSERT INTO `payment_proof_rejections` (protocol_id, reviewer_id, comment) VALUES (?, ?, ?)"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('iis', $protocolId, $reviewerId, $comment);
        if (! $stmt->execute()) {
            return false;
        }

        $stmt = $this->connection->prepare(
            "UPDATE `protocols` SET payment_status = 'rejected' WHERE id = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('i', $protocolId);
        return $stmt->execute();
    }

    public function getPaymentRejections(int $protocolId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT comment, created_at
             FROM `payment_proof_rejections`
             WHERE protocol_id = ?
             ORDER BY created_at DESC, id DESC"
        );
        if (! $stmt) {
            return [];
        }

        $stmt->bind_param('i', $protocolId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getLatestPaymentRejection(int $protocolId): ?array
    {
        $stmt = $this->connection->prepare(
            "SELECT r.comment, r.created_at, u.first_name, u.last_name
             FROM `payment_proof_rejections` r
             JOIN `users` u ON u.id = r.reviewer_id
             WHERE r.protocol_id = ?
             ORDER BY r.created_at DESC
             LIMIT 1"
        );
        if (! $stmt) {
            return null;
        }

        $stmt->bind_param('i', $protocolId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // ===== PROTOCOL LISTS =====
    public function getEndorsedProtocols(): array
    {
        $sql = "SELECT
                    p.id AS protocol_id, p.reference_no, p.ar_number, p.title AS research_title,
                    u.first_name, u.last_name,
                    (SELECT pv.id FROM `protocol_versions` pv
                     WHERE pv.protocol_id = p.id AND pv.file_type = 'clearance'
                     ORDER BY pv.version_number DESC LIMIT 1) AS latest_clearance_version_id
                FROM `protocols` p
                JOIN `users` u ON u.id = p.user_id
                WHERE p.status = 'Endorsed'
                ORDER BY p.reference_no ASC";

        $result = $this->connection->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getReviewedWithLatestFile(): array
    {
        $sql = "SELECT
                    p.id, p.reference_no, p.title AS research_title,
                    (SELECT pv.file_path
                     FROM `protocol_versions` pv
                     WHERE pv.protocol_id = p.id
                       AND pv.file_type = 'protocol'
                     ORDER BY pv.version_number DESC
                     LIMIT 1) AS file_path
                FROM `protocols` p
                WHERE p.status = 'Reviewed'
                ORDER BY p.reference_no ASC";

        $result = $this->connection->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getAll(): array
    {
        $sql = "SELECT
                    p.id            AS protocol_id,
                    p.reference_no,
                    p.ar_number,
                    p.title         AS research_title,
                    p.status,
                    p.submitted_at,
                    p.updated_at,
                    p.user_id,
                    p.previous_title,
                    p.title_changed_by_name,
                    p.title_changed_by_role,
                    p.title_changed_at,
                    p.deletion_requested_at,
                    p.deletion_requested_by_name,
                    p.deletion_requested_by_role,
                    p.deletion_request_reason,
                    p.payment_status,
                    p.payment_method,
                    p.paid_at,
                    p.paid_by,
                    p.hard_copy_released_at,
                    u.first_name,
                    u.last_name,
                    u.school,
                    (SELECT MAX(pv.version_number)
                     FROM `protocol_versions` pv
                     WHERE pv.protocol_id = p.id
                       AND pv.file_type = 'protocol') AS latest_version,
                    (SELECT pv1.id
                     FROM `protocol_versions` pv1
                     WHERE pv1.protocol_id = p.id
                       AND pv1.file_type = 'protocol'
                     ORDER BY pv1.version_number DESC
                     LIMIT 1) AS latest_protocol_version_id,
                    (SELECT pv5.id
                     FROM `protocol_versions` pv5
                     WHERE pv5.protocol_id = p.id
                       AND pv5.file_type = 'payment_proof'
                     ORDER BY pv5.version_number DESC
                     LIMIT 1) AS latest_payment_proof_version_id,
                    (SELECT pv6.id
                     FROM `protocol_versions` pv6
                     WHERE pv6.protocol_id = p.id
                       AND pv6.file_type = 'signed_scan'
                     ORDER BY pv6.version_number DESC
                     LIMIT 1) AS latest_signed_scan_version_id,
                    (SELECT pv7.id
                     FROM `protocol_versions` pv7
                     WHERE pv7.protocol_id = p.id
                       AND pv7.file_type = 'clearance'
                     ORDER BY pv7.version_number DESC
                     LIMIT 1) AS latest_clearance_version_id,
                    (SELECT COUNT(*) FROM `protocol_title_history` h
                     WHERE h.protocol_id = p.id) AS title_history_count,
                    (SELECT COUNT(*) FROM `protocol_versions` pva
                     WHERE pva.protocol_id = p.id
                       AND pva.file_type = 'amendment') AS amendment_count,
                    EXISTS(SELECT 1 FROM `payment_proof_rejections` pr
                           WHERE pr.protocol_id = p.id) AS has_payment_rejections,
                    GREATEST(
                        p.updated_at,
                        COALESCE(
                            (SELECT MAX(pv4.uploaded_at)
                             FROM `protocol_versions` pv4
                             WHERE pv4.protocol_id = p.id),
                            p.updated_at
                        )
                    ) AS last_activity_at
                FROM `protocols` p
                JOIN `users` u ON u.id = p.user_id
                WHERE u.status != 'deactivated' AND p.deleted_at IS NULL
                ORDER BY last_activity_at DESC, p.id DESC";

        $result = $this->connection->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getByUser(int $userId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT
                p.id            AS protocol_id,
                p.user_id,
                p.reference_no,
                p.title         AS research_title,
                p.status,
                p.submitted_at,
                p.updated_at,
                p.previous_title,
                p.title_changed_by_name,
                p.title_changed_by_role,
                p.title_changed_at,
                p.deletion_requested_at,
                p.deletion_requested_by_name,
                p.deletion_requested_by_role,
                p.deletion_request_reason,
                p.payment_status,
                p.payment_method,
                p.paid_at,
                p.clearance_claimed_at,
                p.hard_copy_released_at,
                (SELECT MAX(pv.version_number)
                 FROM `protocol_versions` pv
                 WHERE pv.protocol_id = p.id
                   AND pv.file_type = 'protocol') AS latest_version,
                (SELECT pv5.id
                 FROM `protocol_versions` pv5
                 WHERE pv5.protocol_id = p.id
                   AND pv5.file_type = 'payment_proof'
                 ORDER BY pv5.version_number DESC
                 LIMIT 1) AS latest_payment_proof_version_id,
                (SELECT pv6.id
                 FROM `protocol_versions` pv6
                 WHERE pv6.protocol_id = p.id
                   AND pv6.file_type = 'signed_scan'
                 ORDER BY pv6.version_number DESC
                 LIMIT 1) AS latest_signed_scan_version_id,
                (SELECT pv7.original_name
                 FROM `protocol_versions` pv7
                 WHERE pv7.protocol_id = p.id
                   AND pv7.file_type = 'clearance'
                 ORDER BY pv7.version_number DESC
                 LIMIT 1) AS latest_clearance_original_name,
                (SELECT pv8.id
                 FROM `protocol_versions` pv8
                 WHERE pv8.protocol_id = p.id
                   AND pv8.file_type = 'protocol'
                 ORDER BY pv8.version_number DESC
                 LIMIT 1) AS latest_protocol_version_id,
                (SELECT COUNT(*) FROM `protocol_title_history` h
                 WHERE h.protocol_id = p.id) AS title_history_count,
                (SELECT COUNT(*) FROM `protocol_versions` pva
                 WHERE pva.protocol_id = p.id
                   AND pva.file_type = 'amendment') AS amendment_count,
                EXISTS(SELECT 1 FROM `payment_proof_rejections` pr
                       WHERE pr.protocol_id = p.id) AS has_payment_rejections,
                GREATEST(
                    p.updated_at,
                    COALESCE(
                        (SELECT MAX(pv4.uploaded_at)
                         FROM `protocol_versions` pv4
                         WHERE pv4.protocol_id = p.id),
                        p.updated_at
                    )
                ) AS last_activity_at,
                rr.other_reason AS rr_other_reason,
                rr.comment      AS rr_comment,
                rr.created_at   AS rr_created_at,
                ppr.comment     AS payment_rejection_comment
             FROM `protocols` p
             LEFT JOIN `protocol_return_reasons` rr
                ON rr.id = (
                    SELECT MAX(id) FROM `protocol_return_reasons`
                    WHERE protocol_id = p.id
                )
             LEFT JOIN `payment_proof_rejections` ppr
                ON ppr.id = (
                    SELECT MAX(id) FROM `payment_proof_rejections`
                    WHERE protocol_id = p.id
                )
             WHERE p.user_id = ? AND p.deleted_at IS NULL
             ORDER BY last_activity_at DESC, p.id DESC"
        );
        if (! $stmt) {
            return [];
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ===== LIVE UPDATE CHECK =====
    public function getLatestActivityTimestamp(?int $userId = null): ?string
    {
        $sql = "SELECT MAX(GREATEST(
                    p.updated_at,
                    COALESCE(
                        (SELECT MAX(pv.uploaded_at) FROM `protocol_versions` pv WHERE pv.protocol_id = p.id),
                        p.updated_at
                    )
                )) AS latest
                FROM `protocols` p";

        if ($userId === null) {
            $sql .= " JOIN `users` u ON u.id = p.user_id WHERE u.status != 'deactivated' AND p.deleted_at IS NULL";
        } else {
            $sql .= " WHERE p.user_id = ? AND p.deleted_at IS NULL";
        }

        $stmt = $this->connection->prepare($sql);
        if (! $stmt) {
            return null;
        }

        if ($userId !== null) {
            $stmt->bind_param('i', $userId);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row['latest'] ?? null;
    }

    // ===== PREVIOUS DETAILS (pre-fills the submission form) =====
    public function lastDetailsByUser(int $userId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT research_adviser, veterinarian FROM `protocols`
             WHERE user_id = ? AND deleted_at IS NULL AND research_adviser IS NOT NULL
             ORDER BY id DESC LIMIT 1"
        );
        if (! $stmt) {
            return [];
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: [];
    }

    // ===== OWN PAST NAMES (autocomplete for the submission form) =====
    // Only this user's own protocols, so one researcher never sees names another entered.
    public function distinctDetailsByUser(int $userId, string $column): array
    {
        if (! in_array($column, ['research_adviser', 'veterinarian'], true)) {
            return [];
        }

        $stmt = $this->connection->prepare(
            "SELECT DISTINCT `$column` FROM `protocols`
             WHERE user_id = ? AND deleted_at IS NULL
               AND `$column` IS NOT NULL AND `$column` != ''
             ORDER BY `$column`"
        );
        if (! $stmt) {
            return [];
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), $column);
    }

    // ===== SINGLE PROTOCOL =====
    public function getById(int $id): ?array
    {
        $stmt = $this->connection->prepare(
            "SELECT
                p.id AS protocol_id,
                p.reference_no,
                p.ar_number,
                p.title AS research_title,
                p.animal_type,
                p.animal_count,
                p.research_adviser,
                p.veterinarian,
                p.research_duration_start,
                p.research_duration_end,
                p.status,
                p.submitted_at,
                p.updated_at,
                p.user_id,
                p.previous_title,
                p.title_changed_by,
                p.title_changed_by_name,
                p.title_changed_by_role,
                p.title_changed_at,
                p.title_change_seen_by,
                p.deletion_requested_at,
                p.deletion_requested_by,
                p.deletion_requested_by_name,
                p.deletion_requested_by_role,
                p.deletion_request_reason,
                p.deletion_rejection_reason,
                p.payment_status,
                p.payment_method,
                p.paid_at,
                p.paid_by,
                u.first_name AS submitter_first_name,
                u.last_name AS submitter_last_name,
                u.school AS submitter_school,
                u.sex AS submitter_sex,
                u.researcher_type AS submitter_researcher_type
             FROM `protocols` p
             JOIN `users` u ON u.id = p.user_id
             WHERE p.id = ? AND p.deleted_at IS NULL LIMIT 1"
        );
        if (! $stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // ===== STATUS & REFERENCE NUMBERS =====
    public function updateStatus(int $protocolId, string $status): bool
    {
        $allowed = ['Under Review', 'Needs Revision', 'Reviewed', 'Endorsed', 'Approved'];
        if (! in_array($status, $allowed, true)) {
            return false;
        }

        $stmt = $this->connection->prepare(
            "UPDATE `protocols` SET status = ? WHERE id = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('si', $status, $protocolId);
        return $stmt->execute();
    }

    public function referenceNoExists(string $refNo): bool
    {
        $stmt = $this->connection->prepare("SELECT 1 FROM `protocols` WHERE reference_no = ?");
        if (! $stmt) {
            return false;
        }
        $stmt->bind_param('s', $refNo);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_row();
    }

    public function setReferenceNo(int $protocolId, string $refNo): bool
    {
        $stmt = $this->connection->prepare("UPDATE `protocols` SET reference_no = ? WHERE id = ?");
        if (! $stmt) {
            return false;
        }
        $stmt->bind_param('si', $refNo, $protocolId);
        return $stmt->execute();
    }

    public function arNumberExists(string $arNumber): bool
    {
        $stmt = $this->connection->prepare("SELECT 1 FROM `protocols` WHERE ar_number = ?");
        if (! $stmt) {
            return false;
        }
        $stmt->bind_param('s', $arNumber);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_row();
    }

    public function setArNumber(int $protocolId, string $arNumber): bool
    {
        $stmt = $this->connection->prepare("UPDATE `protocols` SET ar_number = ? WHERE id = ?");
        if (! $stmt) {
            return false;
        }
        $stmt->bind_param('si', $arNumber, $protocolId);
        return $stmt->execute();
    }

    // ===== PROTOCOL VERSIONS =====

    public function insertVersion(
        int $protocolId,
        string $filePath,
        string $originalName,
        int $uploadedBy,
        string $fileType = 'protocol',
        ?string $note = null
    ): int | false {
        $stmt = $this->connection->prepare(
            "SELECT COALESCE(MAX(version_number), 0) + 1 AS next_v
             FROM `protocol_versions`
             WHERE protocol_id = ? AND file_type = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('is', $protocolId, $fileType);
        $stmt->execute();
        $nextVersion = (int) $stmt->get_result()->fetch_assoc()['next_v'];

        $stmt = $this->connection->prepare(
            "INSERT INTO `protocol_versions`
                (protocol_id, version_number, file_path, original_name, file_type, uploaded_by, note)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('iisssis', $protocolId, $nextVersion, $filePath, $originalName, $fileType, $uploadedBy, $note);
        if (! $stmt->execute()) {
            return false;
        }

        return $this->connection->insert_id;
    }

    public function getVersions(int $protocolId, string $fileType = 'protocol'): array
    {
        $stmt = $this->connection->prepare(
            "SELECT
                pv.id,
                pv.version_number,
                pv.file_path,
                pv.original_name,
                pv.file_type,
                pv.note,
                pv.uploaded_at,
                u.first_name,
                u.last_name
             FROM `protocol_versions` pv
             JOIN `users` u ON u.id = pv.uploaded_by
             WHERE pv.protocol_id = ? AND pv.file_type = ?
             ORDER BY pv.version_number DESC"
        );
        if (! $stmt) {
            return [];
        }

        $stmt->bind_param('is', $protocolId, $fileType);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Protocol versions, signed scans and amendments as one list, newest first, each
    // with a display label ("Original submission", "Revision 2", "Signed scan", "Amendment 1").
    public function getVersionTimeline(int $protocolId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT
                pv.id,
                pv.version_number,
                pv.file_path,
                pv.original_name,
                pv.file_type,
                pv.note,
                pv.uploaded_at,
                u.first_name,
                u.last_name
             FROM `protocol_versions` pv
             JOIN `users` u ON u.id = pv.uploaded_by
             WHERE pv.protocol_id = ? AND pv.file_type IN ('protocol', 'signed_scan', 'amendment')
             ORDER BY pv.uploaded_at DESC, pv.id DESC"
        );
        if (! $stmt) {
            return [];
        }

        $stmt->bind_param('i', $protocolId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $scanCount = count(array_filter($rows, fn($r) => $r['file_type'] === 'signed_scan'));

        foreach ($rows as &$row) {
            $row['label'] = match ($row['file_type']) {
                'signed_scan' => $scanCount > 1 ? 'Signed scan ' . (int) $row['version_number'] : 'Signed scan',
                'amendment'   => 'Amendment ' . (int) $row['version_number'],
                default       => submission_round_label((int) $row['version_number']),
            };
        }
        unset($row);

        return $rows;
    }

    public function getLatestVersion(int $protocolId, string $fileType = 'protocol'): ?array
    {
        $stmt = $this->connection->prepare(
            "SELECT pv.*, d.user_id AS owner_id
             FROM `protocol_versions` pv
             JOIN `protocols` d ON d.id = pv.protocol_id
             WHERE pv.protocol_id = ? AND pv.file_type = ?
             ORDER BY pv.version_number DESC
             LIMIT 1"
        );
        if (! $stmt) {
            return null;
        }

        $stmt->bind_param('is', $protocolId, $fileType);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function getVersionById(int $versionId): ?array
    {
        $stmt = $this->connection->prepare(
            "SELECT pv.*, p.user_id AS owner_id, p.status AS protocol_status, p.title AS protocol_title, p.reference_no
             FROM `protocol_versions` pv
             JOIN `protocols` p ON p.id = pv.protocol_id
             WHERE pv.id = ? LIMIT 1"
        );
        if (! $stmt) {
            return null;
        }

        $stmt->bind_param('i', $versionId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function getLatestVersionAsOf(int $protocolId, string $fileType, string $asOf): ?array
    {
        $stmt = $this->connection->prepare(
            "SELECT pv.*, d.user_id AS owner_id
             FROM `protocol_versions` pv
             JOIN `protocols` d ON d.id = pv.protocol_id
             WHERE pv.protocol_id = ? AND pv.file_type = ? AND pv.uploaded_at <= ?
             ORDER BY pv.version_number DESC
             LIMIT 1"
        );
        if (! $stmt) {
            return null;
        }

        $stmt->bind_param('iss', $protocolId, $fileType, $asOf);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // ===== ANNOTATIONS =====

    public function getAnnotations(int $versionId, bool $withAuthor = false): array
    {
        $author = $withAuthor ? ', ' . self::AUTHOR_SQL . ' AS author' : '';
        $stmt = $this->connection->prepare(
            "SELECT
                a.id, a.page_number, a.x, a.y, a.width, a.height,
                a.comment, a.created_at$author
             FROM `annotations` a
             JOIN `users` u ON u.id = a.created_by
             WHERE a.version_id = ?
             ORDER BY a.page_number, a.y, a.x"
        );
        if (! $stmt) {
            return [];
        }

        $stmt->bind_param('i', $versionId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function insertAnnotation(
        int $versionId,
        int $pageNumber,
        float $x,
        float $y,
        float $width,
        float $height,
        string $comment,
        int $createdBy
    ): int | false {
        $x      = max(0.0, min(1.0, $x));
        $y      = max(0.0, min(1.0, $y));
        $width  = max(0.0, min(1.0, $width));
        $height = max(0.0, min(1.0, $height));

        if ($pageNumber < 1 || $width <= 0 || $height <= 0 || trim($comment) === '') {
            return false;
        }

        $stmt = $this->connection->prepare(
            "INSERT INTO `annotations`
                (version_id, page_number, x, y, width, height, comment, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('iiddddsi', $versionId, $pageNumber, $x, $y, $width, $height, $comment, $createdBy);
        if (! $stmt->execute()) {
            return false;
        }

        return $this->connection->insert_id;
    }

    public function updateAnnotation(int $annotationId, string $comment): bool
    {
        $comment = trim($comment);
        if ($annotationId < 1 || $comment === '') {
            return false;
        }

        $stmt = $this->connection->prepare(
            "UPDATE `annotations` SET comment = ? WHERE id = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('si', $comment, $annotationId);
        return $stmt->execute();
    }

    public function deleteAnnotation(int $annotationId): bool
    {
        $stmt = $this->connection->prepare(
            "DELETE FROM `annotations` WHERE id = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('i', $annotationId);
        return $stmt->execute();
    }

    // ===== RETURN REASONS =====
    public function insertReturnReason(
        int $protocolId,
        int $reviewerId,
        string $comment,
        ?int $versionId = null
    ): bool {
        $comment = mb_substr(trim($comment), 0, 1000);

        $stmt = $this->connection->prepare(
            "INSERT INTO `protocol_return_reasons`
                (protocol_id, reviewer_id, comment, version_id)
             VALUES (?, ?, ?, ?)"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('iisi', $protocolId, $reviewerId, $comment, $versionId);
        return $stmt->execute();
    }

    public function getLatestReturnReason(int $protocolId, bool $withAuthor = false): ?array
    {
        $author = $withAuthor ? ', ' . self::AUTHOR_SQL . ' AS returned_by' : '';
        $stmt = $this->connection->prepare(
            "SELECT
                r.other_reason,
                r.comment,
                r.created_at$author
             FROM `protocol_return_reasons` r
             JOIN `users` u ON u.id = r.reviewer_id
             WHERE r.protocol_id = ?
             ORDER BY r.created_at DESC
             LIMIT 1"
        );
        if (! $stmt) {
            return null;
        }

        $stmt->bind_param('i', $protocolId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function getReturnReasonForVersion(int $versionId, bool $withAuthor = false): ?array
    {
        if ($versionId < 1) {
            return null;
        }

        $author = $withAuthor ? ', ' . self::AUTHOR_SQL . ' AS returned_by' : '';
        $stmt = $this->connection->prepare(
            "SELECT
                r.other_reason,
                r.comment,
                r.created_at$author
             FROM `protocol_return_reasons` r
             JOIN `users` u ON u.id = r.reviewer_id
             WHERE r.version_id = ?
             ORDER BY r.created_at DESC
             LIMIT 1"
        );
        if (! $stmt) {
            return null;
        }

        $stmt->bind_param('i', $versionId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // ===== CLEARANCE POOL =====

    public function insertClearancePoolItem(string $filePath, string $originalName, string $arNumber, int $uploadedBy): int | false
    {
        $stmt = $this->connection->prepare(
            "INSERT INTO `clearance_pool` (file_path, original_name, ar_number, uploaded_by) VALUES (?, ?, ?, ?)"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('sssi', $filePath, $originalName, $arNumber, $uploadedBy);
        if (! $stmt->execute()) {
            return false;
        }

        return $this->connection->insert_id;
    }

    public function clearancePoolArExists(string $arNumber): bool
    {
        $stmt = $this->connection->prepare("SELECT 1 FROM `clearance_pool` WHERE ar_number = ? AND version_id IS NULL");
        if (! $stmt) {
            return false;
        }
        $stmt->bind_param('s', $arNumber);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_row();
    }

    public function getUnassignedClearancePool(): array
    {
        $sql = "SELECT
                    cp.id, cp.file_path, cp.original_name, cp.ar_number, cp.uploaded_at,
                    u.first_name, u.last_name
                FROM `clearance_pool` cp
                JOIN `users` u ON u.id = cp.uploaded_by
                WHERE cp.protocol_id IS NULL
                ORDER BY cp.uploaded_at ASC";

        $result = $this->connection->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getClearancePoolItem(int $id): ?array
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM `clearance_pool` WHERE id = ? LIMIT 1"
        );
        if (! $stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function getClearancePoolItemForProtocol(int $protocolId): ?array
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM `clearance_pool` WHERE protocol_id = ? LIMIT 1"
        );
        if (! $stmt) {
            return null;
        }

        $stmt->bind_param('i', $protocolId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function stageClearancePoolItem(int $poolId, int $protocolId, int $assignedBy): bool
    {
        $swap = $this->connection->prepare(
            "UPDATE `clearance_pool`
             SET protocol_id = NULL, assigned_by = NULL, assigned_at = NULL
             WHERE protocol_id = ? AND version_id IS NULL AND id != ?"
        );
        if ($swap) {
            $swap->bind_param('ii', $protocolId, $poolId);
            $swap->execute();
        }

        $stmt = $this->connection->prepare(
            "UPDATE `clearance_pool`
             SET protocol_id = ?, assigned_by = ?, assigned_at = NOW()
             WHERE id = ? AND protocol_id IS NULL"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('iii', $protocolId, $assignedBy, $poolId);
        return $stmt->execute() && $stmt->affected_rows > 0;
    }

    public function unstageClearancePoolItem(int $poolId): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE `clearance_pool`
             SET protocol_id = NULL, assigned_by = NULL, assigned_at = NULL
             WHERE id = ? AND version_id IS NULL"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('i', $poolId);
        return $stmt->execute() && $stmt->affected_rows > 0;
    }

    public function deleteClearancePoolItem(int $id): bool
    {
        $stmt = $this->connection->prepare(
            "DELETE FROM `clearance_pool` WHERE id = ? AND protocol_id IS NULL"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('i', $id);
        return $stmt->execute() && $stmt->affected_rows > 0;
    }

    public function confirmClearancePoolItem(int $poolId, int $versionId): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE `clearance_pool`
             SET version_id = ?
             WHERE id = ? AND protocol_id IS NOT NULL AND version_id IS NULL"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('ii', $versionId, $poolId);
        return $stmt->execute() && $stmt->affected_rows > 0;
    }

    public function getStagedClearanceItems(): array
    {
        $sql = "SELECT
                    cp.id, cp.file_path, cp.original_name, cp.ar_number, cp.protocol_id,
                    p.title AS protocol_title, p.reference_no, p.status AS protocol_status,
                    u.first_name, u.last_name
                FROM `clearance_pool` cp
                JOIN `protocols` p ON p.id = cp.protocol_id
                JOIN `users` u ON u.id = cp.uploaded_by
                WHERE cp.protocol_id IS NOT NULL AND cp.version_id IS NULL
                ORDER BY cp.assigned_at ASC";

        $result = $this->connection->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getConfirmedClearanceItems(int $limit = 20): array
    {
        $sql = "SELECT
                    cp.id, cp.original_name, cp.protocol_id, cp.assigned_at,
                    p.title AS protocol_title, p.reference_no
                FROM `clearance_pool` cp
                JOIN `protocols` p ON p.id = cp.protocol_id
                WHERE cp.protocol_id IS NOT NULL AND cp.version_id IS NOT NULL
                ORDER BY cp.assigned_at DESC
                LIMIT " . (int) $limit;

        $result = $this->connection->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function unassignClearancePoolItem(int $poolId): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE `clearance_pool`
             SET protocol_id = NULL, assigned_by = NULL, assigned_at = NULL, version_id = NULL
             WHERE id = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('i', $poolId);
        return $stmt->execute();
    }

    // ===== DELETE VERSION =====
    public function deleteVersion(int $versionId): bool
    {
        $stmt = $this->connection->prepare(
            "DELETE FROM `protocol_versions` WHERE id = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('i', $versionId);
        return $stmt->execute();
    }

    // ===== ACCOUNT DELETION (reversible) =====

    public function softDeleteAllForUser(int $userId, string $actorName, string $reason = 'Account deleted by owner'): bool
    {
        $stmt = $this->connection->prepare("SELECT id FROM `protocols` WHERE user_id = ? AND deleted_at IS NULL");
        if (! $stmt) {
            return false;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $protocolIds = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');

        $ok = true;
        foreach ($protocolIds as $protocolId) {
            $ok = $this->softDelete((int) $protocolId, $userId, $actorName, $reason) && $ok;
        }
        return $ok;
    }

    public function hasActiveProtocols(int $userId): bool
    {
        $stmt = $this->connection->prepare(
            "SELECT 1 FROM `protocols`
             WHERE user_id = ? AND deleted_at IS NULL AND status != 'Approved'
             LIMIT 1"
        );
        if (! $stmt) {
            return true;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_row();
    }

    // ===== RECENT ACTION COUNT =====
    public function countRecentActions(int $userId, string $action, int $windowSeconds = 3600): int
    {
        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) AS cnt FROM audit_logs
              WHERE user_id = ? AND action = ?
              AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->bind_param('isi', $userId, $action, $windowSeconds);
        $stmt->execute();
        return (int) $stmt->get_result()->fetch_assoc()['cnt'];
    }
}
