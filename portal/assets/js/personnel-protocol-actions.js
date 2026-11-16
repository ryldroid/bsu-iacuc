// ===== Personnel protocol actions =====
// One table for every status action on the inbox. The row buttons
// ([data-action]) and the three dots menu (protocol-menu.js) both dispatch
// through window.protocolRowActions, each action receives (protocolId, btn).
// Needs the inbox page globals: protocolsData, protocolsList, showArchived,
// ROOT_URL, CSRF_TOKEN and the API urls.

const rowProtocol = (protocolId) =>
  protocolsData.find((p) => p.protocol_id == protocolId);

function openProtocolViewer(protocolId) {
  window.location.href =
    ROOT_URL +
    "/apply/viewer/" +
    (rowProtocol(protocolId)?.viewer_ref ?? protocolId) +
    (showArchived ? "?from=archived" : "");
}

const protocolName = (protocolId) =>
  confirmName(rowProtocol(protocolId)?.research_title ?? "") || "this protocol";

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
        ...csrfHeaders(),
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

  "endorse-with-proof": (id) => openEndorsementModal([id]),

  "view-endorsement-proof": (id) =>
    window.open(
      ROOT_URL + "/apply/file/" + rowProtocol(id)?.latest_endorsement_proof_version_id,
      "_blank",
      "noopener",
    ),

  "revert-reviewed": (id, btn) =>
    confirmThen(
      `Undo the review of ${protocolName(id)}? It will go back to **To Review** and its record will be removed. Use this if **Finish Review** was clicked by mistake.`,
      "Undo",
      () => submitStatusChange(id, "Under Review", btn, "revert"),
    ),

  "revert-endorsed": (id, btn) =>
    confirmThen(
      `Revert ${protocolName(id)} back to **Reviewed**? Use this if the protocol was endorsed by mistake.`,
      "Revert",
      () => submitStatusChange(id, "Reviewed", btn, "revert"),
    ),

  "return-endorsed-for-revision": (id) =>
    openDeleteModal(id, rowProtocol(id)?.research_title ?? "", {
      mode: "agencyReturn",
    }),

  "revert-approved": (id, btn) =>
    confirmThen(
      `Revert ${protocolName(id)} back to **Endorsed**? Use this if the clearance was wrong. Its image goes back to **Assign Clearances** so you can match it again.`,
      "Revert",
      () => submitStatusChange(id, "Endorsed", btn, "revert"),
    ),

  restore: (id, btn) =>
    confirmThen(
      `Restore ${protocolName(id)} to the main list? ${
        rowProtocol(id)?.deleted_at
          ? "The researcher will be able to see it again."
          : "The researcher will be able to continue it."
      }`,
      "Restore",
      () =>
        postProtocolAction(
          RESTORE_API,
          { protocol_id: id },
          btn,
          "Could not restore the protocol.",
          "Restoring...",
        ),
    ),

  "assign-ipn": (id) => openNumberModal("ipn", id, ""),

  "edit-ipn": (id) =>
    openNumberModal("ipn", id, rowProtocol(id)?.reference_no ?? ""),

  "undo-payment": (id, btn) =>
    confirmThen(
      `Undo **Mark as Paid** for ${protocolName(id)}? It will go back to **Proof Submitted**.`,
      "Undo",
      () => submitPaymentUndo(id, btn),
    ),

  "release-hard-copy": (id, btn) =>
    confirmThen(
      `Confirm that the researcher has picked up the embossed hard copy of ${protocolName(id)}? This will be recorded under your name.`,
      "Confirm Pickup",
      () => submitHardCopy(id, "release", btn),
    ),

  "undo-hard-copy": (id, btn) =>
    confirmThen(
      `Undo the pickup of ${protocolName(id)}? It will show as awaiting pickup again.`,
      "Undo",
      () => submitHardCopy(id, "undo", btn),
    ),

  "review-payment": (id) => {
    const p = rowProtocol(id);
    openReviewPaymentModal(
      id,
      p?.research_title ?? "",
      p?.payment_method ?? "online",
      p?.payment_receipt_no ?? "",
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
