<?php

/** @var array  $user          */
/** @var string $role          */
/** @var string $csrf          */
/** @var array  $announcements */

$title = 'Manage Announcements';
include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/scroll-top.php';

$user          = $user          ?? $_SESSION['user'] ?? [];
$role          = $role          ?? $user['role'] ?? '';
$csrf          = $csrf          ?? '';
$announcements = $announcements ?? [];
$first_name    = $user['first_name'] ?? '';
?>

<link rel="stylesheet" href="<?= asset_css('personnel/personnel-home.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/records.css') ?>">
<link rel="stylesheet" href="<?= asset_css('announcements.css') ?>">

<div class="body">
    <?php include dirname(__DIR__) . '/includes/navigation.php'; ?>

    <main class="main-content" id="main-content" tabindex="-1">

        <div class="dashboard-page-header records-page-header">
            <div>
                <h1 class="dashboard-page-title">Manage Announcements</h1>
            </div>

            <?php if ($role === 'staff'): ?>
                <button class="row-btn row-btn-primary" id="addAnnouncementBtn" type="button">
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#add-icon">
                    </svg>
                    Add Announcement
                </button>
            <?php endif; ?>
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
                            <?php if ($role === 'staff'): ?>
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
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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

        document.querySelectorAll('[data-close]').forEach(btn => {
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

        const removeImageCheckbox = document.getElementById('edit_ann_remove_image');
        if (removeImageCheckbox) {
            removeImageCheckbox.addEventListener('change', () => {
                const previewWrap = document.getElementById('edit_ann_image_preview_wrap');
                previewWrap.style.opacity = removeImageCheckbox.checked ? '0.35' : '1';
            });
        }

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
                    'Post this announcement? It will immediately be visible on the public Announcements page.', {
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

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>