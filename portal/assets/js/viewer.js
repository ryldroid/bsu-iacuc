// ===== Protocol viewer =====
// PDF, annotations and sidebar. The config script in protocol.view.php defines
// PDF_URL, VERSION_ID, BACK_URL and the other flags used here. The optional modal
// scripts (payment-modal.js and friends) load after this one, viewer-boot.js last.

// Footer for the proof of payment popup: offers "Resubmit Payment" when the payment was rejected.
function paymentResubmitFooter() {
  return PAYMENT_RESUBMIT
    ? {
        ...PAYMENT_RESUBMIT,
        onAction: () => openPaymentModal(),
      }
    : null;
}

window.protocolMenuHooks = {
  paymentResubmit: paymentResubmitFooter,
};

// ===== Version switcher dropdown =====
const versionTrigger = document.getElementById("versionSwitcherTrigger");
const versionMenu = document.getElementById("versionDropdownMenu");
const versionSwitcher = document.getElementById("versionSwitcher");

// ===== Title expand =====
const viewerTitle = document.getElementById("viewerTitle");
const toggleViewerTitle = () =>
  viewerTitle.setAttribute(
    "aria-expanded",
    viewerTitle.classList.toggle("is-expanded"),
  );

viewerTitle.addEventListener("click", toggleViewerTitle);
viewerTitle.addEventListener("keydown", (e) => {
  if (e.key === "Enter" || e.key === " ") {
    e.preventDefault();
    toggleViewerTitle();
  }
});

versionTrigger?.addEventListener("click", (e) => {
  e.stopPropagation();
  closeProtocolMenus();
  const isOpen = versionMenu.classList.toggle("open");
  versionTrigger.setAttribute("aria-expanded", isOpen ? "true" : "false");

  if (isOpen) {
    positionEdgeAwareDropdown(versionSwitcher, versionMenu);
  }
});

document.addEventListener("click", (e) => {
  if (versionSwitcher && !versionSwitcher.contains(e.target)) {
    versionMenu?.classList.remove("open");
    versionTrigger?.setAttribute("aria-expanded", "false");
  }
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    versionMenu?.classList.remove("open");
    versionTrigger?.setAttribute("aria-expanded", "false");
  }
});

// ===== State =====
let pdfDoc = null;
let annotations = [];
let annotationsFailed = false;
let pendingBox = null;
let dragState = null;
let editingAnnotId = null;
let zoom = 1;
let renderToken = 0;
let renderedWidth = 0;
let renderTimer = null;

// ===== Load PDF =====
async function loadPdf() {
  if (SCAN_IS_IMAGE) {
    showScanImage();
    return;
  }
  try {
    pdfDoc = await pdfjsLib.getDocument(PDF_URL).promise;
    await renderPages();

    const pageIndicator = document.getElementById("pageIndicator");
    pageIndicator.textContent =
      pdfDoc.numPages + (pdfDoc.numPages === 1 ? " page" : " pages");
    pageIndicator.hidden = false;
  } catch (err) {
    document.getElementById("viewerLoading")?.remove();
    const message = document.createElement("p");
    message.className = "error-msg";
    message.textContent =
      err.name === "MissingPDFException"
        ? "File not found."
        : "Could not load the file (" +
          (err.message || err.name || "unknown error") +
          ").";
    document.getElementById("pdfColumn").replaceChildren(message);
  }
  await loadAnnotations();
}

// ===== Render pages (first load, window resize and zoom) =====
const MAX_CANVAS_PIXELS = 16e6;

async function renderPages() {
  const token = ++renderToken;
  const col = document.getElementById("pdfColumn");
  const isFirst = !col.querySelector(".page-wrapper");
  renderedWidth = col.clientWidth;
  const wrappers = [];

  for (let p = 1; p <= pdfDoc.numPages; p++) {
    const page = await pdfDoc.getPage(p);
    if (token !== renderToken) return;

    const fitScale = Math.min(
      1.5,
      (renderedWidth - 48) / page.getViewport({ scale: 1 }).width,
    );
    const vp = page.getViewport({ scale: fitScale * zoom });
    const outputScale = Math.min(
      window.devicePixelRatio || 1,
      Math.sqrt(MAX_CANVAS_PIXELS / (vp.width * vp.height)),
    );

    const wrapper = document.createElement("div");
    wrapper.className = "page-wrapper";
    wrapper.dataset.page = p;
    wrapper.style.width = vp.width + "px";

    const canvas = document.createElement("canvas");
    canvas.className = "pdf-canvas";
    canvas.width = Math.floor(vp.width * outputScale);
    canvas.height = Math.floor(vp.height * outputScale);
    canvas.style.width = vp.width + "px";
    canvas.style.height = vp.height + "px";
    wrapper.appendChild(canvas);

    const overlay = document.createElement("div");
    overlay.className = "annot-overlay";
    overlay.dataset.origW = vp.width;
    overlay.dataset.origH = vp.height;
    overlay.dataset.page = p;

    if (CAN_REVIEW) {
      overlay.style.cursor = "crosshair";
      attachDrawListeners(overlay, p, canvas);
    }
    wrapper.appendChild(overlay);
    wrappers.push(wrapper);
    if (isFirst) col.appendChild(wrapper);

    await page.render({
      canvasContext: canvas.getContext("2d"),
      viewport: vp,
      transform:
        outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : undefined,
    }).promise;
    if (token !== renderToken) return;
    document.getElementById("viewerLoading")?.remove();
  }

  if (isFirst) return;
  const scroller = scrollingContainer(col);
  const ratio = scroller.scrollTop / (scroller.scrollHeight || 1);
  closeAnnotPopup();
  col.replaceChildren(...wrappers);
  scroller.scrollTop = ratio * scroller.scrollHeight;
  renderAnnotations();
}

function scrollingContainer(col) {
  return (
    [col, document.querySelector(".viewer-layout"), document.scrollingElement]
      .filter(Boolean)
      .find((el) => el.scrollHeight > el.clientHeight + 1) || col
  );
}

function scheduleRender(delay = 200) {
  clearTimeout(renderTimer);
  renderTimer = setTimeout(() => {
    if (dragState) return scheduleRender();
    renderPages();
  }, delay);
}

// ===== Resize: re-fit the pages when the column width changes =====
new ResizeObserver(() => {
  const col = document.getElementById("pdfColumn");
  if (pdfDoc && Math.abs(col.clientWidth - renderedWidth) >= 2)
    scheduleRender();
}).observe(document.getElementById("pdfColumn"));

// ===== Zoom =====
const ZOOM_STEPS = [0.5, 0.75, 1, 1.25, 1.5, 2, 2.5];
const zoomOutBtn = document.getElementById("zoomOut");
const zoomInBtn = document.getElementById("zoomIn");

function setZoom(next) {
  zoom = next;
  document.getElementById("zoomLevel").textContent =
    Math.round(zoom * 100) + "%";
  zoomOutBtn.disabled = zoom === ZOOM_STEPS[0];
  zoomInBtn.disabled = zoom === ZOOM_STEPS[ZOOM_STEPS.length - 1];
  document.getElementById("pdfColumn").classList.toggle("is-zoomed", zoom > 1);
  updateToolbarHint();
  if (pdfDoc) scheduleRender(0);
}

function stepZoom(direction) {
  const index = ZOOM_STEPS.indexOf(zoom) + direction;
  setZoom(ZOOM_STEPS[Math.min(Math.max(index, 0), ZOOM_STEPS.length - 1)]);
}

zoomOutBtn.addEventListener("click", () => stepZoom(-1));
zoomInBtn.addEventListener("click", () => stepZoom(1));
document.getElementById("zoomFit").addEventListener("click", () => setZoom(1));

// Older signed scans were uploaded as JPG/PNG, which PDF.js can't open.
function showScanImage() {
  document.getElementById("zoomControls").hidden = true;
  const img = document.createElement("img");
  img.className = "scan-image";
  img.alt = "Signed scan";
  img.addEventListener("load", () =>
    document.getElementById("viewerLoading")?.remove(),
  );
  img.addEventListener("error", () => {
    document.getElementById("viewerLoading")?.remove();
    const message = document.createElement("p");
    message.className = "error-msg";
    message.textContent = "Could not load the file.";
    document.getElementById("pdfColumn").replaceChildren(message);
  });
  img.src = PDF_URL;
  document.getElementById("pdfColumn").replaceChildren(img);
}

// ===== Draw listeners =====
function attachDrawListeners(overlay, pageNum, canvas) {
  overlay.addEventListener("pointerdown", (e) => {
    if (e.pointerType === "mouse" && e.button !== 0) return;
    e.preventDefault();
    overlay.setPointerCapture(e.pointerId);
    const rect = overlay.getBoundingClientRect();
    const ghost = document.createElement("div");
    ghost.className = "annot-ghost";
    overlay.appendChild(ghost);
    dragState = {
      pointerId: e.pointerId,
      pageNum,
      startX: e.clientX - rect.left,
      startY: e.clientY - rect.top,
      canvas,
      ghost,
    };
  });

  overlay.addEventListener("pointermove", (e) => {
    if (
      !dragState ||
      dragState.pageNum !== pageNum ||
      dragState.pointerId !== e.pointerId
    )
      return;
    e.preventDefault();
    const rect = overlay.getBoundingClientRect();
    const curX = e.clientX - rect.left;
    const curY = e.clientY - rect.top;
    const g = dragState.ghost;
    g.style.left = Math.min(dragState.startX, curX) + "px";
    g.style.top = Math.min(dragState.startY, curY) + "px";
    g.style.width = Math.abs(curX - dragState.startX) + "px";
    g.style.height = Math.abs(curY - dragState.startY) + "px";
  });

  overlay.addEventListener("pointerup", (e) => {
    if (
      !dragState ||
      dragState.pageNum !== pageNum ||
      dragState.pointerId !== e.pointerId
    )
      return;
    const rect = overlay.getBoundingClientRect();
    const curX = e.clientX - rect.left;
    const curY = e.clientY - rect.top;
    const rawW = Math.abs(curX - dragState.startX);
    const rawH = Math.abs(curY - dragState.startY);
    dragState.ghost?.remove();

    if (rawW < 10 || rawH < 10) {
      dragState = null;
      return;
    }

    const cRect = dragState.canvas.getBoundingClientRect();
    pendingBox = {
      pageNum: dragState.pageNum,
      x: Math.min(dragState.startX, curX) / cRect.width,
      y: Math.min(dragState.startY, curY) / cRect.height,
      w: rawW / cRect.width,
      h: rawH / cRect.height,
    };
    dragState = null;
    openCommentDialog();
  });

  overlay.addEventListener("pointercancel", (e) => {
    if (!dragState || dragState.pointerId !== e.pointerId) return;
    dragState.ghost?.remove();
    dragState = null;
  });
}

// ===== Comment dialog =====
function openCommentDialog() {
  editingAnnotId = null;
  document.getElementById("commentDialogTitle").textContent = "Add Comment";
  document.getElementById("commentDialogSaveLabel").textContent = "Save";
  document.getElementById("commentText").value = "";
  document.getElementById("commentDialog").classList.add("open");
  document.getElementById("commentText").focus();
}

function editAnnotation(annotId) {
  const ann = annotations.find((a) => a.id === annotId);
  if (!ann) return;
  pendingBox = null;
  editingAnnotId = annotId;
  document.getElementById("commentDialogTitle").textContent = "Edit Comment";
  document.getElementById("commentDialogSaveLabel").textContent =
    "Save Changes";
  document.getElementById("commentText").value = ann.comment;
  document.getElementById("commentDialog").classList.add("open");
  document.getElementById("commentText").focus();
}

function cancelComment() {
  pendingBox = null;
  editingAnnotId = null;
  document.getElementById("commentDialog").classList.remove("open");
}
async function saveComment() {
  const text = document.getElementById("commentText").value.trim();
  if (!text || (!pendingBox && !editingAnnotId)) return;
  document.getElementById("commentDialog").classList.remove("open");

  if (editingAnnotId) {
    const annotId = editingAnnotId;
    editingAnnotId = null;
    const res = await fetch(ANNOT_API, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": CSRF_TOKEN,
      },
      body: JSON.stringify({
        action: "edit",
        id: annotId,
        comment: text,
      }),
    });
    const data = await res.json();
    if (data.ok) {
      const ann = annotations.find((a) => a.id === annotId);
      if (ann) ann.comment = text;
      renderAnnotations();
    } else {
      showToast(
        "Could not save comment: " + (data.error ?? "unknown error"),
        "error",
      );
    }
    return;
  }

  const res = await fetch(ANNOT_API, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": CSRF_TOKEN,
    },
    body: JSON.stringify({
      action: "save",
      version_id: VERSION_ID,
      page_number: pendingBox.pageNum,
      x: pendingBox.x,
      y: pendingBox.y,
      width: pendingBox.w,
      height: pendingBox.h,
      comment: text,
    }),
  });
  const data = await res.json();
  if (data.ok) {
    annotations.push({
      id: data.id,
      page_number: pendingBox.pageNum,
      x: pendingBox.x,
      y: pendingBox.y,
      width: pendingBox.w,
      height: pendingBox.h,
      comment: text,
      author: data.author,
      created_at: new Date().toISOString(),
    });
    pendingBox = null;
    renderAnnotations();
  } else if (data.queued) {
    annotations.push({
      id: "queued-" + Date.now(),
      page_number: pendingBox.pageNum,
      x: pendingBox.x,
      y: pendingBox.y,
      width: pendingBox.w,
      height: pendingBox.h,
      comment: text,
      created_at: new Date().toISOString(),
      _queued: true,
    });
    pendingBox = null;
    renderAnnotations();
  } else {
    showToast(
      "Could not save comment: " + (data.error ?? "unknown error"),
      "error",
    );
  }
}

// ===== Load + render annotations =====
function queuedAnnotationEntries() {
  return (window._actionQueue?.load() ?? []).flatMap((entry) => {
    if (entry.error || !entry.url.includes("/apply/annotate")) return [];
    try {
      return [
        {
          entry,
          body: JSON.parse(entry.body || "{}"),
        },
      ];
    } catch {
      return [];
    }
  });
}

async function loadAnnotations() {
  if (IS_READ_ONLY_FILE) return;
  try {
    const res = await fetch(ANNOT_API + "?version_id=" + VERSION_ID);
    const data = await res.json();
    annotations = Array.isArray(data) ? data : [];
    annotationsFailed = false;
  } catch (err) {
    annotations = [];
    annotationsFailed = true;
  }
  queuedAnnotationEntries().forEach(({ entry, body }) => {
    if (body.action !== "save" || body.version_id !== VERSION_ID) return;
    annotations.push({
      id: "queued-" + entry.id,
      page_number: body.page_number,
      x: body.x,
      y: body.y,
      width: body.width,
      height: body.height,
      comment: body.comment,
      created_at: entry.queuedAt,
      _queued: true,
    });
  });
  renderAnnotations();
}

async function commentsStillSyncing() {
  if (!queuedAnnotationEntries().length) return false;
  await confirmAction(
    "Some comments are still waiting to sync. Wait until they finish syncing, then try again.",
    {
      okText: "Back",
      hideCancel: true,
    },
  );
  return true;
}

window.addEventListener("online", loadAnnotations);
window.addEventListener("offline", renderSidebar);

// ===== Render annotations & sidebar =====
function renderAnnotations() {
  document.querySelectorAll(".annot-box").forEach((el) => el.remove());

  annotations.forEach((ann, idx) => {
    const overlay = document.querySelector(
      '.annot-overlay[data-page="' + ann.page_number + '"]',
    );
    if (!overlay) return;

    const canvas = overlay.closest(".page-wrapper")?.querySelector("canvas");
    const pw = canvas
      ? canvas.getBoundingClientRect().width
      : parseFloat(overlay.dataset.origW);
    const ph = canvas
      ? canvas.getBoundingClientRect().height
      : parseFloat(overlay.dataset.origH);
    const box = document.createElement("div");
    box.className = "annot-box" + (ann._queued ? " annot-box--queued" : "");
    box.dataset.annotId = ann.id;
    box.style.left = ann.x * pw + "px";
    box.style.top = ann.y * ph + "px";
    box.style.width = ann.width * pw + "px";
    box.style.height = ann.height * ph + "px";
    box.title = ann._queued
      ? "[Queued - pending sync] " + ann.comment
      : ann.comment;

    const label = document.createElement("span");
    label.className = "annot-label";
    label.textContent = idx + 1;
    box.appendChild(label);
    box.addEventListener("pointerdown", (e) => e.stopPropagation());
    box.addEventListener("click", (e) => handleAnnotBoxClick(e, ann.id));
    overlay.appendChild(box);
  });

  renderSidebar();
}

function renderSidebar() {
  if (IS_READ_ONLY_FILE) return;
  const list = document.getElementById("annotList");
  const offlineNote = document.getElementById("annotOfflineNote");
  offlineNote.hidden = navigator.onLine || !annotations.length;
  offlineNote.textContent = annotationsFailed
    ? "Saved comments unavailable offline. Showing only your queued comments."
    : "Showing saved comments (offline)";
  if (!annotations.length) {
    list.innerHTML =
      '<p class="annot-empty">' +
      (annotationsFailed || !navigator.onLine
        ? "Comments unavailable offline."
        : "No comments yet.") +
      "</p>";
    return;
  }
  list.innerHTML = annotations
    .map(
      (ann, idx) => `
    <div class="annot-item${ann._queued ? " annot-item--queued" : ""}" id="sidebar-${ann.id}" onclick="scrollToAnnotation('${ann.id}')">
        <div class="annot-item-header">
            <span class="annot-num">${idx + 1}</span>
            <span class="annot-page">Page ${ann.page_number}</span>
            ${ann._queued ? '<span class="pending-sync-badge annot-queued-badge">Pending sync</span>' : ""}
            ${
              CAN_REVIEW && !ann._queued
                ? `
            <button class="annot-edit" title="Edit comment" onclick="event.stopPropagation(); editAnnotation(${ann.id})">
                 <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#edit-icon">
                </svg>
            </button>
            <button class="annot-delete" title="Delete comment"
                onclick="event.stopPropagation(); confirmAction('Delete this comment? This cannot be undone.', { okText: 'Delete', danger: true }).then(ok => ok && deleteAnnotation(${ann.id}))">
                 <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#trash-icon">
                </svg>
            </button>`
                : ""
            }
        </div>
        <p class="annot-comment">${escapeHtml(ann.comment)}</p>
        <p class="annot-date">${ann.author ? escapeHtml(ann.author) + " &middot; " : ""}${formatAnnotDate(ann.created_at)}</p>
    </div>
`,
    )
    .join("");
}

// ===== Sidebar highlight & scroll =====
function formatAnnotDate(value) {
  if (!value) return "";
  return isNaN(new Date(value).getTime()) ? "" : formatDateTime(value);
}

function highlightSidebarItem(annotId) {
  document
    .querySelectorAll(".annot-item")
    .forEach((el) => el.classList.remove("highlight"));
  const el = document.getElementById("sidebar-" + annotId);
  if (el) {
    el.classList.add("highlight");
    el.scrollIntoView({
      behavior: "smooth",
      block: "nearest",
    });
  }
}

function highlightAnnotBox(annotId) {
  document
    .querySelectorAll(".annot-box")
    .forEach((el) => el.classList.remove("highlight"));
  document
    .querySelector('.annot-box[data-annot-id="' + annotId + '"]')
    ?.classList.add("highlight");
}

let annotPopupToken = 0;
let annotPopupScrollGuardUntil = 0;

// Waits until the element stops moving (smooth scrolling finished), then runs callback
function whenPositionSettled(el, callback, timeoutMs = 1500) {
  const start = performance.now();
  let last = "";
  let stableFrames = 0;
  (function check() {
    if (!el.isConnected) return;
    const r = el.getBoundingClientRect();
    const key = r.top + "," + r.left;
    stableFrames = key === last ? stableFrames + 1 : 0;
    last = key;
    if (stableFrames >= 6 || performance.now() - start > timeoutMs) {
      callback();
      return;
    }
    requestAnimationFrame(check);
  })();
}

function scrollToAnnotation(annotId) {
  const box = document.querySelector(
    '.annot-box[data-annot-id="' + annotId + '"]',
  );
  if (box)
    box.scrollIntoView({
      behavior: "smooth",
      block: "center",
    });
  highlightSidebarItem(annotId);
  highlightAnnotBox(annotId);

  closeAnnotPopup();
  const token = ++annotPopupToken;
  const ann = annotations.find((a) => String(a.id) === String(annotId));
  if (!box || !ann) return;
  whenPositionSettled(box, () => {
    if (token !== annotPopupToken || !box.isConnected) return;
    annotPopupScrollGuardUntil = performance.now() + 250;
    showAnnotPopup(ann.id, 0, 0, box.getBoundingClientRect());
  });
}

// ===== Comment popup (small screens) =====
const MOBILE_MQ = window.matchMedia("(max-width: 767px)");
const TOUCH_MQ = window.matchMedia("(pointer: coarse)");
const annotPopup = document.getElementById("annotPopup");

// ===== Toolbar hint =====
function updateToolbarHint() {
  const hint = document.getElementById("toolbarHint");
  if (!hint) return;
  hint.textContent =
    zoom > 1 && TOUCH_MQ.matches
      ? "Zoomed in: drag to move around. Press Fit width to add a comment."
      : MOBILE_MQ.matches
        ? "Tap and drag on the document to add a comment."
        : "Click and drag on the document to add a comment.";
}
updateToolbarHint();
MOBILE_MQ.addEventListener("change", updateToolbarHint);

function handleAnnotBoxClick(e, annotId) {
  highlightSidebarItem(annotId);
  highlightAnnotBox(annotId);
  e.stopPropagation();
  showAnnotPopup(annotId, e.clientX, e.clientY);
}

// ===== Comment popup (small screens) =====
function showAnnotPopup(annotId, x, y, anchorRect = null) {
  const idx = annotations.findIndex((a) => a.id === annotId);
  if (idx === -1) return;
  const ann = annotations[idx];

  document.getElementById("annotPopupNum").textContent = idx + 1;
  document.getElementById("annotPopupPage").textContent =
    "Page " + ann.page_number;
  document.getElementById("annotPopupComment").textContent = ann.comment;
  document.getElementById("annotPopupDate").textContent = [
    ann.author,
    formatAnnotDate(ann.created_at),
  ]
    .filter(Boolean)
    .join(" \u00b7 ");

  const actions = document.getElementById("annotPopupActions");
  const editBtn = document.getElementById("annotPopupEdit");
  const deleteBtn = document.getElementById("annotPopupDelete");
  if (CAN_REVIEW && !ann._queued) {
    actions.hidden = false;

    editBtn.onclick = () => {
      closeAnnotPopup();
      editAnnotation(annotId);
    };

    deleteBtn.onclick = () => {
      confirmAction("Delete this comment? This cannot be undone.", {
        okText: "Delete",
        danger: true,
      }).then((ok) => ok && deleteAnnotation(annotId));
      closeAnnotPopup();
    };
  } else {
    actions.hidden = true;
    editBtn.onclick = null;
    deleteBtn.onclick = null;
  }

  annotPopup.style.left = "0px";
  annotPopup.style.top = "0px";
  annotPopup.classList.add("open");

  const margin = 10;
  const rect = annotPopup.getBoundingClientRect();
  let left = x + margin;
  let top = y + margin;
  if (anchorRect) {
    // Beside the comment box: right of it, else left of it, else below/above it
    left = anchorRect.right + margin;
    top = anchorRect.top;
    if (left + rect.width > window.innerWidth - margin)
      left = anchorRect.left - rect.width - margin;
    if (left < margin) {
      left = Math.min(
        Math.max(anchorRect.left, margin),
        window.innerWidth - rect.width - margin,
      );
      top = anchorRect.bottom + margin;
      if (top + rect.height > window.innerHeight - margin)
        top = anchorRect.top - rect.height - margin;
    }
    top = Math.min(
      Math.max(top, margin),
      window.innerHeight - rect.height - margin,
    );
  } else {
    if (left + rect.width > window.innerWidth - margin)
      left = x - rect.width - margin;
    if (top + rect.height > window.innerHeight - margin)
      top = y - rect.height - margin;
  }
  if (left < margin) left = margin;
  if (top < margin) top = margin;

  annotPopup.style.left = left + "px";
  annotPopup.style.top = top + "px";
}

function closeAnnotPopup() {
  annotPopup.classList.remove("open");
}

document
  .getElementById("annotPopupClose")
  .addEventListener("click", closeAnnotPopup);

document.addEventListener("click", (e) => {
  if (
    annotPopup.classList.contains("open") &&
    !annotPopup.contains(e.target) &&
    !e.target.closest(".annot-box")
  ) {
    closeAnnotPopup();
  }
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeAnnotPopup();
});

window.addEventListener(
  "scroll",
  () => {
    if (performance.now() < annotPopupScrollGuardUntil) return;
    closeAnnotPopup();
  },
  true,
);
MOBILE_MQ.addEventListener("change", closeAnnotPopup);

// ===== Delete annotation =====
async function deleteAnnotation(annotId) {
  const res = await fetch(ANNOT_API, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": CSRF_TOKEN,
    },
    body: JSON.stringify({
      action: "delete",
      id: annotId,
    }),
  });
  const data = await res.json();
  if (data.ok) {
    annotations = annotations.filter((a) => a.id !== annotId);
    renderAnnotations();
  }
}

// ===== Status update =====
async function updateStatus(btn, newStatus) {
  if (await commentsStillSyncing()) return;
  setButtonBusy(btn, true);
  try {
    const res = await fetch(STATUS_API, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": CSRF_TOKEN,
      },
      body: JSON.stringify({
        protocol_id: PROTOCOL_ID,
        status: newStatus,
      }),
    });
    const data = await res.json();
    if (data.ok) {
      window.location.href = BACK_URL;
    } else if (data.queued) {
      setButtonBusy(btn, false);
    } else {
      setButtonBusy(btn, false);
      showToast(data.error ?? "unknown error", "error");
    }
  } catch (err) {
    setButtonBusy(btn, false);
    showToast(
      "Could not reach the server and the action could not be queued. Please check your connection.",
      "error",
    );
  }
}
