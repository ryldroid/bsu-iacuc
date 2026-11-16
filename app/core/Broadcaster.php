<?php
class Broadcaster
{
    private static array $pending = [];

    // ===== QUEUE USERS TO NOTIFY =====
    public static function notify(array $userIds): void
    {
        if (PUSHER_KEY === '' || !$userIds) {
            return;
        }

        if (!self::$pending) {
            ob_start();
            register_shutdown_function([self::class, 'flush']);
        }

        self::$pending = array_unique(array_merge(self::$pending, array_map('intval', $userIds)));
    }

    // ===== PUBLISH AFTER THE RESPONSE IS SENT =====
    public static function flush(): void
    {
        if (!self::$pending) {
            return;
        }

        $userIds       = array_values(self::$pending);
        self::$pending = [];

        ignore_user_abort(true);
        session_write_close();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (!headers_sent() && ob_get_level() > 0) {
            while (ob_get_level() > 1) {
                ob_end_flush();
            }
            header('Content-Length: ' . ob_get_length());
            header('Connection: close');
            ob_end_flush();
            flush();
        }

        $channels = array_map(fn($id) => 'private-user-' . $id, $userIds);
        $path     = '/apps/' . PUSHER_APP_ID . '/events';

        foreach (array_chunk($channels, 100) as $batch) {
            $body  = json_encode(['name' => 'notification', 'channels' => $batch, 'data' => '{}']);
            $query = [
                'auth_key'       => PUSHER_KEY,
                'auth_timestamp' => time(),
                'auth_version'   => '1.0',
                'body_md5'       => md5($body),
            ];
            $query['auth_signature'] = hash_hmac('sha256', "POST\n$path\n" . http_build_query($query), PUSHER_SECRET);

            $ch = curl_init('https://api-' . PUSHER_CLUSTER . '.pusher.com' . $path . '?' . http_build_query($query));
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_TIMEOUT        => 2,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => $body,
            ]);

            if ($ca = PdfConverter::caBundle()) {
                curl_setopt($ch, CURLOPT_CAINFO, $ca);
            }

            curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error  = curl_error($ch);
            curl_close($ch);

            if ($error || $status !== 200) {
                error_log('Pusher publish failed: ' . ($error ?: "status $status"));
            }
        }
    }

    // ===== SIGN PRIVATE CHANNEL =====
    public static function authorize(string $socketId, string $channel): string
    {
        return PUSHER_KEY . ':' . hash_hmac('sha256', $socketId . ':' . $channel, PUSHER_SECRET);
    }
}
