<?php

class Notifier
{
  // ===== STATUS NOTIFICATION TEXT =====
  public const STATUS_NOTICES = [
    'under review'   => ['Protocol Under Review', 'is now under review by CCARD. No action needed for now. You will be notified if revisions are needed or once the review is finished.'],
    'needs revision' => ['Revision Needed', 'was returned by CCARD for revision. Open it, read the comments, then fix your files and re-submit.'],
    'reviewed'       => ['Review Passed: Pay Clearance Fee', 'passed CCARD review. Please pay the Php 100.00 clearance fee. Go to My Protocols and press "Pay Clearance Fee" on this protocol.'],
    'reviewed paid'  => ['Protocol Moved Back to Reviewed', 'was moved back to Reviewed. Your payment is already verified, so no action is needed. Please wait for it to be endorsed to Department of Agriculture – Regional Field Office Cordillera Administrative Region (DA-RFO CAR).'],
    'endorsed'       => ['Protocol Endorsed', 'has been endorsed to Department of Agriculture – Regional Field Office Cordillera Administrative Region (DA-RFO CAR). No action needed. Please wait while BAI processes your animal research clearance.'],
    'approved'       => ['Protocol Approved', 'has been approved. Download your clearance from My Protocols, then visit the CCARD office to claim the embossed hard copy.'],
  ];

  public const SIGNED_SCAN_NEXT_STEP = 'No action needed. It will be endorsed to Department of Agriculture – Regional Field Office Cordillera Administrative Region (DA-RFO CAR) next.';

  public const NEXT_STEPS = [
    'protocol_submitted'          => 'No action needed. You will be notified of any feedback.',
    'protocol_renamed'            => 'Review your protocol.',
    'protocol_deleted'            => 'No action needed.',
    'protocol_deletion_rejected'  => 'Your protocol stays active, so no action is needed.',
    'new_submission_reviewer'     => 'Open it to start the review. Your part ends once it is marked Reviewed or returned for revision.',
    'new_submission_staff'        => 'No action needed for now. A reviewer will assess it first.',
    'first_submission_reviewer'   => 'This is their first submission. Open it to verify their training certificate, then start the review.',
    'first_submission_staff'      => 'This is their first submission. Open it to verify their training certificate.',
    'protocol_resubmitted_reviewer' => 'Open it to review the revisions.',
    'protocol_resubmitted_staff'  => 'No action needed for now. A reviewer will assess the revisions first.',
    'protocol_amended'            => 'No action needed. It does not need to be reviewed again.',
    'protocol_deletion_requested' => 'Open it to approve or reject the request.',
    'payment_proof_uploaded'      => 'Open it to verify the payment.',
    'cert_verification_reviewer'  => 'Open it to verify the certificate.',
    'cert_verification_staff'     => 'Open it to verify the certificate.',
  ];

  public const PAYMENT_VERIFIED_NEXT_STEP = 'No action needed. CCARD will now sign your protocol and endorse it to Department of Agriculture – Regional Field Office Cordillera Administrative Region (DA-RFO CAR).';

  public const CERT_VERIFIED_NEXT_STEP = 'No action needed. Your protocol review continues and you can submit new protocols.';

  public const CERT_REJECTED_NEXT_STEP = 'Press Resubmit Certificate on My Protocols or on your protocol.';

  public const PAYMENT_REJECTED_NEXT_STEP = 'Go to My Protocols, press "Pay Clearance Fee" and submit your payment again.';

  public const PAYMENT_UNDONE_NEXT_STEP = 'No action needed. CCARD will verify your payment again.';

  public const HARD_COPY_UNDONE_NEXT_STEP = 'If you already claimed it, please tell the CCARD office. Otherwise, visit the office to claim it.';

  public const REVIEW_UNDONE_NOTICE = ['Review Undone', 'was moved back to review because its review was undone. Please do not pay the clearance fee for now. You will be notified once the review is finished.'];

  // ===== EVERY NEXT-STEP TEXT WITH THE NOTIFICATION TYPE THAT CARRIES IT =====
  // Schema uses this to rewrite saved notifications whenever one of these texts is reworded.
  public static function stepTexts(): array
  {
    $steps = [];
    foreach (self::NEXT_STEPS as $key => $step) {
      $steps[$key] = [preg_replace(['/^first_submission/', '/_(staff|reviewer)$/'], ['new_submission', ''], $key), $step];
    }

    return $steps + [
      'signed_scan_uploaded'   => ['signed_scan_uploaded', self::SIGNED_SCAN_NEXT_STEP],
      'payment_verified'       => ['payment_verified', self::PAYMENT_VERIFIED_NEXT_STEP],
      'cert_verified'          => ['cert_verified', self::CERT_VERIFIED_NEXT_STEP],
      'cert_rejected'          => ['cert_rejected', self::CERT_REJECTED_NEXT_STEP],
      'payment_proof_rejected' => ['payment_proof_rejected', self::PAYMENT_REJECTED_NEXT_STEP],
    ];
  }

  // ===== RETURNED TO REVIEW (BY DA-RFO CAR OR BAI) =====
  public static function reasonSentence(string $reason): string
  {
    return $reason === '' ? '' : ' Reason: ' . rtrim($reason, '.') . '.';
  }

  public static function returnedToReviewNotice(string $reason): array
  {
    return ['Protocol Returned to Review', 'was returned to review after Department of Agriculture – Regional Field Office Cordillera Administrative Region (DA-RFO CAR) or BAI sent it back.' . self::reasonSentence($reason) . ' No action needed for now. You will be notified if revisions are needed or once the review is finished.'];
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
    array $email = [],
    ?int $exceptUserId = null
  ): void {
    $recipients = (new NotificationModel())->createForRole($role, $type, $title, $message, $link, $exceptUserId);

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

  // ===== PERSONNEL APPLICATION =====
  public static function personnelApplicationMessage(string $name, string $role): string
  {
    return self::bold($name) . ' applied as ' . ($role === 'staff' ? 'Administrative Staff' : 'Reviewer') . '. Open Administration to approve or reject the application.';
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
