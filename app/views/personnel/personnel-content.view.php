<?php

/** @var array  $user          */
/** @var string $csrf          */
/** @var array  $announcements */
/** @var array  $settings      */
/** @var array  $offices       */
/** @var string $activeTab     */

$title = 'Content';
include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/scroll-top.php';

$user          = $user          ?? $_SESSION['user'] ?? [];
$csrf          = $csrf          ?? '';
$announcements = $announcements ?? [];
$settings      = $settings      ?? [];
$offices       = $offices       ?? [];
$activeTab     = $activeTab     ?? 'announcements';
?>

<link rel="stylesheet" href="<?= asset_css('personnel/personnel-base.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/personnel-home.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/accounts.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/records.css') ?>">
<link rel="stylesheet" href="<?= asset_css('announcements.css') ?>">
<link rel="stylesheet" href="<?= asset_css('tabs.css') ?>">

<div class="body">
  <?php include dirname(__DIR__) . '/includes/navigation.php'; ?>

  <main class="main-content" id="main-content" tabindex="-1">

    <div class="dashboard-page-header">
      <h1 class="dashboard-page-title">Content</h1>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="alert success-message" id="flashSuccess">
        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#check-icon" />
        </svg>
        <?= htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <div class="tab-strip" role="tablist" aria-label="Content sections" data-tab-panels="contentTabPanels">
      <button type="button" role="tab" id="tab-announcements" data-tab="announcements"
        data-tab-href="<?= ROOT ?>/personnel/announcements"
        aria-selected="<?= $activeTab === 'announcements' ? 'true' : 'false' ?>"
        aria-controls="panel-announcements">Announcements</button>
      <button type="button" role="tab" id="tab-site_content" data-tab="site_content"
        data-tab-href="<?= ROOT ?>/personnel/site_content"
        aria-selected="<?= $activeTab === 'site_content' ? 'true' : 'false' ?>"
        aria-controls="panel-site_content">Site Content</button>
    </div>

    <div id="contentTabPanels">

      <!-- ===== ANNOUNCEMENTS PANEL ===== -->
      <div class="tab-panel" id="panel-announcements" role="tabpanel" aria-labelledby="tab-announcements"
        data-tab-panel="announcements" <?= $activeTab === 'announcements' ? '' : 'hidden' ?>>

        <div class="dashboard-page-header records-page-header">
          <div>
            <h2>Manage Announcements</h2>
          </div>
          <button class="row-btn row-btn-primary" id="addAnnouncementBtn" type="button">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <use href="#add-icon">
            </svg>
            Add Announcement
          </button>
        </div>

        <div class="records-table-wrap">
          <?php if (empty($announcements)): ?>
            <p style="padding: 1.5rem;">No announcements yet.</p>
          <?php else: ?>
            <div class="ann-list">
              <?php foreach ($announcements as $a):
                $annTitle = normalize_pasted_text($a['title']);
                $annBody  = normalize_pasted_text($a['body']);
                $hasImage = !empty($a['image_path']);
              ?>
                <div class="ann-row">
                  <div class="ann-row-thumb">
                    <?php if ($hasImage): ?>
                      <img src="<?= ROOT . '/assets/uploads/announcements/' . rawurlencode($a['image_path']) ?>" alt="">
                    <?php else: ?>
                      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#file-x-icon">
                      </svg>
                    <?php endif; ?>
                  </div>
                  <div class="ann-row-body">
                    <?php if ($annTitle !== ''): ?>
                      <div class="ann-row-title"><?= htmlspecialchars($annTitle, ENT_QUOTES) ?></div>
                    <?php else: ?>
                      <div class="ann-row-title ann-empty-state">Untitled</div>
                    <?php endif; ?>

                    <?php if ($annBody !== ''): ?>
                      <div class="ann-row-snippet"><?= htmlspecialchars($annBody, ENT_QUOTES) ?></div>
                    <?php else: ?>
                      <div class="ann-row-snippet ann-empty-state">No caption</div>
                    <?php endif; ?>

                    <?php $annRowTs = strtotime($a['created_at']); ?>
                    <div class="ann-row-date"><?= $annRowTs ? htmlspecialchars(date('M j, Y g:i A', $annRowTs), ENT_QUOTES) : htmlspecialchars($a['created_at'], ENT_QUOTES) ?></div>
                  </div>
                  <div class="ann-row-actions">
                    <button type="button" class="row-btn edit-announcement-btn" data-id="<?= (int) $a['id'] ?>" aria-label="Edit announcement">
                      <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#edit-icon">
                      </svg>
                    </button>
                    <button type="button" class="row-btn delete-announcement-btn" data-id="<?= (int) $a['id'] ?>" data-title="<?= htmlspecialchars($annTitle, ENT_QUOTES) ?>" aria-label="Delete announcement">
                      <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#trash-icon">
                      </svg>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ===== SITE CONTENT PANEL ===== -->
      <div class="tab-panel" id="panel-site_content" role="tabpanel" aria-labelledby="tab-site_content"
        data-tab-panel="site_content" <?= $activeTab === 'site_content' ? '' : 'hidden' ?>>

        <div class="sections">
          <!-- HOMEPAGE CONTENT -->
          <section class="accounts-card">
            <h2>Homepage Content</h2>
            <p class="audit-description">Controls the banner title and the first two "What is IACUC?" paragraphs on the public homepage.</p>
            <form method="POST" action="<?= ROOT ?>/personnel/site_content_settings"
              data-confirm-message="Save changes to the homepage content?" data-confirm-ok-text="Save">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
              <div class="records-form-grid" style="margin-bottom: 1.25rem;">
                <div class="records-form-group records-form-full">
                  <label for="banner_title">Banner Title</label>
                  <input type="text" id="banner_title" name="banner_title"
                    value="<?= htmlspecialchars($settings['banner_title'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="records-form-group records-form-full">
                  <label for="about_paragraph_1">About IACUC &ndash; Paragraph 1</label>
                  <textarea id="about_paragraph_1" name="about_paragraph_1" rows="5"><?= htmlspecialchars($settings['about_paragraph_1'] ?? '', ENT_QUOTES) ?></textarea>
                </div>
                <div class="records-form-group records-form-full">
                  <label for="about_paragraph_2">About IACUC &ndash; Paragraph 2</label>
                  <textarea id="about_paragraph_2" name="about_paragraph_2" rows="3"><?= htmlspecialchars($settings['about_paragraph_2'] ?? '', ENT_QUOTES) ?></textarea>
                </div>
              </div>
              <button type="submit" class="accounts-btn-primary">Save Changes</button>
            </form>
          </section>
          <!-- CONTACT OFFICES -->
          <section class="accounts-card">
            <div class="dashboard-page-header records-page-header">
              <div>
                <h2>Contact Page Offices</h2>
                <p class="audit-description">These cards appear on the public Contact page.</p>
              </div>
              <button class="row-btn row-btn-primary" id="addOfficeBtn" type="button">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <use href="#add-icon">
                </svg>
                Add Office
              </button>
            </div>
            <div class="records-table-wrap">
              <?php if (empty($offices)): ?>
                <p style="padding: 1.5rem;">No offices yet.</p>
              <?php else: ?>
                <div class="ann-list">
                  <?php foreach ($offices as $o): ?>
                    <div class="ann-row">
                      <div class="ann-row-body">
                        <div class="ann-row-title"><?= htmlspecialchars($o['name'], ENT_QUOTES) ?></div>
                        <div class="ann-row-snippet"><?= htmlspecialchars($o['address'] ?? '', ENT_QUOTES) ?></div>
                      </div>
                      <div class="ann-row-actions">
                        <button type="button" class="row-btn edit-office-btn" data-id="<?= (int) $o['id'] ?>" aria-label="Edit office">
                          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#edit-icon">
                          </svg>
                        </button>
                        <button type="button" class="row-btn delete-office-btn" data-id="<?= (int) $o['id'] ?>" data-name="<?= htmlspecialchars($o['name'], ENT_QUOTES) ?>" aria-label="Delete office">
                          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#trash-icon">
                          </svg>
                        </button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </section>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- ===== ADD ANNOUNCEMENT MODAL ===== -->
<div class="modal-backdrop" id="addAnnouncementModal" role="dialog" aria-modal="true" aria-labelledby="addAnnouncementModalTitle">
  <div class="modal-card records-modal-card">
    <div class="records-modal-header">
      <h2 id="addAnnouncementModalTitle">Add Announcement</h2>
      <button type="button" class="records-modal-close" data-close="addAnnouncementModal" aria-label="Close">✕</button>
    </div>
    <div class="records-modal-body">
      <div class="alert error-messages" id="addAnnouncementError" hidden></div>
      <div class="records-form-grid">
        <div class="records-form-group records-form-full">
          <label for="add_ann_title">Title (optional)</label>
          <input type="text" id="add_ann_title" name="title" placeholder="e.g. IACUC Protocol Orientation">
        </div>
        <div class="records-form-group records-form-full">
          <label for="add_ann_body">Content (optional)</label>
          <textarea id="add_ann_body" name="body" rows="5"></textarea>
        </div>
        <div class="records-form-group records-form-full">
          <label for="add_ann_image">Image (optional)</label>
          <input type="file" id="add_ann_image" name="image" accept="image/png,image/jpeg,image/webp,image/gif">
          <div class="ann-image-preview-wrap" id="add_ann_image_preview_wrap" hidden>
            <img id="add_ann_image_preview" src="" alt="Selected image preview">
            <button type="button" class="image-zoom-btn" id="add_ann_image_zoom_btn" title="Zoom image" aria-label="Zoom image">
              <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#search-icon" />
              </svg>
            </button>
          </div>
          <div class="upload-progress-container" id="addAnnImageProgress"></div>
        </div>
      </div>
    </div>
    <div class="records-modal-footer">
      <button type="button" class="row-btn" data-close="addAnnouncementModal">Cancel</button>
      <button type="button" class="row-btn row-btn-primary" id="addAnnouncementSave">Post Announcement</button>
    </div>
  </div>
</div>

<!-- ===== EDIT ANNOUNCEMENT MODAL ===== -->
<div class="modal-backdrop" id="editAnnouncementModal" role="dialog" aria-modal="true" aria-labelledby="editAnnouncementModalTitle">
  <div class="modal-card records-modal-card">
    <div class="records-modal-header">
      <h2 id="editAnnouncementModalTitle">Edit Announcement</h2>
      <button type="button" class="records-modal-close" data-close="editAnnouncementModal" aria-label="Close">✕</button>
    </div>
    <div class="records-modal-body">
      <div class="alert error-messages" id="editAnnouncementError" hidden></div>
      <div class="records-form-grid">
        <input type="hidden" id="edit_ann_id">
        <div class="records-form-group records-form-full">
          <label for="edit_ann_title">Title (optional)</label>
          <input type="text" id="edit_ann_title" name="title">
        </div>
        <div class="records-form-group records-form-full">
          <label for="edit_ann_body">Content (optional)</label>
          <textarea id="edit_ann_body" name="body" rows="5"></textarea>
        </div>
        <div class="records-form-group records-form-full">
          <label for="edit_ann_image">Replace image (optional)</label>
          <input type="file" id="edit_ann_image" name="image" accept="image/png,image/jpeg,image/webp,image/gif">
          <div class="ann-image-preview-wrap" id="edit_ann_image_preview_wrap" hidden>
            <img id="edit_ann_image_preview" src="" alt="Announcement image preview">
            <button type="button" class="image-zoom-btn" id="edit_ann_image_zoom_btn" title="Zoom image" aria-label="Zoom image">
              <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#search-icon" />
              </svg>
            </button>
          </div>
          <div class="ann-remove-image-row" id="edit_ann_remove_row" hidden>
            <input type="checkbox" id="edit_ann_remove_image">
            <label for="edit_ann_remove_image">Remove current image</label>
          </div>
          <div class="upload-progress-container" id="editAnnImageProgress"></div>
        </div>

      </div>
    </div>
    <div class="records-modal-footer">
      <button type="button" class="row-btn" data-close="editAnnouncementModal">Cancel</button>
      <button type="button" class="row-btn row-btn-primary" id="editAnnouncementSave">Save Changes</button>
    </div>
  </div>
</div>

<!-- ===== ADD / EDIT OFFICE MODAL (shared) ===== -->
<div class="modal-backdrop" id="officeModal" role="dialog" aria-modal="true" aria-labelledby="officeModalTitle">
  <div class="modal-card records-modal-card">
    <div class="records-modal-header">
      <h2 id="officeModalTitle">Add Office</h2>
      <button type="button" class="records-modal-close" data-close="officeModal" aria-label="Close">✕</button>
    </div>
    <div class="records-modal-body">
      <div class="alert error-messages" id="officeModalError" hidden></div>
      <input type="hidden" id="office_id" value="">
      <div class="records-form-grid">
        <div class="records-form-group records-form-full">
          <label for="office_name">Office Name *</label>
          <input type="text" id="office_name" placeholder="e.g. Bureau of Animal Industry">
        </div>
        <div class="records-form-group records-form-full">
          <label for="office_address">Address</label>
          <textarea id="office_address" rows="2"></textarea>
        </div>
        <div class="records-form-group records-form-full">
          <label for="office_phone">Phone(s) &ndash; one per line if more than one</label>
          <textarea id="office_phone" rows="2"></textarea>
        </div>
        <div class="records-form-group records-form-full">
          <label for="office_email">Email(s) &ndash; one per line if more than one</label>
          <textarea id="office_email" rows="2"></textarea>
        </div>
        <div class="records-form-group records-form-full">
          <label for="office_facebook_url">Facebook URL</label>
          <input type="url" id="office_facebook_url" placeholder="https://www.facebook.com/...">
        </div>
        <div class="records-form-group records-form-full">
          <label for="office_facebook_label">Facebook Link Text</label>
          <input type="text" id="office_facebook_label" placeholder="e.g. BSU - CCARD">
        </div>
        <div class="records-form-group records-form-full">
          <label for="office_director_name">Contact Person Name (optional)</label>
          <input type="text" id="office_director_name">
        </div>
        <div class="records-form-group records-form-full">
          <label for="office_director_role">Contact Person Role (optional)</label>
          <input type="text" id="office_director_role" placeholder="e.g. Director, BSU-CCARD">
        </div>
        <div class="records-form-group records-form-full">
          <label for="office_director_email">Contact Person Email (optional)</label>
          <input type="email" id="office_director_email">
        </div>
      </div>
    </div>
    <div class="records-modal-footer">
      <button type="button" class="row-btn" data-close="officeModal">Cancel</button>
      <button type="button" class="row-btn row-btn-primary" id="officeModalSave">Save Office</button>
    </div>
  </div>
</div>

<script src="<?= asset_js('tabs.js') ?>" defer></script>

<script>
  (function() {
    const ROOT = '<?= ROOT ?>';
    const CSRF = '<?= htmlspecialchars($csrf, ENT_QUOTES) ?>';

    // ===== Modal helpers (same pattern as personnel/records.view.php) =====
    function openModal(id) {
      const modal = document.getElementById(id);
      modal.classList.add('open');
      const focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (focusable) focusable.focus();
    }

    function closeModal(id) {
      document.getElementById(id).classList.remove('open');
    }

    document.querySelectorAll('#addAnnouncementModal [data-close], #editAnnouncementModal [data-close]').forEach(btn => {
      btn.addEventListener('click', () => closeModal(btn.dataset.close));
    });

    function post(url, body, onProgress) {
      body.csrf_token = CSRF;
      const fd = new FormData();
      Object.entries(body).forEach(([k, v]) => {
        if (v === undefined || v === null) return;
        fd.append(k, v);
      });
      // Real upload progress needs XHR (fetch can't report it); only worth
      // the XHR path when there's actually a file attached to track.
      if (onProgress) {
        return uploadWithProgress(ROOT + url, fd, {
          onProgress
        });
      }
      return fetch(ROOT + url, {
          method: 'POST',
          body: fd
        })
        .then(r => r.json());
    }

    function showErr(id, msg) {
      const el = document.getElementById(id);
      if (!el) return;
      el.textContent = msg;
      el.hidden = false;
    }

    function hideErr(id) {
      const el = document.getElementById(id);
      if (el) {
        el.hidden = true;
        el.textContent = '';
      }
    }

    function setPreviewImage(imgId, zoomBtnId, src) {
      document.getElementById(imgId).src = src;
      const zoomBtn = document.getElementById(zoomBtnId);
      if (zoomBtn) zoomBtn.dataset.zoomSrc = src;
    }

    function wireImagePreview(inputId, wrapId, imgId, zoomBtnId) {
      const input = document.getElementById(inputId);
      if (!input) return;
      input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        const wrap = document.getElementById(wrapId);
        if (!file) {
          wrap.hidden = true;
          return;
        }
        const reader = new FileReader();
        reader.onload = e => {
          setPreviewImage(imgId, zoomBtnId, e.target.result);
          wrap.hidden = false;
        };
        reader.readAsDataURL(file);
      });
    }

    wireImagePreview('add_ann_image', 'add_ann_image_preview_wrap', 'add_ann_image_preview', 'add_ann_image_zoom_btn');
    wireImagePreview('edit_ann_image', 'edit_ann_image_preview_wrap', 'edit_ann_image_preview', 'edit_ann_image_zoom_btn');

    // ===== ADD =====
    const addBtn = document.getElementById('addAnnouncementBtn');
    if (addBtn) {
      addBtn.addEventListener('click', () => {
        hideErr('addAnnouncementError');
        document.getElementById('add_ann_title').value = '';
        document.getElementById('add_ann_body').value = '';
        document.getElementById('add_ann_image').value = '';
        document.getElementById('add_ann_image_preview_wrap').hidden = true;
        openModal('addAnnouncementModal');
      });
    }

    const addSave = document.getElementById('addAnnouncementSave');
    if (addSave) {
      addSave.addEventListener('click', async () => {
        hideErr('addAnnouncementError');
        const title = document.getElementById('add_ann_title').value.trim();
        const body = document.getElementById('add_ann_body').value.trim();
        const imageFile = document.getElementById('add_ann_image').files[0];
        if (!body && !imageFile) {
          showErr('addAnnouncementError', 'Add either content or an image.');
          return;
        }

        const confirmed = await confirmAction(
          'Post this announcement?', {
            okText: 'Post',
            cancelText: 'Cancel'
          }
        );
        if (!confirmed) return;

        setButtonBusy(addSave, true, imageFile ? 'Uploading...' : 'Posting...');

        const addProgressContainer = document.getElementById('addAnnImageProgress');
        addProgressContainer.innerHTML = '';
        const addBar = imageFile ? createUploadProgressBar(addProgressContainer) : null;

        post('/personnel/announcements_add', {
          title,
          body,
          image: imageFile
        }, addBar ? (pct => addBar.update(pct)) : undefined).then(data => {
          if (data.ok) {
            closeModal('addAnnouncementModal');
            sessionStorage.setItem('announcements_flash', 'Announcement added.');
            location.reload();
          } else {
            setButtonBusy(addSave, false);
            if (addBar) addBar.remove();
            showErr('addAnnouncementError', data.message || 'Add failed.');
          }
        }).catch(err => {
          setButtonBusy(addSave, false);
          if (addBar) addBar.remove();
          showErr('addAnnouncementError', err.message || 'Network error. Please try again.');
        });
      });
    }

    // ===== EDIT =====
    document.querySelectorAll('.edit-announcement-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        hideErr('editAnnouncementError');
        const id = btn.dataset.id;
        fetch(ROOT + '/personnel/announcements_get?id=' + encodeURIComponent(id))
          .then(r => r.json())
          .then(data => {
            if (!data.ok) {
              alert(data.message || 'Could not load announcement.');
              return;
            }
            document.getElementById('edit_ann_id').value = data.data.id;
            document.getElementById('edit_ann_title').value = data.data.title ?? '';
            document.getElementById('edit_ann_body').value = data.data.body ?? '';

            document.getElementById('edit_ann_image').value = '';
            const previewWrap = document.getElementById('edit_ann_image_preview_wrap');
            const removeRow = document.getElementById('edit_ann_remove_row');
            const removeCheckbox = document.getElementById('edit_ann_remove_image');
            removeCheckbox.checked = false;
            previewWrap.style.opacity = '';
            if (data.data.image_path) {
              setPreviewImage('edit_ann_image_preview', 'edit_ann_image_zoom_btn',
                ROOT + '/assets/uploads/announcements/' + encodeURIComponent(data.data.image_path));
              previewWrap.hidden = false;
              removeRow.hidden = false;
            } else {
              previewWrap.hidden = true;
              removeRow.hidden = true;
            }

            openModal('editAnnouncementModal');
          })
          .catch(() => alert('Network error. Please try again.'));
      });
    });

    const editSave = document.getElementById('editAnnouncementSave');
    if (editSave) {
      editSave.addEventListener('click', async () => {
        hideErr('editAnnouncementError');
        const title = document.getElementById('edit_ann_title').value.trim();
        const body = document.getElementById('edit_ann_body').value.trim();
        const imageFile = document.getElementById('edit_ann_image').files[0];
        const removeImage = document.getElementById('edit_ann_remove_image').checked;
        const previewWrap = document.getElementById('edit_ann_image_preview_wrap');
        const keepingExistingImage = !previewWrap.hidden && !removeImage;
        if (!body && !imageFile && !keepingExistingImage) {
          showErr('editAnnouncementError', 'Add either content or an image.');
          return;
        }

        const confirmed = await confirmAction(
          'Save changes to this announcement?', {
            okText: 'Save',
            cancelText: 'Cancel'
          }
        );
        if (!confirmed) return;

        setButtonBusy(editSave, true, imageFile ? 'Uploading...' : 'Saving...');

        const editProgressContainer = document.getElementById('editAnnImageProgress');
        editProgressContainer.innerHTML = '';
        const editBar = imageFile ? createUploadProgressBar(editProgressContainer) : null;

        post('/personnel/announcements_edit', {
          id: document.getElementById('edit_ann_id').value,
          title,
          body,
          image: imageFile,
          remove_image: removeImage ? '1' : '0'
        }, editBar ? (pct => editBar.update(pct)) : undefined).then(data => {
          if (data.ok) {
            closeModal('editAnnouncementModal');
            sessionStorage.setItem('announcements_flash', 'Announcement updated.');
            location.reload();
          } else {
            setButtonBusy(editSave, false);
            if (editBar) editBar.remove();
            showErr('editAnnouncementError', data.message || 'Update failed.');
          }
        }).catch(err => {
          setButtonBusy(editSave, false);
          if (editBar) editBar.remove();
          showErr('editAnnouncementError', err.message || 'Network error. Please try again.');
        });
      });
    }

    // ===== DELETE =====
    document.querySelectorAll('.delete-announcement-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const title = btn.dataset.title || '#' + btn.dataset.id;
        const confirmed = await confirmAction(
          'Delete "' + title + '"? This cannot be undone.', {
            okText: 'Delete',
            cancelText: 'Cancel',
            danger: true
          }
        );
        if (!confirmed) return;

        setButtonBusy(btn, true, 'Deleting...');

        post('/personnel/announcements_delete', {
          id: btn.dataset.id
        }).then(data => {
          if (data.ok) {
            sessionStorage.setItem('announcements_flash', 'Announcement deleted.');
            location.reload();
          } else {
            setButtonBusy(btn, false);
            alert(data.message || 'Delete failed.');
          }
        }).catch(() => {
          setButtonBusy(btn, false);
          alert('Network error. Please try again.');
        });
      });
    });

    // ===== sessionStorage flash (after reload) =====
    const pendingFlash = sessionStorage.getItem('announcements_flash');
    if (pendingFlash) {
      sessionStorage.removeItem('announcements_flash');
      const flash = document.createElement('div');
      flash.className = 'alert success-message';
      flash.textContent = pendingFlash;
      const main = document.getElementById('main-content');
      main.insertBefore(flash, main.firstChild);
      setTimeout(() => flash.remove(), 4000);
    }
  })();
</script>

<script>
  (function() {
    const ROOT = '<?= ROOT ?>';
    const CSRF = '<?= htmlspecialchars($csrf, ENT_QUOTES) ?>';

    // ===== Modal helpers (same pattern as personnel/personnel-announcements.view.php) =====
    function openModal(id) {
      const modal = document.getElementById(id);
      modal.classList.add('open');
      const focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (focusable) focusable.focus();
    }

    function closeModal(id) {
      document.getElementById(id).classList.remove('open');
    }

    document.querySelectorAll('#officeModal [data-close]').forEach(btn => {
      btn.addEventListener('click', () => closeModal(btn.dataset.close));
    });

    function post(url, body) {
      body.csrf_token = CSRF;
      const fd = new FormData();
      Object.entries(body).forEach(([k, v]) => {
        if (v === undefined || v === null) return;
        fd.append(k, v);
      });
      return fetch(ROOT + url, {
          method: 'POST',
          body: fd
        })
        .then(r => r.json());
    }

    function showErr(msg) {
      const el = document.getElementById('officeModalError');
      el.textContent = msg;
      el.hidden = false;
    }

    function hideErr() {
      const el = document.getElementById('officeModalError');
      el.hidden = true;
      el.textContent = '';
    }

    function fillOfficeForm(data) {
      document.getElementById('office_id').value = data.id ?? '';
      document.getElementById('office_name').value = data.name ?? '';
      document.getElementById('office_address').value = data.address ?? '';
      document.getElementById('office_phone').value = data.phone ?? '';
      document.getElementById('office_email').value = data.email ?? '';
      document.getElementById('office_facebook_url').value = data.facebook_url ?? '';
      document.getElementById('office_facebook_label').value = data.facebook_label ?? '';
      document.getElementById('office_director_name').value = data.director_name ?? '';
      document.getElementById('office_director_role').value = data.director_role ?? '';
      document.getElementById('office_director_email').value = data.director_email ?? '';
    }

    // ===== ADD =====
    const addBtn = document.getElementById('addOfficeBtn');
    if (addBtn) {
      addBtn.addEventListener('click', () => {
        hideErr();
        fillOfficeForm({});
        document.getElementById('officeModalTitle').textContent = 'Add Office';
        openModal('officeModal');
      });
    }

    // ===== EDIT =====
    document.querySelectorAll('.edit-office-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        hideErr();
        const id = btn.dataset.id;
        const res = await fetch(ROOT + '/personnel/site_content_office_get?id=' + id).then(r => r.json());
        if (!res.ok) {
          alert(res.message || 'Could not load office.');
          return;
        }
        fillOfficeForm(res.data);
        document.getElementById('officeModalTitle').textContent = 'Edit Office';
        openModal('officeModal');
      });
    });

    // ===== SAVE (add or edit, depending on office_id) =====
    const saveBtn = document.getElementById('officeModalSave');
    if (saveBtn) {
      saveBtn.addEventListener('click', async () => {
        hideErr();
        const id = document.getElementById('office_id').value;
        const name = document.getElementById('office_name').value.trim();
        if (!name) {
          showErr('Office name is required.');
          return;
        }

        const confirmed = await confirmAction(
          id ? 'Save changes to this office?' : 'Add this office to the Contact page?', {
            okText: 'Save',
            cancelText: 'Cancel'
          }
        );
        if (!confirmed) return;

        setButtonBusy(saveBtn, true, 'Saving...');

        const payload = {
          name,
          address: document.getElementById('office_address').value.trim(),
          phone: document.getElementById('office_phone').value.trim(),
          email: document.getElementById('office_email').value.trim(),
          facebook_url: document.getElementById('office_facebook_url').value.trim(),
          facebook_label: document.getElementById('office_facebook_label').value.trim(),
          director_name: document.getElementById('office_director_name').value.trim(),
          director_role: document.getElementById('office_director_role').value.trim(),
          director_email: document.getElementById('office_director_email').value.trim(),
        };
        if (id) payload.id = id;

        post(id ? '/personnel/site_content_office_edit' : '/personnel/site_content_office_add', payload)
          .then(data => {
            if (data.ok) {
              closeModal('officeModal');
              location.reload();
            } else {
              setButtonBusy(saveBtn, false);
              showErr(data.message || 'Save failed.');
            }
          }).catch(err => {
            setButtonBusy(saveBtn, false);
            showErr(err.message || 'Network error. Please try again.');
          });
      });
    }

    // ===== DELETE =====
    document.querySelectorAll('.delete-office-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        const confirmed = await confirmAction(
          `Delete "${btn.dataset.name}"? This removes it from the public Contact page.`, {
            okText: 'Delete',
            cancelText: 'Cancel',
            danger: true
          }
        );
        if (!confirmed) return;

        post('/personnel/site_content_office_delete', {
          id
        }).then(data => {
          if (data.ok) {
            location.reload();
          } else {
            alert(data.message || 'Delete failed.');
          }
        });
      });
    });
  })();
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>