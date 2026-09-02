<?php
// ADDED by SPM - full rebuild of the Manage Announcements admin page.

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

<link rel="stylesheet" href="<?= asset_css('admin/admin-home.css') ?>">
<link rel="stylesheet" href="<?= asset_css('admin/records.css') ?>">
<link rel="stylesheet" href="<?= asset_css('announcements.css') ?>">

<div class="body">
    <?php include dirname(__DIR__) . '/includes/navigation.php'; ?>

    <main class="main-content" id="main-content" tabindex="-1">

        <div class="dashboard-page-header records-page-header">
            <div>
                <h1 class="dashboard-page-title">Manage Announcements</h1>
                <p>Posts added here appear under "From Our Office" on the public Announcements page. The "From Our Partner Pages" Facebook section is separate and updates automatically.</p>
            </div>

            <?php if ($role === 'admin'): ?>
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
                <table class="announcements-table">
                    <thead>
                        <tr>
                            <th class="col-ann-title">Title</th>
                            <th class="col-ann-content">Content</th>
                            <th class="col-ann-image">Image</th>
                            <th class="col-ann-posted">Posted</th>
                            <?php if ($role === 'admin'): ?>
                                <th class="col-ann-actions">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($announcements as $a): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['title'], ENT_QUOTES) ?></td>
                                <td>
                                    <span class="ann-body-clamp"><?= nl2br(htmlspecialchars($a['body'], ENT_QUOTES)) ?></span>
                                    <button type="button" class="see-more-btn">See more</button>
                                </td>
                                <td>
                                    <?php if (!empty($a['image_path'])): ?>
                                        <img class="announcement-thumb" src="<?= ROOT . '/assets/uploads/announcements/' . rawurlencode($a['image_path']) ?>" alt="">
                                    <?php else: ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($a['created_at'], ENT_QUOTES) ?></td>
                                <?php if ($role === 'admin'): ?>
                                    <td>
                                        <button type="button" class="row-btn edit-announcement-btn" data-id="<?= (int) $a['id'] ?>">Edit</button>
                                        <button type="button" class="row-btn delete-announcement-btn" data-id="<?= (int) $a['id'] ?>" data-title="<?= htmlspecialchars($a['title'], ENT_QUOTES) ?>">Delete</button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- ADDED by SPM - read-only preview of "From Our Partner Pages" so admin
             can see it here for reference.  -->
        <div id="fb-root"></div>
        <script async defer crossorigin="anonymous"
            src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v19.0&appId=1366404755375168">
        </script>

        <section class="fb-cards" style="margin-top: 2rem;">
            <h2>From Our Partner Pages</h2>
            <p class="announcements-subtitle">Preview only — updates automatically from Facebook, not managed here.</p>

            <div class="fb-pages-grid">
                <!-- BSU Research Services FB (Bsu Ors) -->
                <section class="fb-page-section">
                    <div class="fb-page-label">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="var(--green)" aria-hidden="true">
                            <path d="M24 12.073C24 5.406 18.627 0 12 0S0 5.406 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.235 2.686.235v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.27h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z" />
                        </svg>
                        Office of the Vice President for Research and Extension - BSU
                    </div>
                    <div class="fb-embed-wrap">
                        <div class="fb-page"
                            data-href="https://www.facebook.com/bsuovpre"
                            data-tabs="timeline"
                            data-width=""
                            data-height="620"
                            data-small-header="false"
                            data-adapt-container-width="true"
                            data-hide-cover="false"
                            data-show-facepile="true">
                        </div>
                    </div>
                </section>
                <!-- BSU CCARD FB -->
                <section class="fb-page-section">
                    <div class="fb-page-label">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="var(--green)" aria-hidden="true">
                            <path d="M24 12.073C24 5.406 18.627 0 12 0S0 5.406 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.235 2.686.235v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.27h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z" />
                        </svg>
                        BSU - Cordillera Center for Animal Research &amp; Development
                    </div>
                    <div class="fb-embed-wrap">
                        <div class="fb-page"
                            data-href="https://www.facebook.com/p/BSU-Cordillera-Center-for-Animal-Research-Development-100083273710247/"
                            data-tabs="timeline"
                            data-width=""
                            data-height="600"
                            data-small-header="false"
                            data-adapt-container-width="true"
                            data-hide-cover="false"
                            data-show-facepile="true">
                        </div>
                    </div>
                </section>
            </div>
        </section>
        <!-- END ADDED -->

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
                    <label for="add_ann_title">Title</label>
                    <input type="text" id="add_ann_title" name="title" placeholder="e.g. Office Closure Notice">
                </div>
                <div class="records-form-group records-form-full">
                    <label for="add_ann_body">Content</label>
                    <textarea id="add_ann_body" name="body" rows="5"></textarea>
                </div>
                <!-- ADDED by SPM - optional image upload for the announcement -->
                <div class="records-form-group records-form-full">
                    <label for="add_ann_image">Image (optional)</label>
                    <input type="file" id="add_ann_image" name="image" accept="image/png,image/jpeg,image/webp,image/gif">
                    <div class="ann-image-preview-wrap" id="add_ann_image_preview_wrap" hidden>
                        <img id="add_ann_image_preview" src="" alt="Selected image preview">
                    </div>
                </div>
                <!-- END ADDED -->
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
                    <label for="edit_ann_title">Title</label>
                    <input type="text" id="edit_ann_title" name="title">
                </div>
                <div class="records-form-group records-form-full">
                    <label for="edit_ann_body">Content</label>
                    <textarea id="edit_ann_body" name="body" rows="5"></textarea>
                </div>
                <!-- ADDED by SPM - replace/remove the announcement's image -->
                <div class="records-form-group records-form-full">
                    <label for="edit_ann_image">Replace image (optional)</label>
                    <input type="file" id="edit_ann_image" name="image" accept="image/png,image/jpeg,image/webp,image/gif">
                    <div class="ann-image-preview-wrap" id="edit_ann_image_preview_wrap" hidden>
                        <img id="edit_ann_image_preview" src="" alt="Announcement image preview">
                    </div>
                    <div class="ann-remove-image-row" id="edit_ann_remove_row" hidden>
                        <input type="checkbox" id="edit_ann_remove_image">
                        <label for="edit_ann_remove_image" style="margin:0; text-transform:none; letter-spacing:normal; font-weight:400;">Remove current image</label>
                    </div>
                </div>
                <!-- END ADDED -->
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

        // ===== Modal helpers (same pattern as admin/records.view.php) =====
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

        function post(url, body) {
            body.csrf_token = CSRF;
            const fd = new FormData();
            Object.entries(body).forEach(([k, v]) => {
                if (v === undefined || v === null) return;
                fd.append(k, v); // works for strings AND File objects
            });
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

        // ADDED by SPM- shows a live preview of the chosen file before upload
        function wireImagePreview(inputId, wrapId, imgId) {
            const input = document.getElementById(inputId);
            if (!input) return;
            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                const wrap = document.getElementById(wrapId);
                const img = document.getElementById(imgId);
                if (!file) {
                    wrap.hidden = true;
                    return;
                }
                const reader = new FileReader();
                reader.onload = e => {
                    img.src = e.target.result;
                    wrap.hidden = false;
                };
                reader.readAsDataURL(file);
            });
        }
        wireImagePreview('add_ann_image', 'add_ann_image_preview_wrap', 'add_ann_image_preview');
        wireImagePreview('edit_ann_image', 'edit_ann_image_preview_wrap', 'edit_ann_image_preview');
        // END ADDED

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
                if (!title) {
                    showErr('addAnnouncementError', 'Title is required.');
                    return;
                }
                if (!body) {
                    showErr('addAnnouncementError', 'Content is required.');
                    return;
                }

                closeModal('addAnnouncementModal');
                const confirmed = await confirmAction(
                    'Post this announcement? It will immediately be visible on the public Announcements page.', {
                        okText: 'Post',
                        cancelText: 'Cancel'
                    }
                );
                if (!confirmed) {
                    openModal('addAnnouncementModal');
                    return;
                }

                const imageFile = document.getElementById('add_ann_image').files[0]; 
                post('/admin/announcements_add', {
                    title,
                    body,
                    image: imageFile 
                }).then(data => {
                    if (data.ok) {
                        closeModal('addAnnouncementModal');
                        sessionStorage.setItem('announcements_flash', 'Announcement added.');
                        location.reload();
                    } else {
                        showErr('addAnnouncementError', data.message || 'Add failed.');
                    }
                }).catch(() => showErr('addAnnouncementError', 'Network error. Please try again.'));
            });
        }

        // ===== EDIT =====
        document.querySelectorAll('.edit-announcement-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                hideErr('editAnnouncementError');
                const id = btn.dataset.id;
                fetch(ROOT + '/admin/announcements_get?id=' + encodeURIComponent(id))
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
                        const preview = document.getElementById('edit_ann_image_preview');
                        const removeRow = document.getElementById('edit_ann_remove_row');
                        const removeCheckbox = document.getElementById('edit_ann_remove_image');
                        removeCheckbox.checked = false;
                        if (data.data.image_path) {
                            preview.src = ROOT + '/assets/uploads/announcements/' + encodeURIComponent(data.data.image_path);
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
                if (!title) {
                    showErr('editAnnouncementError', 'Title is required.');
                    return;
                }
                if (!body) {
                    showErr('editAnnouncementError', 'Content is required.');
                    return;
                }

                closeModal('editAnnouncementModal');
                const confirmed = await confirmAction(
                    'Save changes to this announcement?', {
                        okText: 'Save',
                        cancelText: 'Cancel'
                    }
                );
                if (!confirmed) {
                    openModal('editAnnouncementModal');
                    return;
                }

                const imageFile = document.getElementById('edit_ann_image').files[0]; 
                const removeImage = document.getElementById('edit_ann_remove_image').checked; 

                post('/admin/announcements_edit', {
                    id: document.getElementById('edit_ann_id').value,
                    title,
                    body,
                    image: imageFile, 
                    remove_image: removeImage ? '1' : '0' 
                }).then(data => {
                    if (data.ok) {
                        closeModal('editAnnouncementModal');
                        sessionStorage.setItem('announcements_flash', 'Announcement updated.');
                        location.reload();
                    } else {
                        showErr('editAnnouncementError', data.message || 'Update failed.');
                    }
                }).catch(() => showErr('editAnnouncementError', 'Network error. Please try again.'));
            });
        }

        // ===== DELETE =====
        document.querySelectorAll('.delete-announcement-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const title = btn.dataset.title || '#' + btn.dataset.id;
                // confirmAction() is a global helper from portal/assets/js/modals.js
                const confirmed = await confirmAction(
                    'Delete "' + title + '"? This cannot be undone.', {
                        okText: 'Delete',
                        cancelText: 'Cancel',
                        danger: true
                    }
                );
                if (!confirmed) return;

                post('/admin/announcements_delete', {
                    id: btn.dataset.id
                }).then(data => {
                    if (data.ok) {
                        sessionStorage.setItem('announcements_flash', 'Announcement deleted.');
                        location.reload();
                    } else {
                        alert(data.message || 'Delete failed.');
                    }
                }).catch(() => alert('Network error. Please try again.'));
            });
        });

        // ADDED by SPM - "See more" toggle for long announcement text in the table
        document.querySelectorAll('.see-more-btn').forEach(btn => {
            const clamp = btn.previousElementSibling;
            if (!clamp) return;
            // Only show the toggle if the text actually overflows 3 lines
            requestAnimationFrame(() => {
                if (clamp.scrollHeight <= clamp.clientHeight + 2) {
                    btn.hidden = true;
                }
            });
            btn.addEventListener('click', () => {
                const expanded = clamp.classList.toggle('expanded');
                btn.textContent = expanded ? 'See less' : 'See more';
            });
        });
        // END ADDED

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
<!-- END ADDED -->