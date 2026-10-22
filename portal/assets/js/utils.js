// ===== HTML escaping =====
function escapeHtml(value) {
  return String(value ?? "").replace(
    /[&<>"']/g,
    (ch) =>
      ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      })[ch],
  );
}

// ===== File size =====
function formatFileSize(bytes) {
  if (bytes === null || bytes === undefined) return "";
  if (bytes < 1024) return bytes + " B";
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
  return (bytes / (1024 * 1024)).toFixed(1) + " MB";
}

// ===== Uploaded document row =====
function uploadedDocRow({
  key,
  title,
  required,
  name,
  size,
  accept,
  hint,
  uploadHandler,
  removeHandler,
}) {
  const sizeText = formatFileSize(size);
  return `<div class="resub-doc is-done" data-doc-key="${key}">
    <span class="resub-doc-badge"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#check-circle-icon" /></svg></span>
    <div class="resub-doc-info">
      <div class="resub-doc-title">${title}${required ? ' <span class="req">*</span>' : ""}</div>
      <span class="resub-doc-sub is-file">${escapeHtml(name)}${sizeText ? ` &middot; ${sizeText}` : ""}${hint ? `<span class="resub-doc-hint">${hint}</span>` : ""}</span>
    </div>
    <div class="resub-doc-actions">
      <label class="resub-btn-link">
        Replace
        <input type="file" accept="${accept}" onchange="${uploadHandler}(event,'${key}')">
      </label>
      <button type="button" class="resub-btn-link is-danger" onclick="${removeHandler}('${key}')">Remove</button>
    </div>
  </div>`;
}
