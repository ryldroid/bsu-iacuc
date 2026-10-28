// ===== History modal =====
const historyBackdrop = document.getElementById("historyModalBackdrop");
const historyOfflineMessage = (window.historyModalConfig || {}).offlineMessage;
let historyLastFocus = null;

// ===== Open modal =====
function openHistoryModal(protocolId, title) {
  const body = document.getElementById("historyModalBody");

  document.getElementById("historyModalTitle").textContent = title;
  body.innerHTML = '<p class="helper history-loading">Loading&hellip;</p>';
  historyLastFocus = document.activeElement;
  historyBackdrop.classList.add("open");
  document.getElementById("historyModalClose").focus();

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
  if (historyLastFocus) historyLastFocus.focus();
}

historyBackdrop.addEventListener("click", (e) => {
  if (e.target === historyBackdrop) closeHistoryModal();
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && historyBackdrop.classList.contains("open"))
    closeHistoryModal();
});

// ===== Row builders =====
const HISTORY_ICON_SIZE = 16;

function historyEvent(tone, icon, title, time, detail, action) {
  return `<div class="payment-event payment-tone-${tone}">
            <span class="payment-event-icon">
                <svg width="${HISTORY_ICON_SIZE}" height="${HISTORY_ICON_SIZE}" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#${icon}" /></svg>
            </span>
            <div class="payment-event-body">
                <span class="payment-event-title">${title}</span>
                <span class="payment-event-time">${time}</span>
                ${detail}
            </div>
            ${action || ""}
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
      const isScan = v.file_type === "signed_scan";
      const roundIndex = rounds.indexOf(v);
      const wasReturned =
        !isScan &&
        (roundIndex > 0 ||
          String(data.status || "").toLowerCase() === "needs revision");

      const titleNote = isScan
        ? signedScanLabel(data.reference_no)
        : roundTitleNote(titles, roundIndex);

      const tone = isScan ? "success" : wasReturned ? "warning" : "info";
      const icon = isScan
        ? "shield-check-icon"
        : wasReturned
          ? "alert-triangle-icon"
          : "upload-icon";
      const badge =
        index === 0 ? ' <span class="history-latest-badge">Current</span>' : "";

      const detail = titleNote
        ? `<span class="payment-event-detail">${escapeHtml(titleNote)}</span>`
        : "";

      const action = `<a class="button button-compact history-open" href="${ROOT_URL}/apply/viewer/${data.viewer_ref}/${v.id}" aria-label="Open ${escapeHtml(v.label)}, ${formatDate(v.uploaded_at)}">
                <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#eye-icon" /></svg>
                Open
            </a>`;

      return historyEvent(
        tone,
        icon,
        escapeHtml(v.label) + badge,
        `${escapeHtml(formatDate(v.uploaded_at))} &middot; ${escapeHtml(formatTime(v.uploaded_at))}`,
        detail,
        action,
      );
    })
    .join("");

  return historySection("Protocol Submissions", rows);
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

      return historyEvent(
        "info",
        "edit-icon",
        h.changed_by_name ? "Title changed" : "Initial title",
        `${who} &middot; ${escapeHtml(formatDateTime(h.changed_at))}`,
        `<span class="payment-event-detail">${escapeHtml(h.title)}</span>`,
      );
    })
    .join("");

  return historySection("Title Name History", rows);
}

const CERT_HISTORY_EVENTS = {
  submitted: ["info", "upload-icon", "Certificate submitted"],
  verified: ["success", "shield-check-icon", "Certificate verified"],
  rejected: [
    "warning",
    "alert-triangle-icon",
    "Certificate resubmission requested",
  ],
};

function buildCertHistorySection(certHistory) {
  if (!certHistory || certHistory.length === 0) return "";

  const rows = certHistory
    .map((h) => {
      const [tone, icon, title] = CERT_HISTORY_EVENTS[h.action] || [
        "info",
        "history-icon",
        h.action,
      ];
      const who = h.actor_name ? escapeHtml(h.actor_name) + " &middot; " : "";
      const detail = h.note
        ? `<span class="payment-event-detail">${escapeHtml(h.note)}</span>`
        : "";
      const action =
        h.action === "submitted"
          ? `<a class="button button-compact history-open" href="${escapeHtml(h.file_url)}" target="_blank" rel="noopener" aria-label="Open ${escapeHtml(h.original_name || "certificate")}">
                <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#eye-icon" /></svg>
                Open
            </a>`
          : "";

      return historyEvent(
        tone,
        icon,
        title,
        `${who}${escapeHtml(formatDateTime(h.created_at))}`,
        detail,
        action,
      );
    })
    .join("");

  return historySection("Training Certificate History", rows);
}

function historySection(label, rows) {
  return `<section class="history-section">
            <p class="history-section-label">${label}</p>
            <div class="payment-timeline">${rows}</div>
        </section>`;
}

// ===== Render history =====
function renderHistory(data) {
  const sections = [
    buildVersionRows(data),
    buildTitleHistorySection(data.title_history),
    buildCertHistorySection(data.cert_history),
  ].filter(Boolean);

  document.getElementById("historyModalBody").innerHTML = sections.length
    ? sections.join("")
    : '<p class="helper history-empty">No submission history found.</p>';
}
