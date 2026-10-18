// ===== Viewer: deletion request rejected modal =====
// Loaded only when the protocol has a deletion rejection reason.

// ===== Deletion request rejected modal =====
const deletionRejectedBackdrop = document.getElementById(
  "deletionRejectedModalBackdrop",
);

function openDeletionRejectedModal() {
  deletionRejectedBackdrop.classList.add("open");
  document.getElementById("deletionRejectedCloseBtn")?.focus();
}

function closeDeletionRejectedModal() {
  deletionRejectedBackdrop.classList.remove("open");
}

deletionRejectedBackdrop.addEventListener("click", (e) => {
  if (e.target === deletionRejectedBackdrop) closeDeletionRejectedModal();
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && deletionRejectedBackdrop.classList.contains("open"))
    closeDeletionRejectedModal();
});
