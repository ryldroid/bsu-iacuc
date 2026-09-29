(function () {
  // Sliding underline for the status filters (.status-filters). The pages
  // already toggle .active on the .status-card buttons, so this only watches
  // for that change and moves one shared indicator under the active button.
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

      // Snap (no slide) on first paint or when the bar was hidden before,
      // e.g. behind another dashboard tab or the mobile dropdown.
      indicator.classList.toggle("no-transition", !animate || lastWidth === 0);
      indicator.style.setProperty("--underline-left", active.offsetLeft + "px");
      indicator.style.setProperty(
        "--underline-width",
        active.offsetWidth + "px",
      );
      lastWidth = active.offsetWidth;
    }

    update(false);
    // Let the first position settle before enabling the slide.
    requestAnimationFrame(() => requestAnimationFrame(() => update(true)));

    new MutationObserver(() => update(true)).observe(bar, {
      attributes: true,
      attributeFilter: ["class"],
      subtree: true,
    });

    // Keep it aligned when button widths change (counts updating, resize,
    // fonts loading, the bar becoming visible).
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

  document.querySelectorAll(".status-filters").forEach(init);
})();
