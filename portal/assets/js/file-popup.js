// ===== Shared file popup =====
// openFilePopup(fileUrl, title, footer?)
//   footer (optional): { note, label, onAction } shows a note and one action
//   button under the file (e.g. "Resubmit Payment" for a rejected proof).
const filePopupBackdrop = document.getElementById("filePopupBackdrop");
const filePopupFrame = document.getElementById("filePopupFrame");
const filePopupPdfPages = document.getElementById("filePopupPdfPages");
const filePopupImg = document.getElementById("filePopupImg");
const filePopupMessage = document.getElementById("filePopupMessage");
const filePopupFooter = document.getElementById("filePopupFooter");
let filePopupObjectUrl = null;
let filePopupFooterAction = null;

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

async function openFilePopup(fileUrl, title, footer) {
  document.getElementById("filePopupTitle").textContent = title;
  filePopupBackdrop.classList.add("open");
  filePopupFrame.scrollTop = 0;
  setFilePopupFooter(footer);

  filePopupMessage.textContent = "Loading\u2026";
  showFilePopupState("message");

  try {
    const res = await fetch(fileUrl);
    if (!res.ok) throw new Error("Failed to load file");

    const contentType = res.headers.get("content-type") || "";

    if (contentType.includes("pdf")) {
      await renderPopupPdf(await res.arrayBuffer());
      showFilePopupState("pdf");
      filePopupFrame.scrollTop = 0;
      return;
    }

    const blob = await res.blob();

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
}

document.getElementById("filePopupFooterBtn").addEventListener("click", () => {
  const action = filePopupFooterAction;
  closeFilePopup();
  if (action) action();
});

filePopupBackdrop.addEventListener("click", (e) => {
  if (e.target === filePopupBackdrop) closeFilePopup();
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeFilePopup();
});
