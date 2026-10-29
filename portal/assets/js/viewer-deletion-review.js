// ===== Viewer: approve / reject deletion request =====
// Loaded only for staff while a deletion request is pending.

// ===== Approve / reject deletion request modal =====
const deletionReviewBackdrop = document.getElementById(
  "deletionReviewModalBackdrop",
);
const deletionReviewLabel = document.getElementById("deletionReviewLabel");
const deletionReviewChoiceActions = document.getElementById(
  "deletionReviewChoiceActions",
);
const deletionReviewActionPanel = document.getElementById(
  "deletionReviewActionPanel",
);
const deletionReviewIntro = document.getElementById("deletionReviewIntro");
const deletionReviewReasonWrap = document.getElementById(
  "deletionReviewReasonWrap",
);
const deletionReviewReasonText = document.getElementById(
  "deletionReviewReasonText",
);
const deletionReviewCharCount = document.getElementById(
  "deletionReviewCharCount",
);
const deletionReviewSubmitBtn = document.getElementById(
  "deletionReviewSubmitBtn",
);
const deletionReviewSubmitLabel = document.getElementById(
  "deletionReviewSubmitLabel",
);
const deletionReviewSubmitIcon = document.getElementById(
  "deletionReviewSubmitIcon",
);
let deletionReviewAction = null;

// ===== Deletion review modal =====
function openDeletionReviewModal() {
  deletionReviewAction = null;
  deletionReviewLabel.textContent = "Deletion Requested";
  deletionReviewChoiceActions.hidden = false;
  deletionReviewActionPanel.hidden = true;
  deletionReviewReasonText.value = "";
  deletionReviewCharCount.textContent = "0";
  document.getElementById("deletionReviewError").hidden = true;
  deletionReviewBackdrop.classList.add("open");
}

function selectDeletionAction(action) {
  deletionReviewAction = action;
  deletionReviewChoiceActions.hidden = true;
  deletionReviewActionPanel.hidden = false;

  if (action === "approve") {
    deletionReviewLabel.textContent = "Approve Deletion Request";
    deletionReviewIntro.textContent =
      "This will permanently delete the protocol and notify the researcher. This cannot be undone.";
    deletionReviewReasonWrap.style.display = "none";
    deletionReviewSubmitLabel.textContent = "Approve & Delete";
    deletionReviewSubmitIcon.setAttribute("href", "#trash-icon");
    deletionReviewSubmitBtn.classList.remove("confirm-modal-ok-danger");
  } else {
    deletionReviewLabel.textContent = "Reject Deletion Request";
    deletionReviewIntro.textContent =
      "The researcher will be notified that their deletion request was rejected, along with your explanation below.";
    deletionReviewReasonWrap.style.display = "";
    deletionReviewSubmitLabel.textContent = "Reject Request";
    deletionReviewSubmitIcon.setAttribute("href", "#close-icon");
    deletionReviewSubmitBtn.classList.add("confirm-modal-ok-danger");
  }
}

function backToDeletionChoice() {
  deletionReviewAction = null;
  deletionReviewLabel.textContent = "Deletion Requested";
  deletionReviewChoiceActions.hidden = false;
  deletionReviewActionPanel.hidden = true;
  document.getElementById("deletionReviewError").hidden = true;
}

function closeDeletionReviewModal() {
  deletionReviewBackdrop.classList.remove("open");
}

deletionReviewBackdrop.addEventListener("click", (e) => {
  if (e.target === deletionReviewBackdrop) closeDeletionReviewModal();
});

deletionReviewReasonText.addEventListener("input", () => {
  deletionReviewCharCount.textContent = deletionReviewReasonText.value.length;
});

async function submitDeletionReview() {
  const isApprove = deletionReviewAction === "approve";
  const reason = deletionReviewReasonText.value.trim();
  const errBox = document.getElementById("deletionReviewError");

  errBox.hidden = true;

  if (!isApprove && !reason) {
    errBox.textContent = "A reason is required.";
    errBox.hidden = false;
    return;
  }

  const confirmMessage = isApprove
    ? "Approve this deletion request? The protocol will be permanently deleted."
    : "Reject this deletion request with the explanation you provided?";

  const ok = await confirmAction(confirmMessage, {
    okText: isApprove ? "Approve & Delete" : "Reject Request",
    cancelText: "Cancel",
    danger: isApprove,
  });
  if (!ok) return;

  setButtonBusy(
    deletionReviewSubmitBtn,
    true,
    isApprove ? "Deleting..." : "Rejecting...",
  );

  try {
    const res = await fetch(
      ROOT_URL +
        (isApprove ? "/apply/approve_deletion" : "/apply/reject_deletion"),
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          ...csrfHeaders(),
        },
        body: JSON.stringify({
          protocol_id: PROTOCOL_ID,
          reason: reason,
        }),
      },
    );
    const data = await res.json();

    if (data.ok) {
      if (isApprove) {
        window.location.href = BACK_URL;
      } else {
        window.location.reload();
      }
    } else if (data.queued) {
      deletionReviewSubmitBtn.disabled = false;
      closeDeletionReviewModal();
    } else {
      errBox.textContent = data.error ?? "Could not complete this action.";
      errBox.hidden = false;
      deletionReviewSubmitBtn.disabled = false;
    }
  } catch {
    errBox.textContent = "Network error. Please try again.";
    errBox.hidden = false;
    deletionReviewSubmitBtn.disabled = false;
  }
}
