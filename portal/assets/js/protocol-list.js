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

  // ===== Sort options: dropdown with order radios (mobile and compact), inline pills otherwise =====
  const sortSelect = document.querySelector(".dashboard-sort-select");
  const sortTrigger = document.querySelector(".mobile-sort-trigger");
  const sortPanel = document.getElementById("sortOptions");
  if (!sortSelect || !sortTrigger || !sortPanel) return;

  const inPillMode = () => getComputedStyle(sortTrigger).display === "none";

  function applySortValue(value) {
    sortSelect.value = value;
    sortSelect.dispatchEvent(new Event("change"));
  }

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

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeSortPanel();
  });

  // Pills flip the direction of the current choice; the dropdown keeps the chosen order
  sortPanel.querySelectorAll(".status-card").forEach((btn) => {
    btn.addEventListener("click", () => {
      if (inPillMode()) {
        applySortValue(
          btn.classList.contains("active")
            ? reverseProtocolSort(btn.dataset.sort)
            : btn.dataset.sort,
        );
        return;
      }
      const currentDirection = sortSelect.value.split("_")[1];
      applySortValue(btn.dataset.sortKey + "_" + currentDirection);
    });
  });

  sortPanel.querySelectorAll(".sort-order input").forEach((radio) => {
    radio.addEventListener("change", () => {
      applySortValue(sortSelect.value.split("_")[0] + "_" + radio.value);
    });
  });
})();
