// ===== Personnel dashboard =====
const STATUS_API = ROOT_URL + "/apply/status";
const VERIFY_PAYMENT_API = ROOT_URL + "/apply/verify_payment";
const UNDO_PAYMENT_API = ROOT_URL + "/apply/undo_payment";
const HARD_COPY_API = ROOT_URL + "/apply/hard_copy";
const REJECT_PAYMENT_API = ROOT_URL + "/apply/reject_payment";
const SIGNED_SCAN_UPLOAD_API = ROOT_URL + "/apply/signed_scan_upload";
const CLEARANCE_VIEW_URL = ROOT_URL + "/apply/clearance/";

// ===== DOM refs =====
const protocolsList = document.getElementById("protocolsList");
const noResultsMsg = document.getElementById("noResultsMsg");
const filterPills = document.querySelectorAll(
  "#filterPillsRow .status-card[data-filter]",
);
const mobileSortTrigger = document.querySelector(".mobile-sort-trigger");
const mobileSortOptions = document.getElementById("mobileSortOptions");
const mobileSortLabel = document.getElementById("mobileSortLabel");
const mobileFilterTrigger = document.querySelector(".mobile-filter-trigger");
const mobileFilterOptions = document.getElementById("mobileFilterOptions");
const mobileFilterLabel = document.getElementById("mobileFilterLabel");
const searchInput = document.getElementById("inboxSearchInput");
const searchClearBtn = document.getElementById("inboxSearchClear");
const resultsSummary = document.getElementById("resultsSummary");
const rowsPerPageSel = document.getElementById("rowsPerPageSelect");
const bulkActionsBar = document.getElementById("bulkActionsBar");
const clearanceBulk = document.getElementById("clearanceBulk");
const paymentFilterWrapper = document.getElementById("paymentFilterWrapper");
const paymentFilterSelect = document.getElementById("paymentFilterSelect");
const clearancePanel = document.getElementById("clearancePanel");
const toggleSelectBtn = document.getElementById("toggleSelectBtn");
const toggleSelectBtnLabel = document.getElementById("toggleSelectBtnLabel");
const toggleSelectBtnIcon = document.getElementById("toggleSelectBtnIcon");
const VISIBLE_SELECTABLE =
  ".protocol:not(.protocol-row-hidden) .protocol-select-checkbox:not(:disabled)";
const bulkSelectControls = document.getElementById("bulkSelectControls");
const bulkSelectCount = document.getElementById("bulkSelectCount");
const selectAllBtn = document.getElementById("selectAllBtn");
const bulkDownloadBtn = document.getElementById("bulkDownloadBtn");
const bulkEndorseBtn = document.getElementById("bulkEndorseBtn");
const sortSelect = document.getElementById("inboxSortSelect");
const paymentClearBtn = document.getElementById("paymentClearBtn");

const allRows = protocolsList
  ? [...protocolsList.querySelectorAll(".protocol")]
  : [];

const DEFAULT_FILTER = "to-review";
const DEFAULT_ROWS = 10;

let activeFilter = DEFAULT_FILTER;
let searchQuery = "";
const pager = createPagination({
  info: document.getElementById("paginationInfo"),
  buttons: document.getElementById("paginationButtons"),
  rowsSelect: rowsPerPageSel,
  defaultRows: DEFAULT_ROWS,
  noun: "protocols",
  onChange: () => renderTable(),
});

// ===== Sort by =====
const SORT_STORAGE_KEY = CURRENT_USER_ID + ":inboxSort";

function setUrlParam(params, key, value) {
  if (value) params.set(key, value);
  else params.delete(key);
}

function saveView() {
  const url = new URL(window.location);
  setUrlParam(url.searchParams, "q", searchInput?.value.trim());
  setUrlParam(
    url.searchParams,
    "rows",
    pager.rows !== DEFAULT_ROWS ? pager.rows : "",
  );
  setUrlParam(url.searchParams, "payment", paymentFilterSelect?.value);
  setUrlParam(url.searchParams, "page", pager.page > 1 ? pager.page : "");
  history.replaceState(null, "", url);
}

function applySort(mode) {
  allRows.sort(protocolSortComparator(mode));
  allRows.forEach((row) => protocolsList.appendChild(row));
}

sortSelect?.addEventListener("change", () => {
  localStorage.setItem(SORT_STORAGE_KEY, sortSelect.value);
  mobileSortOptions
    ?.querySelectorAll(".status-card")
    .forEach((btn) =>
      btn.classList.toggle("active", btn.dataset.sort === sortSelect.value),
    );
  if (mobileSortLabel)
    mobileSortLabel.textContent = sortSelect.selectedOptions[0].textContent;
  applySort(sortSelect.value);
  pager.page = 1;
  renderTable();
});

// ===== Only show the payment filter and select button while viewing the Reviewed tab, and
// the clearance panel while viewing the Endorsed tab; hide the whole
// bar when none of them has anything to do =====
function updateBulkActionsBarVisibility() {
  const canSelect =
    activeFilter === "reviewed" && REVIEWED_DOWNLOADABLE_COUNT > 0;

  if (paymentFilterWrapper) {
    paymentFilterWrapper.hidden = !canSelect;
    if (!canSelect) paymentFilterSelect.value = "";
  }
  if (clearancePanel) {
    const showClearances = activeFilter === "endorsed";
    clearancePanel.hidden = !showClearances;
    clearanceBulk.hidden = !showClearances;
    document
      .getElementById("clearanceLayout")
      .classList.toggle("clearance-layout--split", showClearances);
    if (showClearances && typeof ensureClearanceBoard === "function")
      ensureClearanceBoard();
  }
  if (toggleSelectBtn) {
    toggleSelectBtn.hidden = !canSelect;
    if (toggleSelectBtn.hidden) {
      exitSelectionMode();
    }
  }
  if (bulkActionsBar) {
    bulkActionsBar.hidden =
      (paymentFilterWrapper?.hidden ?? true) &&
      (toggleSelectBtn?.hidden ?? true) &&
      (clearanceBulk?.hidden ?? true);
  }
}

// ===== Selection mode =====
function exitSelectionMode() {
  protocolsList?.classList.remove("selection-mode");
  if (toggleSelectBtnLabel) toggleSelectBtnLabel.textContent = "Select";
  if (toggleSelectBtnIcon) toggleSelectBtnIcon.hidden = false;
  if (bulkSelectControls) bulkSelectControls.hidden = true;
  protocolsList
    ?.querySelectorAll(".protocol-select-checkbox")
    .forEach((cb) => (cb.checked = false));
  updateBulkSelectUI();
}

function updateBulkSelectUI() {
  const checkboxes = [
    ...(protocolsList?.querySelectorAll(VISIBLE_SELECTABLE) ?? []),
  ];
  const selected = checkboxes.filter((cb) => cb.checked);

  if (bulkSelectCount) {
    bulkSelectCount.textContent = `${selected.length} selected`;
  }
  if (bulkDownloadBtn) {
    bulkDownloadBtn.disabled = selected.length === 0;
  }
  if (bulkEndorseBtn) {
    bulkEndorseBtn.disabled = !selected.some((cb) =>
      cb.hasAttribute("data-can-endorse"),
    );
  }
  if (selectAllBtn) {
    selectAllBtn.textContent =
      checkboxes.length > 0 && selected.length === checkboxes.length
        ? "Deselect All"
        : "Select All";
  }
}

toggleSelectBtn?.addEventListener("click", () => {
  const active = protocolsList.classList.toggle("selection-mode");
  if (toggleSelectBtnLabel)
    toggleSelectBtnLabel.textContent = active ? "Cancel" : "Select";
  if (toggleSelectBtnIcon) toggleSelectBtnIcon.hidden = active;
  if (bulkSelectControls) bulkSelectControls.hidden = !active;
  if (!active) {
    protocolsList
      .querySelectorAll(".protocol-select-checkbox")
      .forEach((cb) => (cb.checked = false));
  }
  updateBulkSelectUI();
});

protocolsList?.addEventListener("change", (e) => {
  if (!e.target.classList.contains("protocol-select-checkbox")) return;
  updateBulkSelectUI();
});

protocolsList?.addEventListener("click", (e) => {
  if (!protocolsList.classList.contains("selection-mode")) return;
  if (e.target.closest("a, button, input, label, select")) return;
  const checkbox = e.target
    .closest(".protocol")
    ?.querySelector(".protocol-select-checkbox:not(:disabled)");
  if (!checkbox) return;
  checkbox.checked = !checkbox.checked;
  updateBulkSelectUI();
});

selectAllBtn?.addEventListener("click", () => {
  const checkboxes = [
    ...(protocolsList?.querySelectorAll(VISIBLE_SELECTABLE) ?? []),
  ];
  const allSelected =
    checkboxes.length > 0 && checkboxes.every((cb) => cb.checked);
  checkboxes.forEach((cb) => (cb.checked = !allSelected));
  updateBulkSelectUI();
});

// ===== Selected protocol ids =====
function selectedProtocolIds(selector) {
  return [...(protocolsList?.querySelectorAll(selector) ?? [])]
    .map((cb) => parseInt(cb.closest(".protocol").dataset.protocolId, 10))
    .filter(Boolean);
}

bulkDownloadBtn?.addEventListener("click", () => {
  const protocolIds = selectedProtocolIds(".protocol-select-checkbox:checked");
  if (protocolIds.length === 0) return;
  window.location.href =
    ROOT_URL + "/apply/download_selected?ids=" + protocolIds.join(",");
});

bulkEndorseBtn?.addEventListener("click", () => {
  const protocolIds = selectedProtocolIds(
    ".protocol:not(.protocol-row-hidden) .protocol-select-checkbox[data-can-endorse]:checked",
  );
  const skippedCount =
    selectedProtocolIds(
      ".protocol:not(.protocol-row-hidden) .protocol-select-checkbox:checked",
    ).length -
    protocolIds.length;

  if (protocolIds.length === 0) return;

  const skippedNote =
    skippedCount > 0
      ? ` ${skippedCount} selected protocol${skippedCount === 1 ? " is" : "s are"} not paid with a signed scan and will be skipped.`
      : "";

  confirmAction(
    `Mark ${protocolIds.length} selected protocol${protocolIds.length === 1 ? "" : "s"} as endorsed? Double-check that payment is verified and the signed scans are correct before proceeding.${skippedNote}`,
    {
      okText: "Mark as Endorsed",
      cancelText: "Cancel",
    },
  ).then((ok) => ok && submitBulkEndorse(protocolIds, bulkEndorseBtn));
});

async function submitBulkEndorse(protocolIds, btn) {
  setButtonBusy(btn, true, "Endorsing...");

  const results = await Promise.all(
    protocolIds.map((protocolId) =>
      fetch(STATUS_API, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": CSRF_TOKEN,
        },
        body: JSON.stringify({
          protocol_id: protocolId,
          status: "Endorsed",
        }),
      })
        .then((res) => res.json())
        .then((data) => ({
          protocolId,
          ok: !!data.ok,
          queued: !!data.queued,
        }))
        .catch(() => ({
          protocolId,
          ok: false,
        })),
    ),
  );

  const failedCount = results.filter((r) => !r.ok && !r.queued).length;
  const okCount = results.filter((r) => r.ok).length;

  if (results.some((r) => r.queued)) setButtonBusy(btn, false);

  if (failedCount > 0) {
    setButtonBusy(btn, false);
    showToast(
      okCount > 0
        ? `${okCount} of ${results.length} protocol(s) were marked as endorsed. ${failedCount} could not be updated and may no longer be eligible.`
        : `Could not mark the selected protocol(s) as endorsed. They may no longer be eligible, or a network error occurred.`,
      "error",
    );
  }

  if (okCount > 0) {
    // Give the error toast time to be read before the reload wipes it
    if (failedCount > 0) await new Promise((r) => setTimeout(r, 3500));
    window.location.reload();
  }
}

// ===== Status guide (colors & text) =====
function hexToRgba(hex, alpha) {
  const h = hex.replace("#", "");
  const r = parseInt(h.substring(0, 2), 16);
  const g = parseInt(h.substring(2, 4), 16);
  const b = parseInt(h.substring(4, 6), 16);
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function updateStatusGuide(selected) {
  const guide = document.getElementById("statusGuide");
  if (!guide) return;

  const meta = statusMeta[selected];
  if (!meta) {
    guide.classList.remove("open");
    guide.style.background = "";
    guide.innerHTML = "";
    return;
  }

  guide.innerHTML = `<p class="status-guide-text">
                            <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#info-icon" />
                            </svg> ${meta.desc}
                        </p>`;
  guide.style.background = hexToRgba(meta.color, 0.12);
  guide.classList.add("open");
}

// ===== Mobile sort dropdown open/close =====
function openDropdown(trigger, panel) {
  if (!trigger || !panel) return;
  const isOpen = panel.classList.toggle("active");
  trigger.classList.toggle("open", isOpen);
  trigger.setAttribute("aria-expanded", isOpen);
  if (isOpen) {
    positionEdgeAwareDropdown(panel.closest(".sort-wrapper"), panel);
  }
}

function closeDropdown(trigger, panel) {
  if (!trigger || !panel) return;
  panel.classList.remove("active");
  trigger.classList.remove("open");
  trigger.setAttribute("aria-expanded", "false");
}

mobileSortTrigger?.addEventListener("click", (e) => {
  e.stopPropagation();
  openDropdown(mobileSortTrigger, mobileSortOptions);
});

document.addEventListener("click", (e) => {
  [
    [mobileSortTrigger, mobileSortOptions],
    [mobileFilterTrigger, mobileFilterOptions],
  ].forEach(([trigger, panel]) => {
    if (!panel?.contains(e.target) && !trigger?.contains(e.target)) {
      closeDropdown(trigger, panel);
    }
  });
});

// ===== Mobile sort dropdown mirrors the desktop <select> =====
const mobileSortBtns =
  mobileSortOptions?.querySelectorAll(".status-card") ?? [];
mobileSortBtns.forEach((btn) => {
  btn.addEventListener("click", () => {
    sortSelect.value = btn.dataset.sort;
    sortSelect.dispatchEvent(new Event("change"));
    closeDropdown(mobileSortTrigger, mobileSortOptions);
  });
});

// ===== Mobile status dropdown mirrors the filter pills =====
function syncMobileFilter() {
  mobileFilterOptions
    ?.querySelectorAll(".status-card")
    .forEach((btn) =>
      btn.classList.toggle("active", btn.dataset.mobileFilter === activeFilter),
    );
  if (mobileFilterLabel) {
    mobileFilterLabel.textContent =
      [...filterPills].find((pill) => pill.dataset.filter === activeFilter)
        ?.dataset.label ?? "";
  }
}

mobileFilterTrigger?.addEventListener("click", (e) => {
  e.stopPropagation();
  closeDropdown(mobileSortTrigger, mobileSortOptions);
  openDropdown(mobileFilterTrigger, mobileFilterOptions);
});

mobileFilterOptions?.querySelectorAll(".status-card").forEach((btn) => {
  btn.addEventListener("click", () => {
    closeDropdown(mobileFilterTrigger, mobileFilterOptions);
    [...filterPills]
      .find((pill) => pill.dataset.filter === btn.dataset.mobileFilter)
      ?.click();
  });
});

// ===== Status filter tabs =====
filterPills.forEach((pill) => {
  pill.addEventListener("click", () => {
    filterPills.forEach((p) => p.classList.remove("active"));
    pill.classList.add("active");
    activeFilter = pill.dataset.filter;
    pager.page = 1;

    pill.scrollIntoView({
      block: "nearest",
      inline: "center",
    });

    updateStatusGuide(activeFilter);
    syncMobileFilter();

    const url = new URL(window.location);
    url.searchParams.set("status", activeFilter);
    history.replaceState(null, "", url);
    updateBulkActionsBarVisibility();
    renderTable();
  });
});

// ===== Payment status filter =====
paymentFilterSelect?.addEventListener("change", () => {
  pager.page = 1;
  renderTable();
});

paymentClearBtn?.addEventListener("click", () => {
  paymentFilterSelect.value = "";
  paymentFilterSelect.dispatchEvent(new Event("change"));
  paymentFilterSelect.focus({ preventScroll: true });
});

// ===== Search =====
searchInput?.addEventListener("input", () => {
  searchQuery = searchInput.value.trim().toLowerCase();
  searchClearBtn.classList.toggle("visible", searchQuery.length > 0);
  pager.page = 1;
  renderTable();
});

searchClearBtn?.addEventListener("click", () => {
  searchInput.value = "";
  searchQuery = "";
  searchClearBtn.classList.remove("visible");
  pager.page = 1;
  renderTable();
  searchInput.focus();
});

// ===== Restore filter from URL param (?status=...) =====
(function restoreFilterFromUrl() {
  const urlParams = new URLSearchParams(window.location.search);
  const requestedStatus =
    urlParams.get("status") ||
    (urlParams.get("tab") === "clearance" ? "endorsed" : null);
  if (requestedStatus) {
    const matchingPill = [...filterPills].find(
      (p) => p.dataset.filter === requestedStatus,
    );
    if (matchingPill) {
      filterPills.forEach((p) => p.classList.remove("active"));
      matchingPill.classList.add("active");
      activeFilter = requestedStatus;
      matchingPill.scrollIntoView({
        block: "nearest",
        inline: "center",
      });
    }
  } else {
    const url = new URL(window.location);
    url.searchParams.set("status", activeFilter);
    history.replaceState(null, "", url);
  }
  updateStatusGuide(activeFilter);
  syncMobileFilter();
  updateBulkActionsBarVisibility();
})();

// ===== Main render =====
function renderTable() {
  let tabTotal = 0;
  const visibleRows = allRows.filter((row) => {
    const slug = row.dataset.filterSlug;
    const title =
      row.querySelector(".research-title")?.textContent.toLowerCase() ?? "";
    const researcher = row.dataset.researcher ?? "";

    const matchesFilter = activeFilter === "all" || slug === activeFilter;
    if (matchesFilter) tabTotal++;

    const matchesSearch =
      !searchQuery ||
      title.includes(searchQuery) ||
      researcher.includes(searchQuery);

    const matchesPayment =
      !paymentFilterSelect?.value ||
      row.dataset.payment === paymentFilterSelect.value;

    return matchesFilter && matchesSearch && matchesPayment;
  });

  allRows.forEach((row) => row.classList.add("protocol-row-hidden"));

  const totalRows = visibleRows.length;
  const pageRows = pager.paginate(visibleRows);
  saveView();
  if (paymentClearBtn) paymentClearBtn.hidden = !paymentFilterSelect.value;

  pageRows.forEach((row) => row.classList.remove("protocol-row-hidden"));

  allRows
    .filter((row) => row.classList.contains("protocol-row-hidden"))
    .forEach((row) =>
      row
        .querySelectorAll(".protocol-select-checkbox")
        .forEach((cb) => (cb.checked = false)),
    );
  updateBulkSelectUI();

  if (noResultsMsg) {
    noResultsMsg.style.display = totalRows === 0 ? "flex" : "none";
  }

  if (resultsSummary) {
    resultsSummary.textContent = searchQuery
      ? `Showing ${totalRows} of ${tabTotal} protocol${tabTotal === 1 ? "" : "s"}`
      : "";
  }
}

// ===== Status legend info panel (single click to open/close) =====
(function () {
  const wrapper = document.getElementById("legendInfoWrapper");
  const btn = document.getElementById("legendInfoBtn");
  const panel = document.getElementById("legendInfoPanel");
  if (!wrapper || !btn || !panel) return;

  function openPanel() {
    panel.classList.add("open");
    btn.setAttribute("aria-expanded", "true");
  }

  function closePanel() {
    panel.classList.remove("open");
    btn.setAttribute("aria-expanded", "false");
  }

  btn.addEventListener("click", (e) => {
    e.stopPropagation();
    panel.classList.contains("open") ? closePanel() : openPanel();
  });

  document.addEventListener("click", (e) => {
    if (!wrapper.contains(e.target)) closePanel();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closePanel();
  });
})();

// ===== Restore saved sort =====
restoreProtocolSort(sortSelect, SORT_STORAGE_KEY, applySort);

// ===== Restore search, rows per page, payment filter and page from the URL =====
(function restoreView() {
  const urlParams = new URLSearchParams(window.location.search);
  const rowsValue = urlParams.get("rows");
  const searchText = urlParams.get("q") ?? "";
  const paymentValue = urlParams.get("payment") ?? "";

  if (searchInput && searchText) {
    searchInput.value = searchText;
    searchQuery = searchText.trim().toLowerCase();
    searchClearBtn.classList.toggle("visible", searchQuery.length > 0);
  }

  pager.setRows(rowsValue);

  if (
    paymentFilterSelect &&
    !paymentFilterWrapper.hidden &&
    [...paymentFilterSelect.options].some(
      (option) => option.value === paymentValue,
    )
  ) {
    paymentFilterSelect.value = paymentValue;
  }

  pager.page = Math.max(1, parseInt(urlParams.get("page"), 10) || 1);
})();

// ===== Initial render =====
renderTable();
