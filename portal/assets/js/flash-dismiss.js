// ===== AUTO-DISMISS FLASH MESSAGES =====

(function () {
  function dismissFlash(id, delayMs) {
    const el = document.getElementById(id);
    if (!el) return;
    setTimeout(() => {
      el.style.transition = "opacity 0.4s ease";
      el.style.opacity = "0";
      setTimeout(() => el.remove(), 420);
    }, delayMs);
  }
  dismissFlash("flashSuccess", 4000);
  dismissFlash("flashError", 7000);
})();
