<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    // Templates that shouldn't advertise the notification-preference footer,
    // since these are account-security emails and aren't optional.
    private const ACCOUNT_SECURITY_TEMPLATES = ['verify_email', 'password_reset'];

    public static function send(string $toEmail, string $toName, string $subject, string $body): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;

            $mail->setFrom(MAIL_FROM, MAIL_FROMNAME);
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

    public static function sendTemplate(string $template, array $vars, string $toEmail, string $toName, string $subject): bool
    {
        $file = VIEWSPATH . "emails/{$template}.php";
        if (!file_exists($file)) return false;

        extract($vars);
        $body = require $file;

        if (!in_array($template, self::ACCOUNT_SECURITY_TEMPLATES, true)) {
            $body .= self::notificationFooter();
        }

        return self::send($toEmail, $toName, $subject, $body);
    }

    private static function notificationFooter(): string
    {
        $url = ROOT . '/users/account';
        return '<p style="margin-top:16px;font-size:12px;color:#888;">'
            . 'You can manage email notification preferences in your <a href="' . $url . '">account settings</a>.'
            . '</p>';
    }
}
