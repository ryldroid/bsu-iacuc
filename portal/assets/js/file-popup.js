const filePopupBackdrop = document.getElementById("filePopupBackdrop");
const filePopupFrame = document.getElementById("filePopupFrame");
let filePopupObjectUrl = null;

function releaseFilePopupObjectUrl() {
  if (!filePopupObjectUrl) return;
  URL.revokeObjectURL(filePopupObjectUrl);
  filePopupObjectUrl = null;
}

// ===== Open popup =====
// Images are wrapped in a tiny document so the whole picture fits the frame
// (an iframe pointed straight at an image shows it at natural size, cropped).
// Everything else (PDFs) is loaded directly.
async function openFilePopup(fileUrl, title) {
  document.getElementById("filePopupTitle").textContent = title;
  filePopupFrame.removeAttribute("srcdoc");
  filePopupFrame.src = "about:blank";
  releaseFilePopupObjectUrl();
  filePopupBackdrop.classList.add("open");

  try {
    const res = await fetch(fileUrl);
    const type = res.headers.get("content-type") || "";

    if (!type.startsWith("image/")) {
      if (res.body) res.body.cancel();
      filePopupFrame.src = fileUrl;
      return;
    }

    filePopupObjectUrl = URL.createObjectURL(await res.blob());
    filePopupFrame.srcdoc =
      '<body style="margin:0;height:100vh;display:flex;align-items:center;justify-content:center">' +
      '<img src="' +
      filePopupObjectUrl +
      '" alt="" style="max-width:100%;max-height:100%;object-fit:contain">' +
      "</body>";
  } catch (err) {
    filePopupFrame.src = fileUrl;
  }
}

// ===== Close popup =====
function closeFilePopup() {
  if (!filePopupBackdrop.classList.contains("open")) return;
  filePopupBackdrop.classList.remove("open");
  filePopupFrame.removeAttribute("srcdoc");
  filePopupFrame.src = "about:blank";
  releaseFilePopupObjectUrl();
}

// ===== Click outside to close =====
filePopupBackdrop.addEventListener("click", (e) => {
  if (e.target === filePopupBackdrop) closeFilePopup();
});
