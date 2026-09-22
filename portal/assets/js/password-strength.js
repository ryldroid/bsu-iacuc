(function () {
  // Order must match the <li> order in .password-requirements markup,
  // and mirrors Controller::validatePasswordRequirements() on the backend.
  const RULES = [
    (pw) => pw.length >= 8,
    (pw) => /[A-Z]/.test(pw),
    (pw) => /[a-z]/.test(pw),
    (pw) => /[0-9]/.test(pw),
    (pw) => /[^a-zA-Z0-9]/.test(pw),
  ];

  function checkIconMarkup() {
    return (
      '<svg class="password-req-check" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
      '<use href="#check-icon"></use>' +
      "</svg>"
    );
  }

  function attach(block, input) {
    if (block.dataset.strengthAttached === "1") return;
    block.dataset.strengthAttached = "1";

    const items = Array.from(block.querySelectorAll("ul > li"));
    items.forEach((li) =>
      li.insertAdjacentHTML("afterbegin", checkIconMarkup()),
    );

    block.hidden = true;

    function update() {
      const value = input.value;
      block.hidden = value.length === 0;
      items.forEach((li, i) => {
        const met = typeof RULES[i] === "function" && RULES[i](value);
        li.classList.toggle("met", met);
      });
    }

    input.addEventListener("input", update);
    update();
  }

  function init() {
    document.querySelectorAll(".password-requirements").forEach((block) => {
      const form = block.closest("form");
      const input = form ? form.querySelector("#password") : null;
      if (input) attach(block, input);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
