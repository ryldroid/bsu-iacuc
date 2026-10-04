(function () {
  // ===== Clean typed number =====
  function clean(value) {
    let digits = value.replace(/\D/g, "");

    if (digits.startsWith("63") && digits.length > 10) {
      digits = digits.slice(2);
    }

    if (digits.startsWith("0")) {
      digits = digits.slice(1);
    }

    return digits.slice(0, 10);
  }

  // ===== Attach to a field =====
  function attach(input) {
    if (input.dataset.phoneCleanAttached === "1") return;
    input.dataset.phoneCleanAttached = "1";

    input.addEventListener("input", () => {
      const cleaned = clean(input.value);
      if (cleaned !== input.value) input.value = cleaned;
    });
  }

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
