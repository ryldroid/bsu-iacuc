<?php

/** @var array $menu */

$menuFlags    = $menu['flags'];
$menuIsViewer = ($menu['look'] ?? 'icon') === 'labeled';
$menuTitle    = htmlspecialchars((string) $menu['title'], ENT_QUOTES, 'UTF-8');
$menuItems    = [];
$menuHasUpdate = !empty($menu['hasUpdate']) && $menuFlags['hasRenames'];

if (!empty($menu['certUrl'])) {
  $menuItems[] = ['action' => 'training-certificate', 'label' => 'Training Certificate', 'icon' => 'review-icon', 'fileUrl' => $menu['certUrl']];
}
$menuItems[] = ['action' => 'version-history', 'label' => 'Version History', 'icon' => 'history-icon', 'update' => $menuHasUpdate, 'compact' => true];
if ($menuFlags['hasPaymentHistory']) {
  $menuItems[] = ['action' => 'payment-history', 'label' => 'Payment History', 'icon' => 'history-icon'];
}
if (!empty($menu['download'])) {
  $menuItems[] = ['action' => '', 'label' => 'Download', 'icon' => 'download-icon', 'href' => $menu['download']['url'], 'fileName' => $menu['download']['name'], 'compact' => true];
}
if ($menuFlags['canRename']) {
  $menuItems[] = ['action' => 'rename', 'label' => 'Rename', 'icon' => 'edit-icon', 'compact' => true];
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
  $menuItems[] = ['action' => 'delete', 'label' => 'Delete', 'icon' => 'trash-icon', 'danger' => true, 'compact' => true];
} elseif ($menuFlags['canRequestDeletion']) {
  $requested   = !empty($menu['deletionRequested']);
  $menuItems[] = ['action' => 'delete', 'label' => $requested ? 'Deletion Requested' : 'Request Deletion', 'icon' => 'trash-icon', 'danger' => true, 'disabled' => $requested, 'compact' => true];
}

?>
<div class="protocol-menu protocol-menu--<?= $menuIsViewer ? 'labeled' : 'icon' ?>"
  data-protocol-id="<?= (int) $menu['protocolId'] ?>"
  data-title="<?= $menuTitle ?>"
  data-delete-mode="<?= $menuFlags['canDelete'] ? 'delete' : 'request' ?>"
  data-delete-redirect="<?= htmlspecialchars((string) ($menu['deleteRedirect'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
  data-amend-redirect="<?= htmlspecialchars((string) ($menu['amendRedirect'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($menuIsViewer): ?>
    <?php foreach ($menuItems as $item): ?>
      <?php
      $actionClass = 'tool-btn tool-btn--ghost protocol-menu-action'
        . (!empty($item['danger']) ? ' protocol-menu-action--danger' : '')
        . (!empty($item['update']) ? ' has-update' : '');
      $actionLabel = htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8');
      ?>
      <?php if (isset($item['href'])): ?>
        <a class="<?= $actionClass ?>" title="<?= $actionLabel ?>" aria-label="<?= $actionLabel ?>"
          href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
          download="<?= htmlspecialchars((string) $item['fileName'], ENT_QUOTES, 'UTF-8') ?>">
          <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#<?= $item['icon'] ?>" />
          </svg>
          <?= empty($item['compact']) ? $actionLabel : '' ?>
        </a>
      <?php else: ?>
        <button type="button" class="<?= $actionClass ?>" title="<?= $actionLabel ?>" aria-label="<?= $actionLabel ?>"
          data-menu-action="<?= $item['action'] ?>"
          <?= isset($item['fileUrl']) ? 'data-file-url="' . htmlspecialchars($item['fileUrl'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>
          <?= !empty($item['disabled']) ? 'disabled' : '' ?>>
          <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#<?= $item['icon'] ?>" />
          </svg>
          <?= empty($item['compact']) ? $actionLabel : '' ?>
        </button>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php else: ?>
    <button type="button" class="action-link protocol-menu-trigger<?= $menuHasUpdate ? ' has-update' : '' ?>"
      aria-haspopup="menu" aria-expanded="false"
      aria-label="Actions for <?= $menuTitle ?><?= $menuHasUpdate ? ' (title recently changed)' : '' ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <use href="#more-icon" />
      </svg>
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
            <?= isset($item['fileUrl']) ? 'data-file-url="' . htmlspecialchars($item['fileUrl'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>
            <?= !empty($item['disabled']) ? 'disabled' : '' ?>>
            <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <use href="#<?= $item['icon'] ?>" />
            </svg>
            <?= $item['label'] ?>
          </button>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>