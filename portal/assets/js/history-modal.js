// ===== History modal =====
const historyBackdrop = document.getElementById("historyModalBackdrop");
const historyOfflineMessage = (window.historyModalConfig || {}).offlineMessage;

// ===== Open modal =====
function openHistoryModal(protocolId, title) {
  const body = document.getElementById("historyModalBody");
  const renameToggle = document.getElementById("renameHistoryToggle");
  const renamePanel = document.getElementById("renameHistoryPanel");

  document.getElementById("historyModalTitle").textContent = title;
  body.innerHTML = '<p class="helper history-loading">Loading&hellip;</p>';
  renameToggle.hidden = true;
  renameToggle.setAttribute("aria-expanded", "false");
  renamePanel.hidden = true;
  renamePanel.innerHTML = "";

  historyBackdrop.classList.add("open");

  fetch(ROOT_URL + "/apply/allversions/" + protocolId)
    .then((r) => r.json())
    .then((data) => {
      if (data.error) {
        body.innerHTML =
          '<p class="helper history-error">' + escapeHtml(data.error) + "</p>";
        return;
      }
      renderRenameHistory(data.title_history);
      renderHistory(data);
    })
    .catch(() => {
      body.innerHTML = historyOfflineMessage
        ? '<p class="helper history-offline">' +
          escapeHtml(historyOfflineMessage) +
          "</p>"
        : '<p class="helper history-error">Network error. Please try again.</p>';
    });
}

// ===== Rename history =====
function renderRenameHistory(titleHistory) {
  const renameToggle = document.getElementById("renameHistoryToggle");
  const renamePanel = document.getElementById("renameHistoryPanel");

  if (!titleHistory || titleHistory.length === 0) {
    renameToggle.hidden = true;
    return;
  }

  renamePanel.innerHTML =
    '<div class="rename-history-panel-header">Title Name History</div>' +
    titleHistory
      .map((h) => {
        const who = h.changed_by_name
          ? `${escapeHtml(h.changed_by_role ? h.changed_by_role.charAt(0).toUpperCase() + h.changed_by_role.slice(1) : "")} - ${escapeHtml(h.changed_by_name)}`
          : "Initial title";
        return `<div class="rename-history-entry">
            <div class="rename-history-entry-title">${escapeHtml(h.title)}</div>
            <div class="rename-history-entry-meta">${who} &middot; ${formatDateTime(h.changed_at)}</div>
        </div>`;
      })
      .join("");

  renameToggle.hidden = false;
  renameToggle.onclick = () => {
    const isOpen = renameToggle.getAttribute("aria-expanded") === "true";
    renameToggle.setAttribute("aria-expanded", String(!isOpen));
    renamePanel.hidden = isOpen;
  };
}

// ===== Close modal =====
function closeHistoryModal() {
  historyBackdrop.classList.remove("open");
  closeFilePopup();
}

historyBackdrop.addEventListener("click", (e) => {
  if (e.target === historyBackdrop) closeHistoryModal();
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeHistoryModal();
});

// ===== Row builders =====
function buildReturnLine(reason) {
  const bits = [];
  if (reason) {
    if (reason.wrong_cert) bits.push("Wrong / invalid training certificate");
    if (reason.other_reason && !reason.comment) bits.push("Other");
    if (reason.comment) bits.push(reason.comment);
  }
  const when = reason && reason.created_at ? formatDate(reason.created_at) : "";
  const note = bits.join(" \u00b7 ");
  return `<div class="history-return-line">
                <span class="history-tag history-tag--returned">Returned for revision${when ? " &middot; " + when : ""}</span>
                ${note ? `<span class="history-return-note-text"><span class="history-return-note-label">With note:</span> <span class="history-return-note-body">${escapeHtml(note)}</span></span>` : ""}
            </div>`;
}

function signedScanLabel(referenceNo) {
  return referenceNo
    ? "Protocol Signed by IACUC Chair \u00b7 IPN " + referenceNo
    : "Protocol Signed by IACUC Chair";
}

function buildVersionRows(versions, protocolId, currentStatus) {
  if (!versions || versions.length === 0) return "";

  const titles = versions.map(
    (v) => v.title_at_version || v.original_name || "",
  );

  const rows = versions
    .map((v, index) => {
      const isLatest = index === 0;
      const isOldest = index === versions.length - 1;
      const day = formatDate(v.uploaded_at);
      const time = formatTime(v.uploaded_at);

      const wasReturned =
        !isLatest ||
        String(currentStatus || "").toLowerCase() === "needs revision";

      let titleNote = "";
      if (isOldest) {
        if (versions.length > 1 && titles[index] !== titles[index - 1])
          titleNote = "Titled: " + titles[index];
      } else if (titles[index] !== titles[index + 1]) {
        titleNote = "Renamed to: " + titles[index];
      }

      return `
            <div class="history-entry">
                <div class="history-row history-row--round${isLatest ? " history-row--latest" : ""}">
                    <div class="history-row-detail">
                        <span class="history-filename">${day}</span>
                        <span class="helper">${escapeHtml(v.round_label)} &middot; ${time}</span>
                        ${titleNote ? `<span class="helper history-title-note" title="${escapeHtml(titleNote)}">${escapeHtml(titleNote)}</span>` : ""}
                        ${isLatest ? '<div class="history-tags"><span class="history-latest-badge">Current</span></div>' : ""}
                        ${wasReturned ? buildReturnLine(v.return_reason) : ""}
                    </div>
                    <a class="button history-open-btn" href="${ROOT_URL}/apply/viewer/${protocolId}/${v.id}">
                        <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#review-icon" />
                        </svg>
                        Open
                    </a>
                </div>
            </div>`;
    })
    .join("");

  return `<div class="history-section-label">Protocol Submissions</div>${rows}`;
}

function buildSimpleFileSection(files, label, heading) {
  if (!files || files.length === 0) return "";
  const rows = files
    .map((v, index) => {
      const isLatest = index === 0;
      const who = v.first_name
        ? `${escapeHtml(v.first_name)} ${escapeHtml(v.last_name || "")}`
        : "";
      const fileName = escapeHtml(v.original_name);

      return `
            <div class="history-entry">
                <div class="history-row">
                    <div class="history-row-meta">
                        <span class="history-ver" title="Version no. (number of times this file was uploaded)">v${v.version_number}</span>
                        ${isLatest ? '<span class="history-latest-badge">Latest</span>' : ""}
                    </div>
                    <div class="history-row-detail">
                        <span class="history-filename" title="${fileName}">${fileName}</span>
                        <span class="helper">${who ? who + " &middot; " : ""}${formatDateTime(v.uploaded_at)}</span>
                    </div>
                    <button type="button" class="button history-open-btn"
                        onclick="openFilePopup('${v.file_url}', '${escapeHtml(label)}')">
                        <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#review-icon" />
                        </svg>
                        Open
                    </button>
                </div>
            </div>`;
    })
    .join("");

  return `<div class="history-section-label">${escapeHtml(heading || label)}</div>${rows}`;
}

function buildPaymentHistorySection(events) {
  if (!events || events.length === 0) return "";

  const rows = events
    .map((e) => {
      const when = `${formatDate(e.at)} &middot; ${formatTime(e.at)}`;
      let title = "";
      let detail = "";
      let action = "";

      if (e.type === "proof") {
        title = "Proof of payment submitted";
        detail = `<span class="helper">${escapeHtml(e.original_name || "")}</span>`;
        action = `<button type="button" class="button history-open-btn"
                        onclick="openFilePopup('${e.file_url}', 'Proof of Payment')">
                        <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#review-icon" />
                        </svg>
                        Open
                    </button>`;
      } else if (e.type === "rejected") {
        title = "Payment rejected";
        detail = `<div class="history-return-line">
                <span class="history-return-note-text"><span class="history-return-note-label">Reason:</span> <span class="history-return-note-body">${escapeHtml(e.comment || "No reason given.")}</span></span>
            </div>`;
      } else {
        title = "Payment verified";
        detail = `<span class="helper">${e.method === "in_person" ? "Paid in person at CCARD" : "Paid online"}</span>`;
      }

      return `
            <div class="history-entry">
                <div class="history-row">
                    <div class="history-row-detail">
                        <span class="history-filename">${title}</span>
                        <span class="helper">${when}</span>
                        ${detail}
                    </div>
                    ${action}
                </div>
            </div>`;
    })
    .join("");

  return `<div class="history-section-label">Payment History</div>${rows}`;
}

// ===== Render history =====
function renderHistory(data) {
  const sections = [
    buildVersionRows(data.protocol_files, data.protocol_id, data.status),
    buildPaymentHistorySection(data.payment_history),
    buildSimpleFileSection(
      data.signed_scan_files,
      "Signed Scan",
      signedScanLabel(data.reference_no),
    ),
    buildSimpleFileSection(data.clearance_files, "Animal Research Clearance"),
  ].filter(Boolean);

  document.getElementById("historyModalBody").innerHTML = sections.length
    ? sections.join("")
    : '<p class="helper" style="padding:1.5rem">No submission history found.</p>';
}
