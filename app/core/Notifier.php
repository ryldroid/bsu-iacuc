<?php

class Notifier
{
  public static function send(
    int $userId,
    string $type,
    string $title,
    string $message = '',
    ?string $link = null,
    array $email = []
  ): void {
    require_once dirname(__DIR__) . '/models/NotificationModel.php';
    (new NotificationModel())->create($userId, $type, $title, $message, $link);

    if (empty($email['template']) || empty($email['to'])) {
      return;
    }

    require_once dirname(__DIR__) . '/models/UserModel.php';
    $recipient = (new UserModel())->getUser($userId);

    if ($recipient && !empty($recipient['email_verified'])) {
      self::sendEmail($email);
    }
  }

  public static function sendToRole(
    string $role,
    string $type,
    string $title,
    string $message = '',
    ?string $link = null,
    array $email = []
  ): void {
    require_once dirname(__DIR__) . '/models/NotificationModel.php';

    $recipients = (new NotificationModel())->createForRole($role, $type, $title, $message, $link);

    if (empty($email['template'])) {
      return;
    }

    foreach ($recipients as $user) {
      if (empty($user['email_verified'])) {
        continue;
      }

      self::sendEmail(array_merge($email, [
        'to'   => $user['email'],
        'name' => $user['first_name'],
      ]));
    }
  }

  private static function sendEmail(array $email): void
  {
    try {
      Mailer::sendTemplate(
        $email['template'],
        $email['vars'] ?? [],
        $email['to'],
        $email['name'] ?? '',
        $email['subject'] ?? 'Notification'
      );
    } catch (Throwable $e) {
      error_log('Notifier email error: ' . $e->getMessage());
    }
  }
}
