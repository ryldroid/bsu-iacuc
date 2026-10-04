(function () {
  // ===== Position the underline =====
  function init(bar) {
    const indicator = document.createElement("span");
    indicator.className = "status-underline";
    indicator.setAttribute("aria-hidden", "true");
    bar.appendChild(indicator);
    bar.classList.add("has-underline");

    let lastWidth = 0;

    function update(animate) {
      const active = bar.querySelector(".status-card.active");
      if (!active || active.offsetWidth === 0) return;

      indicator.classList.toggle("no-transition", !animate || lastWidth === 0);
      indicator.style.setProperty("--underline-left", active.offsetLeft + "px");
      indicator.style.setProperty(
        "--underline-width",
        active.offsetWidth + "px",
      );
      lastWidth = active.offsetWidth;
    }

    update(false);
    requestAnimationFrame(() => requestAnimationFrame(() => update(true)));

    new MutationObserver(() => update(true)).observe(bar, {
      attributes: true,
      attributeFilter: ["class"],
      subtree: true,
    });

    if ("ResizeObserver" in window) {
      const ro = new ResizeObserver(() => update(true));
      bar.querySelectorAll(".status-card").forEach((btn) => ro.observe(btn));
    } else {
      window.addEventListener("resize", () => update(false));
    }

    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(() => update(false));
    }
  }

  // ===== Start up =====
  document.querySelectorAll(".status-filters").forEach(init);
})();
