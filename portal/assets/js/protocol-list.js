(function () {
  // ===== Status legend info panel (single click to open/close) =====
  const wrapper = document.getElementById("legendInfoWrapper");
  const legendBtn = document.getElementById("legendInfoBtn");
  const legendPanel = document.getElementById("legendInfoPanel");

  if (wrapper && legendBtn && legendPanel) {
    const fitLegendPanel = () => {
      const top = legendPanel.getBoundingClientRect().top;
      legendPanel.style.maxHeight =
        Math.max(200, window.innerHeight - top - 16) + "px";
    };

    const setLegendOpen = (open) => {
      legendPanel.classList.toggle("open", open);
      legendBtn.setAttribute("aria-expanded", open);
      if (open) fitLegendPanel();
    };

    const refitLegendPanel = () => {
      if (legendPanel.classList.contains("open")) fitLegendPanel();
    };

    window.addEventListener("resize", refitLegendPanel);
    window.addEventListener("scroll", refitLegendPanel, { passive: true });

    legendBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      setLegendOpen(!legendPanel.classList.contains("open"));
    });

    document.addEventListener("click", (e) => {
      if (!wrapper.contains(e.target)) setLegendOpen(false);
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") setLegendOpen(false);
    });
  }

  // ===== Sort options: mobile dropdown panel, inline pills on tablet and desktop =====
  const sortSelect = document.querySelector(".dashboard-sort-select");
  const sortTrigger = document.querySelector(".mobile-sort-trigger");
  const sortPanel = document.getElementById("sortOptions");
  if (!sortSelect || !sortTrigger || !sortPanel) return;

  function closeSortPanel() {
    sortPanel.classList.remove("active");
    sortTrigger.classList.remove("open");
    sortTrigger.setAttribute("aria-expanded", "false");
  }

  sortTrigger.addEventListener("click", (e) => {
    e.stopPropagation();
    const isOpen = sortPanel.classList.toggle("active");
    sortTrigger.classList.toggle("open", isOpen);
    sortTrigger.setAttribute("aria-expanded", isOpen);
    if (isOpen) {
      positionEdgeAwareDropdown(sortPanel.closest(".sort-wrapper"), sortPanel);
    }
  });

  document.addEventListener("click", (e) => {
    if (!sortPanel.contains(e.target) && !sortTrigger.contains(e.target)) {
      closeSortPanel();
    }
  });

  sortPanel.querySelectorAll(".status-card").forEach((btn) => {
    btn.addEventListener("click", () => {
      sortSelect.value = btn.classList.contains("active")
        ? reverseProtocolSort(btn.dataset.sort)
        : btn.dataset.sort;
      sortSelect.dispatchEvent(new Event("change"));
      closeSortPanel();
    });
  });
})();
