// ===== Viewer: re-submit protocol =====
// Loaded only when the researcher can re-submit.

// ===== Re-submit protocol (protocol file + any docs the reviewer flagged) =====
const resubmitModal = document.getElementById("reuploadModalBackdrop");
const RESUBMIT_ENDPOINTS = {
  protocol: {
    path: "/apply/reupload",
    field: "protocol_file",
  },
};
let resubmitFiles = {};
let resubmitConverting = null;
let resubmitSubmitting = false;

function validateResubmitFile(key, file) {
  const ext = file.name.split(".").pop().toLowerCase();
  if (key === "protocol") {
    if (
      !["pdf", "doc", "docx"].includes(ext) ||
      (ext === "pdf" && file.type !== "application/pdf")
    ) {
      return "Please upload your IACUC protocol form as a PDF, DOC, or DOCX file.";
    }
  } else if (!["pdf", "jpg", "jpeg", "png"].includes(ext)) {
    return "Only PDF, JPG, or PNG files are accepted.";
  }
  if (file.size > 10 * 1024 * 1024) {
    return "File is too large. Maximum size is 10 MB.";
  }
  return null;
}

function showResubmitError(message) {
  const errBox = document.getElementById("reuploadError");
  errBox.textContent = message;
  errBox.hidden = false;
}

function clearResubmitError() {
  document.getElementById("reuploadError").hidden = true;
}

function resubmitDocRow(doc) {
  const file = resubmitFiles[doc.key];
  const converting = resubmitConverting === doc.key;
  const title = `${doc.title}${doc.required ? ' <span class="req">*</span>' : ""}`;

  if (file && !converting) {
    return uploadedDocRow({
      key: doc.key,
      title: doc.title,
      required: doc.required,
      name: file.name,
      size: file.size,
      accept: doc.accept,
      hint: file.staged ? "Converted to PDF" : "",
      uploadHandler: "handleResubmitUpload",
      removeHandler: "removeResubmitFile",
      previewHandler: "previewResubmitFile",
    });
  }

  const action = converting
    ? ""
    : `<label class="btn-upload-inline">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#upload-icon" /></svg>
            Upload
            <input type="file" accept="${doc.accept}" onchange="handleResubmitUpload(event,'${doc.key}')">
        </label>`;

  return `<div class="doc-list" data-doc-key="${doc.key}">
        <div class="doc-row">
            <div class="doc-row-info">
                <div class="doc-row-title">${title}</div>
                <span class="doc-row-sub">${doc.subtitle}</span>
            </div>
            <div class="doc-row-action">${action}</div>
        </div>
    </div>`;
}

function resubmitReadyCount() {
  const required = RESUBMIT_DOCS.filter((doc) => doc.required);
  const ready = required.filter((doc) => resubmitFiles[doc.key]).length;
  return { ready, total: required.length };
}

function updateResubmitState() {
  const { ready, total } = resubmitReadyCount();
  const summary = document.getElementById("reuploadSummary");
  const pct = total ? (ready / total) * 100 : 100;

  summary.classList.toggle("is-complete", ready === total);
  summary.innerHTML = `<span>${ready} of ${total} file${total === 1 ? "" : "s"} ready</span>
        <div class="resub-summary-track"><div class="resub-summary-fill" style="width:${pct}%"></div></div>`;

  document.getElementById("reuploadSubmitBtn").disabled =
    ready < total || resubmitConverting !== null || resubmitSubmitting;
}

function renderResubmitDocs() {
  document.getElementById("reuploadDocList").innerHTML =
    RESUBMIT_DOCS.map(resubmitDocRow).join("");
  document
    .getElementById("reuploadConvertedNotice")
    .classList.toggle(
      "is-hidden",
      !(resubmitFiles.protocol && resubmitFiles.protocol.staged),
    );
  updateResubmitState();
}

function previewResubmitFile(key) {
  const file = resubmitFiles[key];
  openFilePopup(file.staged ? ROOT_URL + "/apply/stagedfile" : file, file.name);
}

function removeResubmitFile(key) {
  delete resubmitFiles[key];
  clearResubmitError();
  renderResubmitDocs();
}

async function processResubmitFile(key, file) {
  clearResubmitError();

  const err = validateResubmitFile(key, file);
  if (err) {
    showResubmitError(err);
    return;
  }

  if (key === "protocol" && isWordFile(file)) {
    resubmitConverting = key;
    delete resubmitFiles[key];
    renderResubmitDocs();

    showConversionOverlay();

    try {
      resubmitFiles[key] = await stageWordFile(file, updateConversionOverlay);
    } catch (error) {
      showResubmitError(error.message || "Network error. Please try again.");
    }

    hideBusyOverlay();
    resubmitConverting = null;
  } else {
    resubmitFiles[key] = file;
  }

  renderResubmitDocs();
}

function handleResubmitUpload(event, key) {
  const input = event.target;
  const file = input.files[0];
  if (!file) return;
  input.value = "";
  return processResubmitFile(key, file);
}

// ===== Drag & drop onto a document card =====
function initResubmitDropzones() {
  const list = document.getElementById("reuploadDocList");
  const cardFor = (e) => e.target.closest && e.target.closest("[data-doc-key]");

  ["dragenter", "dragover"].forEach((type) =>
    list.addEventListener(type, (e) => {
      const card = cardFor(e);
      if (!card || resubmitConverting) return;
      e.preventDefault();
      list
        .querySelectorAll(".is-dragover")
        .forEach((el) => el !== card && el.classList.remove("is-dragover"));
      card.classList.add("is-dragover");
    }),
  );

  list.addEventListener("dragleave", (e) => {
    const card = cardFor(e);
    if (card && !card.contains(e.relatedTarget)) {
      card.classList.remove("is-dragover");
    }
  });

  list.addEventListener("drop", (e) => {
    const card = cardFor(e);
    if (!card) return;
    e.preventDefault();
    card.classList.remove("is-dragover");
    if (resubmitConverting) return;
    const file = e.dataTransfer && e.dataTransfer.files[0];
    if (file) processResubmitFile(card.dataset.docKey, file);
  });
}

// Stop the browser from navigating to a file dropped outside a card while the modal is open
["dragover", "drop"].forEach((type) =>
  resubmitModal.addEventListener(type, (e) => {
    if (!e.target.closest("[data-doc-key]")) e.preventDefault();
  }),
);

function openReuploadModal() {
  resubmitFiles = {};
  resubmitConverting = null;
  resubmitSubmitting = false;
  renderResubmitDocs();
  clearResubmitError();
  document.getElementById("reuploadProgress").innerHTML = "";
  resubmitModal.classList.add("open");
}

function resubmitIsBusy() {
  return (
    resubmitConverting !== null ||
    resubmitSubmitting ||
    (typeof isBusyOverlayOpen === "function" && isBusyOverlayOpen())
  );
}

function closeReuploadModal() {
  if (resubmitIsBusy()) return;
  resubmitModal.classList.remove("open");
}
resubmitModal.addEventListener("click", (e) => {
  if (e.target === resubmitModal) closeReuploadModal();
});
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeReuploadModal();
});
initResubmitDropzones();

async function uploadProtocolFile(endpoint, fieldName, file, onProgress) {
  const formData = new FormData();
  formData.append("protocol_id", PROTOCOL_ID);
  if (file && file.staged) {
    formData.append("use_staged", "1");
  } else if (file) {
    formData.append(fieldName, file);
  }

  return uploadWithProgress(ROOT_URL + endpoint, formData, {
    headers: { "X-CSRF-Token": CSRF_TOKEN },
    onProgress,
  });
}

async function submitReupload() {
  const errBox = document.getElementById("reuploadError");
  const submitBtn = document.getElementById("reuploadSubmitBtn");

  for (const doc of RESUBMIT_DOCS) {
    if (doc.required && !resubmitFiles[doc.key]) {
      showResubmitError(`Please select your ${doc.title.toLowerCase()}.`);
      return;
    }
  }

  const ok = await confirmAction(
    "Resubmit this protocol for review? It will be sent back to the reviewer.",
    {
      okText: "Resubmit",
      cancelText: "Cancel",
    },
  );
  if (!ok) return;

  resubmitSubmitting = true;
  document.getElementById("reuploadCancelBtn").disabled = true;
  setButtonBusy(submitBtn, true, "Resubmitting...");
  errBox.hidden = true;

  const progressContainer = document.getElementById("reuploadProgress");
  progressContainer.innerHTML = "";
  const bar = createUploadProgressBar(progressContainer);

  const queue = RESUBMIT_DOCS.filter(
    (doc) => doc.key !== "protocol" && resubmitFiles[doc.key],
  );
  queue.push(RESUBMIT_DOCS.find((doc) => doc.key === "protocol"));
  const total = queue.length;

  // ===== Upload progress =====
  function overallProgress(index, filePct) {
    return ((index + filePct / 100) / total) * 100;
  }

  try {
    for (let i = 0; i < queue.length; i++) {
      const doc = queue[i];
      const file = resubmitFiles[doc.key];
      const { path, field } = RESUBMIT_ENDPOINTS[doc.key];
      const result = await uploadProtocolFile(path, field, file, (pct) =>
        bar.update(overallProgress(i, pct)),
      );
      if (!result.success)
        throw new Error(
          result.error ?? `${doc.title} upload failed. Please try again.`,
        );
    }

    window.location.href = ROOT_URL + "/submissions?status=under-review";
  } catch (err) {
    errBox.textContent = err.message || "Network error. Please try again.";
    errBox.hidden = false;
    resubmitSubmitting = false;
    document.getElementById("reuploadCancelBtn").disabled = false;
    setButtonBusy(submitBtn, false);
    updateResubmitState();
    bar.remove();
  }
}
