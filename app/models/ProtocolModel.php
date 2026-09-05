<?php

require_once dirname(__DIR__) . '/core/Model.php';

class ProtocolModel extends Model
{
    public function insertProtocol(int $userId, string $title, bool $isPi = true): int | false
    {
        $title = mb_substr(trim($title), 0, 255) ?: 'Untitled Protocol';

        $stmt = $this->connection->prepare(
            "INSERT INTO `protocols` (user_id, title, status, is_pi)
            VALUES (?, ?, 'Under Review', ?)"
        );
        if (! $stmt) {
            return false;
        }

        $isPiInt = $isPi ? 1 : 0;
        $stmt->bind_param('isi', $userId, $title, $isPiInt);
        if (! $stmt->execute()) {
            return false;
        }

        $protocolId = $this->connection->insert_id;
        $this->insertTitleHistory($protocolId, $title, $userId, null, 'researcher');

        return $protocolId;
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
                 title_changed_by_name = ?, title_changed_by_role = ?, title_changed_at = NOW()
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

    public function rejectDeletionRequest(int $protocolId): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE `protocols`
             SET deletion_requested_at = NULL, deletion_requested_by = NULL,
                 deletion_requested_by_name = NULL, deletion_requested_by_role = NULL,
                 deletion_request_reason = NULL
             WHERE id = ?"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('i', $protocolId);
        return $stmt->execute();
    }

    public function getAll(): array
    {
        $sql = "SELECT
                    p.id            AS protocol_id,
                    p.reference_no,
                    p.title         AS research_title,
                    p.status,
                    p.submitted_at,
                    p.updated_at,
                    p.user_id,
                    p.is_pi,
                    p.previous_title,
                    p.title_changed_by_name,
                    p.title_changed_by_role,
                    p.title_changed_at,
                    p.deletion_requested_at,
                    p.deletion_requested_by_name,
                    p.deletion_requested_by_role,
                    p.deletion_request_reason,
                    u.first_name,
                    u.last_name,
                    (SELECT MAX(pv.version_number)
                     FROM `protocol_versions` pv
                     WHERE pv.protocol_id = p.id
                       AND pv.file_type = 'protocol') AS latest_version,
                    (SELECT pv2.id
                     FROM `protocol_versions` pv2
                     WHERE pv2.protocol_id = p.id
                       AND pv2.file_type = 'cert'
                     ORDER BY pv2.version_number DESC
                     LIMIT 1) AS latest_cert_version_id,
                    (SELECT pv3.id
                     FROM `protocol_versions` pv3
                     WHERE pv3.protocol_id = p.id
                       AND pv3.file_type = 'auth'
                     ORDER BY pv3.version_number DESC
                     LIMIT 1) AS latest_auth_version_id,
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
                p.reference_no,
                p.title         AS research_title,
                p.status,
                p.submitted_at,
                p.updated_at,
                p.is_pi,
                p.previous_title,
                p.title_changed_by_name,
                p.title_changed_by_role,
                p.title_changed_at,
                p.deletion_requested_at,
                p.deletion_requested_by_name,
                p.deletion_requested_by_role,
                p.deletion_request_reason,
                (SELECT MAX(pv.version_number)
                 FROM `protocol_versions` pv
                 WHERE pv.protocol_id = p.id
                   AND pv.file_type = 'protocol') AS latest_version,
                GREATEST(
                    p.updated_at,
                    COALESCE(
                        (SELECT MAX(pv4.uploaded_at)
                         FROM `protocol_versions` pv4
                         WHERE pv4.protocol_id = p.id),
                        p.updated_at
                    )
                ) AS last_activity_at,
                rr.wrong_cert   AS rr_wrong_cert,
                rr.wrong_auth   AS rr_wrong_auth,
                rr.other_reason AS rr_other_reason,
                rr.comment      AS rr_comment,
                rr.created_at   AS rr_created_at,
                CONCAT(u.first_name, ' ', u.last_name) AS rr_reviewer_name
             FROM `protocols` p
             LEFT JOIN `protocol_return_reasons` rr
                ON rr.id = (
                    SELECT MAX(id) FROM `protocol_return_reasons`
                    WHERE protocol_id = p.id
                )
             LEFT JOIN `users` u ON u.id = rr.reviewer_id
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

    public function getById(int $id): ?array
    {
        $stmt = $this->connection->prepare(
            "SELECT
                p.id AS protocol_id,
                p.reference_no,
                p.title AS research_title,
                p.status,
                p.submitted_at,
                p.updated_at,
                p.user_id,
                p.is_pi,
                p.previous_title,
                p.title_changed_by,
                p.title_changed_by_name,
                p.title_changed_by_role,
                p.title_changed_at,
                p.deletion_requested_at,
                p.deletion_requested_by,
                p.deletion_requested_by_name,
                p.deletion_requested_by_role,
                p.deletion_request_reason,
                u.first_name AS submitter_first_name,
                u.last_name AS submitter_last_name
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

    // ===== PROTOCOL VERSIONS =====

    public function insertVersion(
        int $protocolId,
        string $filePath,
        string $originalName,
        int $uploadedBy,
        string $fileType = 'protocol'
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
                (protocol_id, version_number, file_path, original_name, file_type, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('iisssi', $protocolId, $nextVersion, $filePath, $originalName, $fileType, $uploadedBy);
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
            "SELECT pv.*, p.user_id AS owner_id, p.status AS protocol_status
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

    public function getAnnotations(int $versionId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT
                a.id, a.page_number, a.x, a.y, a.width, a.height,
                a.comment, a.created_at,
                u.first_name, u.last_name
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

    public function insertReturnReason(
        int $protocolId,
        int $reviewerId,
        array $reasons,
        string $comment
    ): bool {
        $wrongCert  = in_array('wrong_cert',  $reasons, true) ? 1 : 0;
        $wrongAuth  = in_array('wrong_auth',  $reasons, true) ? 1 : 0;
        $otherFlag  = in_array('other',       $reasons, true) ? 1 : 0;
        $comment    = mb_substr(trim($comment), 0, 1000);

        $stmt = $this->connection->prepare(
            "INSERT INTO `protocol_return_reasons`
                (protocol_id, reviewer_id, wrong_cert, wrong_auth, other_reason, comment)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (! $stmt) {
            return false;
        }

        $stmt->bind_param('iiiiis', $protocolId, $reviewerId, $wrongCert, $wrongAuth, $otherFlag, $comment);
        return $stmt->execute();
    }

    public function getLatestReturnReason(int $protocolId): ?array
    {
        $stmt = $this->connection->prepare(
            "SELECT
                r.wrong_cert,
                r.wrong_auth,
                r.other_reason,
                r.comment,
                r.created_at,
                u.first_name,
                u.last_name
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
}
