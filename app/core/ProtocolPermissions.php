<?php

/**
 * Single source of truth for what an actor may do with a protocol.
 *
 * The viewer, the list rows and the endpoints all ask this class instead of
 * recomputing the rules inline. It only describes what the UI may offer; every
 * endpoint still enforces its own permission check on the server.
 *
 * The data-driven flags (payment history, renames, amendments, training
 * certificate) come from the list columns on the row (see ProtocolModel::getAll
 * and getByUser). A row without them can pass a ProtocolModel to load them with
 * queries instead. With neither, only the cheap, status-based flags are filled in.
 */
class ProtocolPermissions
{
  public const RENAMEABLE_STATUSES = ['under review', 'needs revision'];

  public static function flags(array $protocol, array $actor, ?ProtocolModel $model = null): array
  {
    $role        = (string) ($actor['role'] ?? '');
    $actorId     = (int) ($actor['id'] ?? 0);
    $isPersonnel = in_array($role, ['staff', 'reviewer'], true);
    $isOwner     = $actorId > 0 && (int) ($protocol['user_id'] ?? 0) === $actorId;
    $status      = strtolower((string) ($protocol['status'] ?? ''));

    $flags = [
      'isOwner'            => $isOwner,
      'isPersonnel'        => $isPersonnel,
      'isStaff'            => $role === 'staff',
      'isReviewer'         => $role === 'reviewer',
      'canRename'          => ($isOwner || $isPersonnel) && in_array($status, self::RENAMEABLE_STATUSES, true),
      'canRequestDeletion' => $isOwner && !$isPersonnel,
      'canDelete'          => $isPersonnel,
      'canAmend'           => $isOwner && !$isPersonnel && $status === 'approved',
      'hasPaymentHistory'  => false,
      'hasRenames'         => false,
      'amendmentCount'     => 0,
    ];

    $protocolId = (int) ($protocol['protocol_id'] ?? $protocol['id'] ?? 0);

    if ($model !== null && !isset($protocol['amendment_count'])) {
      $protocol += [
        'latest_payment_proof_version_id' => $model->getLatestVersion($protocolId, 'payment_proof')['id'] ?? null,
        'has_payment_rejections'          => $model->getPaymentRejections($protocolId) !== [],
        'title_history_count'             => count($model->getTitleHistory($protocolId)),
        'amendment_count'                 => count($model->getVersions($protocolId, 'amendment')),
      ];
    }

    if (!isset($protocol['amendment_count'])) {
      return $flags;
    }

    $flags['hasPaymentHistory'] = ($protocol['payment_status'] ?? '') === 'paid'
      || !empty($protocol['latest_payment_proof_version_id'])
      || !empty($protocol['has_payment_rejections']);

    // The title history always holds the original title, so a rename
    // only exists once there is more than one entry.
    $flags['hasRenames'] = (int) $protocol['title_history_count'] > 1;

    $flags['amendmentCount'] = (int) $protocol['amendment_count'];

    return $flags;
  }
}
