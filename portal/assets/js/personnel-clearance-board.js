// ===== Clearance board =====
const CLEARANCE_POOL_API = ROOT_URL + "/apply/clearance_pool";
const CLEARANCE_POOL_UPLOAD_API = ROOT_URL + "/apply/clearance_pool_upload";
const CLEARANCE_STAGE_API = ROOT_URL + "/apply/clearance_stage";
const CLEARANCE_UNSTAGE_API = ROOT_URL + "/apply/clearance_unstage";
const CLEARANCE_CONFIRM_API = ROOT_URL + "/apply/clearance_confirm";
const CLEARANCE_DELETE_API = ROOT_URL + "/apply/clearance_delete";
const ASSIGN_IPN_API = ROOT_URL + "/apply/assign_ipn";

let boardData = {
  unassigned: [],
  staged: [],
  endorsed_protocols: [],
};
let selectedPoolId = null;
let clearanceBoardLoaded = false;

function ensureClearanceBoard() {
  if (clearanceBoardLoaded) return;
  clearanceBoardLoaded = true;
  loadBoard();
}

// ===== Thumbnail helpers =====
function isImage(name) {
  return /\.(jpe?g|png)$/i.test(name || "");
}

function thumbHtml(item, { allowDelete = true, zoomImage = false } = {}) {
  const deleteBtn = allowDelete
    ? `
    <button type="button" class="clearance-delete-btn" title="Delete" aria-label="Delete"
        onclick="event.stopPropagation(); deletePoolItem(${item.id}, this)">
        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#close-icon" /></svg>
    </button>`
    : "";

  if (!isImage(item.original_name)) {
    return `<div class="clearance-file-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#review-icon" /></svg>
            ${deleteBtn}
        </div>`;
  }
  const zoomAttrs = `${zoomImage ? "data-zoom-src" : "data-preview-src"}="${escapeHtml(item.file_url)}" data-zoom-alt="${escapeHtml(item.original_name)}"`;
  return `<div class="clearance-thumb-media">
        <img src="${item.file_url}" alt="${escapeHtml(item.original_name)}" loading="lazy" ${zoomAttrs}>
        ${deleteBtn}
    </div>`;
}

async function loadBoard() {
  try {
    const res = await fetch(CLEARANCE_POOL_API);
    boardData = await res.json();
  } catch (err) {
    showToast("Could not load the clearances. Please refresh.", "error");
    return;
  }
  if (
    selectedPoolId !== null &&
    !(boardData.unassigned || []).some(
      (item) => Number(item.id) === selectedPoolId,
    )
  ) {
    selectedPoolId = null;
  }
  renderTray();
  renderSlots();
  applySelection();
  updateConfirmButton();
}

function toggleSelectThumb(poolId) {
  poolId = Number(poolId);
  selectedPoolId = selectedPoolId === poolId ? null : poolId;
  applySelection();
}

function applySelection() {
  document.querySelectorAll("#trayGrid .clearance-thumb").forEach((el) => {
    el.classList.toggle(
      "is-selected",
      selectedPoolId !== null && Number(el.dataset.poolId) === selectedPoolId,
    );
  });
  document.querySelectorAll(".clearance-slot").forEach((el) => {
    el.classList.toggle(
      "is-target",
      selectedPoolId !== null && el.dataset.canReceive === "1",
    );
  });
}

const listCaches = new Map();

function reconcileList(container, entries, emptyHtml) {
  let cache = listCaches.get(container.id);
  if (!cache) {
    cache = new Map();
    listCaches.set(container.id, cache);
  }

  if (!entries.length) {
    cache.clear();
    container.innerHTML = emptyHtml;
    return;
  }

  container
    .querySelectorAll(":scope > .clearance-empty")
    .forEach((el) => el.remove());

  const seen = new Set();
  let prev = null;
  entries.forEach(({ key, html }) => {
    seen.add(key);
    let rec = cache.get(key);
    if (!rec || rec.html !== html) {
      const tpl = document.createElement("template");
      tpl.innerHTML = html.trim();
      const el = tpl.content.firstElementChild;
      if (rec) rec.el.replaceWith(el);
      rec = {
        el,
        html,
      };
      cache.set(key, rec);
    }
    const expected = prev
      ? prev.nextElementSibling
      : container.firstElementChild;
    if (rec.el !== expected) container.insertBefore(rec.el, expected);
    prev = rec.el;
  });

  cache.forEach((rec, key) => {
    if (!seen.has(key)) {
      rec.el.remove();
      cache.delete(key);
    }
  });
}

function renderTray() {
  const grid = document.getElementById("trayGrid");
  const items = boardData.unassigned || [];
  document.getElementById("trayCount").textContent = items.length
    ? `(${items.length})`
    : "";

  reconcileList(
    grid,
    items.map((item) => ({
      key: item.id,
      html: `
    <div class="clearance-thumb" draggable="true" data-pool-id="${item.id}"
        ondragstart="onDragStart(event, ${item.id})"
        onclick="toggleSelectThumb(${item.id})">
        ${thumbHtml(item)}
        <span class="clearance-thumb-name">${escapeHtml(item.original_name)}</span>
        <span class="clearance-thumb-ar">AR No. <strong>${escapeHtml(item.ar_number || "")}</strong></span>
    </div>`,
    })),
    '<p class="helper clearance-empty">No unsorted screenshots. Drag image files here or use Upload Clearances.</p>',
  );
}

function stagedFor(protocolId) {
  return (boardData.staged || []).find(
    (s) => Number(s.protocol_id) === Number(protocolId),
  );
}

function renderSlots() {
  const endorsedById = new Map(
    (boardData.endorsed_protocols || []).map((p) => [Number(p.protocol_id), p]),
  );

  document.querySelectorAll(".clearance-slot").forEach((slot) => {
    const protocolId = Number(slot.dataset.protocolId);
    const p = endorsedById.get(protocolId);
    const staged = stagedFor(protocolId);

    let html = "";
    if (p && staged) {
      html = `
                <div class="clearance-thumb clearance-thumb--staged">
                    ${thumbHtml(staged, { allowDelete: false, zoomImage: true })}
                    <button type="button" class="clearance-unstage-btn" data-pool-id="${staged.id}" title="Remove" aria-label="Remove">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#close-icon" /></svg>
                    </button>
                </div>
                <div class="clearance-slot-info">
                    <span class="clearance-ar">AR No. <strong>${escapeHtml(staged.ar_number || "")}</strong></span>
                    <span class="helper clearance-slot-hint">Ready to confirm.</span>
                </div>
                <button type="button" class="button button--primary clearance-confirm-one" data-pool-id="${staged.id}">
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#check-icon" /></svg>
                    Confirm
                </button>`;
    } else if (p && !p.latest_clearance_version_id) {
      html = `
                <span class="helper clearance-slot-hint">
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#upload-icon" /></svg>
                    Drop a clearance here, or tap here after selecting one.</span>`;
    }

    slot.dataset.canReceive =
      p && !p.latest_clearance_version_id && !staged ? "1" : "0";
    slot.classList.toggle("has-staged", !!staged);

    if (slot.dataset.render !== html) {
      slot.innerHTML = html;
      slot.dataset.render = html;
    }
  });
}

function updateConfirmButton() {
  const count = (boardData.staged || []).length;
  document.getElementById("stagedCount").textContent = count;
  document.getElementById("confirmBtn").disabled = count === 0;

  const waiting = (boardData.endorsed_protocols || []).filter(
    (p) => !p.latest_clearance_version_id,
  ).length;
  document.getElementById("clearanceProgress").textContent = waiting
    ? `${count} of ${waiting} protocols matched`
    : "No protocols are waiting for a clearance";
}

// ===== Upload: staff adds screenshots straight into the unsorted tray =====
const clearanceFileInput = document.getElementById("clearanceFileInput");
const clearanceUploadBtn = document.getElementById("clearanceUploadBtn");

clearanceUploadBtn.addEventListener("click", () => clearanceFileInput.click());
clearanceFileInput.addEventListener("change", () => {
  uploadClearances(clearanceFileInput.files);
  clearanceFileInput.value = "";
});

// Chosen files wait in a queue; each one is previewed in a modal, given its AR number, then uploaded
const clearanceUploadBackdrop = document.getElementById(
  "clearanceUploadBackdrop",
);
const clearanceUploadImg = document.getElementById("clearanceUploadImg");
const clearanceUploadAr = document.getElementById("clearanceUploadAr");
const clearanceUploadError = document.getElementById("clearanceUploadError");
let uploadQueue = [];
let uploadTotal = 0;
let uploadedCount = 0;

function uploadClearances(fileList) {
  const files = Array.from(fileList).filter((file) => isImage(file.name));
  if (!files.length) {
    showToast("Please choose JPG or PNG screenshots.", "error");
    return;
  }
  uploadQueue = files;
  uploadTotal = files.length;
  uploadedCount = 0;
  showNextUpload();
}

function showNextUpload() {
  const file = uploadQueue[0];
  if (!file) {
    finishClearanceUploads();
    return;
  }
  URL.revokeObjectURL(clearanceUploadImg.src);
  clearanceUploadImg.src = URL.createObjectURL(file);
  clearanceUploadImg.alt = file.name;
  document.getElementById("clearanceUploadName").textContent = file.name;
  document.getElementById("clearanceUploadTitle").textContent =
    uploadTotal > 1
      ? `Upload Clearance (${uploadedCount + 1} of ${uploadTotal})`
      : "Upload Clearance";
  document.getElementById("clearanceUploadSaveLabel").textContent =
    uploadQueue.length > 1 ? "Upload & Next" : "Upload";
  clearanceUploadAr.value = "";
  clearanceUploadError.hidden = true;
  clearanceUploadBackdrop.classList.add("open");
  clearanceUploadAr.focus();
}

function finishClearanceUploads() {
  clearanceUploadBackdrop.classList.remove("open");
  URL.revokeObjectURL(clearanceUploadImg.src);
  clearanceUploadImg.src = "";
  uploadQueue = [];
  if (uploadedCount) showToast(`${uploadedCount} clearance(s) uploaded.`);
  loadBoard();
}

function cancelClearanceUpload() {
  finishClearanceUploads();
}

clearanceUploadAr.addEventListener("keydown", (e) => {
  if (e.key === "Enter") saveClearanceUpload();
});

async function saveClearanceUpload() {
  const arNumber = clearanceUploadAr.value.trim();
  if (!arNumber) {
    clearanceUploadError.textContent = "Please enter the AR number.";
    clearanceUploadError.hidden = false;
    return;
  }

  const btn = document.getElementById("clearanceUploadSaveBtn");
  setButtonBusy(btn, true, "Uploading...");

  const formData = new FormData();
  formData.append("clearance_screenshot", uploadQueue[0]);
  formData.append("ar_number", arNumber);
  formData.append("csrf_token", CSRF_TOKEN);

  try {
    const res = await fetch(CLEARANCE_POOL_UPLOAD_API, {
      method: "POST",
      headers: {
        "X-CSRF-Token": CSRF_TOKEN,
      },
      body: formData,
    });
    const data = await res.json();
    setButtonBusy(btn, false);

    if (!data.success) {
      clearanceUploadError.textContent = data.error || "Upload failed.";
      clearanceUploadError.hidden = false;
      return;
    }

    uploadedCount++;
    uploadQueue.shift();
    showNextUpload();
  } catch (err) {
    setButtonBusy(btn, false);
    clearanceUploadError.textContent = "Network error. Please try again.";
    clearanceUploadError.hidden = false;
  }
}

// ===== Drag and drop, and tap-to-place (for touch screens) =====
// A thumbnail being moved also carries file data in some browsers, so it must not count as an upload
let draggingThumb = false;

function onDragStart(e, poolId) {
  draggingThumb = true;
  e.dataTransfer.setData("text/plain", String(poolId));
}

document.addEventListener("dragend", () => {
  draggingThumb = false;
});

const clearanceTray = document.getElementById("clearanceTrayCard");
const isFileDrag = (e) =>
  !draggingThumb && Array.from(e.dataTransfer?.types || []).includes("Files");

clearanceTray.addEventListener("dragover", (e) => {
  if (!isFileDrag(e)) return;
  e.preventDefault();
  clearanceTray.classList.add("is-file-drag");
});

clearanceTray.addEventListener("dragleave", (e) => {
  if (!clearanceTray.contains(e.relatedTarget))
    clearanceTray.classList.remove("is-file-drag");
});

clearanceTray.addEventListener("drop", (e) => {
  if (!isFileDrag(e)) return;
  e.preventDefault();
  clearanceTray.classList.remove("is-file-drag");
  if (!clearanceUploadBtn.disabled) uploadClearances(e.dataTransfer.files);
});

function receivingSlot(e) {
  const slot = e.target.closest(".clearance-slot");
  return slot && slot.dataset.canReceive === "1" ? slot : null;
}

protocolsList.addEventListener("dragover", (e) => {
  const slot = receivingSlot(e);
  if (!slot) return;
  e.preventDefault();
  slot.classList.add("is-drag-over");
});

protocolsList.addEventListener("dragleave", (e) => {
  e.target.closest?.(".clearance-slot")?.classList.remove("is-drag-over");
});

protocolsList.addEventListener("drop", (e) => {
  const slot = receivingSlot(e);
  if (!slot) return;
  e.preventDefault();
  slot.classList.remove("is-drag-over");
  const poolId = Number(e.dataTransfer.getData("text/plain"));
  if (!poolId) return;
  stageItem(poolId, Number(slot.dataset.protocolId));
});

protocolsList.addEventListener("click", (e) => {
  const confirmOneBtn = e.target.closest(".clearance-confirm-one");
  if (confirmOneBtn) {
    openConfirmReview(Number(confirmOneBtn.dataset.poolId));
    return;
  }

  const unstageBtn = e.target.closest(".clearance-unstage-btn");
  if (unstageBtn) {
    unstage(Number(unstageBtn.dataset.poolId));
    return;
  }

  const slot = receivingSlot(e);
  if (!slot || selectedPoolId === null) return;
  const poolId = selectedPoolId;
  selectedPoolId = null;
  applySelection();
  stageItem(poolId, Number(slot.dataset.protocolId));
});

async function stageItem(poolId, protocolId) {
  try {
    const res = await fetch(CLEARANCE_STAGE_API, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": CSRF_TOKEN,
      },
      body: JSON.stringify({
        pool_id: poolId,
        protocol_id: protocolId,
      }),
    });
    const data = await res.json();
    if (!data.success)
      showToast(data.error || "Could not match that screenshot.", "error");
  } catch (err) {
    showToast("Network error. Please try again.", "error");
  }
  loadBoard();
}

async function unstage(poolId) {
  try {
    const res = await fetch(CLEARANCE_UNSTAGE_API, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": CSRF_TOKEN,
      },
      body: JSON.stringify({
        pool_id: poolId,
      }),
    });
    const data = await res.json();
    if (!data.success)
      showToast(data.error || "Could not undo that match.", "error");
  } catch (err) {
    showToast("Network error. Please try again.", "error");
  }
  loadBoard();
}

async function deletePoolItem(poolId, btn) {
  const ok = await confirmAction("Delete uploaded clearance?", {
    okText: "Delete",
    danger: true,
  });
  if (!ok) return;

  setButtonBusy(btn, true, "Deleting...");

  try {
    const res = await fetch(CLEARANCE_DELETE_API, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": CSRF_TOKEN,
      },
      body: JSON.stringify({
        pool_id: poolId,
      }),
    });
    const data = await res.json();
    if (!data.success)
      showToast(data.error || "Could not delete that screenshot.", "error");
  } catch (err) {
    showToast("Network error. Please try again.", "error");
  }
  loadBoard();
}

const numberModalBackdrop = document.getElementById("numberModalBackdrop");
let numberModalKind = null;
let numberModalProtocolId = null;

const NUMBER_FIELDS = {
  ipn: {
    label: "IPN",
    api: ASSIGN_IPN_API,
    key: "reference_no",
    placeholder: "e.g. 000026",
    pattern: /^\d{6}$/,
    formatError:
      "IPN must be 6 digits, with the last 2 digits as the year (e.g. 000026).",
    maxlength: 6,
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
  document.getElementById("numberModalInput").inputMode = field.maxlength
    ? "numeric"
    : "text";
  document.getElementById("numberModalInput").value = currentValue || "";
  document.getElementById("numberModalError").hidden = true;
  numberModalBackdrop.classList.add("open");
  document.getElementById("numberModalInput").focus();
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
        "X-CSRF-Token": CSRF_TOKEN,
      },
      body: JSON.stringify({
        protocol_id: numberModalProtocolId,
        [field.key]: value,
      }),
    });
    const data = await res.json();

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

const confirmReviewBackdrop = document.getElementById("confirmReviewBackdrop");

let confirmPoolId = null;

function openConfirmReview(poolId = null) {
  confirmPoolId = poolId;
  const item = poolId
    ? (boardData.staged || []).find((s) => Number(s.id) === poolId)
    : null;
  document.getElementById("confirmReviewTitle").textContent = item
    ? "Confirm Clearance"
    : "Confirm All Clearances";
  document.getElementById("confirmReviewHelper").innerHTML = item
    ? `<strong class="clearance-modal-warning">Double-check the AR number and ARC file before proceeding.</strong> By proceeding, <strong>${escapeHtml(item.protocol_title)}</strong> (AR No. <strong>${escapeHtml(item.ar_number || "")}</strong>) will be marked <strong>Approved</strong>.`
    : `<strong class="clearance-modal-warning">Double-check all matches before proceeding.</strong> Once you proceed, each matched protocol is marked <strong>Approved</strong>.`;
  document.getElementById("confirmReviewError").hidden = true;
  confirmReviewBackdrop.classList.add("open");
}

document.getElementById("confirmBtn").addEventListener("click", () => {
  if ((boardData.staged || []).length === 0) return;
  openConfirmReview();
});

// ===== Confirm review =====
function closeConfirmReview() {
  confirmReviewBackdrop.classList.remove("open");
}

confirmReviewBackdrop.addEventListener("click", (e) => {
  if (e.target === confirmReviewBackdrop) closeConfirmReview();
});

async function proceedConfirm() {
  const btn = document.getElementById("confirmProceedBtn");
  const errBox = document.getElementById("confirmReviewError");
  errBox.hidden = true;
  setButtonBusy(btn, true, "Confirming...");

  try {
    const res = await fetch(CLEARANCE_CONFIRM_API, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": CSRF_TOKEN,
      },
      body: JSON.stringify({
        pool_id: confirmPoolId,
      }),
    });
    const data = await res.json();

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
    loadBoard();
  } catch (err) {
    errBox.textContent = "Network error. Please try again.";
    errBox.hidden = false;
  }
  setButtonBusy(btn, false);
}

// ===== Double-click an unsorted screenshot to enlarge it =====
document.getElementById("trayGrid")?.addEventListener("dblclick", (e) => {
  const img = e.target.closest("img[data-preview-src]");
  if (img) openImageZoom(img.dataset.previewSrc, img.dataset.zoomAlt);
});

if (activeFilter === "endorsed") ensureClearanceBoard();
