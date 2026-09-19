<?php

/** @var array  $items */
/** @var int    $total */
/** @var int    $page */
/** @var int    $totalPages */
/** @var int    $perPage */
/** @var string $csrf */

$title = 'Notifications';

include 'includes/header.php';
include 'includes/scroll-top.php';

$items      = $items      ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage    ?? 20;
$offset     = ($page - 1) * $perPage;

function notifPageUrl(int $p): string
{
  return ROOT . '/notifications/page?page=' . $p;
}

function notifTimeAgo(string $dateStr): string
{
  $seconds = time() - strtotime($dateStr);
  if ($seconds < 60) return 'just now';
  $minutes = (int) floor($seconds / 60);
  if ($minutes < 60) return "{$minutes}m ago";
  $hours = (int) floor($minutes / 60);
  if ($hours < 24) return "{$hours}h ago";
  $days = (int) floor($hours / 24);
  if ($days < 7) return "{$days}d ago";
  return date('M j, Y', strtotime($dateStr));
}
?>

<div class="body">
  <?php include 'includes/navigation.php'; ?>

  <main class="main-content" id="main-content" tabindex="-1">

    <div class="notif-page-header">
      <h1>Notifications</h1>
      <button type="button" class="notif-page-mark-all" id="notifPageMarkAll">Mark all as read</button>
    </div>

    <div class="notif-page-list">
      <?php if (! $items): ?>
        <div class="notif-empty">You're all caught up.</div>
      <?php else: ?>
        <?php foreach ($items as $item): ?>
          <?php
          $isUnread = (int) $item['is_read'] === 0;
          $href     = $item['link'] ? ROOT . '/' . $item['link'] : '#';
          ?>
          <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
            class="notif-item<?= $isUnread ? ' unread' : '' ?>"
            data-id="<?= (int) $item['id'] ?>">
            <span class="notif-item-icon notif-item-icon--<?= $item['variant'] ?>">
              <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#<?= $item['icon'] ?>" />
              </svg>
            </span>
            <div class="notif-item-body">
              <div class="notif-item-title"><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="notif-item-message"><?= $item['message_html'] ?></div>
              <div class="notif-item-time"><?= notifTimeAgo($item['created_at']) ?></div>
            </div>
            <?php if ($isUnread): ?>
              <span class="notif-item-dot" aria-hidden="true"></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="notif-page-pagination">
        <div class="notif-page-pagination-info">
          Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?>
        </div>
        <div class="notif-page-pagination-buttons">
          <?php if ($page > 1): ?>
            <a href="<?= notifPageUrl($page - 1) ?>" class="notif-page-btn" title="Previous">‹</a>
          <?php else: ?>
            <span class="notif-page-btn" style="opacity:.35;cursor:default">‹</span>
          <?php endif; ?>

          <?php
          $start = max(1, $page - 2);
          $end   = min($totalPages, $page + 2);
          if ($start > 1) echo '<span class="notif-page-ellipsis">…</span>';
          for ($i = $start; $i <= $end; $i++):
          ?>
            <a href="<?= notifPageUrl($i) ?>" class="notif-page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor;
          if ($end < $totalPages) echo '<span class="notif-page-ellipsis">…</span>';
          ?>

          <?php if ($page < $totalPages): ?>
            <a href="<?= notifPageUrl($page + 1) ?>" class="notif-page-btn" title="Next">›</a>
          <?php else: ?>
            <span class="notif-page-btn" style="opacity:.35;cursor:default">›</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

  </main>
</div>

<script>
  (function() {
    const csrfToken = <?= json_encode($csrf) ?>;
    const list = document.querySelector('.notif-page-list');
    const markAllBtn = document.getElementById('notifPageMarkAll');

    list?.addEventListener('click', (event) => {
      const item = event.target.closest('.notif-item');
      if (!item) return;
      fetch(<?= json_encode(ROOT . '/notifications/markread') ?>, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
          id: item.dataset.id
        }),
      }).catch(() => {});
    });

    markAllBtn?.addEventListener('click', async () => {
      try {
        await fetch(<?= json_encode(ROOT . '/notifications/markallread') ?>, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
        });
        location.reload();
      } catch (e) {}
    });
  })();
</script>