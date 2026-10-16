// ===== Viewer: re-submit protocol =====
// Loaded only when the researcher can re-submit.

// ===== Re-submit protocol (protocol file + any docs the reviewer flagged) =====
const resubmitModal = document.getElementById("reuploadModalBackdrop");
const RESUBMIT_ENDPOINTS = {
  protocol: {
    path: "/apply/reupload",
    field: "protocol_file",
  },
  cert: {
    path: "/apply/reuploadcert",
    field: "cert_file",
  },
};
let resubmitFiles = {};
let resubmitConverting = null;

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + " B";
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
  return (bytes / (1024 * 1024)).toFixed(1) + " MB";
}

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

function resubmitDocRow(doc) {
  const file = resubmitFiles[doc.key];
  const action = file
    ? `<div class="doc-row-done">
            <svg width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#check-circle-icon" /></svg>
            <label class="doc-row-replace">
                Replace
                <input type="file" accept="${doc.accept}" onchange="handleResubmitUpload(event,'${doc.key}')">
            </label>
        </div>`
    : `<label class="btn-upload-inline">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#upload-icon" /></svg>
            Upload
            <input type="file" accept="${doc.accept}" onchange="handleResubmitUpload(event,'${doc.key}')">
        </label>`;
  const subLine =
    resubmitConverting === doc.key
      ? `<span class="doc-row-sub">Converting to PDF&hellip;</span>`
      : file
        ? `<span class="doc-row-sub done">${escapeHtml(file.name)} &middot; ${formatFileSize(file.size)}</span>`
        : `<span class="doc-row-sub">${doc.subtitle}</span>`;

  return `<div class="doc-row">
        <div class="doc-row-info">
            <div class="doc-row-title">${doc.title}${doc.required ? ' <span class="req">*</span>' : ""}</div>
            ${subLine}
        </div>
        <div class="doc-row-action">${action}</div>
    </div>`;
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
}

async function handleResubmitUpload(event, key) {
  const file = event.target.files[0];
  if (!file) return;
  const err = validateResubmitFile(key, file);
  if (err) {
    alert(err);
    event.target.value = "";
    return;
  }

  if (key === "protocol" && isWordFile(file)) {
    const submitBtn = document.getElementById("reuploadSubmitBtn");
    resubmitConverting = key;
    renderResubmitDocs();
    setButtonBusy(submitBtn, true, "Converting...");

    delete resubmitFiles[key];

    try {
      resubmitFiles[key] = await stageWordFile(file);
    } catch (error) {
      alert(error.message || "Network error. Please try again.");
    }

    resubmitConverting = null;
    setButtonBusy(submitBtn, false);
  } else {
    resubmitFiles[key] = file;
  }

  renderResubmitDocs();
}

function openReuploadModal() {
  resubmitFiles = {};
  resubmitConverting = null;
  renderResubmitDocs();
  document.getElementById("reuploadError").hidden = true;
  document.getElementById("reuploadProgress").innerHTML = "";
  resubmitModal.classList.add("open");
}

function closeReuploadModal() {
  resubmitModal.classList.remove("open");
}
resubmitModal.addEventListener("click", (e) => {
  if (e.target === resubmitModal) closeReuploadModal();
});
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeReuploadModal();
});

async function uploadProtocolFile(endpoint, fieldName, file, onProgress) {
  const formData = new FormData();
  formData.append("protocol_id", PROTOCOL_ID);
  if (file && file.staged) {
    formData.append("use_staged", "1");
  } else if (file) {
    formData.append(fieldName, file);
  }

  return uploadWithProgress(ROOT_URL + endpoint, formData, {
    onProgress,
  });
}

async function submitReupload() {
  const errBox = document.getElementById("reuploadError");
  const submitBtn = document.getElementById("reuploadSubmitBtn");

  for (const doc of RESUBMIT_DOCS) {
    if (doc.required && !resubmitFiles[doc.key]) {
      errBox.textContent = `Please select your ${doc.title.toLowerCase()}.`;
      errBox.hidden = false;
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
    setButtonBusy(submitBtn, false);
    bar.remove();
  }
}
