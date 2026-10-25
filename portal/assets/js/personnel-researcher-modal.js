// ===== Researcher details modal =====
const researcherBackdrop = document.getElementById("researcherModalBackdrop");
const researcherModalBody = document.getElementById("researcherModalBody");
let currentResearcherId = null;
let researcherModalLocked = false;

const CERT_STATUS_LABELS = {
  pending: "Pending",
  verified: "Verified",
  rejected: "Resubmission requested",
};
const CERT_STATUS_BADGES = {
  pending: "payment-badge--proof_submitted",
  verified: "payment-badge--paid",
  rejected: "payment-badge--rejected",
};
const CERT_ACTION_LABELS = {
  submitted: "Submitted",
  verified: "Verified",
  rejected: "Resubmission requested",
};

// ===== Lock (mandatory review: no way to dismiss until a decision is saved) =====
function setResearcherModalLocked(locked) {
  researcherModalLocked = locked;
  researcherBackdrop.querySelector(".modal-close").hidden = locked;
}

function announceCertState(status) {
  document.dispatchEvent(
    new CustomEvent("cert-reviewed", { detail: { status } }),
  );
}

// ===== Open & render researcher details =====
function openResearcherModal(userId, fallbackName) {
  currentResearcherId = userId;
  document.getElementById("researcherModalName").textContent =
    fallbackName || "";
  document.getElementById("researcherModalBody").innerHTML =
    '<p class="helper history-loading">Loading…</p>';
  researcherBackdrop.classList.add("open");

  if (!userId) {
    document.getElementById("researcherModalBody").innerHTML =
      '<p class="helper history-error">No account information available for this researcher.</p>';
    return;
  }

  fetch(
    ROOT_URL + "/personnel/researcher_details?id=" + encodeURIComponent(userId),
  )
    .then((r) => r.json())
    .then((data) => {
      if (!data.ok) {
        document.getElementById("researcherModalBody").innerHTML =
          '<p class="helper history-error">' +
          escapeHtml(data.message || "Could not load researcher details.") +
          "</p>";
        return;
      }
      renderResearcherDetails(data.data);
    })
    .catch(() => {
      document.getElementById("researcherModalBody").innerHTML =
        '<p class="helper history-offline">Researcher details are not available offline. It will load once you reconnect.</p>';
    });
}

function renderResearcherDetails(d) {
  document.getElementById("researcherModalName").textContent = (
    d.first_name +
    " " +
    d.last_name
  ).trim();

  const joined = d.created_at ? formatDate(d.created_at) : "N/A";

  const rows = [
    ["Username", d.username],
    ["Email", d.email],
    ["Phone", formatPhone(d.phone_number) || "N/A"],
    ["School", d.school || "N/A"],
    ["Role", d.role ? d.role.charAt(0).toUpperCase() + d.role.slice(1) : "N/A"],
    [
      "Account status",
      d.status ? d.status.charAt(0).toUpperCase() + d.status.slice(1) : "N/A",
    ],
    ["Joined", joined],
  ]
    .map(
      ([label, value]) => `
        <div class="researcher-detail-row">
            <span class="researcher-detail-label">${escapeHtml(label)}</span>
            <span class="researcher-detail-value">${escapeHtml(value)}</span>
        </div>
    `,
    )
    .join("");

  const protocolsHtml =
    d.protocols && d.protocols.length
      ? d.protocols
          .map(
            (p) => `
            <li class="researcher-protocol-item">
                <span class="researcher-protocol-title">${escapeHtml(p.research_title)}</span>
                <span class="researcher-protocol-meta">
                    ${escapeHtml(p.reference_no || "")} &middot; ${escapeHtml(p.status)}
                    ${p.submitted_at ? " &middot; " + formatDate(p.submitted_at) : ""}
                </span>
            </li>
        `,
          )
          .join("")
      : '<li class="researcher-protocol-item researcher-protocol-empty">No protocols submitted yet.</li>';

  document.getElementById("researcherModalBody").innerHTML = `
        <div class="researcher-detail-rows">${rows}</div>
        ${renderCertSection(d.cert)}
        <div class="researcher-protocol-section">
            <p class="researcher-protocol-heading">Protocols (${d.protocol_count})</p>
            <ul class="researcher-protocol-list">${protocolsHtml}</ul>
        </div>
    `;

  bindCertActions();
  document.dispatchEvent(
    new CustomEvent("researcher-modal-rendered", {
      detail: { status: d.cert ? d.cert.status : null },
    }),
  );
}

// ===== Training certificate =====
function renderCertSection(cert) {
  if (!cert) {
    return `
        <div class="researcher-protocol-section">
            <p class="researcher-protocol-heading">Training certificate</p>
            <ul class="researcher-protocol-list">
                <li class="researcher-protocol-item researcher-protocol-empty">No certificate on file.</li>
            </ul>
        </div>
    `;
  }

  const historyHtml = cert.history
    .map(
      (h) => `
            <li class="researcher-protocol-item">
                <span class="researcher-protocol-title">${escapeHtml(CERT_ACTION_LABELS[h.action] || h.action)}${h.actor_name ? " by " + escapeHtml(h.actor_name) : ""}</span>
                <span class="researcher-protocol-meta">
                    ${formatDateTime(h.created_at)}
                    ${h.action === "submitted" ? ` &middot; <a class="researcher-name-link" href="${escapeHtml(h.file_url)}" target="_blank" rel="noopener">${escapeHtml(h.original_name || "View file")}</a>` : ""}
                </span>
                ${h.note ? `<span class="researcher-protocol-meta">${escapeHtml(h.note)}</span>` : ""}
            </li>
        `,
    )
    .join("");

  const actionsHtml =
    cert.status === "pending"
      ? `
        <div class="modal-actions" id="certActions">
            <button class="confirm-modal-btn confirm-modal-ok confirm-modal-ok-danger" type="button" id="certRejectStartBtn">Request resubmission</button>
            <button class="confirm-modal-btn confirm-modal-ok" type="button" id="certVerifyBtn">Verify</button>
        </div>
        <div id="certRejectPanel" hidden>
            <label for="certRejectNote">Reason <span class="required-asterisk">*</span></label>
            <textarea id="certRejectNote" class="return-comment-textarea" rows="3" maxlength="500" placeholder="e.g. the certificate is expired or the name does not match" required></textarea>
            <div class="modal-actions">
                <button class="confirm-modal-btn confirm-modal-cancel" type="button" id="certRejectBackBtn">Back</button>
                <button class="confirm-modal-btn confirm-modal-ok confirm-modal-ok-danger" type="button" id="certRejectSubmitBtn">Confirm</button>
            </div>
        </div>
    `
      : "";

  return `
        <div class="researcher-protocol-section">
            <p class="researcher-protocol-heading">Training certificate</p>
            <div class="researcher-detail-rows">
                <div class="researcher-detail-row">
                    <span class="researcher-detail-label">Status</span>
                    <span class="researcher-detail-value"><span class="payment-badge ${CERT_STATUS_BADGES[cert.status] || ""}">${escapeHtml(CERT_STATUS_LABELS[cert.status] || cert.status)}</span></span>
                </div>
                <div class="researcher-detail-row">
                    <span class="researcher-detail-label">Current file</span>
                    <span class="researcher-detail-value"><a class="researcher-name-link" href="${escapeHtml(cert.file_url)}" target="_blank" rel="noopener">View certificate</a></span>
                </div>
            </div>
            ${actionsHtml}
            <p class="researcher-protocol-heading">History</p>
            <ul class="researcher-protocol-list">${historyHtml}</ul>
        </div>
    `;
}

function bindCertActions() {
  const actions = document.getElementById("certActions");
  if (!actions) return;

  const rejectPanel = document.getElementById("certRejectPanel");
  const noteInput = document.getElementById("certRejectNote");

  document.getElementById("certVerifyBtn").addEventListener("click", (e) => {
    confirmAction("Verify this training certificate?", {
      okText: "Verify",
      cancelText: "Cancel",
    }).then((ok) => ok && submitCertReview("certverify", "", e.target));
  });

  document
    .getElementById("certRejectStartBtn")
    .addEventListener("click", () => {
      actions.hidden = true;
      rejectPanel.hidden = false;
      noteInput.focus();
    });

  document.getElementById("certRejectBackBtn").addEventListener("click", () => {
    rejectPanel.hidden = true;
    actions.hidden = false;
  });

  document
    .getElementById("certRejectSubmitBtn")
    .addEventListener("click", (e) => {
      const note = noteInput.value.trim();
      if (!note) {
        showToast("Please tell the researcher what is wrong.", "error");
        noteInput.focus();
        return;
      }
      submitCertReview("certreject", note, e.target);
    });
}

async function submitCertReview(endpoint, note, btn) {
  const formData = new FormData();
  formData.append("csrf_token", CSRF_TOKEN);
  formData.append("user_id", currentResearcherId);
  formData.append("note", note);

  setButtonBusy(btn, true, "Saving...");
  try {
    const res = await fetch(ROOT_URL + "/personnel/" + endpoint, {
      method: "POST",
      headers: { Accept: "application/json" },
      body: formData,
    });
    const data = await res.json();
    if (!data.ok) {
      setButtonBusy(btn, false);
      showToast(data.message ?? data.error ?? "Could not save.", "error");
      if (data.cert_status) {
        setResearcherModalLocked(false);
        openResearcherModal(currentResearcherId);
        announceCertState(data.cert_status);
      }
      return;
    }
    showToast(data.message, "success");
    setResearcherModalLocked(false);
    openResearcherModal(currentResearcherId);
    announceCertState(data.cert_status);
  } catch (err) {
    setButtonBusy(btn, false);
    showToast("Network error. Please try again.", "error");
  }
}

// ===== Close researcher modal =====
function closeResearcherModal() {
  if (researcherModalLocked) return;
  researcherBackdrop.classList.remove("open");
}

researcherBackdrop.addEventListener("click", (e) => {
  if (e.target === researcherBackdrop) closeResearcherModal();
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeResearcherModal();
});

document.querySelectorAll(".researcher-name-link").forEach((btn) => {
  btn.addEventListener("click", () => {
    openResearcherModal(btn.dataset.userId, btn.dataset.researcherName);
  });
});

// ===== Open from a notification or email link (?open_researcher=ID) =====
(function openResearcherFromUrl() {
  const url = new URL(window.location);
  const userId = url.searchParams.get("open_researcher");
  if (!/^\d+$/.test(userId || "")) return;

  url.searchParams.delete("open_researcher");
  history.replaceState(null, "", url);
  openResearcherModal(userId);
})();
