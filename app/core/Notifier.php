<?php

class Notifier
{
  // ===== STATUS NOTIFICATION TEXT =====
  public const STATUS_NOTICES = [
    'under review'   => ['Protocol Under Review', 'is now under review by CCARD. No action needed for now. You will be notified if revisions are needed or once the review is finished.'],
    'needs revision' => ['Revision Needed', 'was returned by CCARD for revision. Open it, read the comments, then fix your files and re-submit.'],
    'reviewed'       => ['Review Passed: Pay Clearance Fee', 'passed CCARD review. Please pay the Php 100.00 clearance fee. Go to My Protocols and press "Pay Clearance Fee" on this protocol.'],
    'reviewed paid'  => ['Protocol Moved Back to Reviewed', 'was moved back to Reviewed. Your payment is already verified, so no action is needed. Please wait for it to be endorsed to DA-CARFU.'],
    'endorsed'       => ['Protocol Endorsed', 'has been endorsed to DA-CARFU. No action needed. Please wait while BAI processes your animal research clearance.'],
    'approved'       => ['Protocol Approved', 'has been approved. Download your clearance from My Protocols, then visit the CCARD office to claim the embossed hard copy.'],
  ];

  public const SIGNED_SCAN_NEXT_STEP = 'No action needed. It will be endorsed to DA-CARFU next.';

  public const NEXT_STEPS = [
    'protocol_submitted'          => 'No action needed. You will be notified of any feedback.',
    'protocol_renamed'            => 'Review your protocol.',
    'protocol_deleted'            => 'No action needed.',
    'protocol_deletion_rejected'  => 'Your protocol stays active, so no action is needed.',
    'new_submission_reviewer'     => 'Open it to start the review. Your part ends once it is marked Reviewed or returned for revision.',
    'new_submission_staff'        => 'No action needed for now. A reviewer will assess it first.',
    'protocol_resubmitted_reviewer' => 'Open it to review the revisions.',
    'protocol_resubmitted_staff'  => 'No action needed for now. A reviewer will assess the revisions first.',
    'protocol_amended'            => 'No action needed. It does not need to be reviewed again.',
    'protocol_deletion_requested' => 'Open it to approve or reject the request.',
    'payment_proof_uploaded'      => 'Open it to verify the payment.',
    'cert_verification_reviewer'  => 'Open it to verify the certificate.',
    'cert_verification_staff'     => 'Open it to verify the certificate.',
  ];

  public const PAYMENT_VERIFIED_NEXT_STEP = 'No action needed. CCARD will now sign your protocol and endorse it to DA-CARFU.';

  public const CERT_VERIFIED_NEXT_STEP = 'No action needed. Your protocol review continues and you can submit new protocols.';

  public const CERT_REJECTED_NEXT_STEP = 'Press Resubmit Certificate on My Protocols or on your protocol.';

  public const PAYMENT_REJECTED_NEXT_STEP = 'Go to My Protocols, press "Pay Clearance Fee" and submit your payment again.';

  // ===== RETURNED TO REVIEW (BY DA-CARFU OR BAI) =====
  public static function reasonSentence(string $reason): string
  {
    return $reason === '' ? '' : ' Reason: ' . rtrim($reason, '.') . '.';
  }

  public static function returnedToReviewNotice(string $reason): array
  {
    return ['Protocol Returned to Review', 'was returned to review after DA-CARFU or BAI sent it back.' . self::reasonSentence($reason) . ' No action needed for now. You will be notified if revisions are needed or once the review is finished.'];
  }

  // ===== SEND TO ONE USER =====
  public static function send(
    int $userId,
    string $type,
    string $title,
    string $message = '',
    ?string $link = null,
    array $email = []
  ): void {
    (new NotificationModel())->create($userId, $type, $title, $message, $link);

    if (empty($email['template']) || empty($email['to'])) {
      return;
    }

    $recipient = (new UserModel())->getUser($userId);

    if ($recipient && !empty($recipient['email_verified']) && !empty($recipient['email_notifications'])) {
      self::sendEmail($email);
    }
  }

  // ===== SEND TO A ROLE =====
  public static function sendToRole(
    string $role,
    string $type,
    string $title,
    string $message = '',
    ?string $link = null,
    array $email = []
  ): void {
    $recipients = (new NotificationModel())->createForRole($role, $type, $title, $message, $link);

    if (empty($email['template'])) {
      return;
    }

    foreach ($recipients as $user) {
      if (empty($user['email_verified']) || empty($user['email_notifications'])) {
        continue;
      }

      self::sendEmail(array_merge($email, [
        'to'   => $user['email'],
        'name' => $user['first_name'],
      ]));
    }
  }

  // ===== SEND TO STAFF AND REVIEWERS =====
  public static function sendToPersonnel(
    string $type,
    string $title,
    string $message,
    string $nextStepKey,
    ?string $link = null,
    array $email = []
  ): void {
    foreach (['staff', 'reviewer'] as $role) {
      self::sendToRole($role, $type, $title, $message . ' ' . self::NEXT_STEPS[$nextStepKey . '_' . $role], $link, $email);
    }
  }

  // ===== MESSAGE FORMATTING =====
  public static function bold(string $text): string
  {
    return "**$text**";
  }

  public static function boldTitle(string $title): string
  {
    return '"' . self::bold($title) . '"';
  }

  public static function toHtml(string $message): string
  {
    return preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
  }

  // ===== SEND EMAIL =====
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
