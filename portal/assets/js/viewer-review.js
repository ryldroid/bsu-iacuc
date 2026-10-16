// ===== Viewer: finish review and return for revision =====
// Loaded only while the reviewer can review this version.

// ===== Finish Review decision modal =====
const reviewDecisionBackdrop = document.getElementById(
  "reviewDecisionBackdrop",
);

function openReviewDecisionModal() {
  reviewDecisionBackdrop.classList.add("open");
}

function closeReviewDecisionModal() {
  reviewDecisionBackdrop.classList.remove("open");
}

reviewDecisionBackdrop.addEventListener("click", (e) => {
  if (e.target === reviewDecisionBackdrop) closeReviewDecisionModal();
});

async function completeReview(btn) {
  if (await commentsStillSyncing()) return;
  if (annotations.length) {
    await confirmAction(
      "This protocol still has comments. Either return for revision so the researcher can address them, or remove comments to confirm that your review is finished.",
      {
        okText: "Back",
        hideCancel: true,
      },
    );
    return;
  }
  const ok = await confirmAction(
    "Finish your review? The protocol will be marked as reviewed.",
    {
      okText: "Proceed",
      cancelText: "Back",
    },
  );
  if (!ok) return;
  closeReviewDecisionModal();
  updateStatus(btn, "Reviewed");
}

// ===== Return for Revision modal =====
const RETURN_REVISION_API = ROOT_URL + "/apply/return_revision";
const returnBackdrop = document.getElementById("returnRevisionBackdrop");
const returnComment = document.getElementById("returnComment");
const returnCharCount = document.getElementById("returnCharCount");

function openReturnModal() {
  document
    .querySelectorAll('input[name="return_reason"]')
    .forEach((cb) => (cb.checked = false));
  returnComment.value = "";
  returnCharCount.textContent = "0";
  document.getElementById("returnRevisionError").hidden = true;
  returnBackdrop.classList.add("open");
}

function closeReturnModal() {
  returnBackdrop.classList.remove("open");
}

returnBackdrop.addEventListener("click", (e) => {
  if (e.target === returnBackdrop) closeReturnModal();
});

returnComment.addEventListener("input", () => {
  returnCharCount.textContent = returnComment.value.length;
});

async function submitReturnRevision() {
  if (await commentsStillSyncing()) return;
  const ok = await confirmAction(
    "Return this protocol for revision? The researcher will be notified and asked to resubmit.",
    {
      okText: "Return for Revision",
      cancelText: "Cancel",
      danger: true,
    },
  );
  if (!ok) return;

  const selectedReasons = [
    ...document.querySelectorAll('input[name="return_reason"]:checked'),
  ].map((cb) => cb.value);
  const commentText = returnComment.value.trim();
  const errBox = document.getElementById("returnRevisionError");
  const submitBtn = document.getElementById("returnRevisionSubmitBtn");

  errBox.hidden = true;
  setButtonBusy(submitBtn, true, "Returning...");

  try {
    const res = await fetch(RETURN_REVISION_API, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": CSRF_TOKEN,
      },
      body: JSON.stringify({
        protocol_id: PROTOCOL_ID,
        reasons: selectedReasons,
        comment: commentText,
      }),
    });
    const data = await res.json();

    if (data.ok) {
      window.location.href = BACK_URL;
    } else {
      errBox.textContent =
        data.error ?? "Could not return protocol. Please try again.";
      errBox.hidden = false;
      setButtonBusy(submitBtn, false);
    }
  } catch {
    errBox.textContent = "Network error. Please try again.";
    errBox.hidden = false;
    setButtonBusy(submitBtn, false);
  }
}
