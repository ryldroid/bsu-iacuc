<?php
class EnvLoader
{
    private static $loaded = false;

    // ===== LOAD .ENV FILE =====
    public static function load()
    {
        if (self::$loaded) return;

        $envFile = dirname(__DIR__, 2) . '/.env';

        if (!file_exists($envFile)) {
            error_log(".env file not found at: " . $envFile);
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if (strpos($line, '#') === 0 || empty($line)) continue;

            $firstEqual = strpos($line, '=');
            if ($firstEqual === false) continue;

            $key = trim(substr($line, 0, $firstEqual));
            $value = trim(substr($line, $firstEqual + 1));

            if (strlen($value) > 1 && (strpos($value, '"') === 0 || strpos($value, "'") === 0)) {
                $value = substr($value, 1, -1);
            }

            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        self::$loaded = true;
    }

    // ===== READ ENV VALUE =====
    public static function get($key, $default = null)
    {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        $value = getenv($key);

        return $value !== false ? $value : $default;
    }
}
