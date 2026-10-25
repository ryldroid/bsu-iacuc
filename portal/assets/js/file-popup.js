// ===== Shared file popup =====
// openFilePopup(fileUrl, title, footer?, onClose?)
//   fileUrl: a URL, or a File/Blob picked on this page (previewed before upload).
//   footer (optional): { note, label, onAction } shows a note and one action
//   button under the file (e.g. "Resubmit Payment" for a rejected proof).
//   onClose (optional): runs when the popup is dismissed, e.g. to reopen the
//   modal it was opened from. It is skipped when the footer action is used.
const filePopupBackdrop = document.getElementById("filePopupBackdrop");
const filePopupFrame = document.getElementById("filePopupFrame");
const filePopupPdfPages = document.getElementById("filePopupPdfPages");
const filePopupImg = document.getElementById("filePopupImg");
const filePopupMessage = document.getElementById("filePopupMessage");
const filePopupFooter = document.getElementById("filePopupFooter");
let filePopupObjectUrl = null;
let filePopupFooterAction = null;
let filePopupOnClose = null;

function showFilePopupState(state) {
  filePopupPdfPages.hidden = state !== "pdf";
  filePopupImg.hidden = state !== "img";
  filePopupMessage.hidden = state !== "message";
}

async function renderPopupPdf(data) {
  filePopupPdfPages.innerHTML = "";
  const frameWidth = filePopupFrame.clientWidth;
  const sidePadding = window.matchMedia("(max-width: 768px)").matches ? 16 : 48;
  const doc = await pdfjsLib.getDocument({ data }).promise;

  for (let p = 1; p <= doc.numPages; p++) {
    const page = await doc.getPage(p);
    const scale = Math.min(
      1.5,
      (frameWidth - sidePadding) / page.getViewport({ scale: 1 }).width,
    );
    const vp = page.getViewport({ scale });

    const canvas = document.createElement("canvas");
    const outputScale = window.devicePixelRatio || 1;
    canvas.width = Math.floor(vp.width * outputScale);
    canvas.height = Math.floor(vp.height * outputScale);
    canvas.style.width = vp.width + "px";
    canvas.style.height = vp.height + "px";
    filePopupPdfPages.appendChild(canvas);

    await page.render({
      canvasContext: canvas.getContext("2d"),
      viewport: vp,
      transform:
        outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : undefined,
    }).promise;
  }
}

function setFilePopupFooter(footer) {
  filePopupFooterAction = footer?.onAction ?? null;
  filePopupFooter.hidden = !footer;
  if (!footer) return;
  document.getElementById("filePopupFooterNote").textContent =
    footer.note || "";
  document.getElementById("filePopupFooterLabel").textContent =
    footer.label || "";
}

async function openFilePopup(fileUrl, title, footer, onClose) {
  filePopupOnClose = onClose ?? null;
  document.getElementById("filePopupTitle").textContent = title;
  filePopupBackdrop.classList.add("open");
  filePopupFrame.scrollTop = 0;
  setFilePopupFooter(footer);

  filePopupMessage.textContent = "Loading\u2026";
  showFilePopupState("message");

  try {
    let blob = fileUrl;
    if (!(blob instanceof Blob)) {
      const res = await fetch(fileUrl);
      if (!res.ok) throw new Error("Failed to load file");
      blob = await res.blob();
    }

    if (blob.type.includes("pdf")) {
      await renderPopupPdf(await blob.arrayBuffer());
      showFilePopupState("pdf");
      filePopupFrame.scrollTop = 0;
      return;
    }

    if (filePopupObjectUrl) URL.revokeObjectURL(filePopupObjectUrl);
    filePopupObjectUrl = URL.createObjectURL(blob);

    filePopupImg.src = filePopupObjectUrl;
    filePopupImg.alt = title;
    showFilePopupState("img");
  } catch (err) {
    filePopupMessage.textContent = "Could not load this file.";
    showFilePopupState("message");
  }
}

function closeFilePopup() {
  if (!filePopupBackdrop.classList.contains("open")) return;
  filePopupBackdrop.classList.remove("open");
  filePopupPdfPages.innerHTML = "";
  filePopupImg.removeAttribute("src");
  if (filePopupObjectUrl) {
    URL.revokeObjectURL(filePopupObjectUrl);
    filePopupObjectUrl = null;
  }
  setFilePopupFooter(null);

  const onClose = filePopupOnClose;
  filePopupOnClose = null;
  if (onClose) onClose();
}

document.getElementById("filePopupFooterBtn").addEventListener("click", () => {
  const action = filePopupFooterAction;
  filePopupOnClose = null;
  closeFilePopup();
  if (action) action();
});

filePopupBackdrop.addEventListener("click", (e) => {
  if (e.target === filePopupBackdrop) closeFilePopup();
});

document.addEventListener(
  "keydown",
  (e) => {
    if (e.key !== "Escape" || !filePopupBackdrop.classList.contains("open"))
      return;
    e.stopPropagation();
    closeFilePopup();
  },
  true,
);

// ===== Preview a file picked in an <input type="file"> =====
function isPreviewableFile(file) {
  return file.type === "application/pdf" || file.type.startsWith("image/");
}

function syncPreviewButton(input, buttonId) {
  const file = input.files[0];
  document
    .getElementById(buttonId)
    .classList.toggle("is-hidden", !(file && isPreviewableFile(file)));
}

function previewPickedFile(inputId) {
  const file = document.getElementById(inputId).files[0];
  if (file) openFilePopup(file, file.name);
}
