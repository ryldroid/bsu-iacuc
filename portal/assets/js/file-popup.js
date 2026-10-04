const filePopupBackdrop = document.getElementById("filePopupBackdrop");

function openFilePopup(fileUrl, title) {
  document.getElementById("filePopupTitle").textContent = title;
  document.getElementById("filePopupFrame").src = fileUrl;
  filePopupBackdrop.classList.add("open");
}

function closeFilePopup() {
  if (!filePopupBackdrop.classList.contains("open")) return;
  filePopupBackdrop.classList.remove("open");
  document.getElementById("filePopupFrame").src = "about:blank";
}

filePopupBackdrop.addEventListener("click", (e) => {
  if (e.target === filePopupBackdrop) closeFilePopup();
});
