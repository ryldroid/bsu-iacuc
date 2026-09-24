<?php
$auditLogs       = $auditLogs       ?? [];
$auditPage       = $auditPage       ?? 1;
$auditPages      = $auditPages      ?? 1;
$auditTotal      = $auditTotal      ?? 0;
$auditPerPage    = $auditPerPage    ?? 10;
$auditOffset     = $auditOffset     ?? 0;
$auditFilterDate = $auditFilterDate ?? null;
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
  <div class="audit-viewer-table-wrap">
    <table class="audit-viewer-table">
      <thead>
        <tr>
          <th>Timestamp</th>
          <th>User</th>
          <th>Action</th>
          <th>Target</th>
          <th>Details</th>
          <th>IP Address</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($auditLogs as $log): ?>
          <tr>
            <td data-label="Timestamp"><?= htmlspecialchars(date('M j, Y @ h:i A', strtotime($log['created_at']))) ?></td>
            <td data-label="User">
              <?= htmlspecialchars($log['username']) ?>
              <span class="audit-viewer-role"><?= htmlspecialchars(ucfirst($log['role'])) ?></span>
            </td>
            <td data-label="Action"><?= htmlspecialchars($log['action_label']) ?></td>
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
          <span class="pagination-btn" style="opacity:.35;cursor:default">‹</span>
        <?php endif; ?>

        <span class="pagination-btn active"><?= $auditPage ?></span>

        <?php if ($auditPage < $auditPages): ?>
          <a href="?audit_page=<?= $auditPage + 1 ?><?= !empty($auditFilterDate) ? '&audit_date=' . urlencode($auditFilterDate) : '' ?>#audit-log-viewer"
            class="pagination-btn js-audit-page" data-page="<?= $auditPage + 1 ?>" title="Next">›</a>
        <?php else: ?>
          <span class="pagination-btn" style="opacity:.35;cursor:default">›</span>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>