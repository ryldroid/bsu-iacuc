(function () {
  const manager = document.getElementById("backup-manager");
  if (!manager) return;

  const endpoint = manager.dataset.endpoint;
  const csrf = manager.dataset.csrf;
  const createButton = document.getElementById("backup-create");
  const uploadButton = document.getElementById("backup-upload");
  const uploadInput = document.getElementById("backup-upload-input");
  const status = document.getElementById("backup-status");
  const progress = document.getElementById("backup-progress");
  const error = document.getElementById("backup-error");
  const results = document.getElementById("backup-results");
  const health = document.getElementById("backup-health");

  const restoreModal = document.getElementById("backupRestoreBackdrop");
  const restoreText = document.getElementById("backupRestoreText");
  const restoreError = document.getElementById("backupRestoreError");
  const restorePassword = document.getElementById("backupRestorePassword");
  const restoreConfirm = document.getElementById("backupRestoreConfirm");
  const restoreSubmit = document.getElementById("backupRestoreSubmit");

  const CHUNK_BYTES = 1024 * 1024;

  const defaultStatus = status.textContent;

  let restoreName = "";
  let lastPhase = "";
  let statusTimer;

  // ===== Server requests =====
  async function post(action, fields = {}) {
    const body = new FormData();
    body.append("csrf_token", csrf);
    Object.entries(fields).forEach(([key, value]) => body.append(key, value));

    const response = await fetch(`${endpoint}/${action}`, {
      method: "POST",
      headers: { Accept: "application/json" },
      body,
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok || !data.ok) {
      throw new Error(data.error || "Something went wrong. Please try again.");
    }
    return data;
  }

  function showError(message) {
    error.textContent = message;
    error.hidden = false;
  }

  function flashStatus(message) {
    status.textContent = message;
    clearTimeout(statusTimer);
    statusTimer = setTimeout(() => (status.textContent = defaultStatus), 8000);
  }

  // ===== Backup list =====
  async function loadBackups() {
    try {
      const response = await fetch(`${endpoint}/all`, {
        headers: { Accept: "application/json" },
      });
      const data = await response.json();
      if (data.ok) {
        results.innerHTML = data.html;
        health.innerHTML = data.health;
      }
    } catch (e) {}
  }

  // ===== Run a backup or restore job =====
  async function runJob(state) {
    const bar = createUploadProgressBar(progress);
    results
      .querySelectorAll("button")
      .forEach((button) => (button.disabled = true));

    try {
      while (!state.done) {
        lastPhase = state.phase;
        status.textContent = state.message;
        bar.update(state.percent);
        state = (await post("step", { id: state.id })).status;
      }

      bar.update(100);
    } finally {
      bar.remove();
    }
  }

  // ===== Create a backup =====
  async function createBackup() {
    error.hidden = true;
    setButtonBusy(createButton, true, "Backing up...");

    try {
      await runJob((await post("start")).status);
      flashStatus("Backup created.");
    } catch (e) {
      showError(e.message);
    } finally {
      setButtonBusy(createButton, false);
      await loadBackups();
    }
  }

  // ===== Upload a backup =====
  async function uploadBackup(file) {
    error.hidden = true;
    setButtonBusy(uploadButton, true, "Uploading...");
    createButton.disabled = true;
    status.textContent = "Uploading the backup...";
    const bar = createUploadProgressBar(progress);

    try {
      const { id } = await post("uploadStart", { size: file.size });

      for (let offset = 0; offset < file.size; offset += CHUNK_BYTES) {
        const end = Math.min(offset + CHUNK_BYTES, file.size);
        await post("uploadChunk", {
          id,
          offset,
          chunk: file.slice(offset, end),
        });
        bar.update((end / file.size) * 100);
      }

      status.textContent = "Checking the backup...";
      await post("uploadFinish", { id });
      flashStatus("Backup uploaded. Use Restore in the list to bring it back.");
    } catch (e) {
      showError(e.message);
    } finally {
      bar.remove();
      setButtonBusy(uploadButton, false);
      createButton.disabled = false;
      uploadInput.value = "";
      await loadBackups();
    }
  }

  // ===== Verify a backup =====
  async function verifyBackup(button) {
    error.hidden = true;
    results
      .querySelectorAll("button")
      .forEach((item) => (item.disabled = true));
    createButton.disabled = true;
    uploadButton.disabled = true;
    status.textContent = "Verifying the backup...";
    const bar = createUploadProgressBar(progress);

    try {
      let state = { offset: 0, percent: 0, done: false };

      while (!state.done) {
        state = (
          await post("verify", {
            name: button.dataset.name,
            offset: state.offset,
          })
        ).status;
        bar.update(state.percent);
      }

      flashStatus("Backup verified. Every file matches its checksum.");
    } catch (e) {
      status.textContent = defaultStatus;
      showError(e.message);
    } finally {
      bar.remove();
      createButton.disabled = false;
      uploadButton.disabled = false;
      await loadBackups();
    }
  }

  // ===== Restore a backup =====
  function openRestore(button) {
    restoreName = button.dataset.name;
    restoreText.textContent = `Restore the backup from ${button.dataset.created}?`;
    restoreError.hidden = true;
    restorePassword.value = "";
    restoreConfirm.value = "";
    restoreModal.classList.add("open");
    restorePassword.focus();
  }

  function closeRestore() {
    restoreModal.classList.remove("open");
  }

  async function restoreBackup() {
    const confirmation = restoreConfirm.value.trim();

    if (!restorePassword.value || confirmation !== "RESTORE") {
      restoreError.textContent =
        "Enter your password and type RESTORE to continue.";
      restoreError.hidden = false;
      return;
    }

    setButtonBusy(restoreSubmit, true, "Starting...");
    let state;

    try {
      state = (
        await post("restore", {
          name: restoreName,
          password: restorePassword.value,
          confirm: confirmation,
        })
      ).status;
    } catch (e) {
      restoreError.textContent = e.message;
      restoreError.hidden = false;
      setButtonBusy(restoreSubmit, false);
      return;
    }

    setButtonBusy(restoreSubmit, false);
    closeRestore();
    error.hidden = true;
    setButtonBusy(createButton, true, "Restoring...");

    try {
      await runJob(state);
      status.textContent = "Restore complete. Reloading...";
      setTimeout(() => location.reload(), 1500);
    } catch (e) {
      const untouched = !["swap", "cleanup"].includes(lastPhase);
      showError(
        `${e.message} ${untouched ? "Your current data was not changed." : "Check your data and the backup list before trying again."}`,
      );
      setButtonBusy(createButton, false);
      await loadBackups();
    }
  }

  // ===== Download and delete =====
  async function deleteBackup(button) {
    const confirmed = await confirmAction(
      "Delete this backup permanently? This cannot be undone.",
      { okText: "Delete", danger: true },
    );
    if (!confirmed) return;

    error.hidden = true;
    setButtonBusy(button, true, "Deleting...");

    try {
      await post("delete", { name: button.dataset.name });
      await loadBackups();
    } catch (e) {
      showError(e.message);
      setButtonBusy(button, false);
    }
  }

  results.addEventListener("click", (event) => {
    const download = event.target.closest(".js-backup-download");
    const verify = event.target.closest(".js-backup-verify");
    const restore = event.target.closest(".js-backup-restore");
    const remove = event.target.closest(".js-backup-delete");

    if (download) {
      location.href = `${endpoint}/download/${encodeURIComponent(download.dataset.name)}`;
    } else if (verify) {
      verifyBackup(verify);
    } else if (restore) {
      openRestore(restore);
    } else if (remove) {
      deleteBackup(remove);
    }
  });

  restoreSubmit.addEventListener("click", restoreBackup);
  document
    .getElementById("backupRestoreCancel")
    .addEventListener("click", closeRestore);
  document
    .getElementById("backupRestoreClose")
    .addEventListener("click", closeRestore);

  restoreModal.addEventListener("click", (event) => {
    if (event.target === restoreModal) closeRestore();
  });

  [restorePassword, restoreConfirm].forEach((input) =>
    input.addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        event.preventDefault();
        restoreBackup();
      }
    }),
  );

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && restoreModal.classList.contains("open"))
      closeRestore();
  });

  createButton.addEventListener("click", createBackup);
  uploadButton.addEventListener("click", () => uploadInput.click());
  uploadInput.addEventListener("change", () => {
    if (uploadInput.files[0]) uploadBackup(uploadInput.files[0]);
  });
  loadBackups();
})();
