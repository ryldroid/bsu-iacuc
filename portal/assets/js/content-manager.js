// ===== CONTENT MANAGER (shared) =====
// Behavior for admin pages that manage lists of content (announcements, offices, FAQ and so on):
//   - one add/edit modal per list, opened from [data-cm-add] buttons and by clicking a row
//   - delete and reorder buttons inside rows
//   - live search with a result count
//   - lists refresh in place after a change, so the page never reloads
//   - forms marked data-cm-dirty track unsaved changes
//   - [data-cm-paragraphs] lists inside those forms let people add and delete paragraphs
//
// A page describes its lists in <script type="application/json" id="cmConfig"> and uses data-cm-*
// attributes in its markup. See app/views/personnel/personnel-content.view.php for a working example.
//
// Relies on helpers that are already global: confirmAction, setButtonBusy, showToast,
// uploadWithProgress and createUploadProgressBar (modals.js, flash-dismiss.js).

(function () {
  const configEl = document.getElementById("cmConfig");
  const config = configEl ? JSON.parse(configEl.textContent) : {};
  const ROOT = config.root || "";
  const CSRF = config.csrf || "";
  const entities = config.entities || {};

  // Which row is being edited per list (null means the modal is adding)
  const editing = {};

  const $ = (id) => document.getElementById(id);
  const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  const NETWORK_ERROR = "Network error. Please try again.";

  // ===== Requests =====
  function post(url, body, onProgress) {
    const fd = new FormData();
    fd.append("csrf_token", CSRF);
    Object.entries(body).forEach(([key, value]) => {
      if (value !== undefined && value !== null) fd.append(key, value);
    });

    if (onProgress) return uploadWithProgress(ROOT + url, fd, { onProgress });
    return fetch(ROOT + url, { method: "POST", body: fd }).then((r) =>
      r.json(),
    );
  }

  // ===== Modal helpers =====
  function openModal(id) {
    const modal = $(id);
    modal.classList.add("open");
    const first = modal.querySelector(
      "input:not([type=hidden]):not([type=file]):not([type=checkbox]), textarea",
    );
    if (first) first.focus();
  }

  function closeModal(id) {
    $(id).classList.remove("open");
  }

  function showError(modal, message) {
    const el = modal.querySelector(".error-messages");
    if (!el) return;
    el.textContent = message;
    el.hidden = false;
  }

  function hideError(modal) {
    const el = modal.querySelector(".error-messages");
    if (!el) return;
    el.hidden = true;
    el.textContent = "";
  }

  // ===== Image field (optional per list) =====
  function imageEls(entity) {
    const cfg = entity.image;
    if (!cfg) return null;
    return {
      input: $(cfg.input),
      wrap: $(cfg.previewWrap),
      img: $(cfg.preview),
      removeBtn: $(cfg.removeBtn),
      progress: $(cfg.progress),
      pickerLabel: $(cfg.input).parentElement.querySelector(
        "[data-picker-label]",
      ),
    };
  }

  function syncPickerLabel(els) {
    if (els.pickerLabel)
      els.pickerLabel.textContent = els.wrap.hidden
        ? "Choose image"
        : "Replace image";
  }

  function showPreview(els, src) {
    els.img.src = src;
    els.img.dataset.zoomSrc = src;
    els.img.dataset.zoomAlt = els.img.alt;
    els.wrap.hidden = false;
    syncPickerLabel(els);
  }

  function hidePreview(els) {
    els.input.value = "";
    els.img.removeAttribute("src");
    els.wrap.hidden = true;
    syncPickerLabel(els);
  }

  function clearImage(els) {
    hidePreview(els);
    els.wrap.dataset.saved = "";
    els.wrap.dataset.removed = "";
    els.progress.innerHTML = "";
  }

  function wireImage(entity) {
    const els = imageEls(entity);
    if (!els) return;

    els.input.addEventListener("change", () => {
      const file = els.input.files && els.input.files[0];
      if (!file) {
        els.wrap.hidden = true;
        syncPickerLabel(els);
        return;
      }
      const reader = new FileReader();
      reader.onload = (e) => {
        showPreview(els, e.target.result);
        els.wrap.dataset.removed = "";
      };
      reader.readAsDataURL(file);
    });

    els.removeBtn.addEventListener("click", () => {
      hidePreview(els);
      els.wrap.dataset.removed = els.wrap.dataset.saved;
      els.wrap.closest(".modal-backdrop").dataset.changed = "true";
    });
  }

  // ===== Add and edit =====
  function setModalText(entity, mode) {
    $(entity.title).textContent =
      mode === "edit" ? entity.text.editTitle : entity.text.addTitle;
    const label = $(entity.modal).querySelector("[data-cm-save-label]");
    if (label)
      label.textContent =
        mode === "edit" ? entity.text.editButton : entity.text.addButton;
  }

  function openAdd(key) {
    const entity = entities[key];
    if (!entity) return;

    editing[key] = null;
    hideError($(entity.modal));
    Object.values(entity.fields).forEach((elId) => {
      $(elId).value = "";
    });
    if (entity.image) clearImage(imageEls(entity));
    setModalText(entity, "add");
    openModal(entity.modal);
  }

  async function openEdit(key, id, row) {
    const entity = entities[key];
    if (!entity) return;

    if (row) row.classList.add("is-busy");
    let res;
    try {
      res = await fetch(
        ROOT + entity.endpoints.get + "?id=" + encodeURIComponent(id),
      ).then((r) => r.json());
    } catch (err) {
      if (!row || !row.dataset.cmData) {
        showToast(NETWORK_ERROR, "error");
        return;
      }
      res = { ok: true, data: JSON.parse(row.dataset.cmData) };
    } finally {
      if (row) row.classList.remove("is-busy");
    }

    if (!res.ok) {
      showToast(res.message || "Could not load this item.", "error");
      return;
    }

    editing[key] = id;
    hideError($(entity.modal));
    Object.entries(entity.fields).forEach(([name, elId]) => {
      $(elId).value = res.data[name] ?? "";
    });

    if (entity.image) {
      const els = imageEls(entity);
      clearImage(els);
      const file = res.data[entity.image.column];
      if (file) {
        showPreview(els, ROOT + entity.image.base + encodeURIComponent(file));
        els.wrap.dataset.saved = "1";
      }
    }

    setModalText(entity, "edit");
    openModal(entity.modal);
  }

  async function save(key) {
    const entity = entities[key];
    const modal = $(entity.modal);
    const saveBtn = modal.querySelector("[data-cm-submit]");
    const id = editing[key];

    hideError(modal);

    const payload = {};
    Object.entries(entity.fields).forEach(([name, elId]) => {
      payload[name] = $(elId).value.trim();
    });

    const els = entity.image ? imageEls(entity) : null;
    const file = els && els.input.files[0];
    const hasImage = !!file || (els && !els.wrap.hidden);

    const rules = entity.validate || {};
    if ((rules.all || []).some((name) => !payload[name])) {
      showError(
        modal,
        rules.allMessage || "Please fill in the required fields.",
      );
      return;
    }
    if (
      rules.any &&
      !rules.any.some((name) => payload[name]) &&
      !(rules.anyImage && hasImage)
    ) {
      showError(
        modal,
        rules.anyMessage || "Please fill in at least one field.",
      );
      return;
    }

    const confirmed = await confirmAction(
      id ? entity.text.confirmEdit : entity.text.confirmAdd,
      {
        okText: id ? entity.text.editOk : entity.text.addOk,
        cancelText: "Cancel",
      },
    );
    if (!confirmed) return;

    if (id) payload.id = id;
    if (els) {
      if (file) payload[entity.image.field] = file;
      if (id) payload.remove_image = els.wrap.dataset.removed ? "1" : "0";
    }

    setButtonBusy(saveBtn, true, file ? "Uploading..." : "Saving...");

    let bar = null;
    if (file) {
      els.progress.innerHTML = "";
      bar = createUploadProgressBar(els.progress);
    }

    let data;
    try {
      data = await post(
        id ? entity.endpoints.edit : entity.endpoints.add,
        payload,
        bar ? (pct) => bar.update(pct) : undefined,
      );
    } catch (err) {
      data = { ok: false, message: err.message || NETWORK_ERROR };
    }

    setButtonBusy(saveBtn, false);

    if (data.queued) {
      closeModal(entity.modal);
      notifyQueued();
      return;
    }

    if (!data.ok) {
      if (bar) bar.remove();
      showError(modal, data.message || "Save failed.");
      return;
    }

    closeModal(entity.modal);
    showToast(data.message || "Saved.");
    await refresh(entity.refresh, id ? { key, id } : { added: true });
  }

  // ===== Row actions =====
  async function deleteRow(entity, row) {
    const label = row.dataset.cmLabel || "#" + row.dataset.id;
    const confirmed = await confirmAction(
      entity.text.confirmDelete.replace("{name}", label),
      {
        okText: "Delete",
        cancelText: "Cancel",
        danger: true,
      },
    );
    if (!confirmed) return;

    row.classList.add("is-busy");

    let data;
    try {
      data = await post(entity.endpoints.delete, { id: row.dataset.id });
    } catch (err) {
      data = { ok: false, message: NETWORK_ERROR };
    }

    if (data.queued) {
      row.classList.remove("is-busy");
      notifyQueued();
      return;
    }

    if (!data.ok) {
      row.classList.remove("is-busy");
      showToast(data.message || "Delete failed.", "error");
      return;
    }

    row.classList.add("is-removing");
    showToast(data.message || "Deleted.");
    await wait(200);
    await refresh(entity.refresh);
  }

  function updateMoveButtons(list) {
    const rows = [...list.querySelectorAll(":scope > .cm-row")];
    rows.forEach((row, index) => {
      const up = row.querySelector('[data-direction="up"]');
      const down = row.querySelector('[data-direction="down"]');
      if (up) up.disabled = index === 0;
      if (down) down.disabled = index === rows.length - 1;
    });
  }

  async function moveRow(entity, row, direction) {
    const neighbour =
      direction === "up" ? row.previousElementSibling : row.nextElementSibling;
    if (!neighbour) return;

    row.classList.add("is-busy");

    let data;
    try {
      data = await post(entity.endpoints.move, {
        id: row.dataset.id,
        direction,
      });
    } catch (err) {
      data = { ok: false, message: NETWORK_ERROR };
    }

    row.classList.remove("is-busy");

    if (!data.ok && !data.queued) {
      showToast(data.message || "Move failed.", "error");
      return;
    }

    if (direction === "up") neighbour.before(row);
    else neighbour.after(row);
    updateMoveButtons(row.parentElement);
    flashRow(row);
    if (data.queued) notifyQueued();
    else showToast(data.message || "Order updated.");
  }

  function flashRow(row) {
    row.classList.remove("cm-row-flash");
    void row.offsetWidth; // restart the animation if the row was flashed a moment ago
    row.classList.add("cm-row-flash");
    row.addEventListener(
      "animationend",
      () => row.classList.remove("cm-row-flash"),
      { once: true },
    );
  }

  // ===== In-place refresh =====
  // Fetches the current page again and swaps in the matching list wrappers, so nothing reloads.
  function rowIds(wraps) {
    return new Set(
      wraps.flatMap((wrap) =>
        [...wrap.querySelectorAll(".cm-row")].map((row) => row.dataset.id),
      ),
    );
  }

  async function refresh(ids, flash) {
    const wraps = (ids || []).map($).filter(Boolean);
    if (!wraps.length) return;

    const before = rowIds(wraps);
    wraps.forEach((wrap) => wrap.classList.add("is-refreshing"));

    let doc;
    try {
      const res = await fetch(location.href, { credentials: "same-origin" });
      if (!res.ok) throw new Error("Bad response");
      doc = new DOMParser().parseFromString(await res.text(), "text/html");
    } catch (err) {
      location.reload();
      return;
    }

    // A login page or error page will not have the wrappers, so fall back to a normal reload
    if (wraps.some((wrap) => !doc.getElementById(wrap.id))) {
      location.reload();
      return;
    }

    wraps.forEach((wrap) => {
      wrap.innerHTML = doc.getElementById(wrap.id).innerHTML;
      wrap.classList.remove("is-refreshing");
      applyFilter(wrap);
    });
    window._actionQueue.syncTags();

    if (!flash) return;

    const rows = wraps.flatMap((wrap) => [...wrap.querySelectorAll(".cm-row")]);
    const row = flash.added
      ? rows.find((r) => !before.has(r.dataset.id))
      : rows.find(
          (r) =>
            r.dataset.cmEntity === flash.key &&
            r.dataset.id === String(flash.id),
        );
    if (!row) return;

    flashRow(row);
    row.scrollIntoView({ block: "nearest", behavior: "smooth" });
  }

  // ===== Live search and counts =====
  function applyFilter(wrap) {
    const input = document.querySelector('[data-cm-search="' + wrap.id + '"]');
    const query = input
      ? input.value.trim().toLowerCase().replace(/\s+/g, " ")
      : "";
    const rows = [...wrap.querySelectorAll(".cm-row")];
    wrap.classList.toggle("is-filtered", !!query);

    let shown = 0;
    rows.forEach((row) => {
      const text = row.textContent.toLowerCase().replace(/\s+/g, " ");
      const match = !query || text.includes(query);
      row.hidden = !match;
      if (match) shown++;
    });

    if (input) {
      const box = input.closest(".inbox-search-wrap");
      if (box) box.hidden = rows.length === 0;
      const clear = input.parentElement.querySelector("[data-cm-search-clear]");
      if (clear) clear.classList.toggle("visible", !!query);
    }

    updateNoResults(wrap, input ? input.value.trim() : "", rows.length, shown);

    document
      .querySelectorAll('[data-cm-count="' + wrap.id + '"]')
      .forEach((el) => {
        el.textContent = query
          ? shown + " of " + rows.length
          : String(rows.length);
      });
  }

  function updateNoResults(wrap, query, total, shown) {
    let box = wrap.querySelector(".cm-no-results");

    if (!query || !total || shown) {
      if (box) box.hidden = true;
      return;
    }

    if (!box) {
      box = document.createElement("div");
      box.className = "cm-empty cm-no-results";
      box.innerHTML =
        '<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#search-icon" /></svg>' +
        "<h3>No matches</h3><p></p>";
      wrap.appendChild(box);
    }

    box.querySelector("p").textContent = 'Nothing matches "' + query + '".';
    box.hidden = false;
  }

  // ===== Paragraph lists (inside forms marked data-cm-dirty) =====
  const paragraphSnapshots = new WeakMap();

  function paragraphKeys(list) {
    return [...list.querySelectorAll("[data-cm-paragraph]")]
      .map((paragraph) => paragraph.dataset.key)
      .join();
  }

  function numberParagraphs(list) {
    list.querySelectorAll("[data-cm-paragraph]").forEach((paragraph, index) => {
      const number = index + 1;
      const textarea = paragraph.querySelector("textarea");
      textarea.id = list.dataset.cmParagraphs + "_" + number;
      paragraph.querySelector("label").htmlFor = textarea.id;
      paragraph.querySelector("[data-cm-paragraph-number]").textContent =
        number;
      paragraph
        .querySelector("[data-cm-paragraph-remove]")
        .setAttribute("aria-label", "Delete paragraph " + number);
    });
  }

  function addParagraph(form) {
    const list = form.querySelector("[data-cm-paragraphs]");
    const template = form.querySelector("[data-cm-paragraph-template]");
    list.appendChild(template.content.cloneNode(true));
    numberParagraphs(list);
    list.lastElementChild.querySelector("textarea").focus();
    updateDirty(form);
  }

  async function removeParagraph(paragraph) {
    const confirmed = await confirmAction(
      "Delete paragraph " +
        paragraph.querySelector("[data-cm-paragraph-number]").textContent +
        "? This is applied when you save your changes.",
      { okText: "Delete", cancelText: "Cancel", danger: true },
    );
    if (!confirmed) return;

    const form = paragraph.closest("form");
    const list = paragraph.parentElement;
    paragraph.remove();
    numberParagraphs(list);
    form.querySelector("[data-cm-paragraph-add]").focus();
    updateDirty(form);
  }

  // ===== Unsaved changes (forms marked data-cm-dirty) =====
  function isDirty(form) {
    const list = form.querySelector("[data-cm-paragraphs]");
    if (list && paragraphKeys(list) !== list.dataset.keys) return true;

    return [...form.elements].some((el) => {
      if (!el.name || el.disabled) return false;
      if (el.type === "checkbox" || el.type === "radio")
        return el.checked !== el.defaultChecked;
      if (el.tagName === "SELECT")
        return [...el.options].some((o) => o.selected !== o.defaultSelected);
      return el.value !== el.defaultValue;
    });
  }

  function updateDirty(form) {
    const dirty = isDirty(form);
    form.classList.toggle("is-dirty", dirty);
    const submit = form.querySelector("[data-cm-dirty-submit]");
    const reset = form.querySelector("[data-cm-dirty-reset]");
    if (submit) submit.disabled = !dirty;
    if (reset) reset.hidden = !dirty;
  }

  // ===== Events =====
  document.addEventListener("click", (e) => {
    const paragraphAdd = e.target.closest("[data-cm-paragraph-add]");
    if (paragraphAdd) {
      addParagraph(paragraphAdd.closest("form"));
      return;
    }

    const paragraphRemove = e.target.closest("[data-cm-paragraph-remove]");
    if (paragraphRemove) {
      removeParagraph(paragraphRemove.closest("[data-cm-paragraph]"));
      return;
    }

    const addBtn = e.target.closest("[data-cm-add]");
    if (addBtn) {
      openAdd(addBtn.dataset.cmAdd);
      return;
    }

    const submitBtn = e.target.closest("[data-cm-submit]");
    if (submitBtn) {
      save(submitBtn.dataset.cmSubmit);
      return;
    }

    const closeBtn = e.target.closest("[data-close]");
    if (closeBtn && closeBtn.closest(".modal-backdrop[data-cm-modal]")) {
      closeModal(closeBtn.dataset.close);
      return;
    }

    const clearBtn = e.target.closest("[data-cm-search-clear]");
    if (clearBtn) {
      const input = clearBtn.parentElement.querySelector("[data-cm-search]");
      input.value = "";
      input.focus();
      applyFilter($(input.dataset.cmSearch));
      return;
    }

    const actionBtn = e.target.closest("[data-cm-action]");
    const row = e.target.closest(".cm-row[data-cm-entity]");
    if (!row) return;

    const entity = entities[row.dataset.cmEntity];
    if (!entity) return;

    if (actionBtn) {
      const action = actionBtn.dataset.cmAction;
      if (action === "edit")
        openEdit(row.dataset.cmEntity, row.dataset.id, row);
      else if (action === "delete") deleteRow(entity, row);
      else if (action === "move")
        moveRow(entity, row, actionBtn.dataset.direction);
      return;
    }

    // Clicking anywhere else on the row opens it for editing, unless the person is selecting text
    if (e.target.closest("a, button, label, input, [data-zoom-src]")) return;
    if (String(window.getSelection()).trim()) return;
    openEdit(row.dataset.cmEntity, row.dataset.id, row);
  });

  document.addEventListener("input", (e) => {
    const search = e.target.closest("[data-cm-search]");
    if (search) applyFilter($(search.dataset.cmSearch));

    const form = e.target.closest("form[data-cm-dirty]");
    if (form) updateDirty(form);
  });

  document.addEventListener("change", (e) => {
    const form = e.target.closest("form[data-cm-dirty]");
    if (form) updateDirty(form);
  });

  document.addEventListener("reset", (e) => {
    const form = e.target.closest("form[data-cm-dirty]");
    if (!form) return;
    form.querySelectorAll("[data-cm-paragraphs]").forEach((list) => {
      list.innerHTML = paragraphSnapshots.get(list);
    });
    setTimeout(() => updateDirty(form), 0);
  });

  document.addEventListener("keydown", (e) => {
    // Escape clears the search box
    const search = e.target.closest && e.target.closest("[data-cm-search]");
    if (search && e.key === "Escape" && search.value) {
      search.value = "";
      applyFilter($(search.dataset.cmSearch));
      return;
    }

    // Enter in a single-line field, or Ctrl/Cmd+Enter anywhere, saves the open modal
    if (e.key !== "Enter") return;
    const modal =
      e.target.closest && e.target.closest(".modal-backdrop[data-cm-modal]");
    const submit = modal && modal.querySelector("[data-cm-submit]");
    if (!submit || submit.disabled) return;

    const singleLine =
      e.target.tagName === "INPUT" &&
      e.target.type !== "file" &&
      e.target.type !== "checkbox";
    if (singleLine || e.ctrlKey || e.metaKey) {
      e.preventDefault();
      submit.click();
    }
  });

  // Warn before leaving a page that has unsaved changes (a confirmed save is not a reason to warn)
  window.addEventListener("beforeunload", (e) => {
    const dirty = document.querySelector(
      'form[data-cm-dirty].is-dirty:not([data-confirmed="true"])',
    );
    if (!dirty) return;
    e.preventDefault();
    e.returnValue = "";
  });

  // ===== Start up =====
  function init() {
    Object.values(entities).forEach(wireImage);
    document.querySelectorAll(".cm-list-wrap").forEach(applyFilter);
    document.querySelectorAll("[data-cm-paragraphs]").forEach((list) => {
      list.dataset.keys = paragraphKeys(list);
      paragraphSnapshots.set(list, list.innerHTML);
    });
    document.querySelectorAll("form[data-cm-dirty]").forEach(updateDirty);
  }

  window.addEventListener("pageshow", () => {
    document.querySelectorAll("form[data-cm-dirty]").forEach(updateDirty);
  });

  init();

  window.ContentManager = { refresh, applyFilter };
})();
