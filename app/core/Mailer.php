<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private const ACCOUNT_SECURITY_TEMPLATES = ['verify_email', 'password_reset'];

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

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer error: " . $mail->ErrorInfo);
            return false;
        }
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
            $body .= self::notificationFooter();
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
    public static function resubscribe(string $email): bool
    {
        if (empty(BREVO_API_KEY)) {
            error_log('Brevo resubscribe skipped: BREVO_API_KEY is not configured.');
            return false;
        }

        $ch = curl_init('https://api.brevo.com/v3/smtp/blockedContacts/' . rawurlencode($email));
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'api-key: ' . BREVO_API_KEY,
                'Accept: application/json',
            ],
        ]);

        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('Brevo resubscribe error for ' . $email . ': ' . $error);
            return false;
        }

        if (!in_array($status, [204, 404], true)) {
            error_log('Brevo resubscribe unexpected status ' . $status . ' for ' . $email);
            return false;
        }

        return true;
    }

    // ===== NOTIFICATION FOOTER =====
    private static function notificationFooter(): string
    {
        $url = ROOT . '/user/account';
        return '<p style="margin-top:16px;font-size:12px;color:#888;">'
            . 'You can manage email notification preferences in your <a href="' . $url . '">account settings</a>.'
            . '</p>';
    }
}
