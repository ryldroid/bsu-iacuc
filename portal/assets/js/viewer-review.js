// ===== Viewer: finish review and return for revision =====
// Loaded only while the reviewer can review this version.

// ===== Top right button: Return for Revision when there are comments, Finish Review when there are none =====
function syncReviewButton() {
  const btn = document.getElementById("btnReviewAction");
  if (!btn || annotationsFailed) return;
  const hasComments = annotations.length > 0;
  btn.classList.toggle("tool-btn--warn", hasComments);
  btn.classList.toggle("tool-btn--success", !hasComments);
  btn.textContent = hasComments ? "Return for Revision" : "Finish Review";
}

function reviewAction(btn) {
  if (certState !== "verified") {
    confirmAction(
      "The researcher's training certificate has to be resubmitted and verified first. Don't worry, your comments are saved, so you can leave this page and come back later.",
      { okText: "Got it", hideCancel: true },
    );
    return;
  }
  if (annotations.length) openReturnModal();
  else completeReview(btn);
}

async function completeReview(btn) {
  if (await commentsStillSyncing()) return;
  const ok = await confirmAction(
    `Finish your review of ${confirmName(PROTOCOL_TITLE)}? The protocol will be marked as reviewed.`,
    {
      okText: "Proceed",
      cancelText: "Back",
    },
  );
  if (!ok) return;
  updateStatus(btn, "Reviewed");
}

// ===== Return for Revision modal =====
const RETURN_REVISION_API = ROOT_URL + "/apply/return_revision";
const returnBackdrop = document.getElementById("returnRevisionBackdrop");
const returnComment = document.getElementById("returnComment");
const returnCharCount = document.getElementById("returnCharCount");

function openReturnModal() {
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
    `Return ${confirmName(PROTOCOL_TITLE)} for revision? The researcher will be notified and asked to resubmit.`,
    {
      okText: "Return for Revision",
      cancelText: "Cancel",
      danger: true,
    },
  );
  if (!ok) return;

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
        ...csrfHeaders(),
      },
      body: JSON.stringify({
        protocol_id: PROTOCOL_ID,
        comment: commentText,
      }),
    });
    const data = await res.json();

    if (data.ok) {
      window.location.href = BACK_URL;
    } else if (data.queued) {
      setButtonBusy(submitBtn, false);
      closeReturnModal();
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
