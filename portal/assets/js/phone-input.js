(function () {
  const MAX_DIGITS = 10;

  // ===== Clean typed number to the 10 digits after +63 =====
  function clean(value) {
    let digits = String(value).replace(/\D/g, "");

    if (digits.startsWith("63") && digits.length > MAX_DIGITS) {
      digits = digits.slice(2);
    }

    if (digits.startsWith("0")) {
      digits = digits.slice(1);
    }

    return digits.slice(0, MAX_DIGITS);
  }

  // ===== Group digits as 912 345 6789 =====
  function group(digits) {
    return [digits.slice(0, 3), digits.slice(3, 6), digits.slice(6)]
      .filter(Boolean)
      .join(" ");
  }

  // Full display form of a stored number, e.g. +639123456789 -> +63 912 345 6789
  window.formatPhone = (stored) => {
    const digits = clean(stored ?? "");
    return digits ? `+63 ${group(digits)}` : "";
  };

  // Caret position just after the nth digit of a grouped value
  function caretAfterDigits(formatted, count) {
    if (count <= 0) return 0;
    let seen = 0;
    for (let i = 0; i < formatted.length; i++) {
      if (/\d/.test(formatted[i]) && ++seen === count) return i + 1;
    }
    return formatted.length;
  }

  // ===== Attach to a field =====
  function attach(input) {
    if (input.dataset.phoneCleanAttached === "1") return;
    input.dataset.phoneCleanAttached = "1";

    // The grouped value has spaces, so the field's limits are set here with the formatting
    input.maxLength = 12;
    input.pattern = "9\\d{2} \\d{3} \\d{4}";

    input.value = group(clean(input.value));
    let lastDigits = clean(input.value);

    input.addEventListener("input", (event) => {
      const caret = input.selectionStart ?? input.value.length;
      let before = input.value.slice(0, caret).replace(/\D/g, "").length;
      let digits = clean(input.value);

      // Deleting a space changes no digits, so delete the neighbouring digit instead
      if (
        digits === lastDigits &&
        before > 0 &&
        event.inputType === "deleteContentBackward"
      ) {
        digits = digits.slice(0, before - 1) + digits.slice(before);
        before--;
      } else if (
        digits === lastDigits &&
        event.inputType === "deleteContentForward"
      ) {
        digits = digits.slice(0, before) + digits.slice(before + 1);
      }

      const formatted = group(digits);
      lastDigits = digits;
      if (formatted === input.value) return;

      input.value = formatted;
      if (document.activeElement === input) {
        const pos = caretAfterDigits(formatted, before);
        input.setSelectionRange(pos, pos);
      }
    });
  }

  // ===== "?" note =====
  function setNote(btn, open) {
    btn.setAttribute("aria-expanded", open);
    document.getElementById(btn.getAttribute("aria-controls")).hidden = !open;
  }

  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".field-info-btn");
    document
      .querySelectorAll('.field-info-btn[aria-expanded="true"]')
      .forEach((open) => open !== btn && setNote(open, false));
    if (btn) setNote(btn, btn.getAttribute("aria-expanded") !== "true");
  });

  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    document
      .querySelectorAll('.field-info-btn[aria-expanded="true"]')
      .forEach((open) => setNote(open, false));
  });

  // ===== Init =====
  function init() {
    document.querySelectorAll(".phone-field-wrap input").forEach(attach);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
