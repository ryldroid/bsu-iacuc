(function () {
  var STORAGE_KEY = "iacuc_action_queue";
  var REPLAY_DELAY = 1200;
  var replayTimer = null;
  var bannerEl = null;

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

  function clearQueue() {
    localStorage.removeItem(STORAGE_KEY);
  }

  // ===== Unique id =====

  function uid() {
    return (
      Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 7)
    );
  }

  // ===== Human-readable label for an action =====

  function labelFor(url, body) {
    var parsed = {};
    try {
      parsed = JSON.parse(body || "{}");
    } catch (e) {}

    if (url.indexOf("/apply/status") !== -1) {
      var s = parsed.status || "";
      if (s === "Needs Revision") return "Return for revision";
      if (s === "Reviewed") return "Finish review";
      if (s === "Endorsed") return "Mark as endorsed";
      return "Status change";
    }

    if (url.indexOf("/apply/annotate") !== -1) {
      var action = parsed.action || "";
      if (action === "save") return "Add comment";
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
    btn.addEventListener("click", openQueuePanel);

    inner.appendChild(msg);
    inner.appendChild(btn);
    bannerEl.appendChild(inner);

    document.body.appendChild(bannerEl);
  }

  function updateBanner() {
    var queue = loadQueue();
    ensureBanner();

    if (queue.length === 0) {
      bannerEl.classList.remove("iq-banner--visible");
      return;
    }

    var msg = document.getElementById("iq-banner-msg");
    if (!navigator.onLine) {
      msg.textContent =
        queue.length === 1
          ? "1 action queued while offline."
          : queue.length + " actions queued while offline.";
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
      '  <div class="history-modal-header">',
      "    <div>",
      '      <p class="history-modal-label">Offline Queue</p>',
      '      <p class="history-modal-title" id="iq-panel-title">Queued actions</p>',
      "    </div>",
      '    <button class="button history-modal-close" id="iq-panel-close" aria-label="Close">',
      '      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">',
      '        <use href="#close-icon"/>',
      "      </svg>",
      "    </button>",
      "  </div>",
      '  <div class="history-modal-body" id="iq-panel-body"></div>',
      '  <div class="iq-panel-footer">',
      '    <p class="helper iq-panel-hint" id="iq-panel-hint"></p>',
      '    <button class="button iq-panel-discard-btn" id="iq-panel-discard" type="button">',
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

    hint.textContent = navigator.onLine
      ? "Will replay automatically. Reload the page after replay completes."
      : "These will be sent once your connection is restored.";

    body.innerHTML = queue
      .map(function (entry) {
        var when = new Date(entry.queuedAt).toLocaleString("en-PH", {
          month: "short",
          day: "numeric",
          hour: "2-digit",
          minute: "2-digit",
        });
        return [
          '<div class="iq-entry">',
          '  <div class="iq-entry-info">',
          '    <span class="iq-entry-label">' +
            escHtml(entry.label) +
            "</span>",
          '    <span class="helper iq-entry-time">' + when + "</span>",
          "  </div>",
          '  <button class="button iq-entry-discard" type="button" data-iq-id="' +
            entry.id +
            '"',
          '    title="Discard this action">Remove</button>',
          "</div>",
        ].join("");
      })
      .join("");

    body.querySelectorAll(".iq-entry-discard").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var id = btn.dataset.iqId;
        dequeue(id);
        updateBanner();
        renderPanel();
      });
    });
  }

  // ===== Fetch interception =====

  var INTERCEPTED_PATHS = ["/apply/status", "/apply/annotate"];

  function shouldIntercept(url) {
    if (!url) return false;
    return INTERCEPTED_PATHS.some(function (p) {
      return url.indexOf(p) !== -1;
    });
  }

  var _origFetch = window.fetch;

  window.fetch = function (input, init) {
    var url = typeof input === "string" ? input : (input && input.url) || "";
    var method = init && init.method ? init.method.toUpperCase() : "GET";

    if (method === "POST" && shouldIntercept(url)) {
      if (!navigator.onLine) {
        return captureAndQueue(url, init);
      }
      return _origFetch.apply(this, arguments).catch(function (err) {
        if (err instanceof TypeError) {
          return captureAndQueue(url, init);
        }
        return Promise.reject(err);
      });
    }

    return _origFetch.apply(this, arguments);
  };

  function captureAndQueue(url, init) {
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

    var body = init && init.body ? init.body : null;
    var bodyStr = typeof body === "string" ? body : null;

    var entry = {
      id: uid(),
      url: url,
      method: method,
      headers: headers,
      body: bodyStr,
      queuedAt: new Date().toISOString(),
      label: labelFor(url, bodyStr),
    };

    enqueue(entry);
    updateBanner();

    var syntheticBody = JSON.stringify({
      ok: false,
      queued: true,
      error: "Offline: action queued and will retry when you are back online.",
    });
    return Promise.resolve(
      new Response(syntheticBody, {
        status: 200,
        headers: { "Content-Type": "application/json" },
      }),
    );
  }

  // ===== Replay =====

  var SESSION_EXPIRED = false;

  function replayQueue() {
    var queue = loadQueue();
    if (queue.length === 0) {
      hideBanner();
      return;
    }

    SESSION_EXPIRED = false;
    updateBanner();

    var chain = Promise.resolve();
    queue.forEach(function (entry) {
      chain = chain.then(function () {
        if (SESSION_EXPIRED) return;

        return _origFetch(entry.url, {
          method: entry.method,
          headers: entry.headers,
          body: entry.body,
        })
          .then(function (res) {
            if (res.status === 403) {
              SESSION_EXPIRED = true;
              showSessionExpiredWarning();
              return;
            }
            dequeue(entry.id);
            updateBanner();
          })
          .catch(function () {});
      });
    });

    chain.then(function () {
      if (SESSION_EXPIRED) return;
      var remaining = loadQueue();
      if (remaining.length === 0) {
        hideBanner();
        if (panelEl && panelEl.classList.contains("open")) {
          renderPanel();
        }
      }
    });
  }

  function showSessionExpiredWarning() {
    ensureBanner();
    var msg = document.getElementById("iq-banner-msg");
    var btn = document.getElementById("iq-banner-btn");
    if (msg)
      msg.textContent =
        "Your session expired while offline. Log back in to send your queued actions.";
    if (btn) {
      btn.textContent = "Log in";
      var root = typeof NOTIF_ROOT !== "undefined" ? NOTIF_ROOT : "";
      var loginPath =
        typeof SESSION_LOGIN_PATH !== "undefined"
          ? SESSION_LOGIN_PATH
          : "user/login";
      btn.onclick = function () {
        window.location.href = root + "/" + loginPath;
      };
    }
    bannerEl.classList.add("iq-banner--visible", "iq-banner--warn");
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

  document.addEventListener("DOMContentLoaded", function () {
    var queue = loadQueue();
    if (queue.length === 0) return;

    updateBanner();

    if (navigator.onLine) {
      replayTimer = setTimeout(replayQueue, REPLAY_DELAY);
    }
  });

  // ===== Utility =====

  function escHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  window._actionQueue = {
    load: loadQueue,
    clear: clearQueue,
    replay: replayQueue,
    open: openQueuePanel,
  };
})();
