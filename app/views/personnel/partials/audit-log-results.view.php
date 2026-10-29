<?php
$auditLogs       = $auditLogs       ?? [];
$auditPage       = $auditPage       ?? 1;
$auditPages      = $auditPages      ?? 1;
$auditTotal      = $auditTotal      ?? 0;
$auditPerPage    = $auditPerPage    ?? 10;
$auditOffset     = $auditOffset     ?? 0;
$auditFilterDate = $auditFilterDate ?? null;
$auditToneKeywords = [
  'danger'  => ['delete', 'fail', 'lock', 'denied', 'reject'],
  'warning' => ['restore', 'edit', 'update', 'change', 'reset', 'unassign', 'return'],
  'success' => ['success', 'approve', 'verified', 'confirm', 'reactivated', 'claimed'],
  'info'    => ['creat', 'add', 'upload', 'download', 'submit', 'request'],
];
?>
<?php if (empty($auditLogs)): ?>
  <div class="empty-state">
    <h3>
      <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <use href="#check-circle-icon" />
      </svg>
      <?= !empty($auditFilterDate) ? 'No activity on this date' : 'No activity yet' ?>
    </h3>
    <p><?= !empty($auditFilterDate) ? 'Try a different date, or clear the filter to see the full log.' : 'System activity will appear here as it happens.' ?></p>
  </div>
<?php else: ?>
  <div class="data-table-wrap">
    <table class="data-table audit-viewer-table">
      <thead>
        <tr>
          <th scope="col">Timestamp</th>
          <th scope="col">User</th>
          <th scope="col">Action</th>
          <th scope="col">Target</th>
          <th scope="col">Details</th>
          <th scope="col">IP Address</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($auditLogs as $log):
          $auditTone = 'neutral';
          foreach ($auditToneKeywords as $tone => $words) {
            foreach ($words as $word) {
              if (str_contains($log['action'], $word)) {
                $auditTone = $tone;
                break 2;
              }
            }
          }
        ?>
          <tr>
            <td data-label="Timestamp">
              <?php $logTime = strtotime($log['created_at']); ?>
              <?= date(DATE_FORMAT, $logTime) ?>
              <span class="audit-viewer-time"><?= date('g:i A', $logTime) ?></span>
            </td>
            <td data-label="User">
              <?= htmlspecialchars($log['username']) ?>
              <span class="audit-viewer-role"><?= htmlspecialchars(ucfirst($log['role'])) ?></span>
            </td>
            <td data-label="Action"><span class="status-dot status-dot--<?= $auditTone ?>"><?= htmlspecialchars($log['action_label']) ?></span></td>
            <td data-label="Target"><?= htmlspecialchars($log['target_label']) ?></td>
            <td data-label="Details"><?= htmlspecialchars($log['details']) ?></td>
            <td data-label="IP Address"><?= htmlspecialchars($log['ip_address']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($auditPages > 1): ?>
    <div class="pagination-bar">
      <div class="pagination-info">
        Showing <?= $auditOffset + 1 ?>–<?= min($auditOffset + $auditPerPage, $auditTotal) ?> of <?= $auditTotal ?> entries
      </div>
      <div class="pagination-buttons">
        <?php if ($auditPage > 1): ?>
          <a href="?audit_page=<?= $auditPage - 1 ?><?= !empty($auditFilterDate) ? '&audit_date=' . urlencode($auditFilterDate) : '' ?>#audit-log-viewer"
            class="pagination-btn js-audit-page" data-page="<?= $auditPage - 1 ?>" title="Previous">‹</a>
        <?php else: ?>
          <span class="pagination-btn disabled">‹</span>
        <?php endif; ?>

        <span class="pagination-btn active"><?= $auditPage ?></span>

        <?php if ($auditPage < $auditPages): ?>
          <a href="?audit_page=<?= $auditPage + 1 ?><?= !empty($auditFilterDate) ? '&audit_date=' . urlencode($auditFilterDate) : '' ?>#audit-log-viewer"
            class="pagination-btn js-audit-page" data-page="<?= $auditPage + 1 ?>" title="Next">›</a>
        <?php else: ?>
          <span class="pagination-btn disabled">›</span>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>