// ===== Comments report =====
// Renders each commented area of the PDF into its canvas, then enables the PDF download.
const PAGE_RENDER_SCALE = 2;
const EXCERPT_PADDING = 0.04;
const EXCERPT_MIN_HEIGHT = 0.1;

const PDF_MARGIN = 15;
const PDF_BRAND_COLOR = "#16470b";
const PDF_MUTED_COLOR = "#595959";
const PDF_SPACE = 4;

const reportDownload = document.getElementById("reportDownload");

// Vertical slice of the page (as fractions) around the commented box
function excerptBounds(y, height) {
  let top = y - EXCERPT_PADDING;
  let bottom = y + height + EXCERPT_PADDING;
  const missing = EXCERPT_MIN_HEIGHT - (bottom - top);
  if (missing > 0) {
    top -= missing / 2;
    bottom += missing / 2;
  }
  const shift = top < 0 ? -top : bottom > 1 ? 1 - bottom : 0;
  return [Math.max(0, top + shift), Math.min(1, bottom + shift)];
}

function drawExcerpt(pageCanvas, comment) {
  const { x, y, width, height } = Object.fromEntries(
    ["x", "y", "width", "height"].map((key) => [key, Number(comment.dataset[key])]),
  );
  const [top, bottom] = excerptBounds(y, height);
  const sourceTop = top * pageCanvas.height;
  const excerptHeight = (bottom - top) * pageCanvas.height;

  const canvas = comment.querySelector("canvas");
  canvas.width = pageCanvas.width;
  canvas.height = excerptHeight;

  const ctx = canvas.getContext("2d");
  ctx.drawImage(pageCanvas, 0, sourceTop, pageCanvas.width, excerptHeight, 0, 0, pageCanvas.width, excerptHeight);
  ctx.strokeStyle = getComputedStyle(document.documentElement).getPropertyValue("--danger-solid").trim() || "#c0392b";
  ctx.lineWidth = 3;
  ctx.strokeRect(x * pageCanvas.width, y * pageCanvas.height - sourceTop, width * pageCanvas.width, height * pageCanvas.height);
}

function showExcerptError(comment) {
  const message = document.createElement("p");
  message.className = "error-msg";
  message.textContent = "Could not load this excerpt.";
  comment.querySelector(".report-excerpt").replaceChildren(message);
}

async function renderSection(section) {
  const comments = [...section.querySelectorAll(".report-comment")];
  if (!comments.length) return;

  let pdf;
  try {
    pdf = await pdfjsLib.getDocument(section.dataset.fileUrl).promise;
  } catch (err) {
    comments.forEach(showExcerptError);
    return;
  }

  const pageNumbers = [...new Set(comments.map((comment) => Number(comment.dataset.page)))];

  for (const pageNumber of pageNumbers) {
    const pageComments = comments.filter((comment) => Number(comment.dataset.page) === pageNumber);
    try {
      const page = await pdf.getPage(pageNumber);
      const viewport = page.getViewport({ scale: PAGE_RENDER_SCALE });
      const pageCanvas = document.createElement("canvas");
      pageCanvas.width = Math.floor(viewport.width);
      pageCanvas.height = Math.floor(viewport.height);
      await page.render({ canvasContext: pageCanvas.getContext("2d"), viewport }).promise;
      pageComments.forEach((comment) => drawExcerpt(pageCanvas, comment));
    } catch (err) {
      pageComments.forEach(showExcerptError);
    }
  }

  pdf.destroy();
}

// ===== PDF download =====
function cleanText(element) {
  return element.textContent.replace(/\s+/g, " ").trim();
}

function downloadReport() {
  const doc = new window.jspdf.jsPDF({ unit: "mm", format: "a4" });
  const pageWidth = doc.internal.pageSize.getWidth();
  const pageHeight = doc.internal.pageSize.getHeight();
  const contentWidth = pageWidth - PDF_MARGIN * 2;
  let y = PDF_MARGIN;

  const ensureSpace = (height) => {
    if (y + height <= pageHeight - PDF_MARGIN) return;
    doc.addPage();
    y = PDF_MARGIN;
  };

  const writeText = (text, { size, bold = false, color = "#1a1a1a", gap = PDF_SPACE }) => {
    doc.setFont("helvetica", bold ? "bold" : "normal");
    doc.setFontSize(size);
    doc.setTextColor(color);
    const lines = doc.splitTextToSize(text, contentWidth);
    const lineHeight = size * 0.45;
    ensureSpace(lines.length * lineHeight);
    doc.text(lines, PDF_MARGIN, y + size * 0.35);
    y += lines.length * lineHeight + gap;
  };

  writeText(cleanText(document.querySelector(".report-header h1")), { size: 18, bold: true, color: PDF_BRAND_COLOR, gap: 2 });
  writeText(cleanText(document.querySelector(".report-title")), { size: 12, bold: true, gap: 1 });
  writeText(cleanText(document.querySelector(".report-meta")), { size: 9, color: PDF_MUTED_COLOR, gap: 1 });
  doc.setDrawColor(PDF_BRAND_COLOR);
  doc.setLineWidth(0.5);
  doc.line(PDF_MARGIN, y, pageWidth - PDF_MARGIN, y);
  y += PDF_SPACE * 2;

  document.querySelectorAll(".report-section").forEach((section, index) => {
    const heading = section.querySelector("h2");
    if (index > 0) {
      doc.addPage();
      y = PDF_MARGIN;
    }
    writeText(`${cleanText(heading.firstChild)}   ${cleanText(heading.querySelector(".report-section-date"))}`, { size: 12, bold: true, color: PDF_BRAND_COLOR });

    const emptyNote = section.querySelector(".annot-empty");
    if (emptyNote) writeText(cleanText(emptyNote), { size: 10, color: PDF_MUTED_COLOR, gap: PDF_SPACE * 2 });

    section.querySelectorAll(".report-comment").forEach((comment) => {
      const canvas = comment.querySelector("canvas");
      const imageHeight = canvas ? (contentWidth * canvas.height) / canvas.width : 0;
      ensureSpace(imageHeight + 30);

      writeText(`${cleanText(comment.querySelector(".annot-num"))}.  ${cleanText(comment.querySelector(".annot-page"))}`, { size: 10, bold: true });
      if (canvas) {
        doc.addImage(canvas.toDataURL("image/jpeg", 0.85), "JPEG", PDF_MARGIN, y, contentWidth, imageHeight);
        y += imageHeight + PDF_SPACE;
      } else {
        writeText(cleanText(comment.querySelector(".error-msg")), { size: 10, color: PDF_MUTED_COLOR });
      }
      writeText(cleanText(comment.querySelector(".annot-comment")), { size: 11 });
      writeText(cleanText(comment.querySelector(".annot-date")), { size: 8, color: PDF_MUTED_COLOR, gap: PDF_SPACE * 2 });
    });
  });

  const fileName = cleanText(document.querySelector(".report-title")).replace(/[^\w]+/g, "-").slice(0, 60);
  doc.save(`reviewer-comments-${fileName}.pdf`);
}

async function renderReport() {
  for (const section of document.querySelectorAll(".report-section")) {
    await renderSection(section);
  }
  reportDownload.disabled = false;
}

reportDownload.addEventListener("click", downloadReport);
renderReport();
