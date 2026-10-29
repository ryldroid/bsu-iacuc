<?php
$backups = $backups ?? [];
?>
<?php if (empty($backups)): ?>
  <div class="empty-state">
    <h3>
      <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <use href="#check-circle-icon" />
      </svg>
      No backups yet
    </h3>
    <p>Create a backup to keep a copy of the database and uploaded files.</p>
  </div>
<?php else: ?>
  <div class="data-table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Created</th>
          <th>Created by</th>
          <th>Contents</th>
          <th>Size</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($backups as $backup): ?>
          <tr>
            <td>
              <?= date(DATETIME_FORMAT, strtotime($backup['created_at'])) ?>
              <?php if ($backup['label'] === 'prerestore'): ?>
                <span class="personnel-status-pill personnel-status-active">Safety backup</span>
              <?php endif; ?>
              <?php if ($backup['uploaded_at']): ?>
                <span class="personnel-status-pill personnel-status-active">Uploaded <?= date(DATETIME_FORMAT, strtotime($backup['uploaded_at'])) ?></span>
              <?php endif; ?>
              <?php if ($backup['encrypted']): ?>
                <span class="personnel-status-pill personnel-status-active">Encrypted</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($backup['created_by']) ?></td>
            <td>
              <?php if ($backup['valid']): ?>
                Database + <?= number_format($backup['files']) ?> files
              <?php else: ?>
                <span class="personnel-status-pill personnel-status-inactive">Unreadable</span>
              <?php endif; ?>
            </td>
            <td><?= $backup['size'] >= 1048576 ? round($backup['size'] / 1048576, 1) . ' MB' : max(1, (int) round($backup['size'] / 1024)) . ' KB' ?></td>
            <td class="backup-actions-cell">
              <div class="protocol-menu protocol-menu--icon protocol-menu--inline">
                <button type="button" class="action-link protocol-menu-trigger"
                  aria-haspopup="menu" aria-expanded="false"
                  aria-label="Actions for the backup from <?= date(DATETIME_FORMAT, strtotime($backup['created_at'])) ?>">
                  <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#more-icon" />
                  </svg>
                </button>
                <div class="protocol-menu-panel backup-actions" role="menu">
                  <?php if ($backup['valid']): ?>
                    <button type="button" class="accounts-btn-primary accounts-btn-outline personnel-status-btn js-backup-restore"
                      data-name="<?= htmlspecialchars($backup['name']) ?>"
                      data-created="<?= date(DATETIME_FORMAT, strtotime($backup['created_at'])) ?>">
                      <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#undo-icon" />
                      </svg>
                      Restore this backup
                    </button>
                    <button type="button" class="accounts-btn-primary accounts-btn-outline personnel-status-btn js-backup-download"
                      data-name="<?= htmlspecialchars($backup['name']) ?>">
                      <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#download-icon" />
                      </svg>
                      Download
                    </button>
                    <button type="button" class="accounts-btn-primary accounts-btn-outline personnel-status-btn js-backup-verify"
                      data-name="<?= htmlspecialchars($backup['name']) ?>">
                      <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#shield-check-icon" />
                      </svg>
                      Verify backup
                    </button>
                  <?php endif; ?>
                  <button type="button" class="reject-btn personnel-status-btn js-backup-delete"
                    data-name="<?= htmlspecialchars($backup['name']) ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                      <use href="#trash-icon" />
                    </svg>
                    Delete
                  </button>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>