(function () {
  // ===== State & settings =====
  var USER_ID = typeof CURRENT_USER_ID !== "undefined" ? CURRENT_USER_ID : 0;
  var STORAGE_KEY = "iacuc_action_queue_" + USER_ID;
  var IS_PERSONNEL =
    typeof SESSION_LOGIN_PATH !== "undefined" &&
    SESSION_LOGIN_PATH === "personnel/login";
  var REPLAY_DELAY = 1200;
  var replayTimer = null;
  var bannerEl = null;
  var bannerAction = openQueuePanel;

  // ===== Queue persistence =====

  function loadQueue() {
    try {
      return JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
    } catch (e) {
      return [];
    }
  }

  function saveQueue(queue) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(queue));
    } catch (e) {}
    syncPendingTags();
  }

  function enqueue(entry) {
    var queue = loadQueue();
    queue.push(entry);
    saveQueue(queue);
  }

  function dequeue(id) {
    var queue = loadQueue().filter(function (e) {
      return e.id !== id;
    });
    saveQueue(queue);
    return queue;
  }

  function markFailed(id, message, retryable) {
    var queue = loadQueue();
    queue.forEach(function (e) {
      if (e.id === id) {
        e.error = message;
        e.retryable = !!retryable;
      }
    });
    saveQueue(queue);
  }

  function clearQueue() {
    localStorage.removeItem(STORAGE_KEY);
    syncPendingTags();
  }

  // ===== Pending sync tags =====

  function syncPendingTags() {
    var byProtocol = {};
    loadQueue().forEach(function (entry) {
      var target = entry.target || entry.protocolId;
      if (!target) return;
      (byProtocol[target] = byProtocol[target] || []).push(entry);
    });

    document.querySelectorAll("[data-pending-sync]").forEach(function (tag) {
      var entries = byProtocol[tag.dataset.pendingSync] || [];
      var failed = entries.some(function (e) {
        return e.error;
      });
      tag.hidden = entries.length === 0;
      tag.classList.toggle("pending-sync-badge--failed", failed);
      tag.textContent =
        (failed ? "Sync failed" : "Pending sync") +
        (entries.length > 1 ? " (" + entries.length + ")" : "");
      tag.title = entries
        .map(function (e) {
          return e.label.replace(/ \(protocol #\d+\)$/, "");
        })
        .join(", ");
    });
  }

  // ===== Unique id =====

  function uid() {
    return (
      Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 7)
    );
  }

  // ===== Human-readable label for an action =====

  var ACTION_LABELS = {
    "/apply/return_revision": "Return for revision",
    "/apply/verify_payment": "Mark payment as verified",
    "/apply/reject_payment": "Reject payment proof",
    "/apply/undo_payment": "Undo payment mark",
    "/apply/hard_copy": "Update hard copy pickup",
    "/apply/approve_deletion": "Approve deletion request",
    "/apply/reject_deletion": "Reject deletion request",
    "/apply/assign_ipn": "Assign IPN",
    "/apply/rename": "Rename protocol",
    "/apply/delete": "Delete protocol",
    "/personnel/certverify": "Verify training certificate",
    "/personnel/certreject": "Reject training certificate",
    "/personnel/records_add": "Add record",
    "/personnel/records_edit": "Edit record",
    "/personnel/records_delete": "Delete record",
    "/personnel/announcements_add": "Add announcement",
    "/personnel/announcements_edit": "Edit announcement",
    "/personnel/announcements_delete": "Delete announcement",
    "/personnel/site_content_office_add": "Add contact office",
    "/personnel/site_content_office_edit": "Edit contact office",
    "/personnel/site_content_office_move": "Move contact office",
    "/personnel/site_content_office_delete": "Delete contact office",
    "/personnel/site_content_faq_add": "Add FAQ",
    "/personnel/site_content_faq_edit": "Edit FAQ",
    "/personnel/site_content_faq_move": "Move FAQ",
    "/personnel/site_content_faq_delete": "Delete FAQ",
  };

  function labelFor(url, parsed) {
    if (url.indexOf("/apply/status") !== -1) {
      var s = parsed.status || "";
      var revert = parsed.intent === "revert";
      if (s === "Needs Revision") return "Return for revision";
      if (s === "Reviewed")
        return revert ? "Revert endorsement" : "Finish review";
      if (s === "Endorsed")
        return revert ? "Revert approval" : "Mark as endorsed";
      if (s === "Approved") return "Mark as approved";
      if (s === "Under Review") return "Send back to review";
      return "Status change";
    }

    for (var path in ACTION_LABELS) {
      if (url.indexOf(path) !== -1) return ACTION_LABELS[path];
    }

    if (url.indexOf("/apply/annotate") !== -1) {
      var action = parsed.action || "";
      if (action === "save") return "Add comment";
      if (action === "edit") return "Edit comment";
      if (action === "delete") return "Delete comment";
      return "Annotation";
    }

    return "Action";
  }

  // ===== Banner =====

  function ensureBanner() {
    if (bannerEl) return;

    bannerEl = document.createElement("div");
    bannerEl.id = "iq-banner";
    bannerEl.className = "iq-banner";
    bannerEl.setAttribute("aria-live", "polite");
    bannerEl.setAttribute("aria-atomic", "true");

    var inner = document.createElement("div");
    inner.className = "iq-banner-inner";

    var msg = document.createElement("span");
    msg.id = "iq-banner-msg";
    msg.className = "iq-banner-msg";

    var btn = document.createElement("button");
    btn.id = "iq-banner-btn";
    btn.className = "iq-banner-btn";
    btn.textContent = "View";
    btn.type = "button";
    btn.addEventListener("click", function () {
      bannerAction();
    });

    inner.appendChild(msg);
    inner.appendChild(btn);
    bannerEl.appendChild(inner);

    document.body.appendChild(bannerEl);
  }

  function setBannerAction(label, action) {
    ensureBanner();
    bannerAction = action;
    document.getElementById("iq-banner-btn").textContent = label;
  }

  function updateBanner() {
    var queue = loadQueue();
    setBannerAction("View", openQueuePanel);

    if (queue.length === 0) {
      bannerEl.classList.remove("iq-banner--visible");
      return;
    }

    var msg = document.getElementById("iq-banner-msg");
    var failed = queue.filter(function (e) {
      return e.error;
    }).length;
    var pending = queue.length - failed;

    bannerEl.classList.toggle("iq-banner--warn", pending === 0);

    if (pending === 0) {
      msg.textContent =
        failed === 1
          ? "1 queued action could not be sent. Open the queue to choose what to keep."
          : failed +
            " queued actions could not be sent. Open the queue to choose what to keep.";
    } else if (!navigator.onLine) {
      msg.textContent =
        queue.length === 1
          ? "1 action queued while offline. Do not clear your browser data until it is sent."
          : queue.length +
            " actions queued while offline. Do not clear your browser data until they are sent.";
    } else {
      msg.textContent =
        queue.length === 1
          ? "1 action replaying..."
          : queue.length + " actions replaying...";
    }

    bannerEl.classList.add("iq-banner--visible");
  }

  function hideBanner() {
    if (bannerEl) bannerEl.classList.remove("iq-banner--visible");
  }

  // ===== Queue panel (modal) =====

  var panelEl = null;

  function buildPanel() {
    if (panelEl) return;

    panelEl = document.createElement("div");
    panelEl.id = "iq-panel-backdrop";
    panelEl.className = "modal-backdrop";

    panelEl.innerHTML = [
      '<div class="modal-card iq-panel-card">',
      '  <div class="modal-header">',
      '    <span class="modal-header-icon" aria-hidden="true">',
      '      <svg width="20" height="20" viewBox="0 0 24 24" focusable="false"><use href="#refresh-icon"/></svg>',
      "    </span>",
      "    <div>",
      '      <p class="modal-label">Offline Queue</p>',
      '      <p class="modal-title" id="iq-panel-title">Queued actions</p>',
      "    </div>",
      '    <button class="modal-close" id="iq-panel-close" aria-label="Close">',
      '      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">',
      '        <use href="#close-icon"/>',
      "      </svg>",
      "    </button>",
      "  </div>",
      '  <div class="history-modal-body" id="iq-panel-body"></div>',
      '  <div class="iq-panel-footer">',
      '    <p class="helper iq-panel-hint" id="iq-panel-hint"></p>',
      '    <button class="confirm-modal-btn confirm-modal-ok confirm-modal-ok-danger" id="iq-panel-discard" type="button">',
      '      <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#trash-icon"/></svg>',
      "      Discard all",
      "    </button>",
      "  </div>",
      "</div>",
    ].join("");

    document.body.appendChild(panelEl);

    document
      .getElementById("iq-panel-close")
      .addEventListener("click", closeQueuePanel);
    panelEl.addEventListener("click", function (e) {
      if (e.target === panelEl) closeQueuePanel();
    });
    document
      .getElementById("iq-panel-discard")
      .addEventListener("click", function () {
        confirmAction(
          "Discard all queued actions? They will not be sent to the server.",
          { okText: "Discard", cancelText: "Keep", danger: true },
        ).then(function (ok) {
          if (!ok) return;
          clearQueue();
          closeQueuePanel();
          hideBanner();
        });
      });
  }

  function openQueuePanel() {
    buildPanel();
    renderPanel();
    panelEl.classList.add("open");
  }

  function closeQueuePanel() {
    if (panelEl) panelEl.classList.remove("open");
  }

  var HIDDEN_FIELDS = ["csrf_token", "csrf", "id"];

  function entryIcon(title) {
    if (/^(Delete|Reject|Remove)/.test(title)) return "trash-icon";
    if (/^Add/.test(title)) return "add-icon";
    if (/^(Edit|Rename|Update|Assign)/.test(title)) return "edit-icon";
    return "refresh-icon";
  }

  function fieldTitle(name) {
    var text = name.replace(/_/g, " ");
    return text.charAt(0).toUpperCase() + text.slice(1);
  }

  function entryDetails(entry) {
    var data = parsePayload({ fields: entry.fields, text: entry.body });
    if (!data || typeof data !== "object") return "";
    return Object.keys(data)
      .filter(function (key) {
        var unused = /^remove_/.test(key) && String(data[key]) === "0";
        return HIDDEN_FIELDS.indexOf(key) === -1 && !unused;
      })
      .map(function (key) {
        var value =
          typeof data[key] === "object"
            ? JSON.stringify(data[key])
            : /^remove_/.test(key)
              ? "Yes"
              : String(data[key]);
        return (
          "<dt>" +
          escapeHtml(fieldTitle(key)) +
          "</dt><dd>" +
          (value ? escapeHtml(value) : '<span class="helper">Empty</span>') +
          "</dd>"
        );
      })
      .join("");
  }

  function renderPanel() {
    var queue = loadQueue();
    var body = document.getElementById("iq-panel-body");
    var hint = document.getElementById("iq-panel-hint");
    var title = document.getElementById("iq-panel-title");

    if (!body) return;

    title.textContent =
      queue.length === 0
        ? "No queued actions"
        : queue.length === 1
          ? "1 queued action"
          : queue.length + " queued actions";

    if (queue.length === 0) {
      body.innerHTML = '<p class="helper history-loading">Queue is empty.</p>';
      hint.textContent = "";
      return;
    }

    var hasConflict = queue.some(function (e) {
      return e.error;
    });
    hint.textContent = hasConflict
      ? "Keep mine tries your action again. Keep theirs drops your action and leaves the server as it is."
      : navigator.onLine
        ? "Will replay automatically."
        : "These will be sent once your connection is restored.";

    body.innerHTML = queue
      .map(function (entry) {
        var when = formatDateTime(entry.queuedAt);
        var parts = entry.label.split(": ");
        var title = parts.shift();
        var detail = parts.join(": ");
        var fields = entryDetails(entry);
        var reason = entry.error
          ? '    <span class="helper iq-entry-error">Server: ' +
            escapeHtml(entry.error) +
            "</span>"
          : "";
        return [
          '<div class="iq-entry">',
          '  <span class="iq-entry-icon' +
            (entry.error ? " iq-entry-icon--error" : "") +
            '" aria-hidden="true">',
          '    <svg width="16" height="16" viewBox="0 0 24 24" focusable="false"><use href="#' +
            entryIcon(title) +
            '"/></svg>',
          "  </span>",
          '  <div class="iq-entry-info">',
          '    <span class="iq-entry-label">' + escapeHtml(title) + "</span>",
          detail
            ? '    <span class="iq-entry-detail">' +
              escapeHtml(detail) +
              "</span>"
            : "",
          '    <span class="helper iq-entry-time">' + when + "</span>",
          reason,
          "  </div>",
          '  <div class="iq-entry-actions">',
          '  <button class="button iq-entry-toggle" type="button" aria-expanded="false">View</button>',
          entry.error
            ? '  <button class="button iq-entry-retry" type="button" data-iq-id="' +
              entry.id +
              '" title="Try your action again">Keep mine</button>'
            : "",
          '  <button class="button iq-entry-discard" type="button" data-iq-id="' +
            entry.id +
            '"',
          entry.error
            ? '    title="Drop your action and leave the server as it is">Keep theirs</button>'
            : '    title="Discard this action">Remove</button>',
          "  </div>",
          '  <div class="iq-entry-view" hidden>',
          fields
            ? "    <dl>" + fields + "</dl>"
            : '    <p class="helper">No details were saved for this action.</p>',
          "  </div>",
          "</div>",
        ].join("");
      })
      .join("");

    panelEl
      .querySelector(".modal-header-icon")
      .classList.toggle("modal-header-icon--warn", hasConflict);

    body.querySelectorAll(".iq-entry-toggle").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var view = btn.closest(".iq-entry").querySelector(".iq-entry-view");
        var opening = view.hidden;
        view.hidden = !opening;
        btn.textContent = opening ? "Hide" : "View";
        btn.setAttribute("aria-expanded", opening);
      });
    });

    body.querySelectorAll(".iq-entry-retry").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var id = btn.dataset.iqId;
        var queue = loadQueue();
        queue.forEach(function (e) {
          if (e.id === id) {
            delete e.error;
            delete e.retryable;
          }
        });
        saveQueue(queue);
        replayQueue();
      });
    });

    body.querySelectorAll(".iq-entry-discard").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var id = btn.dataset.iqId;
        dequeue(id);
        updateBanner();
        renderPanel();
        if (navigator.onLine) replayQueue();
      });
    });
  }

  // ===== Fetch interception =====

  var INTERCEPTED_PATHS = ["/apply/status", "/apply/annotate"].concat(
    Object.keys(ACTION_LABELS),
  );

  function shouldIntercept(url) {
    if (!url || !IS_PERSONNEL) return false;
    var path = url.split("?")[0];
    return INTERCEPTED_PATHS.some(function (p) {
      return path.slice(-p.length) === p;
    });
  }

  var _origFetch = window.fetch;

  // JSON strings and text-only forms can be queued. Anything with a file cannot.
  function queueableBody(body) {
    if (typeof body === "string") return { text: body };
    if (typeof FormData === "undefined" || !(body instanceof FormData))
      return null;

    var fields = [];
    var hasFile = false;
    body.forEach(function (value, name) {
      if (typeof value === "string") fields.push([name, value]);
      else hasFile = true;
    });
    return hasFile ? null : { fields: fields };
  }

  window.fetch = function (input, init) {
    var url = typeof input === "string" ? input : (input && input.url) || "";
    var method = init && init.method ? init.method.toUpperCase() : "GET";
    var payload =
      method === "POST" && shouldIntercept(url)
        ? queueableBody(init.body)
        : null;

    if (!payload) return _origFetch.apply(this, arguments);

    var key = uid();
    if (!navigator.onLine) return captureAndQueue(url, init, payload, key);

    var headers = new Headers(init.headers || {});
    headers.set("X-Idempotency-Key", key);
    return _origFetch
      .call(this, input, Object.assign({}, init, { headers: headers }))
      .catch(function (err) {
        if (err instanceof TypeError) {
          return captureAndQueue(url, init, payload, key);
        }
        return Promise.reject(err);
      });
  };

  var CONTENT_TARGETS = {
    records: "record",
    announcements: "announcement",
    site_content_office: "office",
    site_content_faq: "faq",
  };

  function parsePayload(payload) {
    if (!payload.fields) {
      try {
        return JSON.parse(payload.text || "{}");
      } catch (e) {
        return {};
      }
    }
    var out = {};
    payload.fields.forEach(function (field) {
      out[field[0]] = field[1];
    });
    return out;
  }

  function captureAndQueue(url, init, payload, key) {
    var method = init && init.method ? init.method.toUpperCase() : "POST";
    var headers = {};
    if (init && init.headers) {
      if (init.headers instanceof Headers) {
        init.headers.forEach(function (v, k) {
          headers[k] = v;
        });
      } else {
        headers = Object.assign({}, init.headers);
      }
    }

    var parsed = parsePayload(payload);
    var content = url.match(
      /\/personnel\/(records|announcements|site_content_office|site_content_faq)_/,
    );
    var protocolId = content
      ? null
      : parsed.protocol_id ||
        (typeof PROTOCOL_ID !== "undefined" ? PROTOCOL_ID : null);
    var target = content
      ? parsed.id
        ? CONTENT_TARGETS[content[1]] + ":" + parsed.id
        : null
      : protocolId
        ? String(protocolId)
        : null;
    var detail =
      parsed.title_of_research ||
      parsed.question ||
      parsed.name ||
      parsed.title ||
      "";

    enqueue({
      id: key,
      protocolId: protocolId,
      target: target,
      url: url,
      method: method,
      headers: headers,
      body: payload.text || null,
      fields: payload.fields || null,
      queuedAt: new Date().toISOString(),
      label:
        labelFor(url, parsed) +
        (detail ? ": " + detail.slice(0, 40) : "") +
        (protocolId ? " (protocol #" + protocolId + ")" : ""),
    });
    updateBanner();

    var message =
      "Offline: action queued and will retry when you are back online.";
    return Promise.resolve(
      new Response(
        JSON.stringify({
          ok: false,
          queued: true,
          error: message,
          message: message,
        }),
        { status: 200, headers: { "Content-Type": "application/json" } },
      ),
    );
  }

  // ===== Replay =====

  var SESSION_EXPIRED = false;

  function replayQueue() {
    if (navigator.locks) {
      navigator.locks.request("iacuc-replay", runReplay);
    } else {
      runReplay();
    }
  }

  function sendEntry(entry) {
    var headers = new Headers(entry.headers);
    var token =
      typeof NOTIF_CSRF_TOKEN !== "undefined" ? NOTIF_CSRF_TOKEN : null;
    var body = entry.body;

    headers.set("X-Idempotency-Key", entry.id);
    headers.set("Accept", "application/json");
    if (token) headers.set("X-CSRF-Token", token);

    if (entry.fields) {
      body = new FormData();
      entry.fields.forEach(function (field) {
        body.append(
          field[0],
          field[0] === "csrf_token" && token ? token : field[1],
        );
      });
      headers.delete("Content-Type");
    }

    return _origFetch(entry.url, {
      method: entry.method,
      headers: headers,
      body: body,
    })
      .then(function (res) {
        if (res.status === 401) {
          SESSION_EXPIRED = true;
          showSessionExpiredWarning();
          return;
        }
        return res
          .json()
          .catch(function () {
            return {};
          })
          .then(function (data) {
            if (res.ok && data.ok !== false && data.success !== false) {
              dequeue(entry.id);
              return true;
            }
            var transient =
              res.status >= 500 ||
              (res.status === 403 && /csrf/i.test(data.error || ""));
            markFailed(
              entry.id,
              data.error ||
                data.message ||
                (transient
                  ? "Server error (" + res.status + "). Tap Retry."
                  : "The server rejected this action (error " +
                    res.status +
                    ")."),
              transient,
            );
            return false;
          });
      })
      .catch(function () {
        if (navigator.onLine) {
          markFailed(entry.id, "Could not reach the server. Tap Retry.", true);
        }
        return false;
      });
  }

  function runReplay() {
    var queue = loadQueue();
    if (queue.length === 0) {
      hideBanner();
      return;
    }

    SESSION_EXPIRED = false;
    updateBanner();

    var blocked = {};
    var sent = 0;
    var chain = Promise.resolve();
    queue.forEach(function (entry) {
      chain = chain.then(function () {
        if (SESSION_EXPIRED || !navigator.onLine) return;
        var key = entry.target || entry.protocolId;

        if (key && blocked[key]) {
          if (!entry.error) {
            markFailed(
              entry.id,
              "Waiting for an earlier action on this protocol to succeed.",
              true,
            );
          }
          return;
        }

        if (entry.error && !entry.retryable) {
          if (key) blocked[key] = true;
          return;
        }

        return sendEntry(entry).then(function (ok) {
          if (ok === true) sent++;
          if (ok === false && key) blocked[key] = true;
        });
      });
    });

    return chain.then(function () {
      if (SESSION_EXPIRED) return;
      if (sent > 0 && loadQueue().length === 0) {
        showSyncedBanner();
      } else {
        updateBanner();
      }
      if (panelEl && panelEl.classList.contains("open")) {
        renderPanel();
      }
    });
  }

  function showSessionExpiredWarning() {
    ensureBanner();
    var root = typeof NOTIF_ROOT !== "undefined" ? NOTIF_ROOT : "";
    var loginPath =
      typeof SESSION_LOGIN_PATH !== "undefined"
        ? SESSION_LOGIN_PATH
        : "user/login";
    document.getElementById("iq-banner-msg").textContent =
      "Your session expired while offline. Log back in to send your queued actions.";
    setBannerAction("Log in", function () {
      window.location.href = root + "/" + loginPath;
    });
    bannerEl.classList.add("iq-banner--visible", "iq-banner--warn");
  }

  function showSyncedBanner() {
    document.getElementById("iq-banner-msg").textContent =
      "Queued actions were sent. Reload to see the latest status.";
    setBannerAction("Reload", function () {
      window.location.reload();
    });
    bannerEl.classList.remove("iq-banner--warn");
    bannerEl.classList.add("iq-banner--visible");
  }

  // ===== Online / offline listeners =====

  window.addEventListener("online", function () {
    clearTimeout(replayTimer);
    replayTimer = setTimeout(replayQueue, REPLAY_DELAY);
  });

  window.addEventListener("offline", function () {
    clearTimeout(replayTimer);
    updateBanner();
  });

  // ===== Initialise on load =====

  window.addEventListener("storage", function (e) {
    if (e.key !== STORAGE_KEY) return;
    syncPendingTags();
    updateBanner();
  });

  document.addEventListener("DOMContentLoaded", function () {
    var queue = loadQueue();
    if (queue.length === 0) return;

    syncPendingTags();

    updateBanner();

    if (navigator.onLine) {
      replayTimer = setTimeout(replayQueue, REPLAY_DELAY);
    }
  });

  window.notifyQueued = function () {
    showToast("Saved offline. It will sync when you are back online.");
  };

  window._actionQueue = {
    syncTags: syncPendingTags,
    load: loadQueue,
    clear: clearQueue,
    replay: replayQueue,
    open: openQueuePanel,
  };
})();
