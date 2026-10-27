// ===== Personnel protocol actions =====
// One table for every status action on the inbox. The row buttons
// ([data-action]) and the three dots menu (protocol-menu.js) both dispatch
// through window.protocolRowActions, each action receives (protocolId, btn).
// Needs the inbox page globals: protocolsData, protocolsList, activeFilter,
// ROOT_URL, CSRF_TOKEN and the API urls.

const rowProtocol = (protocolId) =>
  protocolsData.find((p) => p.protocol_id == protocolId);

function openProtocolViewer(protocolId) {
  window.location.href =
    ROOT_URL +
    "/apply/viewer/" +
    protocolId +
    "?from=" +
    encodeURIComponent(activeFilter);
}

function confirmThen(message, okText, onConfirm, options = {}) {
  confirmAction(message, { okText, cancelText: "Cancel", ...options }).then(
    (ok) => ok && onConfirm(),
  );
}

// ===== API calls =====
async function postProtocolAction(url, body, btn, errorFallback, busyText) {
  if (btn && busyText !== null) setButtonBusy(btn, true, busyText);
  try {
    const res = await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": CSRF_TOKEN,
      },
      body: JSON.stringify(body),
    });
    const data = await res.json();
    if (data.ok) {
      window.location.reload();
      return;
    }
    setButtonBusy(btn, false);
    if (data.queued) {
      if (typeof closeReviewPaymentModal === "function")
        closeReviewPaymentModal();
    } else {
      showToast(data.error ?? errorFallback, "error");
    }
  } catch (err) {
    setButtonBusy(btn, false);
    showToast(
      navigator.onLine
        ? "Network error. Please try again."
        : "You are offline. The action could not be queued. Please try again when reconnected.",
      "error",
    );
  }
}

const submitPaymentVerify = (protocolId) =>
  postProtocolAction(
    VERIFY_PAYMENT_API,
    { protocol_id: protocolId },
    document.getElementById("reviewPaymentApproveBtn"),
    "Could not mark as paid.",
    null,
  );

const submitPaymentUndo = (protocolId, btn) =>
  postProtocolAction(
    UNDO_PAYMENT_API,
    { protocol_id: protocolId },
    btn,
    "Could not undo payment mark.",
    "Undoing...",
  );

const submitHardCopy = (protocolId, action, btn) =>
  postProtocolAction(
    HARD_COPY_API,
    { protocol_id: protocolId, action },
    btn,
    "Could not update the hard copy pickup.",
  );

const submitStatusChange = (protocolId, status, btn, intent) =>
  postProtocolAction(
    STATUS_API,
    { protocol_id: protocolId, status, intent },
    btn,
    "Could not update status.",
  );

// ===== Action table =====
window.protocolRowActions = {
  open: openProtocolViewer,
  view: openProtocolViewer,

  "view-clearance": (id) =>
    window.open(CLEARANCE_VIEW_URL + id, "_blank", "noopener"),

  "mark-endorsed": (id, btn) =>
    confirmThen(
      "Mark this protocol as endorsed? Double-check that payment is verified and the signed scan is correct before proceeding.",
      "Mark as Endorsed",
      () => submitStatusChange(id, "Endorsed", btn),
    ),

  "revert-endorsed": (id, btn) =>
    confirmThen(
      'Revert this protocol back to Reviewed? Use this if "Mark as Endorsed" was clicked by mistake.',
      "Revert",
      () => submitStatusChange(id, "Reviewed", btn, "revert"),
    ),

  "revert-endorsed-to-review": (id) =>
    openDeleteModal(id, rowProtocol(id)?.research_title ?? "", {
      mode: "revert",
    }),

  "mark-approved": (id, btn) =>
    confirmThen(
      "Mark this protocol as approved? Double-check that the attached clearance is correct before proceeding.",
      "Mark as Approved",
      () => submitStatusChange(id, "Approved", btn),
    ),

  "revert-approved": (id, btn) =>
    confirmThen(
      'Revert this protocol back to Endorsed? Use this if "Mark as Approved" was clicked by mistake.',
      "Revert",
      () => submitStatusChange(id, "Endorsed", btn, "revert"),
    ),

  "upload-signed-scan": (id) =>
    openSignedScanModal(id, rowProtocol(id)?.research_title ?? ""),

  "assign-ipn": (id) => openNumberModal("ipn", id, ""),

  "edit-ipn": (id) => {
    const protocol = rowProtocol(id);
    const open = () => openNumberModal("ipn", id, protocol?.reference_no ?? "");
    if (!protocol?.latest_signed_scan_version_id) return open();
    confirmThen(
      "This protocol already has a signed scan. Changing the IPN will make the signed scan not match the record.",
      "Edit IPN",
      open,
      { danger: true },
    );
  },

  "undo-payment": (id, btn) =>
    confirmThen(
      'Undo this "Mark as Paid"? The protocol will go back to "Proof Submitted".',
      "Undo",
      () => submitPaymentUndo(id, btn),
    ),

  "release-hard-copy": (id, btn) =>
    confirmThen(
      "Confirm that the researcher has picked up the embossed hard copy? This will be recorded under your name.",
      "Confirm Pickup",
      () => submitHardCopy(id, "release", btn),
    ),

  "undo-hard-copy": (id, btn) =>
    confirmThen(
      "Undo the pickup? The protocol will show as awaiting pickup again.",
      "Undo",
      () => submitHardCopy(id, "undo", btn),
    ),

  "review-payment": (id) => {
    const p = rowProtocol(id);
    openReviewPaymentModal(
      id,
      p?.research_title ?? "",
      p?.payment_method ?? "online",
      `${p?.first_name ?? ""} ${p?.last_name ?? ""}`.trim(),
      p?.school ?? "",
    );
  },
};

// ===== Row buttons =====
protocolsList?.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-action]");
  const protocolId = parseInt(
    btn?.closest(".protocol")?.dataset.protocolId,
    10,
  );
  if (protocolId) protocolRowActions[btn.dataset.action]?.(protocolId, btn);
});
