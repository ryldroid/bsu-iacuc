<?php

/**
 * Builds the $menu array that includes/protocol-menu.php renders.
 *
 * The list views and the viewer call this instead of assembling the array themselves. The
 * flags come from ProtocolPermissions, so the rows, the viewer and the endpoints
 * share one set of rules.
 */
class ProtocolMenu
{
  public static function forRow(array $protocol, array $actor, array $extraItems = []): array
  {
    $flags      = ProtocolPermissions::flags($protocol, $actor);
    $latestFile = (int) ($protocol['latest_protocol_version_id'] ?? 0);
    return self::base($protocol) + [
      'look'     => 'icon',
      'flags'    => $flags,
      'extraItems' => $extraItems,
      'download' => $latestFile > 0
        ? ['url' => ROOT . '/apply/file/' . $latestFile . '?download=1', 'name' => '']
        : null,
    ];
  }

  /**
   * The viewer already knows its flags, the version on screen and where to go
   * after an action, so it passes those in $options (download,
   * hasUpdate, deleteRedirect, amendRedirect).
   */
  public static function forViewer(array $protocol, array $flags, array $options): array
  {
    return $options + self::base($protocol) + [
      'look'  => 'labeled',
      'flags' => $flags,
    ];
  }

  private static function base(array $protocol): array
  {
    return [
      'protocolId'        => (int) $protocol['protocol_id'],
      'title'             => $protocol['research_title'],
      'deletionRequested' => !empty($protocol['deletion_requested_at']),
    ];
  }
}
