(function () {
  // ===== Register service worker =====
  if (!("serviceWorker" in navigator)) return;

  var script = document.querySelector("script[data-root]");
  var root = script ? script.dataset.root : "";
  var userId = typeof CURRENT_USER_ID !== "undefined" ? CURRENT_USER_ID : 0;
  var isPersonnel =
    typeof SESSION_LOGIN_PATH !== "undefined" &&
    SESSION_LOGIN_PATH === "personnel/login";

  var WARM_INTERVAL_MS = 10 * 60 * 1000;
  var WARM_VIEWER_LIMIT =
    typeof OFFLINE_SAVE_PROTOCOLS !== "undefined"
      ? Number(OFFLINE_SAVE_PROTOCOLS) || 0
      : 40;

  function tellWorker(message) {
    navigator.serviceWorker.ready.then(function (reg) {
      if (reg.active) reg.active.postMessage(message);
    });
  }

  // ===== Purge per-user pages when the signed-in user changes =====
  try {
    if (localStorage.getItem("iacuc_cache_user") !== String(userId)) {
      localStorage.setItem("iacuc_cache_user", String(userId));
      localStorage.removeItem("iacuc_warm_at");
      localStorage.removeItem("iacuc_saved_at");
      tellWorker({ type: "purge" });
    }
  } catch (e) {}

  // ===== Remember when pages were last saved =====
  navigator.serviceWorker.addEventListener("message", function (e) {
    if (!e.data || e.data.type !== "warmed") return;
    try {
      localStorage.setItem("iacuc_saved_at", String(e.data.at));
    } catch (err) {}
    window.dispatchEvent(new Event("offline-saved"));
  });

  // ===== Purge saved pages on logout =====
  document.addEventListener(
    "submit",
    function (e) {
      var action = e.target && e.target.action ? e.target.action : "";
      if (/\/(user|personnel)\/logout$/.test(action)) {
        tellWorker({ type: "purge" });
        try {
          localStorage.removeItem("iacuc_warm_at");
          localStorage.removeItem("iacuc_saved_at");
        } catch (err) {}
      }
    },
    true,
  );

  // ===== Save pages for offline use (staff and reviewers) =====
  function offlineUrls() {
    var urls = [];
    document.querySelectorAll("nav a[href]").forEach(function (a) {
      var path = new URL(a.href, location.href).pathname;
      if (
        path.indexOf(root + "/personnel/") === 0 &&
        !/logout|login|register/.test(path)
      ) {
        urls.push(a.href);
      }
    });
    urls.push(location.href);

    if (
      urls.some(function (u) {
        return /\/personnel\/announcements$/.test(u);
      })
    ) {
      urls.push(root + "/personnel/site_content");
    }

    if (typeof protocolsData !== "undefined" && Array.isArray(protocolsData)) {
      var saved =
        WARM_VIEWER_LIMIT > 0
          ? protocolsData.slice(0, WARM_VIEWER_LIMIT)
          : protocolsData;
      saved.forEach(function (p) {
        urls.push(root + "/apply/viewer/" + (p.viewer_ref || p.protocol_id));
      });
    }
    return urls.filter(function (u, i) {
      return urls.indexOf(u) === i;
    });
  }

  function warmForOffline(force) {
    if (!isPersonnel || !navigator.onLine) return;
    try {
      var last = Number(localStorage.getItem("iacuc_warm_at") || 0);
      if (!force && Date.now() - last < WARM_INTERVAL_MS) return;
      localStorage.setItem("iacuc_warm_at", String(Date.now()));
    } catch (e) {}
    tellWorker({ type: "prefetch", urls: offlineUrls() });
  }

  window.addEventListener("load", function () {
    navigator.serviceWorker
      .register(root + "/sw.js")
      .then(function () {
        setTimeout(warmForOffline, 4000);
      })
      .catch(function (err) {
        console.warn("Service worker registration failed:", err);
      });
  });

  window.addEventListener("online", function () {
    setTimeout(warmForOffline, 3000);
  });

  window.warmOfflineCache = function () {
    warmForOffline(true);
  };
})();
