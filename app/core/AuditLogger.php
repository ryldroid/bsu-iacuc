<?php

class AuditLogger
{
    public static function log(
        \mysqli $database,
        string  $event,
        ?int    $actorId = null,
        string  $actorUsername = '',
        string  $actorRole = '',
        string  $targetType = '',
        ?int    $targetId = null,
        string  $description = ''
    ): void {
        $ip = self::getClientIp();

        $query = $database->prepare(
            "INSERT INTO audit_logs
                (user_id, username, role, action, target_type, target_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$query) {
            error_log('AuditLogger prepare failed: ' . $database->error);
            return;
        }

        $query->bind_param(
            'issssiss',
            $actorId,
            $actorUsername,
            $actorRole,
            $event,
            $targetType,
            $targetId,
            $description,
            $ip
        );

        if (!$query->execute()) {
            error_log('AuditLogger execute failed: ' . $query->error);
        }
    }

    private static function getClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

        if (in_array($remoteAddr, TrustedProxies::$ips, true)) {
            foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP'] as $key) {
                if (!empty($_SERVER[$key])) {
                    $ip = trim(explode(',', $_SERVER[$key])[0]);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        if (filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return $remoteAddr;
        }

        return 'unknown';
    }
}
