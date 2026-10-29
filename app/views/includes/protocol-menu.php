<?php

/** @var array $menu */

$menuFlags    = $menu['flags'];
$menuIsViewer = ($menu['look'] ?? 'icon') === 'labeled';
$menuTitle    = htmlspecialchars((string) $menu['title'], ENT_QUOTES, 'UTF-8');
$menuItems    = [];
$menuHasUpdate = !empty($menu['hasUpdate']) && $menuFlags['hasRenames'];

$menuItems[] = ['action' => 'history', 'label' => 'History', 'icon' => 'history-icon', 'update' => $menuHasUpdate];
if (!empty($menu['download'])) {
  $menuItems[] = ['action' => '', 'label' => 'Download', 'icon' => 'download-icon', 'href' => $menu['download']['url'], 'fileName' => $menu['download']['name']];
}
if ($menuFlags['canRename']) {
  $menuItems[] = ['action' => 'rename', 'label' => 'Rename', 'icon' => 'edit-icon'];
}
if ($menuFlags['canAmend']) {
  $menuItems[] = ['action' => 'submit-amendment', 'label' => 'Submit Amendment', 'icon' => 'upload-icon'];
}
if ($menuFlags['amendmentCount'] > 0) {
  $menuItems[] = ['action' => 'amendments', 'label' => 'Amendments (' . (int) $menuFlags['amendmentCount'] . ')', 'icon' => 'review-icon'];
}
foreach ($menu['extraItems'] ?? [] as $extra) {
  $menuItems[] = $extra;
}
if ($menuFlags['canDelete']) {
  $menuItems[] = ['action' => 'delete', 'label' => 'Delete', 'icon' => 'trash-icon', 'danger' => true];
} elseif ($menuFlags['canRequestDeletion']) {
  $menuItems[] = !empty($menu['deletionRequested'])
    ? ['action' => 'cancel-deletion', 'label' => 'Cancel Deletion Request', 'icon' => 'undo-icon']
    : ['action' => 'delete', 'label' => 'Request Deletion', 'icon' => 'trash-icon', 'danger' => true];
}

?>
<div class="protocol-menu protocol-menu--<?= $menuIsViewer ? 'labeled' : 'icon' ?>"
  data-protocol-id="<?= (int) $menu['protocolId'] ?>"
  data-title="<?= $menuTitle ?>"
  data-delete-mode="<?= $menuFlags['canDelete'] ? 'delete' : 'request' ?>"
  data-delete-redirect="<?= htmlspecialchars((string) ($menu['deleteRedirect'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
  data-amend-redirect="<?= htmlspecialchars((string) ($menu['amendRedirect'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
  <button type="button" class="<?= $menuIsViewer ? 'tool-btn tool-btn--ghost' : 'action-link' ?> protocol-menu-trigger<?= $menuHasUpdate ? ' has-update' : '' ?>"
    aria-haspopup="menu" aria-expanded="false"
    aria-label="Actions for <?= $menuTitle ?><?= $menuHasUpdate ? ' (title recently changed)' : '' ?>">
    <svg width="<?= $menuIsViewer ? 16 : 20 ?>" height="<?= $menuIsViewer ? 16 : 20 ?>" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <use href="#more-icon" />
    </svg>
    <?= $menuIsViewer ? 'More' : '' ?>
  </button>

  <div class="protocol-menu-panel" role="menu">
    <?php foreach ($menuItems as $item): ?>
      <?php
      $itemClass = 'protocol-menu-item'
        . (!empty($item['danger']) ? ' protocol-menu-item--danger' : '')
        . (!empty($item['update']) ? ' has-update' : '');
      ?>
      <?php if (isset($item['href'])): ?>
        <a class="<?= $itemClass ?>" role="menuitem"
          href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
          download="<?= htmlspecialchars((string) $item['fileName'], ENT_QUOTES, 'UTF-8') ?>">
          <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#<?= $item['icon'] ?>" />
          </svg>
          <?= $item['label'] ?>
        </a>
      <?php else: ?>
        <button type="button" class="<?= $itemClass ?>" role="menuitem"
          data-menu-action="<?= $item['action'] ?>"
          <?= isset($item['fileUrl']) ? 'data-file-url="' . htmlspecialchars($item['fileUrl'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
          <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#<?= $item['icon'] ?>" />
          </svg>
          <?= $item['label'] ?>
        </button>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>