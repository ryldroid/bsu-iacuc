(function () {
  // ===== Sort helpers =====
  function timestampOf(el) {
    const parsed = Date.parse(el.dataset.submitted || "");
    return isNaN(parsed) ? 0 : parsed;
  }

  function updatedOf(el) {
    const parsed = Date.parse(el.dataset.updated || "");
    return isNaN(parsed) ? 0 : parsed;
  }

  function titleOf(el) {
    return el.dataset.title || "";
  }

  const STATUS_ORDER = [
    "to-review",
    "returned-for-revision",
    "reviewed",
    "endorsed",
    "approved",
    "archived",
  ];

  function statusOf(el) {
    const index = STATUS_ORDER.indexOf(el.dataset.filterSlug);
    return index === -1 ? STATUS_ORDER.length : index;
  }

  // ===== Sort comparator (mode is key_direction, e.g. submitted_desc) =====
  window.protocolSortComparator = function (mode) {
    const [key, direction] = mode.split("_");
    const flip = direction === "desc" ? -1 : 1;
    switch (key) {
      case "updated":
        return (a, b) => flip * (updatedOf(a) - updatedOf(b));
      case "title":
        return (a, b) => flip * titleOf(a).localeCompare(titleOf(b));
      case "status":
        return (a, b) =>
          flip * (statusOf(a) - statusOf(b)) || timestampOf(a) - timestampOf(b);
      case "submitted":
      default:
        return (a, b) => flip * (timestampOf(a) - timestampOf(b));
    }
  };

  window.reverseProtocolSort = function (mode) {
    return mode.endsWith("_asc")
      ? mode.replace(/_asc$/, "_desc")
      : mode.replace(/_desc$/, "_asc");
  };

  // ===== Sort controls (pills with direction arrow on tablet and desktop, dropdown label on mobile) =====
  window.syncProtocolSortControls = function (sortSelect) {
    const [currentKey, currentDirection] = sortSelect.value.split("_");
    document.querySelectorAll("#sortOptions .status-card").forEach((btn) => {
      const isCurrent = btn.dataset.sortKey === currentKey;
      const direction = isCurrent ? currentDirection : btn.dataset.firstDir;
      btn.dataset.sort = btn.dataset.sortKey + "_" + direction;
      btn.classList.toggle("active", isCurrent);
      btn.setAttribute("aria-pressed", isCurrent);
      btn
        .querySelector(".sort-direction use")
        .setAttribute(
          "href",
          direction === "asc" ? "#chev-up-icon" : "#chev-down-icon",
        );
    });
    const mobileSortLabel = document.getElementById("mobileSortLabel");
    if (mobileSortLabel)
      mobileSortLabel.textContent = sortSelect.selectedOptions[0].textContent;
  };

  // ===== Saved choices live on the account, so every device shows the same view =====
  window.saveViewPreference = function (key, value) {
    if (VIEW_PREFERENCES[key] === value) return;
    VIEW_PREFERENCES[key] = value;
    fetch(NOTIF_ROOT + "/user/view_preference", {
      method: "POST",
      headers: csrfHeaders({ "Content-Type": "application/json" }),
      body: JSON.stringify({ key, value }),
    }).catch(() => {});
  };

  // ===== Sort restore =====
  window.restoreProtocolSort = function (sortSelect, preferenceKey, applySort) {
    if (!sortSelect) return;
    const savedSort = VIEW_PREFERENCES[preferenceKey];
    if ([...sortSelect.options].some((option) => option.value === savedSort)) {
      sortSelect.value = savedSort;
    }
    syncProtocolSortControls(sortSelect);
    applySort(sortSelect.value);
  };
})();
