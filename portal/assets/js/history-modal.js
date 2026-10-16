// ===== History modal =====
const historyBackdrop = document.getElementById("historyModalBackdrop");
const historyOfflineMessage = (window.historyModalConfig || {}).offlineMessage;

// ===== Open modal =====
function openHistoryModal(protocolId, title) {
  const body = document.getElementById("historyModalBody");

  document.getElementById("historyModalTitle").textContent = title;
  body.innerHTML = '<p class="helper history-loading">Loading&hellip;</p>';
  historyBackdrop.classList.add("open");

  fetch(ROOT_URL + "/apply/allversions/" + protocolId)
    .then((r) => r.json())
    .then((data) => {
      if (data.error) {
        body.innerHTML =
          '<p class="helper history-error">' + escapeHtml(data.error) + "</p>";
        return;
      }
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

// ===== Close modal =====
function closeHistoryModal() {
  historyBackdrop.classList.remove("open");
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
  const by =
    reason && reason.returned_by
      ? " &middot; " + escapeHtml(reason.returned_by)
      : "";
  return `<div class="history-return-line">
                <span class="history-tag history-tag--returned">Returned for revision${when ? " &middot; " + when : ""}${by}</span>
                ${note ? `<span class="history-return-note-text"><span class="history-return-note-label">With note:</span> <span class="history-return-note-body">${escapeHtml(note)}</span></span>` : ""}
            </div>`;
}

function signedScanLabel(referenceNo) {
  return referenceNo
    ? "Protocol Signed by IACUC Chairperson \u00b7 IPN " + referenceNo
    : "Protocol Signed by IACUC Chairperson";
}

function roundTitleNote(titles, i) {
  if (i === titles.length - 1) {
    return titles.length > 1 && titles[i] !== titles[i - 1]
      ? "Titled: " + titles[i]
      : "";
  }
  return titles[i] !== titles[i + 1] ? "Renamed to: " + titles[i] : "";
}

function buildVersionRows(data) {
  const versions = data.versions;
  if (!versions || versions.length === 0) return "";

  const rounds = versions.filter((v) => v.file_type === "protocol");
  const titles = rounds.map((v) => v.title_at_version || v.original_name || "");

  const rows = versions
    .map((v, index) => {
      const isCurrent = index === 0;
      const isScan = v.file_type === "signed_scan";
      const roundIndex = rounds.indexOf(v);

      const titleNote = isScan
        ? signedScanLabel(data.reference_no)
        : roundTitleNote(titles, roundIndex);
      const wasReturned =
        !isScan &&
        (roundIndex > 0 ||
          String(data.status || "").toLowerCase() === "needs revision");

      return `
            <div class="history-entry">
                <div class="history-row history-row--round${isCurrent ? " history-row--latest" : ""}">
                    <div class="history-row-detail">
                        <span class="history-filename">${formatDate(v.uploaded_at)}</span>
                        <span class="helper">${escapeHtml(v.label)} &middot; ${formatTime(v.uploaded_at)}</span>
                        ${titleNote ? `<span class="helper history-title-note" title="${escapeHtml(titleNote)}">${escapeHtml(titleNote)}</span>` : ""}
                        ${isCurrent ? '<div class="history-tags"><span class="history-latest-badge">Current</span></div>' : ""}
                        ${wasReturned ? buildReturnLine(v.return_reason) : ""}
                    </div>
                    <a class="button history-open-btn" href="${ROOT_URL}/apply/viewer/${data.protocol_id}/${v.id}">
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

function buildTitleHistorySection(titleHistory) {
  if (!titleHistory || titleHistory.length === 0) return "";

  const rows = titleHistory
    .map((h) => {
      const role = h.changed_by_role
        ? h.changed_by_role.charAt(0).toUpperCase() + h.changed_by_role.slice(1)
        : "";
      const who = h.changed_by_name
        ? `${escapeHtml(role)} - ${escapeHtml(h.changed_by_name)}`
        : "Initial title";

      return `
            <div class="history-entry">
                <div class="history-row">
                    <div class="history-row-detail">
                        <span class="history-filename" title="${escapeHtml(h.title)}">${escapeHtml(h.title)}</span>
                        <span class="helper">${who} &middot; ${formatDateTime(h.changed_at)}</span>
                    </div>
                </div>
            </div>`;
    })
    .join("");

  return `<div class="history-section-label">Title Name History</div>${rows}`;
}

// ===== Render history =====
function renderHistory(data) {
  const sections = [
    buildVersionRows(data),
    buildTitleHistorySection(data.title_history),
  ].filter(Boolean);

  document.getElementById("historyModalBody").innerHTML = sections.length
    ? sections.join("")
    : '<p class="helper" style="padding:1.5rem">No submission history found.</p>';
}
