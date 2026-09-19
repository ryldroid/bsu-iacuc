(function () {
  function clean(value) {
    let digits = value.replace(/\D/g, "");

    // Pasted with the country code, e.g. "639171234567" or "+639171234567"
    if (digits.startsWith("63") && digits.length > 10) {
      digits = digits.slice(2);
    }

    // Typed in local "09XX" format
    if (digits.startsWith("0")) {
      digits = digits.slice(1);
    }

    return digits.slice(0, 10);
  }

  function attach(input) {
    if (input.dataset.phoneCleanAttached === "1") return;
    input.dataset.phoneCleanAttached = "1";

    input.addEventListener("input", () => {
      const cleaned = clean(input.value);
      if (cleaned !== input.value) input.value = cleaned;
    });
  }

  function init() {
    document.querySelectorAll(".phone-field-wrap input").forEach(attach);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
