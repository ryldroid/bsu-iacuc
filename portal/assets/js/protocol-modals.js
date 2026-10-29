// ===== Shared protocol modals =====
// Markup: includes/protocol-modals.php. Every opener takes the protocol id, so
// the viewer and the dashboard lists can share them. Relies on the page
// globals ROOT_URL and CSRF_TOKEN plus confirmAction, setButtonBusy,
// uploadWithProgress, createUploadProgressBar (modals.js) and openFilePopup.

function protocolModalBind(backdropId, closeFn) {
  const backdrop = document.getElementById(backdropId);
  backdrop.addEventListener("click", (e) => {
    if (e.target === backdrop) closeFn();
  });
  return backdrop;
}

function protocolModalPost(path, body) {
  return fetch(ROOT_URL + path, {
    method: "POST",
    headers: { "Content-Type": "application/json", "X-CSRF-Token": CSRF_TOKEN },
    body: JSON.stringify(body),
  }).then((res) => res.json());
}

function protocolModalData(protocolId) {
  return fetch(ROOT_URL + "/apply/modaldata/" + protocolId).then((res) =>
    res.json(),
  );
}

function showProtocolModalError(box, message) {
  box.textContent = message;
  box.hidden = false;
}

// Called after a successful action. Pages can pass opts.onDone or
// opts.redirectUrl; otherwise the page reloads to show the new state.
function finishProtocolAction(opts) {
  if (opts?.onDone) return opts.onDone();
  if (opts?.redirectUrl) {
    window.location.href = opts.redirectUrl;
    return;
  }
  window.location.reload();
}

// ===== Rename =====
const renameBackdrop = protocolModalBind("renameModalBackdrop", () =>
  closeRenameModal(),
);
const renameTitleInput = document.getElementById("renameTitleInput");
let renameCtx = null;

function openRenameModal(protocolId, title, opts) {
  renameCtx = { protocolId, opts };
  document.getElementById("renameModalTitle").textContent = title;
  renameTitleInput.value = title;
  document.getElementById("renameError").hidden = true;
  renameBackdrop.classList.add("open");
  renameTitleInput.focus();
  renameTitleInput.select();
}

function closeRenameModal() {
  renameBackdrop.classList.remove("open");
}

async function submitRename() {
  const newTitle = renameTitleInput.value.trim();
  const errBox = document.getElementById("renameError");
  const submitBtn = document.getElementById("renameSubmitBtn");
  errBox.hidden = true;

  if (!newTitle) {
    showProtocolModalError(errBox, "Please enter a research title.");
    return;
  }

  const ok = await confirmAction(`Rename this protocol to "${newTitle}"?`, {
    okText: "Rename",
    cancelText: "Cancel",
  });
  if (!ok) return;

  setButtonBusy(submitBtn, true, "Renaming...");
  try {
    const data = await protocolModalPost("/apply/rename", {
      protocol_id: renameCtx.protocolId,
      title: newTitle,
    });
    if (data.queued) {
      closeRenameModal();
      notifyQueued();
      setButtonBusy(submitBtn, false);
      return;
    }
    if (data.ok) return finishProtocolAction(renameCtx.opts);
    showProtocolModalError(
      errBox,
      data.error ?? "Could not rename this protocol.",
    );
  } catch {
    showProtocolModalError(errBox, "Network error. Please try again.");
  }
  setButtonBusy(submitBtn, false);
}

// ===== Delete / request deletion =====
// opts.mode: "delete" (personnel) or "request" (researcher)
const DELETE_MODES = {
  delete: {
    api: "/apply/delete",
    label: "Delete Protocol",
    intro:
      "This will delete the protocol and notify the researcher. Please explain why.",
    button: "Delete",
    busy: "Deleting...",
    confirm:
      "Delete this protocol? The researcher will be notified with your reason. This cannot be undone.",
    danger: true,
    icon: "trash-icon",
    placeholder: "Explain why this protocol should be deleted...",
  },
  request: {
    api: "/apply/request_deletion",
    label: "Request Deletion",
    intro:
      "CCARD will be notified of your request along with the reason below.",
    button: "Send Request",
    busy: "Sending...",
    confirm: "Send a deletion request with this reason?",
    danger: false,
    icon: "trash-icon",
    placeholder: "Explain why this protocol should be deleted...",
  },
  agencyReturn: {
    api: "/apply/status",
    label: "Mark as returned by DA-RFO-CAR or BAI",
    intro:
      "The protocol will go to Returned for Revision and its record will be removed until it is reviewed again. The researcher and reviewers will be notified, and the researcher will see your note. Add why Department of Agriculture – Regional Field Office Cordillera Administrative Region (DA-RFO CAR) or BAI returned it.",
    button: "Return",
    busy: "Returning...",
    confirm: "Return this protocol to the researcher for revision?",
    danger: false,
    icon: "undo-icon",
    placeholder:
      "For example: Department of Agriculture – Regional Field Office Cordillera Administrative Region (DA-RFO CAR) asked for a corrected animal count.",
    neutral: true,
    body: { status: "Needs Revision" },
  },
};
const deleteBackdrop = protocolModalBind("deleteModalBackdrop", () =>
  closeDeleteModal(),
);
const deleteReasonText = document.getElementById("deleteReasonText");
const deleteCharCount = document.getElementById("deleteCharCount");
let deleteCtx = null;

function openDeleteModal(protocolId, title, opts) {
  const mode = DELETE_MODES[opts?.mode] ?? DELETE_MODES.request;
  deleteCtx = { protocolId, opts, mode };
  document.getElementById("deleteModalLabel").textContent = mode.label;
  document.getElementById("deleteModalTitle").textContent = title;
  document.getElementById("deleteModalIntro").textContent = mode.intro;
  document.getElementById("deleteSubmitLabel").textContent = mode.button;
  deleteReasonText.placeholder = mode.placeholder;
  document.getElementById("deleteReasonRequirement").textContent = mode.optional
    ? "(optional)"
    : "(required)";
  document
    .getElementById("deleteModalIcon")
    .classList.toggle("modal-header-icon--danger", !mode.neutral);
  document
    .getElementById("deleteSubmitBtn")
    .classList.toggle("confirm-modal-ok-danger", !mode.neutral);
  document
    .querySelectorAll("#deleteModalBackdrop [data-mode-icon]")
    .forEach((use) => use.setAttribute("href", "#" + mode.icon));
  deleteReasonText.value = "";
  deleteCharCount.textContent = "0";
  document.getElementById("deleteError").hidden = true;
  deleteBackdrop.classList.add("open");
}

function closeDeleteModal() {
  deleteBackdrop.classList.remove("open");
}

deleteReasonText.addEventListener("input", () => {
  deleteCharCount.textContent = deleteReasonText.value.length;
});

async function submitDelete() {
  const reason = deleteReasonText.value.trim();
  const errBox = document.getElementById("deleteError");
  const submitBtn = document.getElementById("deleteSubmitBtn");
  const { mode, protocolId, opts } = deleteCtx;
  errBox.hidden = true;

  if (!reason && !mode.optional) {
    showProtocolModalError(errBox, "A reason is required.");
    return;
  }

  const ok = await confirmAction(mode.confirm, {
    okText: mode.button,
    cancelText: "Cancel",
    danger: mode.danger,
  });
  if (!ok) return;

  setButtonBusy(submitBtn, true, mode.busy);
  try {
    const data = await protocolModalPost(mode.api, {
      ...mode.body,
      protocol_id: protocolId,
      reason,
    });
    if (data.queued) {
      closeDeleteModal();
      notifyQueued();
      setButtonBusy(submitBtn, false);
      return;
    }
    if (data.ok) return finishProtocolAction(opts);
    showProtocolModalError(
      errBox,
      data.error ?? "Could not complete this action.",
    );
  } catch {
    showProtocolModalError(errBox, "Network error. Please try again.");
  }
  setButtonBusy(submitBtn, false);
}

// ===== Cancel a pending deletion request (researcher) =====
async function cancelDeletionRequest(protocolId, opts) {
  const ok = await confirmAction(
    "Cancel your deletion request? The protocol stays active.",
    { okText: "Cancel Request", cancelText: "Keep Request" },
  );
  if (!ok) return;

  try {
    const data = await protocolModalPost("/apply/cancel_deletion", {
      protocol_id: protocolId,
    });
    if (data.ok) return finishProtocolAction(opts);
    showToast(data.error ?? "Could not cancel the deletion request.", "error");
  } catch {
    showToast("Network error. Please try again.", "error");
  }
}

// ===== Amendments list =====
// Same timeline look as the history modal. Opening a file closes this
// modal and brings it back when the file popup is dismissed.
const amendmentsBackdrop = protocolModalBind("amendmentsModalBackdrop", () =>
  closeAmendmentsModal(),
);

async function openAmendmentsModal(protocolId, title, opts) {
  const list = document.getElementById("amendmentsList");
  const count = document.getElementById("amendmentsCount");
  document.getElementById("amendmentsModalTitle").textContent = title;
  count.hidden = true;
  list.innerHTML = '<p class="helper">Loading&hellip;</p>';
  amendmentsBackdrop.classList.add("open");

  try {
    const data = await protocolModalData(protocolId);
    if (data.error) throw new Error(data.error);

    const amendments = data.amendments || [];
    if (amendments.length === 0) {
      list.innerHTML =
        '<p class="helper">No amendments have been submitted.</p>';
      return;
    }

    count.textContent =
      amendments.length === 1
        ? "1 amendment"
        : `${amendments.length} amendments`;
    count.hidden = false;

    list.innerHTML = amendments
      .map((a, i) => {
        const badge =
          i === 0 && amendments.length > 1
            ? ' <span class="status-dot status-dot--success">Latest</span>'
            : "";
        const note = (a.note || "").trim();
        const detail = note
          ? `<span class="payment-event-reason is-multiline"><strong>What changed</strong>${escapeHtml(note)}</span>`
          : '<span class="payment-event-note">No note was provided.</span>';

        return `<div class="payment-event payment-tone-info">
            <span class="payment-event-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#edit-icon" /></svg>
            </span>
            <div class="payment-event-body">
              <span class="payment-event-title">Amendment ${Number(a.version_number)}${badge}</span>
              <span class="payment-event-time">${escapeHtml(formatDate(a.uploaded_at))} &middot; ${escapeHtml(formatTime(a.uploaded_at))}</span>
              ${detail}
            </div>
            <button class="button button-compact" type="button" data-index="${i}" aria-label="Open Amendment ${Number(a.version_number)}">
              <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#eye-icon" /></svg>
              Open
            </button>
            ${
              data.can_withdraw
                ? `<button class="button button-compact" type="button" data-withdraw="${i}" aria-label="Withdraw Amendment ${Number(a.version_number)}">
              <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#undo-icon" /></svg>
              Withdraw
            </button>`
                : ""
            }
          </div>`;
      })
      .join("");

    list.querySelectorAll("button[data-index]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const a = amendments[btn.dataset.index];
        closeAmendmentsModal();
        openFilePopup(a.file_url, "Amendment " + a.version_number, null, () =>
          amendmentsBackdrop.classList.add("open"),
        );
      });
    });
    list.querySelectorAll("button[data-withdraw]").forEach((btn) => {
      btn.addEventListener("click", () =>
        withdrawAmendment(amendments[btn.dataset.withdraw], opts),
      );
    });
  } catch {
    list.innerHTML =
      '<p class="helper">Could not load the amendments. Please try again.</p>';
  }
}

async function withdrawAmendment(amendment, opts) {
  const ok = await confirmAction(
    `Withdraw Amendment ${amendment.version_number}? Its file and note will be removed and CCARD will be notified.`,
    { okText: "Withdraw", cancelText: "Keep", danger: true },
  );
  if (!ok) return;

  try {
    const data = await protocolModalPost("/apply/withdraw_amendment", {
      version_id: amendment.id,
    });
    if (data.ok) return finishProtocolAction(opts);
    showToast(data.error ?? "Could not withdraw the amendment.", "error");
  } catch {
    showToast("Network error. Please try again.", "error");
  }
}

function closeAmendmentsModal() {
  amendmentsBackdrop.classList.remove("open");
}

// ===== Convert Word file for review (used by amendment submit and re-submit) =====
function isWordFile(file) {
  return /\.(doc|docx)$/i.test(file.name);
}

async function stageWordFile(file, onProgress) {
  const formData = new FormData();
  formData.append("staged_file", file);

  const result = await uploadWithProgress(
    ROOT_URL + "/apply/stageprotocol",
    formData,
    {
      headers: { "X-CSRF-Token": CSRF_TOKEN },
      onProgress,
    },
  );
  if (result.error) throw new Error(result.error);

  return { staged: true, name: result.name, size: result.size };
}

function previewStagedFile(event) {
  event.preventDefault();
  openFilePopup(ROOT_URL + "/apply/stagedfile", "Converted protocol form");
}

// ===== Submit amendment =====
const AMENDMENT_FILE_HINT = "PDF, DOC, or DOCX, max 10 MB";
const amendmentBackdrop = protocolModalBind("amendmentModalBackdrop", () =>
  closeAmendmentModal(),
);
const amendmentFile = document.getElementById("amendmentFile");
const amendmentFileName = document.getElementById("amendmentFileName");
const amendmentNote = document.getElementById("amendmentNote");
const amendmentCharCount = document.getElementById("amendmentCharCount");
const amendmentError = document.getElementById("amendmentError");
const amendmentConvertedNotice = document.getElementById(
  "amendmentConvertedNotice",
);
const amendmentPreviewBtn = document.getElementById("amendmentPreviewBtn");
let amendmentStaged = null;
let amendmentCtx = null;

function openAmendmentModal(protocolId, title, opts) {
  amendmentCtx = { protocolId, opts };
  document.getElementById("amendmentModalTitle").textContent = title;
  amendmentFile.value = "";
  amendmentFileName.textContent = AMENDMENT_FILE_HINT;
  amendmentFileName.classList.remove("done");
  amendmentNote.value = "";
  amendmentCharCount.textContent = "0";
  amendmentError.hidden = true;
  amendmentStaged = null;
  amendmentConvertedNotice.classList.add("is-hidden");
  amendmentPreviewBtn.classList.add("is-hidden");
  document.getElementById("amendmentProgress").innerHTML = "";
  amendmentBackdrop.classList.add("open");
}

function closeAmendmentModal() {
  amendmentBackdrop.classList.remove("open");
}

amendmentNote.addEventListener("input", () => {
  amendmentCharCount.textContent = amendmentNote.value.length;
});

async function handleAmendmentFile(event) {
  const file = event.target.files[0];
  if (!file) return;

  amendmentStaged = null;
  amendmentConvertedNotice.classList.add("is-hidden");
  amendmentPreviewBtn.classList.add("is-hidden");

  const isPdf =
    file.type === "application/pdf" && file.name.toLowerCase().endsWith(".pdf");
  const error =
    !isPdf && !isWordFile(file)
      ? "Only PDF, DOC, or DOCX files are accepted."
      : file.size > 10 * 1024 * 1024
        ? "File is too large. Maximum size is 10 MB."
        : null;

  amendmentError.textContent = error ?? "";
  amendmentError.hidden = !error;
  amendmentFileName.textContent = error ? AMENDMENT_FILE_HINT : file.name;
  amendmentFileName.classList.toggle("done", !error);
  if (error) {
    event.target.value = "";
    return;
  }
  if (isPdf) {
    amendmentPreviewBtn.classList.remove("is-hidden");
    return;
  }

  const submitBtn = document.getElementById("amendmentSubmitBtn");
  setButtonBusy(submitBtn, true, "Converting...");
  showConversionOverlay();

  try {
    amendmentStaged = await stageWordFile(file, updateConversionOverlay);
    amendmentFileName.textContent = amendmentStaged.name;
    amendmentConvertedNotice.classList.remove("is-hidden");
    amendmentPreviewBtn.classList.remove("is-hidden");
  } catch (err) {
    showProtocolModalError(
      amendmentError,
      err.message || "Network error. Please try again.",
    );
    amendmentFileName.textContent = AMENDMENT_FILE_HINT;
    amendmentFileName.classList.remove("done");
    event.target.value = "";
  }

  hideBusyOverlay();
  setButtonBusy(submitBtn, false);
}

function previewAmendmentFile() {
  openFilePopup(
    amendmentStaged ? ROOT_URL + "/apply/stagedfile" : amendmentFile.files[0],
    amendmentFileName.textContent,
  );
}

async function submitAmendment() {
  const submitBtn = document.getElementById("amendmentSubmitBtn");
  const note = amendmentNote.value.trim();
  amendmentError.hidden = true;

  if (!amendmentFile.files[0]) {
    showProtocolModalError(
      amendmentError,
      "Please upload the updated protocol.",
    );
    return;
  }
  if (!note) {
    showProtocolModalError(amendmentError, "Please explain what changed.");
    return;
  }

  const ok = await confirmAction(
    "Submit this amendment? CCARD will be notified.",
    { okText: "Submit", cancelText: "Cancel" },
  );
  if (!ok) return;

  setButtonBusy(submitBtn, true, "Submitting...");

  const progressContainer = document.getElementById("amendmentProgress");
  progressContainer.innerHTML = "";
  const bar = createUploadProgressBar(progressContainer);

  const formData = new FormData();
  formData.append("protocol_id", amendmentCtx.protocolId);
  if (amendmentStaged) {
    formData.append("use_staged", "1");
  } else {
    formData.append("amendment_file", amendmentFile.files[0]);
  }
  formData.append("note", note);

  try {
    const result = await uploadWithProgress(
      ROOT_URL + "/apply/amendment_upload",
      formData,
      {
        headers: { "X-CSRF-Token": CSRF_TOKEN },
        onProgress: (pct) => bar.update(pct),
      },
    );
    if (!result.success) {
      throw new Error(
        result.error ?? "Could not submit the amendment. Please try again.",
      );
    }
    finishProtocolAction(amendmentCtx.opts);
  } catch (err) {
    showProtocolModalError(
      amendmentError,
      err.message || "Network error. Please try again.",
    );
    setButtonBusy(submitBtn, false);
    bar.remove();
  }
}
