// ===== Clearances window =====
const CLEARANCE_POOL_API = ROOT_URL + "/apply/clearance_pool";
const CLEARANCE_POOL_UPLOAD_API = ROOT_URL + "/apply/clearance_pool_upload";
const CLEARANCE_STAGE_API = ROOT_URL + "/apply/clearance_stage";
const CLEARANCE_UNSTAGE_API = ROOT_URL + "/apply/clearance_unstage";
const CLEARANCE_CONFIRM_API = ROOT_URL + "/apply/clearance_confirm";
const CLEARANCE_DELETE_API = ROOT_URL + "/apply/clearance_delete";
const ASSIGN_IPN_API = ROOT_URL + "/apply/assign_ipn";
const NEXT_IPN_API = ROOT_URL + "/apply/next_ipn";

const clearanceWindow = document.getElementById("clearanceWindowBackdrop");
const clearanceDropzone = document.getElementById("clearanceDropzone");
const clearanceFileInput = document.getElementById("clearanceFileInput");
const clearanceRowsEl = document.getElementById("clearanceRows");
const clearanceEmpty = document.getElementById("clearanceEmpty");
const clearanceWindowError = document.getElementById("clearanceWindowError");
const clearanceSummary = document.getElementById("clearanceSummary");
const clearanceApproveBtn = document.getElementById("clearanceApproveBtn");
const confirmReviewBackdrop = document.getElementById("confirmReviewBackdrop");

const AR_FORMAT = {
  pattern: new RegExp(clearanceWindow.dataset.pattern),
  message: clearanceWindow.dataset.message,
  leading: clearanceWindow.dataset.leading,
  placeholder: clearanceWindow.dataset.placeholder,
  maxlength: clearanceWindow.dataset.maxlength,
};

let boardData = { unassigned: [], staged: [], endorsed_protocols: [] };
let boardReady = Promise.resolve();
let clearanceRows = [];
let nextRowKey = 1;
let preselectProtocolId = null;

// ===== Helpers =====
function isImage(name) {
  return /\.(jpe?g|png)$/i.test(name || "");
}

async function postJson(url, body) {
  try {
    const res = await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        ...csrfHeaders(),
      },
      body: JSON.stringify(body),
    });
    return await res.json();
  } catch (err) {
    return { error: "Network error. Please try again." };
  }
}

async function loadBoard() {
  try {
    const res = await fetch(CLEARANCE_POOL_API);
    const data = await res.json();
    boardData = {
      unassigned: data.unassigned || [],
      staged: data.staged || [],
      endorsed_protocols: data.endorsed_protocols || [],
    };
  } catch (err) {
    showToast("Could not load the clearances. Please refresh.", "error");
  }
}

function setWindowError(message) {
  clearanceWindowError.textContent = message;
  clearanceWindowError.hidden = !message;
}

function revokePreview(row) {
  if (row.previewUrl.startsWith("blob:")) URL.revokeObjectURL(row.previewUrl);
}

// ===== Rows: a saved image (already in the clearance pool) or a newly chosen file =====
function savedRow(item, protocolId = null) {
  return {
    key: nextRowKey++,
    poolId: Number(item.id),
    file: null,
    name: item.original_name,
    previewUrl: item.file_url,
    ar: item.ar_number,
    protocolId,
    stagedTo: protocolId,
    protocolTitle: item.protocol_title || "",
    arTouched: false,
    error: "",
  };
}

function newRow(file) {
  return {
    key: nextRowKey++,
    poolId: null,
    file,
    name: file.name,
    previewUrl: URL.createObjectURL(file),
    ar: AR_FORMAT.leading,
    protocolId: null,
    stagedTo: null,
    protocolTitle: "",
    arTouched: false,
    error: "",
  };
}

const endorsedProtocol = (id) =>
  (boardData.endorsed_protocols || []).find((p) => Number(p.protocol_id) === id);

const holderOf = (row, protocolId) =>
  clearanceRows.find(
    (other) => other !== row && other.protocolId === protocolId,
  );

function protocolLabel(row) {
  if (row.protocolId === null) return "";
  return endorsedProtocol(row.protocolId)?.research_title || row.protocolTitle;
}

const isArBlank = (row) => !row.ar.trim() || row.ar.trim() === AR_FORMAT.leading;
const isArValid = (row) => !isArBlank(row) && AR_FORMAT.pattern.test(row.ar.trim());

function rowStatus(row) {
  const ar = row.ar.trim();
  const blank = isArBlank(row);
  const validAr = isArValid(row);
  const noProtocol = row.protocolId === null;

  if (!validAr && row.arTouched && !blank)
    return { text: AR_FORMAT.message, tone: "error" };
  if (!validAr || noProtocol) {
    const missing = [!validAr && "AR number", noProtocol && "protocol"]
      .filter(Boolean)
      .join(" and ");
    return { text: `Needs ${missing}`, tone: "pending" };
  }
  if (clearanceRows.some((other) => other !== row && other.ar.trim() === ar))
    return { text: "Another row has this AR number.", tone: "error" };
  return { text: "Ready", tone: "ready", ready: true };
}

const isReady = (row) => rowStatus(row).ready === true;
const readyRows = () => clearanceRows.filter(isReady);

function rowHtml(row) {
  const key = row.key;
  const name = escapeHtml(row.name);
  const antiAutofill =
    'data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other"';
  return `
    <li class="clearance-row" data-key="${key}">
        <button type="button" class="clearance-row-thumb" data-zoom-src="${escapeHtml(row.previewUrl)}" data-zoom-alt="${name}" aria-label="Enlarge ${name}">
            <img src="${escapeHtml(row.previewUrl)}" alt="">
        </button>
        <div class="clearance-row-fields">
            <p class="clearance-row-name">${name}</p>
            <div class="clearance-number-field">
                <label for="clearance-ar-${key}">AR No.</label>
                <input type="text" id="clearance-ar-${key}" class="clearance-ar-input" value="${escapeHtml(row.ar)}"
                    maxlength="${AR_FORMAT.maxlength}" placeholder="${escapeHtml(AR_FORMAT.placeholder)}"
                    autocomplete="off" ${antiAutofill} ${row.poolId ? "readonly" : ""}>
            </div>
            <div class="clearance-number-field">
                <label for="clearance-protocol-${key}">Protocol</label>
                <select id="clearance-protocol-${key}" class="clearance-protocol-select"></select>
            </div>
        </div>
        <p class="clearance-row-status"></p>
        <button type="button" class="clearance-row-remove" aria-label="Remove ${name}">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#close-icon" /></svg>
        </button>
    </li>`;
}

// The protocol list opens only once the AR number is valid, or when a protocol is already chosen
function fillProtocolSelect(row) {
  const select = clearanceRowsEl.querySelector(
    `[data-key="${row.key}"] .clearance-protocol-select`,
  );
  const locked = !isArValid(row) && row.protocolId === null;
  const options = (boardData.endorsed_protocols || []).map((p) => {
    const id = Number(p.protocol_id);
    const taken = holderOf(row, id) ? " (clearance already assigned)" : "";
    const label = `${p.research_title} (${p.first_name} ${p.last_name}, ${p.reference_no})${taken}`;
    return `<option value="${id}">${escapeHtml(label)}</option>`;
  });

  select.disabled = locked;
  select.innerHTML =
    `<option value="">${locked ? "Enter the AR No. first" : "Select a protocol"}</option>` +
    options.join("");
  select.value = row.protocolId === null ? "" : String(row.protocolId);
}

function refreshClearanceRows() {
  clearanceRows.forEach((row) => {
    const status = row.error
      ? { text: row.error, tone: "error" }
      : rowStatus(row);
    const el = clearanceRowsEl.querySelector(
      `[data-key="${row.key}"] .clearance-row-status`,
    );
    fillProtocolSelect(row);
    el.className = `clearance-row-status clearance-row-status--${status.tone}`;
    if (status.ready) {
      el.innerHTML =
        '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#check-icon" /></svg> Ready';
    } else {
      el.textContent = status.text;
    }
  });

  const ready = readyRows().length;
  clearanceEmpty.hidden = clearanceRows.length > 0;
  clearanceSummary.textContent = clearanceRows.length
    ? `${ready} of ${clearanceRows.length} ready`
    : "";
  clearanceApproveBtn.disabled = ready === 0;
  document.getElementById("clearanceApproveLabel").textContent = ready
    ? `Approve ${ready} protocol${ready === 1 ? "" : "s"}`
    : "Approve";

  if (clearanceRows.some((row) => row.file))
    clearanceWindow.dataset.changed = "true";
  else delete clearanceWindow.dataset.changed;
}

function renderClearanceRows() {
  clearanceRowsEl.innerHTML = clearanceRows.map(rowHtml).join("");
  refreshClearanceRows();
}

function rowOf(el) {
  const rowEl = el.closest(".clearance-row");
  const row = clearanceRows.find((r) => String(r.key) === rowEl.dataset.key);
  return { rowEl, row };
}

// ===== Open and close =====
function openClearanceWindow(protocolId = null) {
  preselectProtocolId = protocolId;
  setWindowError("");
  clearanceRows = [];
  renderClearanceRows();
  clearanceWindow.classList.add("open");

  boardReady = loadBoard().then(() => {
    clearanceRows = [
      ...boardData.unassigned.map((item) => savedRow(item)),
      ...boardData.staged.map((item) =>
        savedRow(item, Number(item.protocol_id)),
      ),
    ];
    renderClearanceRows();
  });
}

function closeClearanceWindow() {
  clearanceWindow.classList.remove("open");
  clearanceRows.forEach(revokePreview);
  clearanceRows = [];
}

document
  .getElementById("clearanceUploadBtn")
  .addEventListener("click", () => openClearanceWindow());

protocolsList.addEventListener("click", (e) => {
  const addBtn = e.target.closest(".clearance-add-btn");
  if (addBtn) openClearanceWindow(Number(addBtn.dataset.protocolId));
});

document
  .getElementById("clearanceCancelBtn")
  .addEventListener("click", () =>
    clearanceWindow.querySelector(".modal-close").click(),
  );

// ===== Adding images: choose them, or drop them anywhere on the window =====
async function addClearanceFiles(fileList) {
  const files = Array.from(fileList).filter((file) => isImage(file.name));
  if (!files.length) {
    setWindowError("Please choose JPG or PNG screenshots.");
    return;
  }
  setWindowError("");
  await boardReady;

  const added = files.map(newRow);
  clearanceRows.push(...added);

  const preselected = preselectProtocolId;
  preselectProtocolId = null;
  if (
    preselected &&
    endorsedProtocol(preselected) &&
    !holderOf(added[0], preselected)
  )
    added[0].protocolId = preselected;

  renderClearanceRows();
}

document
  .getElementById("clearanceChooseBtn")
  .addEventListener("click", () => clearanceFileInput.click());

clearanceFileInput.addEventListener("change", () => {
  const files = Array.from(clearanceFileInput.files);
  clearanceFileInput.value = "";
  addClearanceFiles(files);
});

const isFileDrag = (e) =>
  Array.from(e.dataTransfer?.types || []).includes("Files");

clearanceWindow.addEventListener("dragover", (e) => {
  if (!isFileDrag(e)) return;
  e.preventDefault();
  clearanceDropzone.classList.add("is-file-drag");
});

clearanceWindow.addEventListener("dragleave", (e) => {
  if (!clearanceWindow.contains(e.relatedTarget))
    clearanceDropzone.classList.remove("is-file-drag");
});

clearanceWindow.addEventListener("drop", (e) => {
  if (!isFileDrag(e)) return;
  e.preventDefault();
  clearanceDropzone.classList.remove("is-file-drag");
  addClearanceFiles(e.dataTransfer.files);
});

// ===== Row fields =====
clearanceRowsEl.addEventListener("input", (e) => {
  if (!e.target.matches(".clearance-ar-input")) return;
  const { row } = rowOf(e.target);
  row.error = "";
  row.ar = e.target.value;
  refreshClearanceRows();
});

clearanceRowsEl.addEventListener("change", async (e) => {
  const { row } = rowOf(e.target);

  if (e.target.matches(".clearance-ar-input")) {
    row.arTouched = true;
  } else if (e.target.matches(".clearance-protocol-select")) {
    const protocolId = e.target.value ? Number(e.target.value) : null;
    const holder = protocolId === null ? null : holderOf(row, protocolId);
    const swap =
      holder &&
      (await confirmAction(
        `A clearance is already assigned to ${confirmName(endorsedProtocol(protocolId).research_title)}. Swap it with ${confirmName(row.name)}? ${confirmName(holder.name)} will need another protocol.`,
        { okText: "Swap", cancelText: "Cancel" },
      ));

    if (!holder || swap) {
      if (holder) holder.protocolId = null;
      row.protocolId = protocolId;
      row.error = "";
    }
  }
  refreshClearanceRows();
});

clearanceRowsEl.addEventListener("click", (e) => {
  const removeBtn = e.target.closest(".clearance-row-remove");
  if (removeBtn) removeRow(rowOf(removeBtn).row);
});

async function removeRow(row) {
  if (row.poolId) {
    const ok = await confirmAction(
      `Delete the uploaded clearance ${confirmName(row.name)}?`,
      { okText: "Delete", danger: true },
    );
    if (!ok) return;

    let data = row.stagedTo
      ? await postJson(CLEARANCE_UNSTAGE_API, { pool_id: row.poolId })
      : { success: true };
    if (data.success)
      data = await postJson(CLEARANCE_DELETE_API, { pool_id: row.poolId });
    if (!data.success) {
      row.error = data.error || "Could not delete that screenshot.";
      refreshClearanceRows();
      return;
    }
  }
  revokePreview(row);
  clearanceRows = clearanceRows.filter((r) => r !== row);
  renderClearanceRows();
}

// ===== Approve: final check, then upload, match and confirm every ready row =====
clearanceApproveBtn.addEventListener("click", () => {
  const ready = readyRows();
  if (!ready.length) return;

  document.getElementById("confirmReviewList").innerHTML = ready
    .map(
      (row) => `
    <li>
        <button type="button" class="clearance-row-thumb" data-zoom-src="${escapeHtml(row.previewUrl)}" data-zoom-alt="${escapeHtml(row.name)}" aria-label="Enlarge ${escapeHtml(row.name)}">
            <img src="${escapeHtml(row.previewUrl)}" alt="">
        </button>
        <div class="clearance-review-text">
            <strong>${escapeHtml(protocolLabel(row))}</strong>
            <span>AR No. <strong>${escapeHtml(row.ar.trim())}</strong></span>
        </div>
    </li>`,
    )
    .join("");

  const skipped = clearanceRows.filter(
    (row) => row.file && !isReady(row),
  ).length;
  const note = document.getElementById("confirmReviewNote");
  note.textContent = `${skipped} image${skipped === 1 ? " is" : "s are"} still missing an AR number or protocol and will not be saved.`;
  note.hidden = skipped === 0;

  document.getElementById("confirmReviewError").hidden = true;
  confirmReviewBackdrop.classList.add("open");
});

function closeConfirmReview() {
  confirmReviewBackdrop.classList.remove("open");
}

confirmReviewBackdrop.addEventListener("click", (e) => {
  if (e.target === confirmReviewBackdrop) closeConfirmReview();
});

async function uploadRow(row) {
  const formData = new FormData();
  formData.append("clearance_screenshot", row.file);
  formData.append("ar_number", row.ar.trim());
  formData.append("csrf_token", CSRF_TOKEN);

  try {
    const res = await fetch(CLEARANCE_POOL_UPLOAD_API, {
      method: "POST",
      headers: {
        ...csrfHeaders(),
      },
      body: formData,
    });
    const data = await res.json();
    if (data.queued) {
      notifyQueued();
      row.error = "Saved offline. Approve again once you are back online.";
    } else if (!data.success) {
      row.error = data.error || "Upload failed.";
    }
  } catch (err) {
    row.error = "Network error. Please try again.";
  }
  return !row.error;
}

// Returns true when every ready row is uploaded and matched; a row that fails shows why
async function syncClearanceRows() {
  clearanceRows.forEach((row) => (row.error = ""));
  let allSynced = true;

  const uploaded = [];
  for (const row of readyRows().filter((r) => !r.poolId)) {
    if (await uploadRow(row)) uploaded.push(row);
    else allSynced = false;
  }

  if (uploaded.length) {
    await loadBoard();
    const poolIds = new Map(
      boardData.unassigned.map((item) => [item.ar_number, Number(item.id)]),
    );
    uploaded.forEach((row) => {
      row.poolId = poolIds.get(row.ar.trim()) ?? null;
      if (row.poolId) {
        row.file = null;
      } else {
        row.error =
          "Could not find the uploaded image. Please reopen this window.";
        allSynced = false;
      }
    });
  }

  // Unmatch first so a swapped protocol is free before its new image is matched
  const saved = clearanceRows.filter((r) => r.poolId);
  const targetOf = (row) => (isReady(row) ? row.protocolId : null);

  for (const row of saved.filter((r) => r.stagedTo !== null && r.stagedTo !== targetOf(r))) {
    const data = await postJson(CLEARANCE_UNSTAGE_API, { pool_id: row.poolId });
    if (data.success) row.stagedTo = null;
    else {
      row.error = data.error || "Could not match that screenshot.";
      allSynced = false;
    }
  }

  for (const row of saved.filter((r) => r.stagedTo === null && targetOf(r) !== null)) {
    const target = targetOf(row);
    const data = await postJson(CLEARANCE_STAGE_API, {
      pool_id: row.poolId,
      protocol_id: target,
    });
    if (data.success) row.stagedTo = target;
    else {
      row.error = data.error || "Could not match that screenshot.";
      allSynced = false;
    }
  }
  return allSynced;
}

async function proceedConfirm() {
  const btn = document.getElementById("confirmProceedBtn");
  const errBox = document.getElementById("confirmReviewError");
  errBox.hidden = true;
  setButtonBusy(btn, true, "Approving...");

  if (!(await syncClearanceRows())) {
    closeConfirmReview();
    setWindowError(
      "Some rows need another look. Fix them, then press Approve again.",
    );
    renderClearanceRows();
    setButtonBusy(btn, false);
    return;
  }

  const data = await postJson(CLEARANCE_CONFIRM_API, { pool_id: null });
  if (data.confirmed > 0) {
    window.location.reload();
    return;
  }

  if (data.failures && data.failures.length > 0) {
    errBox.textContent = data.failures.join(" ");
    errBox.hidden = false;
  } else if (data.error) {
    errBox.textContent = data.error;
    errBox.hidden = false;
  } else {
    closeConfirmReview();
  }
  setButtonBusy(btn, false);
}

const numberModalBackdrop = document.getElementById("numberModalBackdrop");
let numberModalKind = null;
let numberModalProtocolId = null;

const NUMBER_FIELDS = {
  ipn: {
    label: "IPN",
    api: ASSIGN_IPN_API,
    suggestApi: NEXT_IPN_API,
    key: "reference_no",
    placeholder: numberModalBackdrop.dataset.placeholder,
    pattern: new RegExp(numberModalBackdrop.dataset.pattern),
    formatError: numberModalBackdrop.dataset.message,
    maxlength: Number(numberModalBackdrop.dataset.maxlength),
    numeric: numberModalBackdrop.dataset.numeric === "1",
    helper:
      "Write this IPN on the printed protocol before the IACUC chairperson signs it. It is kept in sync with the Records page.",
    saved: () => window.location.reload(),
  },
};

// ===== Number modal (IPN / AR) =====
function openNumberModal(kind, protocolId, currentValue) {
  const field = NUMBER_FIELDS[kind];
  numberModalKind = kind;
  numberModalProtocolId = protocolId;
  document.getElementById("numberModalTitle").textContent =
    (currentValue ? "Edit " : "Add ") + field.label;
  document.getElementById("numberModalHelper").textContent = field.helper;
  document.getElementById("numberModalLabel").textContent = field.label;
  document.getElementById("numberModalInput").placeholder = field.placeholder;
  if (field.maxlength)
    document.getElementById("numberModalInput").maxLength = field.maxlength;
  else document.getElementById("numberModalInput").removeAttribute("maxlength");
  document.getElementById("numberModalInput").inputMode = field.numeric
    ? "numeric"
    : "text";
  document.getElementById("numberModalInput").value = currentValue || "";
  if (!currentValue && field.suggestApi) suggestNumber(field, protocolId);
  document.getElementById("numberModalError").hidden = true;
  numberModalBackdrop.classList.add("open");
  document.getElementById("numberModalInput").focus();
}

async function suggestNumber(field, protocolId) {
  try {
    const res = await fetch(field.suggestApi);
    const data = await res.json();
    const input = document.getElementById("numberModalInput");
    if (data.next && numberModalProtocolId === protocolId && !input.value) {
      input.value = data.next;
    }
  } catch (err) {}
}

function closeNumberModal() {
  numberModalBackdrop.classList.remove("open");
  numberModalKind = null;
  numberModalProtocolId = null;
}

numberModalBackdrop.addEventListener("click", (e) => {
  if (e.target === numberModalBackdrop) closeNumberModal();
});

async function saveNumber() {
  const field = NUMBER_FIELDS[numberModalKind];
  const input = document.getElementById("numberModalInput");
  const errBox = document.getElementById("numberModalError");
  const value = input.value.trim();

  if (!value) {
    errBox.textContent = `Please enter an ${field.label}.`;
    errBox.hidden = false;
    return;
  }
  if (field.pattern && !field.pattern.test(value)) {
    errBox.textContent = field.formatError;
    errBox.hidden = false;
    return;
  }

  const btn = document.getElementById("numberModalSaveBtn");
  setButtonBusy(btn, true, "Saving...");

  try {
    const res = await fetch(field.api, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        ...csrfHeaders(),
      },
      body: JSON.stringify({
        protocol_id: numberModalProtocolId,
        [field.key]: value,
      }),
    });
    const data = await res.json();

    if (data.queued) {
      closeNumberModal();
      notifyQueued();
      setButtonBusy(btn, false);
      return;
    }

    if (!data.success) {
      errBox.textContent = data.error || `Could not save this ${field.label}.`;
      errBox.hidden = false;
      setButtonBusy(btn, false);
      return;
    }

    const onSaved = field.saved;
    closeNumberModal();
    onSaved();
  } catch (err) {
    errBox.textContent = "Network error. Please try again.";
    errBox.hidden = false;
  }
  setButtonBusy(btn, false);
}
