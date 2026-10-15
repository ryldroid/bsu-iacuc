(function () {
  // ===== Page number set (first, last, and the pages around the current one) =====
  function buildPageSet(current, total) {
    const pages = new Set([1, total]);
    for (
      let i = Math.max(1, current - 1);
      i <= Math.min(total, current + 1);
      i++
    ) {
      pages.add(i);
    }
    return [...pages].sort((a, b) => a - b);
  }

  // ===== Shared client-side pagination =====
  // Expects the markup of #paginationBar: an info element, a buttons element and a rows-per-page select.
  // Call paginate(items) with the already filtered and sorted items; it returns just the current page's items.
  window.createPagination = function ({
    info,
    buttons,
    rowsSelect,
    defaultRows = 10,
    noun = "items",
    storageKey = "",
    onChange,
  }) {
    const pager = {
      page: 1,
      rows: defaultRows,
      defaultRows,

      paginate(items) {
        const totalPages = Math.max(1, Math.ceil(items.length / pager.rows));
        pager.page = Math.min(Math.max(1, pager.page), totalPages);

        const start = (pager.page - 1) * pager.rows;
        const pageItems = items.slice(start, start + pager.rows);

        if (info) {
          info.textContent = items.length
            ? `Showing ${start + 1}–${start + pageItems.length} of ${items.length} ${noun}`
            : `No ${noun} found`;
        }
        renderButtons(totalPages);
        return pageItems;
      },

      // Jump to whichever page holds the given item
      goToItem(items, item) {
        const index = items.indexOf(item);
        if (index === -1) return;
        pager.page = Math.floor(index / pager.rows) + 1;
        onChange();
      },

      setRows(value) {
        if (
          rowsSelect &&
          [...rowsSelect.options].some((o) => o.value === String(value))
        ) {
          rowsSelect.value = String(value);
          pager.rows = parseInt(value, 10);
        }
      },
    };

    // Remember the rows-per-page choice when a storageKey is given
    const rowsKey = storageKey ? `${storageKey}:rows` : "";
    if (rowsKey) pager.setRows(localStorage.getItem(rowsKey));

    function goTo(page) {
      pager.page = page;
      onChange();
    }

    function makeButton(
      label,
      page,
      { active = false, disabled = false, ariaLabel = "" } = {},
    ) {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "pagination-btn" + (active ? " active" : "");
      btn.innerHTML = label;
      btn.disabled = disabled;
      if (ariaLabel) btn.setAttribute("aria-label", ariaLabel);
      btn.addEventListener("click", () => goTo(page));
      return btn;
    }

    function renderButtons(totalPages) {
      if (!buttons) return;
      buttons.innerHTML = "";
      buttons.appendChild(
        makeButton("&#8249;", pager.page - 1, {
          disabled: pager.page === 1,
          ariaLabel: "Previous page",
        }),
      );

      let previous = null;
      buildPageSet(pager.page, totalPages).forEach((page) => {
        if (previous !== null && page - previous > 1) {
          const gap = document.createElement("span");
          gap.className = "pagination-ellipsis";
          gap.textContent = "...";
          buttons.appendChild(gap);
        }
        buttons.appendChild(
          makeButton(page, page, { active: page === pager.page }),
        );
        previous = page;
      });

      buttons.appendChild(
        makeButton("&#8250;", pager.page + 1, {
          disabled: pager.page === totalPages,
          ariaLabel: "Next page",
        }),
      );
    }

    rowsSelect?.addEventListener("change", () => {
      pager.rows = parseInt(rowsSelect.value, 10);
      if (rowsKey) localStorage.setItem(rowsKey, pager.rows);
      pager.page = 1;
      onChange();
    });

    return pager;
  };
})();
