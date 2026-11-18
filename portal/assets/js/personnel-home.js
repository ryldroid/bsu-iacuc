// ===== Personnel dashboard =====
const STATUS_API = ROOT_URL + "/apply/status";
const VERIFY_PAYMENT_API = ROOT_URL + "/apply/verify_payment";
const UNDO_PAYMENT_API = ROOT_URL + "/apply/undo_payment";
const HARD_COPY_API = ROOT_URL + "/apply/hard_copy";
const REJECT_PAYMENT_API = ROOT_URL + "/apply/reject_payment";
const RESTORE_API = ROOT_URL + "/apply/restore";
const CLEARANCE_VIEW_URL = ROOT_URL + "/apply/clearance/";

// ===== DOM refs =====
const protocolsList = document.getElementById("protocolsList");
const noResultsMsg = document.getElementById("noResultsMsg");
const archivedLink = document.getElementById("archivedLink");
const archiveList = document.getElementById("archiveList");
const archiveCategoryBar = document.getElementById("archiveCategories");
const archiveDesc = document.getElementById("archiveDesc");
const archiveEmpty = document.getElementById("archiveEmpty");
const archiveEmptyText = document.getElementById("archiveEmptyText");
const rowsPerPageSel = document.getElementById("rowsPerPageSelect");
const bulkActionsBar = document.getElementById("bulkActionsBar");
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
const statusFilters = document.getElementById("statusFilters");

const allRows = protocolsList
  ? [...protocolsList.querySelectorAll(".protocol")]
  : [];

const ALL_ARCHIVE = "all";
const DEFAULT_ROWS = 10;

let archiveCategory =
  archiveCategoryBar?.querySelector(".archive-category.active")?.dataset
    .archiveCategory ?? "";
const requestedStatus =
  new URLSearchParams(window.location.search).get("status") ??
  VIEW_PREFERENCES.inboxStatus ??
  "";
let statusFilter = statusFilters?.querySelector(
  `[data-status="${CSS.escape(requestedStatus)}"]`,
)
  ? requestedStatus
  : "";
const pager = createPagination({
  info: document.getElementById("paginationInfo"),
  buttons: document.getElementById("paginationButtons"),
  rowsSelect: rowsPerPageSel,
  defaultRows: DEFAULT_ROWS,
  noun: "protocols",
  onChange: () => renderTable(),
});

// ===== Search =====
const inboxSearch = createInboxSearch({
  onChange: () => {
    pager.page = 1;
    renderTable();
  },
});

// ===== Sort by =====
const SORT_PREFERENCE = "inboxSort";

function setUrlParam(params, key, value) {
  if (value) params.set(key, value);
  else params.delete(key);
}

function saveView() {
  const url = new URL(window.location);
  setUrlParam(url.searchParams, "q", inboxSearch.text);
  setUrlParam(
    url.searchParams,
    "rows",
    pager.rows !== DEFAULT_ROWS ? pager.rows : "",
  );
  setUrlParam(url.searchParams, "status", statusFilter);
  setUrlParam(url.searchParams, "payment", paymentFilterSelect?.value);
  setUrlParam(
    url.searchParams,
    "category",
    archiveWindow.active && archiveCategory !== ALL_ARCHIVE
      ? archiveCategory
      : "",
  );
  setUrlParam(url.searchParams, "page", pager.page > 1 ? pager.page : "");
  history.replaceState(null, "", url);
}

function applySort(mode) {
  allRows.sort(protocolSortComparator(mode));
  allRows.forEach((row) => protocolsList.appendChild(row));
}

sortSelect?.addEventListener("change", () => {
  saveViewPreference(SORT_PREFERENCE, sortSelect.value);
  syncProtocolSortControls(sortSelect);
  applySort(sortSelect.value);
  pager.page = 1;
  renderTable();
});

// ===== Show the payment filter and select button only on the Reviewed tab, and
// the clearance panel only on the Endorsed tab =====
function updateBulkActionsBarVisibility() {
  const canSelect =
    statusFilter === "reviewed" && REVIEWED_DOWNLOADABLE_COUNT > 0;

  if (paymentFilterWrapper) {
    paymentFilterWrapper.hidden = !canSelect;
    if (!canSelect) paymentFilterSelect.value = "";
  }
  if (clearancePanel) {
    clearancePanel.hidden = statusFilter !== "endorsed" || ENDORSED_COUNT === 0;
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
      (toggleSelectBtn?.hidden ?? true);
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
    ).length - protocolIds.length;

  if (protocolIds.length === 0) return;
  openEndorsementModal(protocolIds, skippedCount);
});

// ===== Archive: a window of its own, kept out of the main list =====
const archiveRows = archiveList
  ? [...archiveList.querySelectorAll(".protocol")]
  : [];

const archiveWindow = createArchiveWindow({
  button: archivedLink,
  backdrop: document.getElementById("archiveBackdrop"),
  onChange: () => saveView(),
});

// ===== Archive categories =====
function renderArchive() {
  const inCategory = (row) =>
    archiveCategory === ALL_ARCHIVE ||
    row.dataset.archiveCategory === archiveCategory;

  archiveRows.forEach((row) =>
    row.classList.toggle("protocol-row-hidden", !inCategory(row)),
  );
  archiveEmpty?.classList.toggle("is-visible", !archiveRows.some(inCategory));
  if (archiveEmptyText)
    archiveEmptyText.textContent =
      archiveCategory === ALL_ARCHIVE
        ? "No archived protocols."
        : "No protocols in this category.";
}

function setArchiveCategory(category) {
  const chips = [
    ...(archiveCategoryBar?.querySelectorAll(".archive-category") ?? []),
  ];
  const chosen = chips.find(
    (chip) => chip.dataset.archiveCategory === category,
  );
  if (!chosen) return;

  archiveCategory = category;
  chips.forEach((chip) => {
    chip.classList.toggle("active", chip === chosen);
    chip.setAttribute("aria-pressed", chip === chosen);
  });
  if (archiveDesc) archiveDesc.textContent = chosen.dataset.desc;
}

archiveCategoryBar?.addEventListener("click", (e) => {
  const chip = e.target.closest(".archive-category");
  if (!chip) return;
  setArchiveCategory(chip.dataset.archiveCategory);
  renderArchive();
  saveView();
});

setArchiveCategory(new URLSearchParams(window.location.search).get("category"));
renderArchive();

// ===== Status filter =====
function setStatusFilter(status) {
  statusFilter = status;
  saveViewPreference("inboxStatus", status);
  statusFilters.querySelectorAll(".status-card").forEach((tab) => {
    const active = tab.dataset.status === status;
    tab.classList.toggle("active", active);
    tab.setAttribute("aria-pressed", active);
  });
  pager.page = 1;
  updateBulkActionsBarVisibility();
  renderTable();
}

statusFilters?.addEventListener("click", (e) => {
  const tab = e.target.closest(".status-card");
  if (tab) setStatusFilter(tab.dataset.status);
});

if (statusFilters) {
  statusFilters.querySelectorAll(".status-card").forEach((tab) => {
    const active = tab.dataset.status === statusFilter;
    tab.classList.toggle("active", active);
    tab.setAttribute("aria-pressed", active);
  });
}

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

updateBulkActionsBarVisibility();

// ===== Main render =====
function renderTable() {
  let tabTotal = 0;
  const visibleRows = allRows.filter((row) => {
    const slug = row.dataset.filterSlug;
    const title =
      row.querySelector(".research-title")?.textContent.toLowerCase() ?? "";
    const researcher = row.dataset.researcher ?? "";

    const matchesFilter = !statusFilter || slug === statusFilter;
    if (matchesFilter) tabTotal++;

    const matchesSearch =
      !inboxSearch.query ||
      title.includes(inboxSearch.query) ||
      researcher.includes(inboxSearch.query);

    const matchesPayment =
      !paymentFilterSelect?.value ||
      (slug === "reviewed" &&
        row.dataset.payment === paymentFilterSelect.value);

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

  inboxSearch.setSummary(
    inboxSearch.query
      ? `Showing ${totalRows} of ${tabTotal} protocol${tabTotal === 1 ? "" : "s"}`
      : "",
  );
}

// ===== Restore saved sort =====
restoreProtocolSort(sortSelect, SORT_PREFERENCE, applySort);

// ===== Restore search, rows per page, payment filter and page from the URL =====
(function restoreView() {
  const urlParams = new URLSearchParams(window.location.search);
  const rowsValue = urlParams.get("rows");
  const searchText = urlParams.get("q") ?? "";
  const paymentValue = urlParams.get("payment") ?? "";

  if (searchText) inboxSearch.setText(searchText);

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
