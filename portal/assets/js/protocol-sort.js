(function () {
  function timestampOf(el) {
    const parsed = Date.parse(el.dataset.submitted || "");
    return isNaN(parsed) ? 0 : parsed;
  }

  function titleOf(el) {
    return el.dataset.title || "";
  }

  window.protocolSortComparator = function (mode) {
    switch (mode) {
      case "oldest":
        return (a, b) => timestampOf(a) - timestampOf(b);
      case "title_asc":
        return (a, b) => titleOf(a).localeCompare(titleOf(b));
      case "title_desc":
        return (a, b) => titleOf(b).localeCompare(titleOf(a));
      case "newest":
      default:
        return (a, b) => timestampOf(b) - timestampOf(a);
    }
  };
})();
