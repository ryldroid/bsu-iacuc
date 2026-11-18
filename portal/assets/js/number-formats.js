// ===== System Settings: live example =====
document.querySelectorAll("form[data-number-format]").forEach((form) => {
  const kind = form.dataset.numberFormat;
  const preview = form.querySelector(".number-preview-value");
  const year = preview.dataset.year;

  const updatePreview = () => {
    const format = Object.fromEntries(new FormData(form));
    const sequence = "1".padStart(Number(format[`${kind}_digits`]), "0");
    const yearText = format[`${kind}_year`] === "yyyy" ? year : year.slice(-2);
    const numbers =
      format[`${kind}_year`] === "none"
        ? [sequence]
        : format[`${kind}_year_position`] === "before"
          ? [yearText, sequence]
          : [sequence, yearText];

    preview.textContent = [format[`${kind}_prefix`].trim(), ...numbers]
      .filter(Boolean)
      .join(format[`${kind}_separator`]);
  };

  form.addEventListener("input", updatePreview);
  form.addEventListener("reset", () => setTimeout(updatePreview));
});
