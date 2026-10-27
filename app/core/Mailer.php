<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private const ACCOUNT_SECURITY_TEMPLATES = ['verify_email', 'password_reset', 'account_deleted'];

    // ===== SEND EMAIL =====
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $body,
        ?string $fromEmail = null,
        ?string $fromName = null
    ): bool {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;
            $mail->Timeout    = 10; // fail fast when offline instead of hanging for PHPMailer's 300s default

            $mail->setFrom($fromEmail ?? MAIL_FROM, $fromName ?? MAIL_FROMNAME);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = self::toPlainText($body);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer error: " . $mail->ErrorInfo);
            return false;
        }
    }

    // ===== EMAIL LINK =====
    public static function link(string $url, string $text): string
    {
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    // ===== HTML TO PLAIN TEXT =====
    private static function toPlainText(string $html): string
    {
        $text = preg_replace('/<a\s[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/is', '$2 ($1)', $html);
        $text = preg_replace('/<\/p>|<br\s*\/?>/i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');

        return trim(preg_replace('/(\R[ \t]*){3,}/', "\n\n", $text));
    }

    // ===== SEND FROM TEMPLATE =====
    public static function sendTemplate(string $template, array $vars, string $toEmail, string $toName, string $subject): bool
    {
        $file = VIEWSPATH . "emails/{$template}.php";
        if (!file_exists($file)) return false;

        extract($vars);
        $body = require $file;

        $isSecurity = in_array($template, self::ACCOUNT_SECURITY_TEMPLATES, true);

        if (!$isSecurity) {
            $body .= self::notificationFooter($toEmail);
        }

        return self::send(
            $toEmail,
            $toName,
            $subject,
            $body,
            $isSecurity ? MAIL_FROM_SECURITY : MAIL_FROM,
            $isSecurity ? MAIL_FROMNAME_SECURITY : MAIL_FROMNAME
        );
    }

    // ===== RESUBSCRIBE CONTACT (BREVO) =====
    public static function resubscribe(string $email, ?int &$failedStatus = null): bool
    {
        if (empty(BREVO_API_KEY)) {
            error_log('Brevo resubscribe skipped: BREVO_API_KEY is not configured.');
            return false;
        }

        $encoded = rawurlencode($email);
        $statuses = [
            self::brevoRequest('DELETE', '/smtp/blockedContacts/' . $encoded),
            self::brevoRequest('PUT', '/contacts/' . $encoded, ['emailBlacklisted' => false]),
        ];

        $failed       = array_diff($statuses, [204, 404]);
        $failedStatus = $failed ? reset($failed) : null;

        return !$failed;
    }

    // ===== BLOCKED CHECK (BREVO) =====
    // Webhooks cannot reach a local or private server, so this asks Brevo directly.
    // Returns the Webhooks event key matching why the address is blocked, or null when it is not blocked.
    public static function blockedEvent(string $email): ?string
    {
        if (empty(BREVO_API_KEY)) {
            return null;
        }

        for ($offset = 0; $offset < 500; $offset += 100) {
            $response = null;
            $status   = self::brevoRequest('GET', "/smtp/blockedContacts?limit=100&sort=desc&offset=$offset", null, $response);
            $contacts = json_decode((string) $response, true)['contacts'] ?? [];

            if ($status !== 200 || !$contacts) {
                return null;
            }

            foreach ($contacts as $contact) {
                if (strcasecmp($contact['email'] ?? '', $email) !== 0) {
                    continue;
                }

                $code = strtolower($contact['reason']['code'] ?? '');
                return match (true) {
                    str_contains($code, 'unsubscribed') => 'unsubscribed',
                    str_contains($code, 'spam')         => 'spam',
                    str_contains($code, 'bounce')       => 'hard_bounce',
                    default                             => 'blocked',
                };
            }

            if (count($contacts) < 100) {
                return null;
            }
        }

        return null;
    }

    // ===== BREVO API REQUEST =====
    private static function brevoRequest(string $method, string $path, ?array $body = null, ?string &$response = null): int
    {
        $ch = curl_init('https://api.brevo.com/v3' . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'api-key: ' . BREVO_API_KEY,
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => $body ? json_encode($body) : null,
        ]);

        if ($ca = PdfConverter::caBundle()) {
            curl_setopt($ch, CURLOPT_CAINFO, $ca);
        }

        $result   = curl_exec($ch);
        $response = is_string($result) ? $result : null;
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error || !in_array($status, [200, 204, 404], true)) {
            error_log("Brevo $method $path failed: " . ($error ?: "status $status"));
        }

        return $status;
    }

    // ===== NOTIFICATION FOOTER =====
    private static function notificationFooter(string $toEmail): string
    {
        $url = ROOT . '/user/email_preferences?email=' . rawurlencode($toEmail) . '&token=' . self::preferencesToken($toEmail);
        return '<p style="margin-top:16px;font-size:12px;color:#888;">'
            . 'Click here to ' . self::link($url, 'manage your email preferences') . '.'
            . '</p>';
    }

    // ===== PREFERENCES LINK TOKEN =====
    public static function preferencesToken(string $email): string
    {
        return hash_hmac('sha256', strtolower($email), MAIL_PASSWORD);
    }
}
