const filePopupBackdrop = document.getElementById("filePopupBackdrop");

// ===== Open popup =====
function openFilePopup(fileUrl, title) {
  document.getElementById("filePopupTitle").textContent = title;
  document.getElementById("filePopupFrame").src = fileUrl;
  filePopupBackdrop.classList.add("open");
}

// ===== Close popup =====
function closeFilePopup() {
  if (!filePopupBackdrop.classList.contains("open")) return;
  filePopupBackdrop.classList.remove("open");
  document.getElementById("filePopupFrame").src = "about:blank";
}

// ===== Click outside to close =====
filePopupBackdrop.addEventListener("click", (e) => {
  if (e.target === filePopupBackdrop) closeFilePopup();
});
