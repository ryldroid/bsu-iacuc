<?php

class Idempotency extends Model
{
    private const PROCESSING_SECONDS = 30;
    private const KEEP_DAYS = 7;

    // ===== START (CALLED BY THE ROUTER BEFORE A CONTROLLER RUNS) =====
    public static function begin(): void
    {
        $key    = trim($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? '');
        $userId = (int) ($_SESSION['user']['user_id'] ?? 0);

        if ($userId < 1 || $_SERVER['REQUEST_METHOD'] !== 'POST' || !preg_match('/^[A-Za-z0-9-]{8,64}$/', $key)) {
            return;
        }

        (new self())->claim($userId, $key);
    }

    // ===== CLAIM THE KEY OR REPLAY THE SAVED ANSWER =====
    private function claim(int $userId, string $key, bool $retried = false): void
    {
        if (random_int(1, 50) === 1) {
            $this->connection->query('DELETE FROM idempotency_keys WHERE created_at < NOW() - INTERVAL ' . self::KEEP_DAYS . ' DAY');
        }

        $insert = $this->connection->prepare('INSERT IGNORE INTO idempotency_keys (user_id, request_key) VALUES (?, ?)');
        if (!$insert) {
            return;
        }
        $insert->bind_param('is', $userId, $key);
        $insert->execute();

        if ($insert->affected_rows === 1) {
            ob_start();
            register_shutdown_function(fn() => $this->finish($userId, $key));
            return;
        }

        $select = $this->connection->prepare('SELECT status_code, response, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age FROM idempotency_keys WHERE user_id = ? AND request_key = ?');
        $select->bind_param('is', $userId, $key);
        $select->execute();
        $row = $select->get_result()->fetch_assoc();

        if ($row && $row['response'] !== null) {
            http_response_code((int) $row['status_code']);
            header('Content-Type: application/json');
            header('X-Idempotent-Replay: 1');
            echo $row['response'];
            exit;
        }

        if ($row && (int) $row['age'] < self::PROCESSING_SECONDS) {
            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'This action is still being processed. Try again shortly.']);
            exit;
        }

        $this->release($userId, $key);
        if (!$retried) {
            $this->claim($userId, $key, true);
        }
    }

    // ===== SAVE THE ANSWER (SUCCESS ONLY) =====
    private function finish(int $userId, string $key): void
    {
        $body = (string) ob_get_contents();
        $code = http_response_code() ?: 200;
        $data = json_decode($body, true);

        $succeeded = $code >= 200 && $code < 300 && is_array($data)
            && ($data['ok'] ?? true) !== false
            && ($data['success'] ?? true) !== false
            && !isset($data['error']);

        if (!$succeeded) {
            $this->release($userId, $key);
            return;
        }

        $update = $this->connection->prepare('UPDATE idempotency_keys SET status_code = ?, response = ? WHERE user_id = ? AND request_key = ?');
        if ($update) {
            $update->bind_param('isis', $code, $body, $userId, $key);
            $update->execute();
        }
    }

    private function release(int $userId, string $key): void
    {
        $delete = $this->connection->prepare('DELETE FROM idempotency_keys WHERE user_id = ? AND request_key = ?');
        if ($delete) {
            $delete->bind_param('is', $userId, $key);
            $delete->execute();
        }
    }
}
