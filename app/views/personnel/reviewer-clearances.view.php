<?php

/** @var array  $user */
/** @var string $csrf */

$title = 'Upload Clearances';
include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/scroll-top.php';

$user = $user ?? $_SESSION['user'] ?? [];
$csrf = $csrf ?? '';

?>

<link rel="stylesheet" href="<?= asset_css('personnel/personnel-home.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/reviewer-clearances.css') ?>">

<div class="body">
  <?php include dirname(__DIR__) . '/includes/navigation.php'; ?>

  <main class="main-content" id="main-content" tabindex="-1">

    <div class="dashboard-page-header">
      <h1 class="dashboard-page-title">Upload Clearances</h1>
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

    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert error-messages" id="flashError">
        <?= htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <section class="clearance-upload-card">
      <h2>Upload Clearances</h2>
      <p class="modal-notice">Upload the released Animal Research Clearances here. Administrative staff will sort and release them to the researchers.</p>

      <div id="clearanceScreenshotError" class="alert error-messages" hidden></div>

      <div class="modal-file-row">
        <div class="modal-file-info">
          <div class="modal-file-title">Screenshots <span class="required-asterisk">*</span></div>
          <div class="modal-file-subtitle" id="clearanceScreenshotFileSubtitle">Image, you can select several at once &middot; max 10 MB each</div>
        </div>
        <label class="modal-file-picker">
          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#upload-icon" />
          </svg>
          <span id="clearanceScreenshotFilePickerLabel">Upload</span>
          <input type="file" id="clearance_screenshots" name="clearance_screenshots[]" multiple
            accept=".jpg,.jpeg,.png,image/jpeg,image/png" required
            onchange="handleClearanceScreenshotFileChange(this)">
        </label>
      </div>

      <input type="file" id="clearance_screenshots_add" multiple
        accept=".jpg,.jpeg,.png,image/jpeg,image/png" hidden>

      <div class="modal-file-previews" id="clearanceScreenshotPreviews" hidden></div>

      <div class="upload-progress-container" id="clearanceScreenshotProgress"></div>

      <div class="modal-actions">
        <button class="button btn-apply" type="button" id="clearanceScreenshotSubmitBtn"
          onclick="submitClearanceScreenshots()">
          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#upload-icon" />
          </svg>
          Upload
        </button>
      </div>
    </section>

  </main>
</div>

<script>
  const CSRF_TOKEN = <?= json_encode($csrf) ?>;
  const ROOT_URL = <?= json_encode(ROOT) ?>;
  const CLEARANCE_POOL_UPLOAD_API = ROOT_URL + '/apply/clearance_pool_upload';
  let clearanceScreenshotPreviewUrls = [];

  function resetClearanceScreenshotFilePicker() {
    document.getElementById('clearanceScreenshotFilePickerLabel').textContent = 'Upload';
    const subtitle = document.getElementById('clearanceScreenshotFileSubtitle');
    subtitle.textContent = 'Image, you can select several at once · max 10 MB each';
    subtitle.classList.remove('done');
  }

  function handleClearanceScreenshotFileChange(input) {
    const subtitle = document.getElementById('clearanceScreenshotFileSubtitle');
    if (input.files.length) {
      document.getElementById('clearanceScreenshotFilePickerLabel').textContent = 'Replace';
      subtitle.textContent = input.files.length === 1 ?
        input.files[0].name :
        input.files.length + ' files selected';
      subtitle.classList.add('done');
    } else {
      resetClearanceScreenshotFilePicker();
    }
    renderClearanceScreenshotPreviews(input.files);
  }

  function clearClearanceScreenshotPreviews() {
    clearanceScreenshotPreviewUrls.forEach(url => URL.revokeObjectURL(url));
    clearanceScreenshotPreviewUrls = [];
    const container = document.getElementById('clearanceScreenshotPreviews');
    container.innerHTML = '';
    container.hidden = true;
  }

  function renderClearanceScreenshotPreviews(fileList) {
    clearanceScreenshotPreviewUrls.forEach(url => URL.revokeObjectURL(url));
    clearanceScreenshotPreviewUrls = [];

    const container = document.getElementById('clearanceScreenshotPreviews');
    container.innerHTML = '';

    if (!fileList.length) {
      container.hidden = true;
      return;
    }
    container.hidden = false;

    [...fileList].forEach((file, index) => {
      const url = URL.createObjectURL(file);
      clearanceScreenshotPreviewUrls.push(url);

      const card = document.createElement('div');
      card.className = 'modal-file-preview-card';

      const img = document.createElement('img');
      img.className = 'modal-file-preview-img';
      img.src = url;
      img.alt = file.name;

      const name = document.createElement('span');
      name.className = 'modal-file-preview-name';
      name.textContent = file.name;
      name.title = file.name;

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'modal-file-preview-remove';
      removeBtn.setAttribute('aria-label', 'Remove ' + file.name);
      removeBtn.textContent = '\u00d7';
      removeBtn.addEventListener('click', () => removeClearanceScreenshotFile(index));

      card.append(img, name, removeBtn);
      container.appendChild(card);
    });

    const addTile = document.createElement('button');
    addTile.type = 'button';
    addTile.className = 'modal-file-preview-add';
    addTile.setAttribute('aria-label', 'Add more screenshots');
    addTile.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#add-icon"></use></svg>';
    addTile.addEventListener('click', () => document.getElementById('clearance_screenshots_add').click());
    container.appendChild(addTile);
  }

  function removeClearanceScreenshotFile(index) {
    const input = document.getElementById('clearance_screenshots');
    const dataTransfer = new DataTransfer();
    [...input.files].forEach((file, i) => {
      if (i !== index) dataTransfer.items.add(file);
    });
    input.files = dataTransfer.files;
    handleClearanceScreenshotFileChange(input);
  }

  document.getElementById('clearance_screenshots_add').addEventListener('change', function() {
    if (!this.files.length) return;

    const mainInput = document.getElementById('clearance_screenshots');
    const dataTransfer = new DataTransfer();
    [...mainInput.files].forEach(file => dataTransfer.items.add(file));
    [...this.files].forEach(file => dataTransfer.items.add(file));
    mainInput.files = dataTransfer.files;

    this.value = '';
    handleClearanceScreenshotFileChange(mainInput);
  });

  async function submitClearanceScreenshots() {
    const fileInput = document.getElementById('clearance_screenshots');
    const errBox = document.getElementById('clearanceScreenshotError');
    const btn = document.getElementById('clearanceScreenshotSubmitBtn');

    if (!fileInput.files.length) {
      errBox.textContent = 'Please select at least one file.';
      errBox.hidden = false;
      return;
    }

    setButtonBusy(btn, true, 'Uploading...');
    errBox.hidden = true;

    const progressContainer = document.getElementById('clearanceScreenshotProgress');
    progressContainer.innerHTML = '';
    const bar = createUploadProgressBar(progressContainer);

    const formData = new FormData();
    for (const file of fileInput.files) {
      formData.append('clearance_screenshots[]', file);
    }
    formData.append('csrf_token', CSRF_TOKEN);

    try {
      const data = await uploadWithProgress(CLEARANCE_POOL_UPLOAD_API, formData, {
        headers: {
          'X-CSRF-Token': CSRF_TOKEN
        },
        onProgress: pct => bar.update(pct)
      });

      if (data.success) {
        window.location.reload();
      } else {
        errBox.textContent = (data.failures && data.failures.length) ? data.failures.join(' ') : (data.error ?? 'Upload failed.');
        errBox.hidden = false;
        setButtonBusy(btn, false);
        bar.remove();
      }
    } catch (err) {
      errBox.textContent = err.message || 'Network error. Please try again.';
      errBox.hidden = false;
      setButtonBusy(btn, false);
      bar.remove();
    }
  }
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>