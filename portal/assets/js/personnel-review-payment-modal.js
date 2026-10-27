// ===== Verify payment modal =====
const reviewPaymentModal = document.getElementById(
  "reviewPaymentModalBackdrop",
);
let currentReviewPaymentProtocolId = null;

function openReviewPaymentModal(
  protocolId,
  title,
  paymentMethod,
  researcherName,
  school,
) {
  currentReviewPaymentProtocolId = protocolId;
  document.getElementById("reviewPaymentSummaryTitle").textContent = title;
  document.getElementById("reviewPaymentSummaryMeta").textContent = [
    researcherName,
    school,
  ]
    .filter(Boolean)
    .join(" \u00b7 ");
  resetReviewPaymentModal();
  reviewPaymentModal.classList.add("open");
  if (paymentMethod === "in_person") {
    document.getElementById("reviewPaymentImageFrame").innerHTML =
      '<p class="review-payment-note">The researcher confirmed paying the fee in person at CCARD. Verify with your records before approving.</p>';
  } else {
    loadPaymentProofImage(protocolId);
  }
}

function closeReviewPaymentModal() {
  reviewPaymentModal.classList.remove("open");
  currentReviewPaymentProtocolId = null;
}

function resetReviewPaymentModal() {
  document.getElementById("reviewPaymentImageFrame").innerHTML =
    '<p class="review-payment-note">Loading proof of payment&hellip;</p>';
  document.getElementById("reviewPaymentActions").hidden = false;
  document.getElementById("reviewPaymentRejectPanel").hidden = true;
  document.getElementById("reject_payment_comment").value = "";
  document.getElementById("rejectPaymentError").hidden = true;
  setButtonBusy(document.getElementById("rejectPaymentSubmitBtn"), false);
  setButtonBusy(document.getElementById("reviewPaymentApproveBtn"), false);
}

reviewPaymentModal.addEventListener("click", (e) => {
  if (e.target === reviewPaymentModal) closeReviewPaymentModal();
});

// ===== Auto-open from notification/email links =====
document.addEventListener("DOMContentLoaded", () => {
  const openPaymentId = parseInt(
    new URLSearchParams(window.location.search).get("open_payment"),
    10,
  );
  if (!openPaymentId) return;
  const protocol = protocolsData.find((p) => p.protocol_id == openPaymentId);
  if (!protocol) return;
  openReviewPaymentModal(
    openPaymentId,
    protocol.research_title ?? "",
    protocol.payment_method ?? "online",
    `${protocol.first_name ?? ""} ${protocol.last_name ?? ""}`.trim(),
    protocol.school ?? "",
  );
});

async function loadPaymentProofImage(protocolId) {
  const frame = document.getElementById("reviewPaymentImageFrame");
  try {
    const res = await fetch(ROOT_URL + "/apply/allversions/" + protocolId);
    const data = await res.json();
    const latest = data?.payment_proof_files?.[0];

    if (!latest) {
      frame.innerHTML =
        '<p class="review-payment-note">No proof of payment file was found for this protocol.</p>';
      return;
    }

    frame.innerHTML = "";
    const img = document.createElement("img");
    img.src = latest.file_url;
    img.alt = "Proof of payment";
    img.dataset.zoomSrc = latest.file_url;
    img.dataset.zoomAlt = img.alt;
    img.onerror = () => {
      frame.innerHTML =
        '<p class="review-payment-note">Could not load the proof of payment image.</p>';
    };
    frame.appendChild(img);
  } catch (err) {
    frame.innerHTML =
      '<p class="review-payment-note">Network error while loading the proof of payment.</p>';
  }
}

// ===== Reject payment reason =====
function showRejectPaymentReason() {
  document.getElementById("reviewPaymentActions").hidden = true;
  document.getElementById("reviewPaymentRejectPanel").hidden = false;
  document.getElementById("reject_payment_comment").focus();
}

function hideRejectPaymentReason() {
  document.getElementById("reviewPaymentRejectPanel").hidden = true;
  document.getElementById("reviewPaymentActions").hidden = false;
}

// ===== Approve reviewed payment =====
function approveReviewedPayment() {
  confirmAction(
    "Mark this protocol as paid? Make sure the payment checks out before approving.",
    {
      okText: "Mark as Paid",
      cancelText: "Cancel",
    },
  ).then((ok) => {
    if (!ok) return;
    const btn = document.getElementById("reviewPaymentApproveBtn");
    setButtonBusy(btn, true, "Marking as Paid...");
    submitPaymentVerify(currentReviewPaymentProtocolId);
  });
}

async function submitRejectPayment() {
  const comment = document
    .getElementById("reject_payment_comment")
    .value.trim();
  const errBox = document.getElementById("rejectPaymentError");
  const btn = document.getElementById("rejectPaymentSubmitBtn");

  if (!comment) {
    errBox.textContent = "Please explain why this proof is being rejected.";
    errBox.hidden = false;
    return;
  }

  errBox.hidden = true;

  const ok = await confirmAction(
    "Reject this payment? The researcher will be notified and asked to resubmit.",
    {
      okText: "Reject Proof",
      cancelText: "Cancel",
      danger: true,
    },
  );
  if (!ok) return;

  setButtonBusy(btn, true, "Rejecting...");

  try {
    const res = await fetch(REJECT_PAYMENT_API, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": CSRF_TOKEN,
      },
      body: JSON.stringify({
        protocol_id: currentReviewPaymentProtocolId,
        comment,
      }),
    });
    const data = await res.json();

    if (data.ok) {
      window.location.reload();
    } else if (data.queued) {
      setButtonBusy(btn, false);
      closeReviewPaymentModal();
    } else {
      errBox.textContent = data.error ?? "Could not reject. Please try again.";
      errBox.hidden = false;
      setButtonBusy(btn, false);
    }
  } catch (err) {
    errBox.textContent = "Network error. Please try again.";
    errBox.hidden = false;
    setButtonBusy(btn, false);
  }
}
