(function () {
  // ===== Register service worker =====
  if (!("serviceWorker" in navigator)) return;

  var script = document.querySelector("script[data-root]");
  var root = script ? script.dataset.root : "";

  window.addEventListener("load", function () {
    navigator.serviceWorker.register(root + "/sw.js").catch(function (err) {
      console.warn("Service worker registration failed:", err);
    });
  });
})();
