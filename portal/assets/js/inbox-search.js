// ===== Inbox search: the magnifier button, its modal and the typed query =====
// Used by the personnel inbox and the researcher dashboard. onChange() redraws the list.
window.createInboxSearch = function ({ onChange }) {
  const button = document.getElementById("inboxSearchBtn");
  const backdrop = document.getElementById("inboxSearchBackdrop");
  const input = document.getElementById("inboxSearchInput");
  const clearBtn = document.getElementById("inboxSearchClear");
  const summary = document.getElementById("resultsSummary");

  const search = {
    query: "",

    get text() {
      return input?.value.trim() ?? "";
    },

    setText(value) {
      if (!input) return;
      if (input.value !== value) input.value = value;
      search.query = value.trim().toLowerCase();
      clearBtn.classList.toggle("visible", search.query.length > 0);
      button.classList.toggle("active", search.query.length > 0);
    },

    setSummary(text) {
      if (summary) summary.textContent = text;
    },
  };

  function setOpen(open) {
    backdrop.classList.toggle("open", open);
    if (open) input.focus();
  }

  button?.addEventListener("click", () => setOpen(true));
  document
    .getElementById("inboxSearchClose")
    ?.addEventListener("click", () => setOpen(false));
  document
    .getElementById("inboxSearchDone")
    ?.addEventListener("click", () => setOpen(false));
  backdrop?.addEventListener("click", (e) => {
    if (e.target === backdrop) setOpen(false);
  });

  input?.addEventListener("keydown", (e) => {
    if (e.key === "Enter") setOpen(false);
  });
  input?.addEventListener("input", () => {
    search.setText(input.value);
    onChange();
  });
  clearBtn?.addEventListener("click", () => {
    search.setText("");
    onChange();
    input.focus();
  });

  return search;
};
