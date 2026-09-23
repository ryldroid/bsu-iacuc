<?php

/** @var array  $user     */
/** @var string $csrf     */
/** @var array  $settings */
/** @var array  $offices  */

$title = 'Site Content';
include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/scroll-top.php';

$user     = $user     ?? $_SESSION['user'] ?? [];
$csrf     = $csrf     ?? '';
$settings = $settings ?? [];
$offices  = $offices  ?? [];
?>

<link rel="stylesheet" href="<?= asset_css('personnel/personnel-base.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/personnel-home.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/accounts.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/records.css') ?>">
<link rel="stylesheet" href="<?= asset_css('announcements.css') ?>">

<div class="body">
  <?php include dirname(__DIR__) . '/includes/navigation.php'; ?>

  <main class="main-content" id="main-content" tabindex="-1">

    <div class="dashboard-page-header">
      <h1 class="dashboard-page-title">Site Content</h1>
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
          <!-- <p class="audit-description">The third paragraph (with the Republic Act links) isn't editable here since it's a legal citation, not general content.</p> -->
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
  </main>
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

    document.querySelectorAll('[data-close]').forEach(btn => {
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