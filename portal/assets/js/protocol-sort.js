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

  // ===== Sort comparator =====
  window.protocolSortComparator = function (mode) {
    switch (mode) {
      case "oldest":
        return (a, b) => timestampOf(a) - timestampOf(b);
      case "updated":
        return (a, b) => updatedOf(b) - updatedOf(a);
      case "title_asc":
        return (a, b) => titleOf(a).localeCompare(titleOf(b));
      case "title_desc":
        return (a, b) => titleOf(b).localeCompare(titleOf(a));
      case "newest":
      default:
        return (a, b) => timestampOf(b) - timestampOf(a);
    }
  };

  // ===== Sort restore =====
  window.restoreProtocolSort = function (sortSelect, storageKey, applySort) {
    if (!sortSelect) return;
    const savedSort = localStorage.getItem(storageKey);
    if ([...sortSelect.options].some((option) => option.value === savedSort)) {
      sortSelect.value = savedSort;
      document
        .querySelectorAll("#mobileSortOptions .status-card")
        .forEach((btn) =>
          btn.classList.toggle("active", btn.dataset.sort === savedSort),
        );
      const mobileSortLabel = document.getElementById("mobileSortLabel");
      if (mobileSortLabel)
        mobileSortLabel.textContent = sortSelect.selectedOptions[0].textContent;
    }
    applySort(sortSelect.value);
  };
})();
