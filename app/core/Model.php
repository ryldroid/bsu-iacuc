<?php

class Model
{
    private static bool $tablesEnsured = false;
    private static ?mysqli $sharedConnection = null;
    public $connection;

    // ===== CONNECTION & SCHEMA CHECK =====
    public function __construct()
    {
        if (self::$sharedConnection === null) {
            mysqli_report(MYSQLI_REPORT_OFF);

            $conn = new mysqli(DBSERVER, DBUSER, DBPASS);

            if ($conn->connect_error) {
                $this->fatalError('Could not connect to the database. Please try again later.');
            }

            $conn->set_charset('utf8mb4');


            $conn->query("SET time_zone = '+08:00'");

            if (! $conn->query("CREATE DATABASE IF NOT EXISTS `" . DBNAME . "`
                            DEFAULT CHARACTER SET utf8mb4
                            COLLATE utf8mb4_general_ci")) {
                $this->fatalError('Could not initialise the database. Please try again later.');
            }

            $conn->select_db(DBNAME);

            self::$sharedConnection = $conn;
        }

        $this->connection = self::$sharedConnection;

        if (!self::$tablesEnsured) {
            self::$tablesEnsured = true;

            if ($this->schemaCheckNeeded()) {
                try {
                    (new Schema($this->connection))->run();
                    $this->markSchemaChecked();
                } catch (Throwable $e) {
                    error_log('Schema failed: ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine());
                    $this->fatalError('Database setup failed. Please try again later.');
                }
            }
        }
    }

    private function schemaMarkerPath(): string
    {
        return dirname(__DIR__, 2) . '/storage/.schema_checked';
    }

    private function schemaVersion(): string
    {
        return (string) max(
            filemtime(__DIR__ . '/Model.php'),
            filemtime(__DIR__ . '/Schema.php'),
            filemtime(__DIR__ . '/Seeder.php')
        );
    }

    private function schemaCheckNeeded(): bool
    {
        $marker  = $this->schemaMarkerPath();
        $current = $this->schemaVersion();
        return !is_file($marker) || trim((string) @file_get_contents($marker)) !== $current;
    }

    private function markSchemaChecked(): void
    {
        $marker = $this->schemaMarkerPath();
        @mkdir(dirname($marker), 0775, true);
        @file_put_contents($marker, $this->schemaVersion());
    }

    // ===== AUDIT LOGGING =====
    public function logAudit(
        string $event,
        ?int $actorId = null,
        string $actorUsername = '',
        string $actorRole = '',
        string $targetType = '',
        ?int $targetId = null,
        string $description = ''
    ): void {
        AuditLogger::log(
            $this->connection,
            $event,
            $actorId,
            $actorUsername,
            $actorRole,
            $targetType,
            $targetId,
            $description
        );
    }

    // ===== MOVE ROW IN SORT ORDER =====
    protected function moveInOrder(string $table, int $id, string $direction): bool
    {
        $result = $this->connection->query("SELECT id FROM `$table` ORDER BY sort_order ASC, id ASC");
        if (! $result) return false;

        $ids  = array_map('intval', array_column($result->fetch_all(MYSQLI_ASSOC), 'id'));
        $from = array_search($id, $ids, true);
        if ($from === false) return false;

        $to = $direction === 'up' ? $from - 1 : $from + 1;
        if (! isset($ids[$to])) return true;

        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];

        $stmt = $this->connection->prepare("UPDATE `$table` SET sort_order = ? WHERE id = ?");
        if (! $stmt) return false;
        foreach ($ids as $position => $rowId) {
            $stmt->bind_param('ii', $position, $rowId);
            $stmt->execute();
        }
        return true;
    }

    // ===== FATAL ERROR =====
    private function fatalError(string $message): void
    {
        error_log("Database Fatal Error: " . $message);
        $_SESSION['flash_error'] = $message;
        ErrorPage::render(500, 'Something Went Wrong', [
            'We encountered a system error while processing your request.',
        ]);
    }
}
