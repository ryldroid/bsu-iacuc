<?php

class Model
{
    private static bool $tablesEnsured = false;
    public $connection;

    public function __construct()
    {
        mysqli_report(MYSQLI_REPORT_OFF);

        $conn = new mysqli(DBSERVER, DBUSER, DBPASS);

        if ($conn->connect_error) {
            $this->fatalError('Could not connect to the database. Please try again later.');
        }

        $conn->query("SET time_zone = '+08:00'");

        if (! $conn->query("CREATE DATABASE IF NOT EXISTS `" . DBNAME . "`
                        DEFAULT CHARACTER SET utf8mb4
                        COLLATE utf8mb4_general_ci")) {
            $this->fatalError('Could not initialise the database. Please try again later.');
        }

        $conn->select_db(DBNAME);

        $this->connection = $conn;

        if (!self::$tablesEnsured) {
            try {
                $this->ensureTables();
                self::$tablesEnsured = true;
            } catch (Throwable $e) {
                $this->fatalError('Database setup failed. Please try again later.');
            }
        }
    }

    private function ensureTables()
    {
        $c = $this->connection;

        $c->query("CREATE TABLE IF NOT EXISTS `users` (
                    `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `username` varchar(50) NOT NULL UNIQUE,
                    `first_name` varchar(100) NOT NULL,
                    `last_name` varchar(100) NOT NULL,
                    `email` varchar(100) NOT NULL,
                    `phone_number` varchar(20) DEFAULT NULL,
                    `password` varchar(255) NOT NULL,
                    `role` varchar(50) DEFAULT 'researcher',
                    `status` varchar(20) NOT NULL DEFAULT 'active',
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                    `cert_path` varchar(255) DEFAULT NULL,
                    `cert_original_name` varchar(255) DEFAULT NULL,
                    `cert_uploaded_at` timestamp NULL DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $this->ensureColumn('users', 'phone_number', "varchar(20) DEFAULT NULL AFTER `email`");

        $c->query("CREATE TABLE IF NOT EXISTS `records` (
                    `id`                     int(11)      NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `reference_no`           varchar(20)  DEFAULT NULL UNIQUE,
                    `title_of_research`      text         NOT NULL,
                    `school`                 varchar(255) NOT NULL DEFAULT '',
                    `animals_used`           varchar(100) DEFAULT NULL,
                    `animal_type`            varchar(100) DEFAULT NULL,
                    `animal_count`           int(11)      DEFAULT NULL,
                    `principal_investigator` varchar(255) DEFAULT NULL,
                    `gender`                 varchar(20)  DEFAULT NULL,
                    `researcher_type`        varchar(50)  DEFAULT NULL,
                    `research_adviser`       varchar(255) DEFAULT NULL,
                    `veterinarian`           varchar(255) DEFAULT NULL,
                    `research_duration`      varchar(100) DEFAULT NULL,
                    `date_released`          date         DEFAULT NULL,
                    `received_by`            varchar(255) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $c->query("CREATE TABLE IF NOT EXISTS `protocols` (
                    `id`                   int(11)      NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `reference_no`         varchar(30)  DEFAULT NULL UNIQUE,
                    `title`                varchar(255) NOT NULL DEFAULT 'Untitled Protocol',
                    `updated_at`           timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `user_id`              int(11)      DEFAULT NULL,
                    `status`               varchar(30)  NOT NULL DEFAULT 'Under Review',
                    `submitted_at`         timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                    `cert_path`            varchar(255) DEFAULT NULL,
                    `auth_path`            varchar(255) DEFAULT NULL,
                    `is_pi`                tinyint(1)   NOT NULL DEFAULT 1
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $c->query("CREATE TABLE IF NOT EXISTS `protocol_versions` (
                    `id`             int(11)      NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `protocol_id`    int(11)      NOT NULL,
                    `version_number` int(11)      NOT NULL DEFAULT 1,
                    `file_path`      varchar(255) NOT NULL,
                    `original_name`  varchar(255) NOT NULL,
                    `file_type`      enum('protocol','cert','auth','clearance') NOT NULL DEFAULT 'protocol',
                    `uploaded_by`    int(11)      NOT NULL,
                    `uploaded_at`    timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                    UNIQUE KEY `unique_version` (`protocol_id`, `version_number`, `file_type`),
                    FOREIGN KEY (`protocol_id`) REFERENCES `protocols`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $c->query("CREATE TABLE IF NOT EXISTS `annotations` (
                `id`          int(11)  NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `version_id`  int(11)  NOT NULL,
                `page_number` int(11)  NOT NULL,
                `x`           float    NOT NULL,
                `y`           float    NOT NULL,
                `width`       float    NOT NULL,
                `height`      float    NOT NULL,
                `comment`     text     NOT NULL,
                `created_by`  int(11)  NOT NULL,
                `created_at`  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                FOREIGN KEY (`version_id`) REFERENCES `protocol_versions`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $c->query("CREATE TABLE IF NOT EXISTS `password_resets` (
                    `id`         int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `user_id`    int(11) NOT NULL,
                    `token`      varchar(64) NOT NULL UNIQUE,
                    `expires_at` datetime NOT NULL,
                    `used`       tinyint(1) NOT NULL DEFAULT 0,
                    INDEX (`token`),
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $c->query("CREATE TABLE IF NOT EXISTS `audit_logs` (
                    `id`          INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `user_id`     INT DEFAULT NULL,
                    `username`    VARCHAR(100) DEFAULT NULL,
                    `role`        VARCHAR(50) DEFAULT NULL,
                    `action`      VARCHAR(100) NOT NULL,
                    `target_type` VARCHAR(50) DEFAULT NULL,
                    `target_id`   INT DEFAULT NULL,
                    `details`     TEXT DEFAULT NULL,
                    `ip_address`  VARCHAR(45) DEFAULT NULL,
                    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $c->query("CREATE TABLE IF NOT EXISTS `login_attempts` (
                    `id`           INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `identifier`   VARCHAR(255) NOT NULL,
                    `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                    INDEX (`identifier`),
                    INDEX (`attempted_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $c->query("CREATE TABLE IF NOT EXISTS `invite_tokens` (
                    `id`         INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `token`      VARCHAR(128) NOT NULL UNIQUE,
                    `role`       VARCHAR(50) NOT NULL DEFAULT 'reviewer',
                    `used`       TINYINT(1) NOT NULL DEFAULT 0,
                    `used_by`    INT DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                    `expires_at` DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        // ADDED by SPM - Admin-managed "From Our Office" announcements
        $c->query("CREATE TABLE IF NOT EXISTS `announcements` (
                    `id`         int(11)      NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `title`      varchar(255) NOT NULL,
                    `body`       text         NOT NULL,
                    `posted_by`  int(11)      DEFAULT NULL,
                    `created_at` timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                    `updated_at` timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`posted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
        // END ADDED

        $c->query("CREATE TABLE IF NOT EXISTS `protocol_return_reasons` (
                    `id`           int(11)       NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `protocol_id`  int(11)       NOT NULL,
                    `reviewer_id`  int(11)       NOT NULL,
                    `wrong_cert`   tinyint(1)    NOT NULL DEFAULT 0,
                    `wrong_auth`   tinyint(1)    NOT NULL DEFAULT 0,
                    `other_reason` tinyint(1)    NOT NULL DEFAULT 0,
                    `comment`      varchar(1000) NOT NULL DEFAULT '',
                    `created_at`   timestamp     NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                    INDEX `idx_prr_protocol` (`protocol_id`),
                    FOREIGN KEY (`protocol_id`) REFERENCES `protocols`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $c = $this->connection;

        $exists = $c->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($exists && $exists->num_rows === 0) {
            $c->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }

    public function logAudit(
        string $event,
        ?int $actorId = null,
        string $actorUsername = '',
        string $actorRole = '',
        string $targetType = '',
        ?int $targetId = null,
        string $description = ''
    ): void {
        require_once __DIR__ . '/AuditLogger.php';
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

    private function fatalError(string $message): void
    {
        error_log("Database Fatal Error: " . $message);
        $_SESSION['flash_error'] = $message;
        ErrorPage::render(500, 'Something Went Wrong', [
            'We encountered a system error while processing your request.',
        ]);
    }
}