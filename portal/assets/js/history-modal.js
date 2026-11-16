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

// ===== Steps =====
const HISTORY_STEPS = [
  { label: "Under Review", key: "under-review", icon: "clock-icon" },
  { label: "Reviewed", key: "reviewed", icon: "checkbox-icon" },
  { label: "Endorsed", key: "endorsed", icon: "shield-check-icon" },
  { label: "Approved", key: "approved", icon: "check-circle-icon" },
];
const HISTORY_RETURNED_STEP = {
  label: "Needs Revision",
  key: "needs-revision",
  icon: "alert-triangle-icon",
};
const HISTORY_ROLE_LABELS = {
  researcher: "Researcher",
  staff: "CCARD Staff",
  reviewer: "Reviewer",
};
const HISTORY_ICON_SIZE = 16;

let historyData = null;
let historySteps = [];
let historyCurrent = 0;
let historySelected = 0;
let historyResubmit = null;

const historyTime = (value) => new Date(value).getTime();
const historyDate = (value) => new Date(value).toLocaleDateString("en-PH");

function historyStatusName(status) {
  return STATUS_DISPLAY_NAMES[String(status).toLowerCase()] || status;
}

function historyStepIndex(status) {
  const name = String(status || "")
    .toLowerCase()
    .replace("needs revision", "under review");
  return HISTORY_STEPS.findIndex((s) => s.label.toLowerCase() === name);
}

// ===== Event row =====
function historyActor(name, role, id) {
  if (id && Number(id) === CURRENT_USER_ID) return "You";
  const label = HISTORY_ROLE_LABELS[role];
  return name + (label ? " (" + label + ")" : "");
}

function historyBy(name, role, id) {
  if (!name) return "";
  if (role === "researcher") return "";
  return "by " + escapeHtml(historyActor(name, role, id));
}

function historyWhen(at) {
  return `<span>${escapeHtml(historyDate(at))}</span><span>${escapeHtml(formatTime(at))}</span>`;
}

function historyEvent(e) {
  const by = historyBy(e.name, e.role, e.actorId);
  const lines = [
    by && `<span class="payment-event-time">${by}</span>`,
    e.detail &&
      `<span class="payment-event-detail">${e.detailLabel ? `<strong>${e.detailLabel}</strong> ` : ""}${escapeHtml(e.detail)}</span>`,
    e.reason &&
      `<span class="payment-event-detail"><strong>Reason:</strong> ${escapeHtml(e.reason)}</span>`,
    e.note && `<span class="payment-event-note">${escapeHtml(e.note)}</span>`,
  ]
    .filter(Boolean)
    .join("");

  return `<div class="payment-event history-event history-tone-${e.tone}">
            <span class="payment-event-icon">
                <svg width="${HISTORY_ICON_SIZE}" height="${HISTORY_ICON_SIZE}" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#${e.icon}" /></svg>
            </span>
            <div class="payment-event-body">
                <span class="payment-event-title">${escapeHtml(e.title)}</span>
                ${lines}
            </div>
            <div class="history-event-aside">${historyWhen(e.at)}${e.action || ""}</div>
        </div>`;
}

// ===== Event builders =====
function historyOpenLink(href, label, extra = "", text = "Open") {
  return `<a class="action-link" href="${escapeHtml(href)}"${extra} aria-label="${escapeHtml(label)}">${text}</a>`;
}

function versionEvents(data) {
  return data.versions.map((v) => {
    const isScan = v.file_type === "signed_scan";
    const title = isScan
      ? "Signed protocol uploaded"
      : v.version_number <= 1
        ? "Protocol submitted"
        : `Protocol resubmitted (${v.label.toLowerCase()})`;

    const href = `${ROOT_URL}/apply/viewer/${data.viewer_ref}/${v.id}`;

    return {
      tone: isScan ? "success" : "info",
      icon: isScan ? "shield-check-icon" : "upload-icon",
      title,
      name: (v.first_name + " " + v.last_name).trim(),
      role: v.uploader_role,
      actorId: v.actor_id,
      at: v.uploaded_at,
      href,
      label: `Open ${v.label}, ${formatDate(v.uploaded_at)}`,
      action: historyOpenLink(
        href,
        `Open ${v.label}, ${formatDate(v.uploaded_at)}`,
      ),
      step: isScan ? undefined : 0,
    };
  });
}

const PAYMENT_EVENT_LOOKS = {
  proof: {
    icon: "upload-icon",
    title: "Proof of payment submitted",
    tone: "info",
  },
  rejected: {
    icon: "alert-triangle-icon",
    title: "Payment rejected",
    tone: "danger",
  },
  paid: {
    icon: "check-circle-icon",
    title: "Payment verified",
    tone: "success",
  },
  unmarked: {
    icon: "undo-icon",
    title: "Payment verification undone",
    tone: "warning",
  },
};

function paymentEvents(events) {
  return events.map((e, i) => {
    const look = PAYMENT_EVENT_LOOKS[e.type] || PAYMENT_EVENT_LOOKS.paid;
    const event = {
      ...look,
      name: e.actor_name,
      role: e.actor_role,
      actorId: e.actor_id,
      at: e.at,
      detail:
        e.method === "in_person"
          ? "Paid in person at CCARD" +
            (e.receipt_no ? ` (receipt no. ${e.receipt_no})` : "")
          : "",
    };

    if (e.type === "proof") {
      event.detail = "Paid online";
      event.action = `<button class="action-link" type="button" data-payment-index="${i}" aria-label="View proof of payment, ${formatDate(e.at)}">View</button>`;
    } else if (e.type === "rejected") {
      event.detail = "";
      event.reason = e.comment || "No reason given.";
      if (i === 0 && historyResubmit) {
        event.note = "Open the latest proof below to resubmit your payment.";
      }
    } else if (e.type === "unmarked") {
      event.detail = "Waiting to be verified again";
    }

    return event;
  });
}

const IPN_EVENT_LOOKS = {
  assigned: { icon: "check-icon", title: "IPN assigned", tone: "success" },
  changed: { icon: "edit-icon", title: "IPN changed", tone: "warning" },
};

function ipnEvents(events) {
  return events.map((e) => ({
    ...IPN_EVENT_LOOKS[e.type],
    name: e.actor_name,
    role: e.actor_role,
    actorId: e.actor_id,
    at: e.at,
    detail: e.detail,
  }));
}

const CLEARANCE_EVENT_LOOKS = {
  downloaded: {
    icon: "download-icon",
    title: "Clearance downloaded",
    tone: "success",
  },
  picked_up: {
    icon: "check-circle-icon",
    title: "Embossed hard copy picked up",
    tone: "success",
    by: "Confirmed by ",
  },
  pickup_undone: {
    icon: "undo-icon",
    title: "Hard copy pickup undone",
    tone: "warning",
    by: "Undone by ",
  },
};

function clearanceEvents(events) {
  return events.map((e) => {
    const look = CLEARANCE_EVENT_LOOKS[e.type];
    return {
      ...look,
      at: e.at,
      detail:
        e.actor_name && look.by
          ? look.by + historyActor(e.actor_name, e.actor_role, e.actor_id)
          : "",
    };
  });
}

function titleEvents(titleHistory) {
  return titleHistory
    .filter((h) => h.changed_by_name)
    .map((h) => ({
      tone: "info",
      icon: "edit-icon",
      title: "Title renamed",
      name: h.changed_by_name,
      role: h.changed_by_role,
      actorId: h.changed_by_id,
      at: h.changed_at,
      detailLabel: "Renamed to:",
      detail: h.title,
    }));
}

const HISTORY_MERGE_MS = 60000;
const CERT_LINK_ATTRS = ' target="_blank" rel="noopener"';
const CERT_HISTORY_EVENTS = {
  submitted: ["info", "upload-icon", "Training certificate submitted"],
  verified: ["success", "shield-check-icon", "Training certificate verified"],
  rejected: [
    "warning",
    "alert-triangle-icon",
    "Training certificate resubmission requested",
  ],
};

function certEvents(certHistory) {
  return certHistory.map((h) => {
    const [tone, icon, title] = CERT_HISTORY_EVENTS[h.action] || [
      "info",
      "history-icon",
      h.action,
    ];

    return {
      tone,
      icon,
      title,
      name: h.actor_name,
      role: h.actor_role,
      actorId: h.actor_id,
      at: h.created_at,
      [h.action === "rejected" ? "reason" : "detail"]: h.note,
      href: h.file_url,
      action:
        h.action === "submitted"
          ? historyOpenLink(
              h.file_url,
              "Open training certificate",
              CERT_LINK_ATTRS,
            )
          : "",
    };
  });
}

// The first protocol upload and its training certificate are saved together, so they share one row.
function mergeCertSubmission(versions, certs) {
  const first = versions.find((v) => v.title === "Protocol submitted");
  const cert =
    first &&
    certs.find(
      (c) =>
        c.title === CERT_HISTORY_EVENTS.submitted[2] &&
        Math.abs(historyTime(c.at) - historyTime(first.at)) <= HISTORY_MERGE_MS,
    );
  if (!cert) return [...versions, ...certs];

  first.title = "Protocol submitted with training certificate";
  first.action =
    historyOpenLink(first.href, first.label, "", "Protocol") +
    historyOpenLink(
      cert.href,
      "Open training certificate",
      CERT_LINK_ATTRS,
      "Certificate",
    );
  return [...versions, ...certs.filter((c) => c !== cert)];
}

function discontinuedEvents(discontinued) {
  if (!discontinued) return [];
  return [
    {
      tone: "danger",
      icon: "archive-icon",
      title: "Protocol discontinued",
      reason: discontinued.reason,
      at: discontinued.at,
    },
  ];
}

// ===== Status changes =====
// What a change means comes from where the protocol was and where it went.
// A submission or resubmission is skipped here: the protocol version rows already show it.
// An undo is filed under the step that got undone, so "Reviewed" holds the whole review story.
const STATUS_CHANGES = {
  "Under Review>Reviewed": {
    tone: "success",
    icon: "checkbox-icon",
    title: "Review completed",
    detail: "",
  },
  "Under Review>Needs Revision": {
    tone: "danger",
    icon: "alert-triangle-icon",
    title: "Returned for revision",
    detail: "",
  },
  "Reviewed>Under Review": {
    tone: "warning",
    icon: "undo-icon",
    title: "Review undone",
    detail: "Back to " + historyStatusName("Under Review"),
    undo: true,
  },
  "Reviewed>Endorsed": {
    tone: "success",
    icon: "shield-check-icon",
    title: "Endorsed to DA-RFO-CAR and BAI Central Office",
    detail: "",
  },
  "Endorsed>Approved": {
    tone: "success",
    icon: "check-circle-icon",
    title: "Approved",
    detail: "Clearance attached",
  },
  "Endorsed>Reviewed": {
    tone: "warning",
    icon: "undo-icon",
    title: "Endorsement undone",
    detail: "Back to Reviewed",
    undo: true,
  },
  "Endorsed>Needs Revision": {
    tone: "danger",
    icon: "alert-triangle-icon",
    title: "Returned by DA-RFO-CAR or BAI",
    detail: "",
  },
  "Approved>Endorsed": {
    tone: "warning",
    icon: "undo-icon",
    title: "Approval undone",
    detail: "Back to Endorsed",
    undo: true,
  },
};

function statusEvents(statusHistory) {
  return statusHistory
    .filter(
      (h) =>
        !(
          h.to_status === "Under Review" &&
          (!h.from_status || h.from_status === "Needs Revision")
        ),
    )
    .map((h) => {
      const toStep = historyStepIndex(h.to_status);
      const look = STATUS_CHANGES[h.from_status + ">" + h.to_status] || {
        tone: "info",
        icon: HISTORY_STEPS[toStep]?.icon || "history-icon",
        title: "Moved to " + historyStatusName(h.to_status),
        detail: h.from_status ? "From " + historyStatusName(h.from_status) : "",
      };
      const step = look.undo ? historyStepIndex(h.from_status) : toStep;

      return {
        tone: look.tone,
        icon: look.icon,
        title: look.title,
        detail: look.detail,
        name: h.actor_name,
        role: h.actor_role,
        actorId: h.actor_id,
        at: h.created_at,
        reason: h.reason,
        step: step < 0 ? undefined : step,
      };
    });
}

// ===== Group events into steps =====
// An event belongs to the step the protocol was in at that moment.
// Every status change is stored with the step it moved to.
function historyTransitions(data) {
  return data.status_history
    .map((h) => ({ step: historyStepIndex(h.to_status), at: h.created_at }))
    .filter((t) => t.step >= 0)
    .sort((a, b) => historyTime(a.at) - historyTime(b.at));
}

function historyStepAt(transitions, at) {
  let step = 0;
  for (const t of transitions) {
    if (historyTime(t.at) > historyTime(at)) break;
    step = t.step;
  }
  return step;
}

function buildHistorySteps(data) {
  const transitions = historyTransitions(data);
  const events = [
    ...mergeCertSubmission(versionEvents(data), certEvents(data.cert_history)),
    ...paymentEvents(data.payment_history),
    ...ipnEvents(data.ipn_history),
    ...clearanceEvents(data.clearance_history),
    ...titleEvents(data.title_history),
    ...statusEvents(data.status_history),
    ...discontinuedEvents(data.discontinued),
  ].map((e) => ({ ...e, step: e.step ?? historyStepAt(transitions, e.at) }));

  const steps =
    data.status === HISTORY_RETURNED_STEP.label
      ? [HISTORY_RETURNED_STEP, ...HISTORY_STEPS.slice(1)]
      : HISTORY_STEPS;

  return steps.map((s, i) => {
    const entered = transitions.filter((t) => t.step === i);
    return {
      ...s,
      at: entered.length ? entered[entered.length - 1].at : null,
      events: events
        .filter((e) => e.step === i)
        .sort((a, b) => historyTime(b.at) - historyTime(a.at)),
    };
  });
}

// ===== Stepper and panel =====
function buildHistoryStepper() {
  return (
    '<ol class="history-stepper">' +
    historySteps
      .map((s, i) => {
        const empty = s.events.length === 0;
        const cls = [
          "history-step",
          "history-step--" + s.key,
          empty ? "is-upcoming" : "",
          i === historySelected ? "is-selected" : "",
        ]
          .filter(Boolean)
          .join(" ");
        const date = s.at
          ? historyDate(s.at)
          : i > historyCurrent
            ? "Pending"
            : "None";

        return `<li class="${cls}"${i === historyCurrent ? ' aria-current="step"' : ""}>
            <button type="button" class="history-step-button" data-step="${i}" ${empty ? "disabled" : ""} aria-pressed="${i === historySelected}">
                <span class="payment-event-icon"><svg width="${HISTORY_ICON_SIZE}" height="${HISTORY_ICON_SIZE}" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#${s.icon}" /></svg></span>
                <span class="history-step-label">${historyStatusName(s.label)}</span>
                <span class="history-step-date">${date}</span>
                ${i === historyCurrent ? '<span class="history-step-current">Current</span>' : ""}
            </button>
        </li>`;
      })
      .join("") +
    "</ol>"
  );
}

function buildHistoryPanel() {
  return `<div class="history-step-panel"><div class="payment-timeline">${historySteps[historySelected].events.map(historyEvent).join("")}</div></div>`;
}

function selectHistoryStep(index) {
  historySelected = index;
  document
    .querySelectorAll("#historyModalBody .history-step")
    .forEach((item, i) => {
      item.classList.toggle("is-selected", i === index);
      item
        .querySelector("[data-step]")
        .setAttribute("aria-pressed", i === index);
    });
  document.querySelector("#historyModalBody .history-step-panel").outerHTML =
    buildHistoryPanel();
}

function openPaymentProof(index) {
  const events = historyData.payment_history;
  const latestProofIndex = events.findIndex((e) => e.type === "proof");

  historyBackdrop.classList.remove("open");
  openFilePopup(
    events[index].file_url,
    "Proof of Payment",
    index === latestProofIndex ? historyResubmit : null,
    () => historyBackdrop.classList.add("open"),
  );
}

document.getElementById("historyModalBody").addEventListener("click", (e) => {
  const step = e.target.closest("[data-step]");
  if (step) return selectHistoryStep(Number(step.dataset.step));

  const proof = e.target.closest("[data-payment-index]");
  if (proof) openPaymentProof(Number(proof.dataset.paymentIndex));
});

// ===== Render history =====
function renderHistory(data) {
  historyData = data;
  historyResubmit = window.protocolMenuHooks?.paymentResubmit?.();
  historySteps = buildHistorySteps(data);

  const withEvents = historySteps.flatMap((s, i) =>
    s.events.length ? [i] : [],
  );
  if (!withEvents.length) {
    document.getElementById("historyModalBody").innerHTML =
      '<p class="helper history-empty">No history found.</p>';
    return;
  }

  const transitions = historyTransitions(data);
  historyCurrent = historyStepIndex(data.status);
  if (historyCurrent < 0) {
    historyCurrent = transitions.length
      ? transitions[transitions.length - 1].step
      : 0;
  }
  historySelected = withEvents.includes(historyCurrent)
    ? historyCurrent
    : withEvents[withEvents.length - 1];

  document.getElementById("historyModalBody").innerHTML =
    buildHistoryStepper() + buildHistoryPanel();
}
